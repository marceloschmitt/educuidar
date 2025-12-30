<?php
// Process POST request BEFORE including header (to allow redirects)
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config/init.php';
    
    // Check if this is a valid setup session
    if (!isset($_SESSION['setup_admin_password']) || !$_SESSION['setup_admin_password']) {
        header('Location: login.php');
        exit;
    }
    
    $username = $_SESSION['setup_admin_username'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $error = 'Por favor, informe uma senha!';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres!';
    } elseif ($password !== $password_confirm) {
        $error = 'As senhas não coincidem!';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        
        // Get admin user ID
        $query = "SELECT id FROM users WHERE username = :username AND user_type = 'administrador' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $admin_user = $stmt->fetch();
            $admin_id = $admin_user['id'];
            
            // Update password
            if ($user->updatePassword($admin_id, $password)) {
                // Clear setup session
                unset($_SESSION['setup_admin_password']);
                unset($_SESSION['setup_admin_username']);
                
                // Login the user
                if ($user->login($username, $password)) {
                    $success = true;
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Senha definida, mas houve erro ao fazer login. Tente fazer login novamente.';
                }
            } else {
                $error = 'Erro ao definir senha. Tente novamente.';
            }
        } else {
            $error = 'Usuário administrador não encontrado!';
        }
    }
}

$page_title = 'Definir Senha do Administrador';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="image_white.png" alt="Logo" class="mb-1" style="max-width: 400px; width: 100%; height: auto;">
                        <h4 class="mt-3">Primeiro Acesso</h4>
                        <p class="text-muted">Defina a senha do administrador</p>
                    </div>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> Senha definida com sucesso! Redirecionando...
                    </div>
                    <?php else: ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="password" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="password" name="password" required autofocus minlength="6">
                            <small class="form-text text-muted">Mínimo de 6 caracteres</small>
                        </div>
                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Confirmar Senha</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-key"></i> Definir Senha
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

