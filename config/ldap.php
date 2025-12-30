<?php
/**
 * LDAP Configuration
 */

class LDAPAuth {
    private $ldap_host;
    private $ldap_port;
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
        $this->ldap_port = (int)$this->configuracao->getLdapPort();
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
            error_log("LDAP Auth: Username or password empty");
            return false;
        }
        
        // Check if LDAP is configured
        if (empty($this->ldap_host) || empty($this->ldap_port) || empty($this->ldap_base_dn) || empty($this->ldap_user_attribute)) {
            $this->last_error = 'LDAP não configurado. Verifique as configurações em Configuração LDAP.';
            error_log("LDAP Auth: LDAP not configured - host: " . ($this->ldap_host ?: 'empty') . ", port: " . ($this->ldap_port ?: 'empty') . ", base_dn: " . ($this->ldap_base_dn ?: 'empty') . ", attribute: " . ($this->ldap_user_attribute ?: 'empty'));
            return false;
        }
        
        // Check if LDAP extension is available
        if (!function_exists('ldap_connect')) {
            $this->last_error = 'Extensão LDAP do PHP não está disponível. Instale a extensão php-ldap.';
            error_log("LDAP Auth: LDAP extension not available");
            return false;
        }
        
        $ldap_conn = @ldap_connect($this->ldap_host, $this->ldap_port);
        
        if (!$ldap_conn) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $this->last_error = "Falha ao conectar ao servidor LDAP {$this->ldap_host}:{$this->ldap_port}. Erro: {$ldap_error}";
            error_log("LDAP Auth: Connection failed to {$this->ldap_host}:{$this->ldap_port} - {$ldap_error}");
            return false;
        }
        
        // Set LDAP options
        if (!@ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $this->last_error = "Falha ao configurar protocolo LDAP. Erro: {$ldap_error}";
            error_log("LDAP Auth: Failed to set protocol version - {$ldap_error}");
            ldap_close($ldap_conn);
            return false;
        }
        
        if (!@ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0)) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            error_log("LDAP Auth: Failed to set referrals option - {$ldap_error}");
        }
        
        // Try to bind with user credentials
        // First try: direct bind with attribute=username,base_dn (for simple LDAP)
        // For Active Directory, we'll search for the user DN first
        $user_dn = null;
        if ($this->ldap_user_attribute === 'sAMAccountName' || $this->ldap_user_attribute === 'userPrincipalName') {
            // Active Directory: search for user DN first
            $user_dn = $this->getUserDN($username);
            if (!$user_dn) {
                $this->last_error = "Usuário '{$username}' não encontrado no LDAP usando atributo '{$this->ldap_user_attribute}' na base '{$this->ldap_base_dn}'";
                error_log("LDAP Auth: User DN not found for username: {$username}");
            }
        } else {
            // Standard LDAP: try direct bind
            $user_dn = $this->ldap_user_attribute . "=$username," . $this->ldap_base_dn;
        }
        
        $bind = false;
        if ($user_dn) {
            $bind = @ldap_bind($ldap_conn, $user_dn, $password);
            if (!$bind) {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                error_log("LDAP Auth: Bind failed with DN '{$user_dn}' - {$ldap_error}");
            }
        }
        
        // If direct bind fails, try to find user DN first
        if (!$bind && $user_dn && strpos($user_dn, ',') !== false) {
            $user_dn = $this->getUserDN($username);
            if ($user_dn) {
                $bind = @ldap_bind($ldap_conn, $user_dn, $password);
                if (!$bind) {
                    $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                    error_log("LDAP Auth: Bind failed with searched DN '{$user_dn}' - {$ldap_error}");
                }
            }
        }
        
        if ($bind) {
            // Authentication successful, get user info
            $filter = "(" . $this->ldap_user_attribute . "=$username)";
            // Search for user attributes - include Active Directory attributes
            $attributes = ['cn', 'mail', 'displayName', 'sn', 'givenName', 'name', 'userPrincipalName', 'sAMAccountName'];
            $search = @ldap_search($ldap_conn, $this->ldap_base_dn, $filter, $attributes);
            $entries = @ldap_get_entries($ldap_conn, $search);
            
            ldap_close($ldap_conn);
            
            if ($entries && $entries['count'] > 0) {
                $entry = $entries[0];
                // Try multiple attributes for full name (Active Directory and standard LDAP)
                $full_name = '';
                if (isset($entry['displayName'][0])) {
                    $full_name = $entry['displayName'][0];
                } elseif (isset($entry['cn'][0])) {
                    $full_name = $entry['cn'][0];
                } elseif (isset($entry['name'][0])) {
                    $full_name = $entry['name'][0];
                } else {
                    $givenName = isset($entry['givenName'][0]) ? $entry['givenName'][0] : '';
                    $sn = isset($entry['sn'][0]) ? $entry['sn'][0] : '';
                    $full_name = trim($givenName . ' ' . $sn);
                }
                
                // Try multiple attributes for email
                $email = '';
                if (isset($entry['mail'][0])) {
                    $email = $entry['mail'][0];
                } elseif (isset($entry['userPrincipalName'][0])) {
                    $email = $entry['userPrincipalName'][0];
                }
                
                return [
                    'username' => $username,
                    'full_name' => trim($full_name) ?: $username,
                    'email' => $email
                ];
            }
            
            return [
                'username' => $username,
                'full_name' => $username,
                'email' => ''
            ];
        }
        
        // Authentication failed
        if (empty($this->last_error)) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Credenciais inválidas';
            $this->last_error = "Falha na autenticação LDAP. Erro: {$ldap_error}";
            if ($user_dn) {
                $this->last_error .= " (DN: {$user_dn})";
            }
            error_log("LDAP Auth: Authentication failed for user '{$username}' - {$ldap_error}");
        }
        
        ldap_close($ldap_conn);
        return false;
    }
    
    /**
     * Get user DN from username
     * @param string $username
     * @return string|false
     */
    private function getUserDN($username) {
        $ldap_conn = @ldap_connect($this->ldap_host, $this->ldap_port);
        
        if (!$ldap_conn) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            error_log("LDAP getUserDN: Connection failed - {$ldap_error}");
            return false;
        }
        
        @ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
        
        // Try anonymous bind first, or use admin credentials if available
        if (!empty($this->ldap_bind_dn) && !empty($this->ldap_bind_password)) {
            $bind = @ldap_bind($ldap_conn, $this->ldap_bind_dn, $this->ldap_bind_password);
            if (!$bind) {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                error_log("LDAP getUserDN: Bind failed with admin DN '{$this->ldap_bind_dn}' - {$ldap_error}");
            }
        } else {
            $bind = @ldap_bind($ldap_conn);
            if (!$bind) {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                error_log("LDAP getUserDN: Anonymous bind failed - {$ldap_error}");
            }
        }
        
        if (!$bind) {
            ldap_close($ldap_conn);
            return false;
        }
        
        // Search for user using configured attribute
        $filter = "(" . $this->ldap_user_attribute . "=$username)";
        $search = @ldap_search($ldap_conn, $this->ldap_base_dn, $filter, ['dn']);
        
        if (!$search) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            error_log("LDAP getUserDN: Search failed with filter '{$filter}' in base '{$this->ldap_base_dn}' - {$ldap_error}");
            ldap_close($ldap_conn);
            return false;
        }
        
        $entries = @ldap_get_entries($ldap_conn, $search);
        ldap_close($ldap_conn);
        
        if ($entries && $entries['count'] > 0) {
            return $entries[0]['dn'];
        }
        
        error_log("LDAP getUserDN: User '{$username}' not found with filter '{$filter}' in base '{$this->ldap_base_dn}'");
        
        // Try alternative: attribute=username,base_dn format (for simple LDAP)
        if ($this->ldap_user_attribute !== 'sAMAccountName' && $this->ldap_user_attribute !== 'userPrincipalName') {
            $user_dn = $this->ldap_user_attribute . "=$username," . $this->ldap_base_dn;
            return $user_dn;
        }
        
        return false;
    }
}
?>

