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
        
        // For Active Directory, Moodle uses different authentication methods
        // Instead of binding with full DN, AD often works better with:
        // 1. userPrincipalName (username@domain)
        // 2. sAMAccountName (DOMAIN\username or just username)
        // 3. Full DN (fallback)
        
        $bind = false;
        $bind_attempts = [];
        $user_dn = null;
        
        // Strategy 1: Try Active Directory style authentication first
        // This is what Moodle does when user_type = 'ad'
        // Try with sAMAccountName (most common in AD)
        error_log("LDAP Auth: Attempting AD-style bind with sAMAccountName: {$username}");
        $bind = @ldap_bind($ldap_conn, $username, $password);
        if (!$bind) {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            $bind_attempts[] = "sAMAccountName: {$username} - Erro: {$ldap_error}";
            error_log("LDAP Auth: Bind failed with sAMAccountName '{$username}' - {$ldap_error}");
        } else {
            error_log("LDAP Auth: Bind successful with sAMAccountName: {$username}");
            // Authentication successful, now get user DN for info retrieval
            $user_dn = $this->getUserDN($username);
        }
        
        // Strategy 2: If sAMAccountName failed, try to find DN and bind with it
        if (!$bind) {
            error_log("LDAP Auth: Searching for user DN with attribute '{$this->ldap_user_attribute}' (searching in all subcontextos)");
            $user_dn = $this->getUserDN($username);
            
            if ($user_dn) {
                error_log("LDAP Auth: User DN found: {$user_dn}");
                error_log("LDAP Auth: Attempting bind with DN: {$user_dn}");
                $bind = @ldap_bind($ldap_conn, $user_dn, $password);
                if (!$bind) {
                    $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                    $bind_attempts[] = "DN: {$user_dn} - Erro: {$ldap_error}";
                    error_log("LDAP Auth: Bind failed with DN '{$user_dn}' - {$ldap_error}");
                } else {
                    error_log("LDAP Auth: Bind successful with DN: {$user_dn}");
                }
            } else {
                // User not found
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
                
                // Build list of tried filters for error message
                $filters_info = "Filtros tentados:\n";
                if ($this->ldap_user_attribute === 'uid') {
                    $filters_info .= "- (uid={$username})\n";
                    $filters_info .= "- (&(objectClass=user)(uid={$username}))\n";
                    $filters_info .= "- (&(objectClass=person)(uid={$username}))\n";
                    $filters_info .= "- (&(objectClass=organizationalPerson)(uid={$username}))\n";
                    $filters_info .= "- (sAMAccountName={$username})\n";
                    $filters_info .= "- (&(objectClass=user)(sAMAccountName={$username}))";
                } else {
                    $filters_info .= "- ({$this->ldap_user_attribute}={$username})";
                }
                
                $error_msg .= "\n\n❌ Usuário '{$username}' não encontrado no LDAP.\n\nDetalhes:\n- Atributo configurado: {$this->ldap_user_attribute}\n- Base DN: {$this->ldap_base_dn}\n- Escopo: Subtree (busca em TODOS os subcontextos)\n\n{$filters_info}\n\n⚠️ IMPORTANTE: A busca foi realizada em TODOS os subcontextos da Base DN usando múltiplas estratégias.\n\nSugestões:\n1. Verifique se o usuário existe no servidor LDAP/Active Directory\n2. Teste com outros atributos:\n   - Para Active Directory: tente 'sAMAccountName' ou 'userPrincipalName'\n   - Para LDAP padrão: tente 'uid' ou 'cn'\n3. Verifique se a Base DN está correta e contém o usuário ou seus subcontextos\n4. Use uma ferramenta LDAP (como ldapsearch) para testar a busca manualmente:\n   ldapsearch -H ldap://sol.poa.ifrs.edu.br -D 'CN=Administrator,CN=Users,DC=ad,DC=poa,DC=ifrs,DC=edu,DC=br' -W -b 'dc=ad,dc=poa,dc=ifrs,dc=edu,dc=br' '(uid=marceloschmitt)'";
                
                $this->last_error = $error_msg;
                error_log("LDAP Auth: User DN not found for username: {$username} (searched in all subcontextos)");
                ldap_close($ldap_conn);
                return false;
            }
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
        
        // Diagnostic: Try to search for any user to verify search is working
        // This helps identify if the problem is with the specific user or with search in general
        $test_filter = "(objectClass=user)";
        $test_search = @ldap_search($ldap_conn, $this->ldap_base_dn, $test_filter, ['dn'], 0, 5, 5, LDAP_DEREF_NEVER);
        if ($test_search) {
            $test_entries = @ldap_get_entries($ldap_conn, $test_search);
            if ($test_entries && $test_entries['count'] > 0) {
                error_log("LDAP getUserDN: Diagnostic search successful - found {$test_entries['count']} user(s) in base DN (search is working)");
            } else {
                error_log("LDAP getUserDN: Diagnostic search found no users - this may indicate a problem with Base DN or permissions");
            }
        } else {
            $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
            error_log("LDAP getUserDN: Diagnostic search failed - {$ldap_error}");
        }
        
        // Search for user using configured attribute
        // For Active Directory, we need to search in all subcontextos (subtree scope)
        // ldap_search() uses LDAP_SCOPE_SUBTREE by default, which searches entire subtree
        // This matches Moodle's "Search subcontexts: Yes" behavior
        // Escape special LDAP characters in username to prevent injection
        $escaped_username = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        
        // Try multiple filter strategies like Moodle does for Active Directory
        $filters_to_try = [];
        
        // For Active Directory, Moodle uses (&(objectClass=user)(sAMAccountName=username))
        // When user_attribute is 'uid', Moodle still searches with sAMAccountName for AD
        if ($this->ldap_user_attribute === 'uid') {
            // Primary AD filter: (&(objectClass=user)(sAMAccountName=username)) - This is what Moodle does!
            $filters_to_try[] = "(&(objectClass=user)(sAMAccountName=" . $escaped_username . "))";
            // Also try without objectClass
            $filters_to_try[] = "(sAMAccountName=" . $escaped_username . ")";
            // Try with configured attribute and objectClass
            $filters_to_try[] = "(&(objectClass=user)(" . $this->ldap_user_attribute . "=" . $escaped_username . "))";
            // Simple filter with configured attribute
            $filters_to_try[] = "(" . $this->ldap_user_attribute . "=" . $escaped_username . ")";
        } else {
            // For other attributes, try with objectClass=user (AD standard)
            $filters_to_try[] = "(&(objectClass=user)(" . $this->ldap_user_attribute . "=" . $escaped_username . "))";
            // Simple filter
            $filters_to_try[] = "(" . $this->ldap_user_attribute . "=" . $escaped_username . ")";
        }
        
        // Try each filter strategy
        $tried_filters = [];
        foreach ($filters_to_try as $filter) {
            $tried_filters[] = $filter;
            error_log("LDAP getUserDN: Trying filter '{$filter}' in base '{$this->ldap_base_dn}' (scope: SUBTREE - all subcontextos)");
            
            // Use ldap_search() which uses LDAP_SCOPE_SUBTREE (searches entire subtree/subcontextos)
            // Parameters: link, base_dn, filter, attributes, attrsonly, sizelimit, timelimit, deref
            // LDAP_DEREF_NEVER matches Moodle's "Dereference aliases: No"
            $search = @ldap_search($ldap_conn, $this->ldap_base_dn, $filter, ['dn'], 0, 1000, 30, LDAP_DEREF_NEVER);
            
            if (!$search) {
                $ldap_error = @ldap_error($ldap_conn) ?: 'Erro desconhecido';
                $ldap_errno = @ldap_errno($ldap_conn);
                error_log("LDAP getUserDN: Search failed with filter '{$filter}' - Error: {$ldap_error} (Code: {$ldap_errno})");
                continue; // Try next filter
            }
            
            $entries = @ldap_get_entries($ldap_conn, $search);
            
            if ($entries && $entries['count'] > 0) {
                error_log("LDAP getUserDN: User found with filter '{$filter}', DN: {$entries[0]['dn']}");
                ldap_close($ldap_conn);
                return $entries[0]['dn'];
            }
            
            error_log("LDAP getUserDN: No results with filter '{$filter}' (count: " . ($entries ? $entries['count'] : 0) . ")");
        }
        
        ldap_close($ldap_conn);
        
        // Build detailed error message with all tried filters
        $filters_list = implode("\n- ", $tried_filters);
        error_log("LDAP getUserDN: User '{$username}' not found with any filter strategy. Tried filters:\n- {$filters_list}");
        
        return false;
    }
}
?>

