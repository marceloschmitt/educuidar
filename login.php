<?php
// Process POST request BEFORE including header (to allow redirects)
$error = '';
$ldap_error = '';

// Check for LDAP error from previous login attempt
if (isset($_SESSION['ldap_error'])) {
    $ldap_error = $_SESSION['ldap_error'];
    unset($_SESSION['ldap_error']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config/init.php';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username)) {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        $configuracao = new Configuracao($db);
        
        // Check if system is installed
        $is_installed = $configuracao->isSistemaInstalado();
        
        // Check if admin user exists and has no password (first installation)
        $check_query = "SELECT u.id, u.password, ut.nivel as user_level
                        FROM users u
                        LEFT JOIN user_user_types uut ON uut.user_id = u.id
                        LEFT JOIN user_types ut ON ut.id = uut.user_type_id
                        WHERE u.username = :username OR u.email = :email
                        LIMIT 1";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':email', $username);
        $check_stmt->execute();
        
        $is_admin_without_password = false;
        if ($check_stmt->rowCount() > 0) {
            $check_user = $check_stmt->fetch();
            if (($check_user['user_level'] ?? '') === 'administrador' && empty($check_user['password']) && !$is_installed) {
                $is_admin_without_password = true;
            }
        }
        
        // Password is required unless it's admin's first login (system not installed)
        if (empty($password) && !$is_admin_without_password) {
            $error = 'Por favor, informe a senha!';
        } else {
            $login_result = $user->login($username, $password ?? '');
            
            if ($login_result === 'SET_PASSWORD') {
                // Admin user needs to set password on first login
                $_SESSION['setup_admin_password'] = true;
                $_SESSION['setup_admin_username'] = $username;
                header('Location: definir_senha_admin.php');
                exit;
            } elseif ($login_result === true) {
            header('Location: index.php');
            exit;
            } else {
                // Check if there's a specific LDAP error
                if (isset($_SESSION['ldap_error'])) {
                    $ldap_error = $_SESSION['ldap_error'];
                    unset($_SESSION['ldap_error']);
                    $error = 'Erro na autenticação LDAP!';
        } else {
            $error = 'Usuário ou senha incorretos!';
                }
            }
        }
    } else {
        $error = 'Por favor, informe o usuário!';
    }
}

$page_title = 'Login';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="image_white.png" alt="Logo" class="mb-1" style="max-width: 400px; width: 100%; height: auto;">
                        <p class="text-muted">Faça login para continuar</p>
                    </div>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <strong><?php echo htmlspecialchars($error); ?></strong>
                        <?php if ($ldap_error): ?>
                        <hr>
                        <small><strong>Detalhes do erro LDAP:</strong><br><?php echo nl2br(htmlspecialchars($ldap_error)); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuário ou E-mail</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted">Deixe em branco se for o primeiro login do administrador</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Entrar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

