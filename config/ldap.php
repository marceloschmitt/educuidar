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
     * Authenticate user against LDAP
     * @param string $username
     * @param string $password
     * @return array|false Returns user info on success, false on failure
     */
    public function authenticate($username, $password) {
        if (empty($username) || empty($password)) {
            return false;
        }
        
        // Check if LDAP extension is available
        if (!function_exists('ldap_connect')) {
            error_log("LDAP extension not available");
            return false;
        }
        
        $ldap_conn = @ldap_connect($this->ldap_host, $this->ldap_port);
        
        if (!$ldap_conn) {
            error_log("LDAP connection failed to {$this->ldap_host}:{$this->ldap_port}");
            return false;
        }
        
        // Set LDAP options
        ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
        
        // Try to bind with user credentials
        // First try: direct bind with attribute=username,base_dn (for simple LDAP)
        // For Active Directory, we'll search for the user DN first
        $user_dn = null;
        if ($this->ldap_user_attribute === 'sAMAccountName' || $this->ldap_user_attribute === 'userPrincipalName') {
            // Active Directory: search for user DN first
            $user_dn = $this->getUserDN($username);
        } else {
            // Standard LDAP: try direct bind
            $user_dn = $this->ldap_user_attribute . "=$username," . $this->ldap_base_dn;
        }
        
        $bind = false;
        if ($user_dn) {
            $bind = @ldap_bind($ldap_conn, $user_dn, $password);
        }
        
        // If direct bind fails, try to find user DN first
        if (!$bind) {
            $user_dn = $this->getUserDN($username);
            if ($user_dn) {
                $bind = @ldap_bind($ldap_conn, $user_dn, $password);
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
            return false;
        }
        
        ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
        
        // Try anonymous bind first, or use admin credentials if available
        if (!empty($this->ldap_bind_dn) && !empty($this->ldap_bind_password)) {
            $bind = @ldap_bind($ldap_conn, $this->ldap_bind_dn, $this->ldap_bind_password);
        } else {
            $bind = @ldap_bind($ldap_conn);
        }
        
        if (!$bind) {
            ldap_close($ldap_conn);
            return false;
        }
        
        // Search for user using configured attribute
        $filter = "(" . $this->ldap_user_attribute . "=$username)";
        $search = @ldap_search($ldap_conn, $this->ldap_base_dn, $filter, ['dn']);
        $entries = @ldap_get_entries($ldap_conn, $search);
        
        ldap_close($ldap_conn);
        
        if ($entries && $entries['count'] > 0) {
            return $entries[0]['dn'];
        }
        
        // Try alternative: attribute=username,base_dn format (for simple LDAP)
        if ($this->ldap_user_attribute !== 'sAMAccountName' && $this->ldap_user_attribute !== 'userPrincipalName') {
            $user_dn = $this->ldap_user_attribute . "=$username," . $this->ldap_base_dn;
            return $user_dn;
        }
        
        return false;
    }
}
?>

