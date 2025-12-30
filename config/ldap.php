<?php
/**
 * LDAP Configuration
 */

class LDAPAuth {
    private $ldap_host;
    private $ldap_base_dn;
    private $ldap_bind_dn;
    private $ldap_bind_password;
    private $ldap_user_attribute;
    private $configuracao;
    private $last_error = '';
    
    public function __construct($db) {
        if (!$db) {
            throw new Exception('Database connection is required for LDAPAuth');
        }
        
        // Load configuration from database
        require_once __DIR__ . '/../models/Configuracao.php';
        $this->configuracao = new Configuracao($db);
        $this->ldap_host = $this->configuracao->getLdapHost();
        $this->ldap_base_dn = $this->configuracao->getLdapBaseDn();
        $this->ldap_bind_dn = $this->configuracao->getLdapBindDn();
        $this->ldap_bind_password = $this->configuracao->getLdapBindPassword();
        $this->ldap_user_attribute = $this->configuracao->getLdapUserAttribute();
    }
    
    /**
     * Get last error message
     * @return string
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    /**
     * Authenticate user against LDAP
     * @param string $username
     * @param string $password
     * @return array|false Returns user info on success, false on failure
     */
    public function authenticate($username, $password) {
        $this->last_error = '';
        
        if (empty($username) || empty($password)) {
            $this->last_error = 'Usuário ou senha não informados';
            return false;
        }
        
        // Check if LDAP is configured
        if (empty($this->ldap_host) || empty($this->ldap_base_dn) || empty($this->ldap_user_attribute)) {
            $this->last_error = 'LDAP não configurado. Verifique as configurações em Configuração LDAP.';
            return false;
        }
        
        // Check if LDAP extension is available
        if (!function_exists('ldap_connect')) {
            $this->last_error = 'Extensão LDAP do PHP não está disponível. Instale a extensão php-ldap.';
            return false;
        }
        
        // Connect to LDAP server
        $ldap_conn = @ldap_connect($this->ldap_host);
        if (!$ldap_conn) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $this->last_error = "❌ Falha ao conectar ao servidor LDAP {$this->ldap_host}.\nErro: {$ldap_error}";
            return false;
        }
        
        // Set LDAP options
        ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
        
        // 1️⃣ Bind ADMINISTRATIVO (obrigatório)
        $admin_bind_success = false;
        if (!empty($this->ldap_bind_dn) && !empty($this->ldap_bind_password)) {
            if (@ldap_bind($ldap_conn, $this->ldap_bind_dn, $this->ldap_bind_password)) {
                $admin_bind_success = true;
            } else {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                $this->last_error = "✅ Conectado ao servidor LDAP {$this->ldap_host}.\n✅ Protocolo LDAP configurado.\n❌ Falha no bind administrativo com DN '{$this->ldap_bind_dn}'.\nErro: {$ldap_error}";
            }
        } else {
            // Try anonymous bind
            if (@ldap_bind($ldap_conn)) {
                $admin_bind_success = true;
            } else {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                $this->last_error = "✅ Conectado ao servidor LDAP {$this->ldap_host}.\n✅ Protocolo LDAP configurado.\n❌ Falha no bind anônimo.\nErro: {$ldap_error}";
            }
        }
        
        if (!$admin_bind_success) {
            ldap_close($ldap_conn);
            return false;
        }
        
        // 2️⃣ Busca DN do usuário
        // For Active Directory, use sAMAccountName in filter if configured attribute is 'uid'
        $search_attribute = ($this->ldap_user_attribute === 'uid') ? 'sAMAccountName' : $this->ldap_user_attribute;
        $escaped_username = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        $filter = '(&(objectClass=user)(' . $search_attribute . '=' . $escaped_username . '))';
        
        $search = @ldap_search($ldap_conn, $this->ldap_base_dn, $filter, ['dn']);
        if (!$search) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $this->last_error = "✅ Conectado ao servidor LDAP {$this->ldap_host}.\n✅ Protocolo LDAP configurado.\n✅ Bind administrativo bem-sucedido.\n❌ Falha na busca do usuário.\nErro: {$ldap_error}";
            ldap_close($ldap_conn);
            return false;
        }
        
        $entries = @ldap_get_entries($ldap_conn, $search);
        if ($entries['count'] !== 1) {
            $this->last_error = "✅ Conectado ao servidor LDAP {$this->ldap_host}.\n✅ Protocolo LDAP configurado.\n✅ Bind administrativo bem-sucedido.\n❌ Usuário '{$username}' não encontrado ou duplicado.\n\nDetalhes:\n- Filtro usado: {$filter}\n- Base DN: {$this->ldap_base_dn}\n- Resultados encontrados: {$entries['count']}";
            ldap_close($ldap_conn);
            return false;
        }
        
        $user_dn = $entries[0]['dn'];
        
        // 3️⃣ Bind como USUÁRIO FINAL (valida senha)
        if (!@ldap_bind($ldap_conn, $user_dn, $password)) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $this->last_error = "✅ Conectado ao servidor LDAP {$this->ldap_host}.\n✅ Protocolo LDAP configurado.\n✅ Bind administrativo bem-sucedido.\n✅ Usuário encontrado: {$user_dn}\n❌ Senha inválida.\nErro: {$ldap_error}";
            ldap_close($ldap_conn);
            return false;
        }
        
        // Authentication successful, get user info
        $attributes = ['cn', 'mail', 'displayName', 'sn', 'givenName', 'name', 'userPrincipalName', 'sAMAccountName'];
        $search = @ldap_search($ldap_conn, $this->ldap_base_dn, $filter, $attributes);
        
        $full_name = $username;
        $email = '';
        
        if ($search) {
            $entries = @ldap_get_entries($ldap_conn, $search);
            if ($entries && $entries['count'] > 0) {
                $entry = $entries[0];
                
                // Get full name
                if (isset($entry['displayName'][0])) {
                    $full_name = $entry['displayName'][0];
                } elseif (isset($entry['cn'][0])) {
                    $full_name = $entry['cn'][0];
                } elseif (isset($entry['name'][0])) {
                    $full_name = $entry['name'][0];
                } elseif (isset($entry['givenName'][0]) || isset($entry['sn'][0])) {
                    $givenName = isset($entry['givenName'][0]) ? $entry['givenName'][0] : '';
                    $sn = isset($entry['sn'][0]) ? $entry['sn'][0] : '';
                    $full_name = trim($givenName . ' ' . $sn);
                }
                
                // Get email
                if (isset($entry['mail'][0])) {
                    $email = $entry['mail'][0];
                } elseif (isset($entry['userPrincipalName'][0])) {
                    $email = $entry['userPrincipalName'][0];
                }
            }
        }
        
        ldap_unbind($ldap_conn);
        
        return [
            'username' => $username,
            'full_name' => trim($full_name) ?: $username,
            'email' => $email
        ];
    }
}
?>

