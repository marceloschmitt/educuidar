<?php
// Process POST request BEFORE including header (to allow redirects)
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config/init.php';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        
        if ($user->login($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $error = 'Usuário ou senha incorretos!';
        }
    } else {
        $error = 'Por favor, preencha todos os campos!';
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
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuário ou E-mail</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="password" name="password" required>
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

