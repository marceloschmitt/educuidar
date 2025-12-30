<?php
$page_title = 'Alterar Senha';
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Only logged in users can change password
if (!$user->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Process POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Por favor, preencha todos os campos!';
    } elseif ($new_password !== $confirm_password) {
        $error = 'As senhas não coincidem!';
    } elseif (strlen($new_password) < 6) {
        $error = 'A nova senha deve ter pelo menos 6 caracteres!';
    } else {
        $result = $user->changePassword($_SESSION['user_id'], $current_password, $new_password);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-key"></i> Alterar Senha</h5>
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

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Senha Atual <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nova Senha <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="text-muted">Mínimo de 6 caracteres</small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Nova Senha <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Alterar Senha
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

