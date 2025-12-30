<?php
// Start output buffering to prevent any output before redirects
ob_start();

// Process POST requests before including header (to allow redirects)
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$configuracao = new Configuracao($db);

// Only admin can access
if (!$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// Process POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ldap_host = trim($_POST['ldap_host'] ?? '');
    $ldap_base_dn = trim($_POST['ldap_base_dn'] ?? '');
    $ldap_bind_dn = trim($_POST['ldap_bind_dn'] ?? '');
    $ldap_bind_password = $_POST['ldap_bind_password'] ?? '';
    $ldap_user_attribute = trim($_POST['ldap_user_attribute'] ?? '');
    
    // Validate required fields
    if (empty($ldap_host)) {
        $error = 'Por favor, informe o endereço do servidor LDAP!';
    } elseif (empty($ldap_base_dn)) {
        $error = 'Por favor, informe a Base DN!';
    } elseif (empty($ldap_user_attribute)) {
        $error = 'Por favor, informe o atributo de usuário!';
    } else {
        // Save all LDAP configurations
        $success_count = 0;
        if ($configuracao->setLdapHost($ldap_host)) $success_count++;
        if ($configuracao->setLdapBaseDn($ldap_base_dn)) $success_count++;
        if ($configuracao->setLdapBindDn($ldap_bind_dn)) $success_count++;
        if ($configuracao->setLdapUserAttribute($ldap_user_attribute)) $success_count++;
        
        // Only update password if provided (to allow clearing it)
        if ($ldap_bind_password !== '') {
            if ($configuracao->setLdapBindPassword($ldap_bind_password)) $success_count++;
        } else {
            // If empty, clear the password
            if ($configuracao->setLdapBindPassword('')) $success_count++;
        }
        
        if ($success_count > 0) {
            $success = 'Configurações LDAP salvas com sucesso!';
        } else {
            $error = 'Erro ao salvar configurações LDAP. Tente novamente.';
        }
    }
}

// Get current LDAP configurations
$ldap_host = $configuracao->getLdapHost();
$ldap_base_dn = $configuracao->getLdapBaseDn();
$ldap_bind_dn = $configuracao->getLdapBindDn();
$ldap_user_attribute = $configuracao->getLdapUserAttribute();
// Don't retrieve password for security reasons - always leave empty in form
$ldap_bind_password = '';

// End output buffering before including header
ob_end_flush();

$page_title = 'Configuração LDAP';
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-server"></i> Configuração LDAP</h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Informação:</strong> Configure os parâmetros de conexão com o servidor LDAP. 
                    Estes parâmetros serão usados para autenticação de usuários que possuem <code>auth_type = 'ldap'</code>.
                </div>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="ldap_host" class="form-label">
                            <strong>Endereço do Servidor LDAP</strong> <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted small mb-2">
                            Endereço do servidor LDAP. Pode ser um hostname ou IP. Use o prefixo <code>ldap://</code> ou <code>ldaps://</code> para conexões seguras.
                        </p>
                        <input type="text" 
                               class="form-control" 
                               id="ldap_host" 
                               name="ldap_host" 
                               value="<?php echo htmlspecialchars($ldap_host); ?>" 
                               placeholder="ldap://ldap.ifrs.edu.br"
                               required>
                        <div class="form-text">
                            Exemplos: <code>ldap://ldap.ifrs.edu.br</code>, <code>ldaps://ldap.ifrs.edu.br</code>, <code>ldap://ldap.ifrs.edu.br:389</code>, <code>192.168.1.100</code><br>
                            <strong>Nota:</strong> A porta pode ser especificada no próprio endereço (ex: <code>ldap://host:389</code>). Se não especificada, será usada a porta padrão (389 para LDAP, 636 para LDAPS).
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ldap_base_dn" class="form-label">
                            <strong>Base DN</strong> <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted small mb-2">
                            Base Distinguished Name onde os usuários serão buscados no LDAP.
                        </p>
                        <input type="text" 
                               class="form-control" 
                               id="ldap_base_dn" 
                               name="ldap_base_dn" 
                               value="<?php echo htmlspecialchars($ldap_base_dn); ?>" 
                               placeholder="ou=users,dc=ifrs,dc=edu,dc=br"
                               required>
                        <div class="form-text">
                            Exemplo: <code>ou=users,dc=ifrs,dc=edu,dc=br</code> ou <code>dc=ifrs,dc=edu,dc=br</code> (Active Directory)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ldap_user_attribute" class="form-label">
                            <strong>Atributo de Usuário</strong> <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted small mb-2">
                            Atributo LDAP usado para buscar e autenticar usuários. Escolha conforme o tipo de servidor LDAP.
                        </p>
                        <select class="form-select" id="ldap_user_attribute" name="ldap_user_attribute" required>
                            <option value="uid" <?php echo $ldap_user_attribute === 'uid' ? 'selected' : ''; ?>>uid (LDAP padrão)</option>
                            <option value="sAMAccountName" <?php echo $ldap_user_attribute === 'sAMAccountName' ? 'selected' : ''; ?>>sAMAccountName (Active Directory)</option>
                            <option value="userPrincipalName" <?php echo $ldap_user_attribute === 'userPrincipalName' ? 'selected' : ''; ?>>userPrincipalName (Active Directory - UPN)</option>
                            <option value="cn" <?php echo $ldap_user_attribute === 'cn' ? 'selected' : ''; ?>>cn (Common Name)</option>
                            <option value="mail" <?php echo $ldap_user_attribute === 'mail' ? 'selected' : ''; ?>>mail (E-mail)</option>
                        </select>
                        <div class="form-text">
                            <strong>LDAP padrão:</strong> use <code>uid</code><br>
                            <strong>Active Directory:</strong> use <code>sAMAccountName</code> (nome de usuário) ou <code>userPrincipalName</code> (formato: usuario@dominio.com)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ldap_bind_dn" class="form-label">
                            <strong>Bind DN (Opcional)</strong>
                        </label>
                        <p class="text-muted small mb-2">
                            Distinguished Name para bind administrativo. Deixe em branco se não for necessário ou se usar bind anônimo.
                        </p>
                        <input type="text" 
                               class="form-control" 
                               id="ldap_bind_dn" 
                               name="ldap_bind_dn" 
                               value="<?php echo htmlspecialchars($ldap_bind_dn); ?>" 
                               placeholder="cn=admin,dc=ifrs,dc=edu,dc=br">
                        <div class="form-text">
                            Exemplo: <code>cn=admin,dc=ifrs,dc=edu,dc=br</code>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ldap_bind_password" class="form-label">
                            <strong>Senha do Bind DN (Opcional)</strong>
                        </label>
                        <p class="text-muted small mb-2">
                            Senha para o bind administrativo. Deixe em branco para manter a senha atual ou para não usar senha.
                        </p>
                        <input type="password" 
                               class="form-control" 
                               id="ldap_bind_password" 
                               name="ldap_bind_password" 
                               value=""
                               placeholder="Deixe em branco para manter a senha atual"
                               autocomplete="new-password">
                        <div class="form-text">
                            <i class="bi bi-shield-lock"></i> A senha atual não é exibida por segurança. 
                            Deixe em branco para manter a senha atual ou preencha para alterá-la.
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="configuracoes.php" class="btn btn-secondary">Voltar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Configurações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

