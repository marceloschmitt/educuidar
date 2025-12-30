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
    private $bind_admin_error = '';
    
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
        $this->bind_admin_error = '';
        
        if (empty($username) || empty($password)) {
            $this->last_error = 'Usuário ou senha não informados';
            error_log("LDAP Auth: Username or password empty");
            return false;
        }
        
        // Check if LDAP is configured
        if (empty($this->ldap_host) || empty($this->ldap_base_dn) || empty($this->ldap_user_attribute)) {
            $this->last_error = 'LDAP não configurado. Verifique as configurações em Configuração LDAP.';
            error_log("LDAP Auth: LDAP not configured - host: " . ($this->ldap_host ?: 'empty') . ", base_dn: " . ($this->ldap_base_dn ?: 'empty') . ", attribute: " . ($this->ldap_user_attribute ?: 'empty'));
            return false;
        }
        
        // Check if LDAP extension is available
        if (!function_exists('ldap_connect')) {
            $this->last_error = 'Extensão LDAP do PHP não está disponível. Instale a extensão php-ldap.';
            error_log("LDAP Auth: LDAP extension not available");
            return false;
        }
        
        $ldap_conn = @ldap_connect($this->ldap_host);
        
        if (!$ldap_conn) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $this->last_error = "❌ Falha ao conectar ao servidor LDAP {$this->ldap_host}.\nErro: {$ldap_error}\n\nVerifique se:\n- O servidor LDAP está acessível\n- O endereço está correto (pode incluir porta: ldap://host:389)\n- Não há firewall bloqueando a conexão";
            error_log("LDAP Auth: Connection failed to {$this->ldap_host} - {$ldap_error}");
            return false;
        }
        
        // Connection successful
        error_log("LDAP Auth: Successfully connected to {$this->ldap_host}");
        
        // Set LDAP options for Active Directory compatibility
        // Protocol version 3 is required for Active Directory
        if (!@ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $this->last_error = "✅ Conectado ao servidor LDAP {$this->ldap_host}.\n❌ Falha ao configurar protocolo LDAP versão 3.\nErro: {$ldap_error}";
            error_log("LDAP Auth: Failed to set protocol version - {$ldap_error}");
            ldap_close($ldap_conn);
            return false;
        }
        
        // Disable referrals for Active Directory (prevents following referrals to other domains)
        if (!@ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0)) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            error_log("LDAP Auth: Failed to set referrals option - {$ldap_error}");
        }
        
        // Set network timeout for better AD compatibility
        @ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, 10);
        
        // Set timelimit for searches (30 seconds)
        @ldap_set_option($ldap_conn, LDAP_OPT_TIMELIMIT, 30);
        
        // Set sizelimit for searches (1000 results)
        @ldap_set_option($ldap_conn, LDAP_OPT_SIZELIMIT, 1000);
        
        error_log("LDAP Auth: LDAP options configured successfully (protocol v3, referrals disabled, compatible with Active Directory)");
        
        // Always search for user DN first to ensure we search in subcontextos
        // This is important for Active Directory and LDAP servers where users may be in subcontextos
        error_log("LDAP Auth: Searching for user DN with attribute '{$this->ldap_user_attribute}' (searching in all subcontextos)");
        $user_dn = $this->getUserDN($username);
        
        if (!$user_dn) {
            $error_msg = "✅ Conectado ao servidor LDAP {$this->ldap_host}.\n✅ Protocolo LDAP configurado.";
            
            // Add bind status information
            if (!empty($this->bind_admin_error)) {
                $error_msg .= "\n\n" . $this->bind_admin_error;
            } else {
                if (!empty($this->ldap_bind_dn)) {
                    $error_msg .= "\n✅ Bind administrativo bem-sucedido com DN: {$this->ldap_bind_dn}";
                } else {
                    $error_msg .= "\n✅ Bind anônimo bem-sucedido";
                }
            }
            
            $error_msg .= "\n\n❌ Usuário '{$username}' não encontrado no LDAP.\n\nDetalhes:\n- Atributo usado: {$this->ldap_user_attribute}\n- Base DN: {$this->ldap_base_dn}\n- Filtro: ({$this->ldap_user_attribute}={$username})\n- Escopo: Subtree (busca em TODOS os subcontextos)\n\n⚠️ IMPORTANTE: A busca foi realizada em TODOS os subcontextos da Base DN.\n\nVerifique se:\n- O usuário existe no servidor LDAP\n- O atributo está correto (ex: uid, sAMAccountName, userPrincipalName, cn)\n- A Base DN está correta e contém o usuário ou seus subcontextos\n- O usuário está dentro da Base DN ou em algum subcontexto abaixo dela";
            
            $this->last_error = $error_msg;
            error_log("LDAP Auth: User DN not found for username: {$username} (searched in all subcontextos)");
            ldap_close($ldap_conn);
            return false;
        }
        
        error_log("LDAP Auth: User DN found: {$user_dn}");
        
        // Now try to bind with the found DN
        $bind = false;
        $bind_attempts = [];
        
        error_log("LDAP Auth: Attempting bind with DN: {$user_dn}");
        $bind = @ldap_bind($ldap_conn, $user_dn, $password);
        if (!$bind) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $bind_attempts[] = "DN: {$user_dn} - Erro: {$ldap_error}";
            error_log("LDAP Auth: Bind failed with DN '{$user_dn}' - {$ldap_error}");
        } else {
            error_log("LDAP Auth: Bind successful with DN: {$user_dn}");
        }
        
        if ($bind) {
            // Authentication successful, get user info
            // Escape special LDAP characters in username to prevent injection
            $escaped_username = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
            $filter = "(" . $this->ldap_user_attribute . "=" . $escaped_username . ")";
            // Search for user attributes - include Active Directory attributes
            $attributes = ['cn', 'mail', 'displayName', 'sn', 'givenName', 'name', 'userPrincipalName', 'sAMAccountName'];
            // Use ldap_search which uses LDAP_SCOPE_SUBTREE by default (searches entire subtree/subcontextos)
            // This matches Moodle's "Search subcontexts: Yes" behavior for Active Directory
            error_log("LDAP Auth: Searching for user attributes with filter '{$filter}' in base '{$this->ldap_base_dn}' (scope: SUBTREE - all subcontextos, like Moodle)");
            $search = @ldap_search($ldap_conn, $this->ldap_base_dn, $filter, $attributes, 0, 1000, 30, LDAP_DEREF_NEVER);
            if (!$search) {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                error_log("LDAP Auth: Search failed - {$ldap_error}");
            }
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
            $error_details = "✅ Conectado ao servidor LDAP {$this->ldap_host}.\n✅ Protocolo LDAP configurado.";
            
            // Add bind admin status if available
            if (!empty($this->bind_admin_error)) {
                $error_details .= "\n\n" . $this->bind_admin_error;
            } else {
                if (!empty($this->ldap_bind_dn)) {
                    $error_details .= "\n✅ Bind administrativo bem-sucedido com DN: {$this->ldap_bind_dn}";
                } else {
                    $error_details .= "\n✅ Bind anônimo bem-sucedido";
                }
            }
            
            if ($user_dn) {
                $error_details .= "\n✅ Usuário encontrado no LDAP.\nDN: {$user_dn}";
            } else {
                $error_details .= "\n❌ Usuário não encontrado no LDAP.";
            }
            
            $error_details .= "\n❌ Falha na autenticação (bind).\nErro: {$ldap_error}";
            
            if (!empty($bind_attempts)) {
                $error_details .= "\n\nTentativas de autenticação:\n" . implode("\n", $bind_attempts);
            }
            
            $error_details .= "\n\nVerifique se:\n- A senha está correta\n- O usuário está ativo no LDAP\n- As credenciais estão corretas";
            
            $this->last_error = $error_details;
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
        $ldap_conn = @ldap_connect($this->ldap_host);
        
        if (!$ldap_conn) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            error_log("LDAP getUserDN: Connection failed - {$ldap_error}");
            return false;
        }
        
        @ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
        @ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, 10);
        @ldap_set_option($ldap_conn, LDAP_OPT_TIMELIMIT, 30);
        @ldap_set_option($ldap_conn, LDAP_OPT_SIZELIMIT, 1000);
        
        // Try anonymous bind first, or use admin credentials if available
        $bind_success = false;
        $bind_method = '';
        $bind_error = '';
        
        if (!empty($this->ldap_bind_dn) && !empty($this->ldap_bind_password)) {
            $bind_method = 'administrativo';
            error_log("LDAP getUserDN: Attempting bind with admin DN: {$this->ldap_bind_dn}");
            $bind = @ldap_bind($ldap_conn, $this->ldap_bind_dn, $this->ldap_bind_password);
            if (!$bind) {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                $bind_error = $ldap_error;
                error_log("LDAP getUserDN: Bind failed with admin DN '{$this->ldap_bind_dn}' - {$ldap_error}");
                // Store bind error for later use
                $this->bind_admin_error = "❌ Falha no bind administrativo com DN '{$this->ldap_bind_dn}'.\nErro: {$ldap_error}\n\nVerifique se:\n- O DN do bind está correto\n- A senha do bind está correta\n- O usuário administrativo tem permissões para buscar usuários";
            } else {
                $bind_success = true;
                error_log("LDAP getUserDN: Bind successful with admin DN");
                $this->bind_admin_error = '';
            }
        } else {
            $bind_method = 'anônimo';
            error_log("LDAP getUserDN: Attempting anonymous bind");
            $bind = @ldap_bind($ldap_conn);
            if (!$bind) {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                $bind_error = $ldap_error;
                error_log("LDAP getUserDN: Anonymous bind failed - {$ldap_error}");
                $this->bind_admin_error = "❌ Falha no bind anônimo.\nErro: {$ldap_error}\n\nNota: Alguns servidores LDAP não permitem bind anônimo. Configure um Bind DN administrativo nas configurações LDAP.";
            } else {
                $bind_success = true;
                error_log("LDAP getUserDN: Anonymous bind successful");
                $this->bind_admin_error = '';
            }
        }
        
        if (!$bind_success) {
            ldap_close($ldap_conn);
            return false;
        }
        
        // Search for user using configured attribute
        // For Active Directory, we need to search in all subcontextos (subtree scope)
        // ldap_search() uses LDAP_SCOPE_SUBTREE by default, which searches entire subtree
        // This matches Moodle's "Search subcontexts: Yes" behavior
        // Escape special LDAP characters in username to prevent injection
        $escaped_username = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        $filter = "(" . $this->ldap_user_attribute . "=" . $escaped_username . ")";
        error_log("LDAP getUserDN: Searching with filter '{$filter}' in base '{$this->ldap_base_dn}' (scope: SUBTREE - all subcontextos, like Moodle)");
        
        // Use ldap_search() which uses LDAP_SCOPE_SUBTREE (searches entire subtree/subcontextos)
        // This is exactly what Moodle does when "Search subcontexts: Yes"
        // Parameters: link, base_dn, filter, attributes, attrsonly, sizelimit, timelimit, deref
        // LDAP_DEREF_NEVER matches Moodle's "Dereference aliases: No"
        $search = @ldap_search($ldap_conn, $this->ldap_base_dn, $filter, ['dn'], 0, 1000, 30, LDAP_DEREF_NEVER);
        
        if (!$search) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            error_log("LDAP getUserDN: Search failed with filter '{$filter}' in base '{$this->ldap_base_dn}' - {$ldap_error}");
            ldap_close($ldap_conn);
            return false;
        }
        
        $entries = @ldap_get_entries($ldap_conn, $search);
        ldap_close($ldap_conn);
        
        if ($entries && $entries['count'] > 0) {
            error_log("LDAP getUserDN: User found in subcontextos, DN: {$entries[0]['dn']}");
            return $entries[0]['dn'];
        }
        
        error_log("LDAP getUserDN: User '{$username}' not found with filter '{$filter}' in base '{$this->ldap_base_dn}' (searched in all subcontextos with SUBTREE scope)");
        
        return false;
    }
}
?>

