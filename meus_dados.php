<?php
$page_title = 'Meus Dados';
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Only logged in users can edit their profile
if (!$user->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Get current user data
$user_data = $user->getById($_SESSION['user_id']);
if (!$user_data) {
    header('Location: index.php');
    exit;
}

// Process POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Por favor, preencha todos os campos!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail inválido!';
    } else {
        // Verify that user is updating their own profile
        $user_id = $_SESSION['user_id'];
        if ($user->updateProfile($user_id, $full_name, $email)) {
            $success = 'Dados atualizados com sucesso!';
            // Reload user data to show updated values
            $user_data = $user->getById($_SESSION['user_id']);
        } else {
            $error = 'Erro ao atualizar dados. Tente novamente.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person"></i> Meus Dados</h5>
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
                        <label for="username" class="form-label">Usuário</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" disabled>
                        <small class="text-muted">O usuário não pode ser alterado</small>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="user_type" class="form-label">Tipo de Usuário</label>
                        <input type="text" class="form-control" id="user_type" value="<?php 
                            $tipo_nome = [
                                'administrador' => 'Administrador',
                                'nivel1' => 'Professor',
                                'nivel2' => 'Nível 2',
                                'assistencia_estudantil' => 'Assistência Estudantil'
                            ];
                            echo htmlspecialchars($tipo_nome[$user_data['user_type']] ?? ucfirst($user_data['user_type'] ?? '')); 
                        ?>" disabled>
                        <small class="text-muted">O tipo de usuário não pode ser alterado</small>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Alterações
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

