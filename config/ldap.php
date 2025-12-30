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
    private $last_error = '';
    
    public function __construct($db) {
        require_once __DIR__ . '/../models/Configuracao.php';
        $configuracao = new Configuracao($db);
        $this->ldap_host = $configuracao->getLdapHost();
        $this->ldap_base_dn = $configuracao->getLdapBaseDn();
        $this->ldap_bind_dn = $configuracao->getLdapBindDn();
        $this->ldap_bind_password = $configuracao->getLdapBindPassword();
        $this->ldap_user_attribute = $configuracao->getLdapUserAttribute();
    }
    
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
        
        if (empty($this->ldap_host) || empty($this->ldap_base_dn) || empty($this->ldap_user_attribute)) {
            $this->last_error = 'LDAP não configurado. Verifique as configurações em Configuração LDAP.';
            return false;
        }
        
        if (!function_exists('ldap_connect')) {
            $this->last_error = 'Extensão LDAP do PHP não está disponível. Instale a extensão php-ldap.';
            return false;
        }
        
        $ldap = @ldap_connect($this->ldap_host);
        if (!$ldap) {
            $this->last_error = 'Falha ao conectar ao servidor LDAP';
            return false;
        }
        
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
        
        // 1️⃣ Bind administrativo
        if (!empty($this->ldap_bind_dn) && !empty($this->ldap_bind_password)) {
            if (!@ldap_bind($ldap, $this->ldap_bind_dn, $this->ldap_bind_password)) {
                $this->last_error = 'Falha no bind administrativo';
                ldap_close($ldap);
                return false;
            }
        } else {
            if (!@ldap_bind($ldap)) {
                $this->last_error = 'Falha no bind anônimo';
                ldap_close($ldap);
                return false;
            }
        }
        
        // 2️⃣ Busca DN do usuário
        $search_attribute = ($this->ldap_user_attribute === 'uid') ? 'sAMAccountName' : $this->ldap_user_attribute;
        $filter = '(&(objectClass=user)(' . $search_attribute . '=' . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . '))';
        
        $search = @ldap_search($ldap, $this->ldap_base_dn, $filter, ['dn']);
        if (!$search) {
            $this->last_error = 'Falha na busca do usuário';
            ldap_close($ldap);
            return false;
        }
        
        $entries = @ldap_get_entries($ldap, $search);
        if ($entries['count'] !== 1) {
            $this->last_error = 'Usuário não encontrado ou duplicado';
            ldap_close($ldap);
            return false;
        }
        
        $user_dn = $entries[0]['dn'];
        
        // 3️⃣ Bind como usuário final (valida senha)
        if (!@ldap_bind($ldap, $user_dn, $password)) {
            $this->last_error = 'Senha inválida';
            ldap_close($ldap);
            return false;
        }
        
        // Busca atributos do usuário
        $attributes = ['cn', 'mail', 'displayName', 'sn', 'givenName', 'name', 'userPrincipalName', 'sAMAccountName'];
        $search = @ldap_search($ldap, $this->ldap_base_dn, $filter, $attributes);
        
        $full_name = $username;
        $email = '';
        
        if ($search) {
            $entries = @ldap_get_entries($ldap, $search);
            if ($entries && $entries['count'] > 0) {
                $entry = $entries[0];
                
                if (isset($entry['displayName'][0])) {
                    $full_name = $entry['displayName'][0];
                } elseif (isset($entry['cn'][0])) {
                    $full_name = $entry['cn'][0];
                } elseif (isset($entry['name'][0])) {
                    $full_name = $entry['name'][0];
                } elseif (isset($entry['givenName'][0]) || isset($entry['sn'][0])) {
                    $full_name = trim((isset($entry['givenName'][0]) ? $entry['givenName'][0] : '') . ' ' . (isset($entry['sn'][0]) ? $entry['sn'][0] : ''));
                }
                
                if (isset($entry['mail'][0])) {
                    $email = $entry['mail'][0];
                } elseif (isset($entry['userPrincipalName'][0])) {
                    $email = $entry['userPrincipalName'][0];
                }
            }
        }
        
        ldap_unbind($ldap);
        
        return [
            'username' => $username,
            'full_name' => trim($full_name) ?: $username,
            'email' => $email
        ];
    }
    
}
?>

