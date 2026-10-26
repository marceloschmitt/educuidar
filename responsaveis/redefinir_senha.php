<?php
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$resp = new Responsavel($db);

if ($resp->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
$conta = $token !== '' ? $resp->findByResetToken($token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
    $senha = $_POST['senha_nova'] ?? '';
    $confirma = $_POST['senha_confirma'] ?? '';

    if ($senha !== $confirma) {
        $error = 'A nova senha e a confirmação não coincidem.';
        $conta = $resp->findByResetToken($token);
    } else {
        $resultado = $resp->redefinirSenhaComToken($token, $senha);
        if ($resultado === true) {
            $success = 'Senha atualizada. Você já pode entrar com a nova senha.';
            $conta = null;
        } elseif ($resultado === 'senha_curta') {
            $error = 'A nova senha deve ter pelo menos 6 caracteres.';
            $conta = $resp->findByResetToken($token);
        } elseif ($resultado === 'token') {
            $error = 'Este link é inválido ou expirou. Solicite um novo.';
            $conta = null;
        } else {
            $error = 'Não foi possível atualizar a senha. Tente novamente.';
            $conta = $resp->findByResetToken($token);
        }
    }
}

$page_title = 'Redefinir senha — Responsável';
$show_header = false;
$layout_full = true;
require __DIR__ . '/header.php';
?>

<div class="row justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <div class="mb-3">
                    <a href="login.php" class="text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Voltar ao login
                    </a>
                </div>
                <h1 class="h4 mb-2">Redefinir senha</h1>

                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <a href="login.php" class="btn btn-success w-100 btn-lg btn-touch">Entrar</a>
                <?php elseif (!$conta): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php echo $error !== '' ? htmlspecialchars($error) : 'Este link é inválido ou expirou.'; ?>
                </div>
                <a href="esqueci_senha.php" class="btn btn-outline-success w-100 btn-touch">Solicitar novo link</a>
                <?php else: ?>
                <p class="text-muted mb-3">
                    Defina uma nova senha para
                    <strong><?php echo htmlspecialchars($conta['nome'] ?? 'sua conta'); ?></strong>.
                </p>
                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="senha_nova">Nova senha</label>
                        <input type="password" class="form-control form-control-lg" id="senha_nova" name="senha_nova"
                               required minlength="6" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="senha_confirma">Confirmar nova senha</label>
                        <input type="password" class="form-control form-control-lg" id="senha_confirma" name="senha_confirma"
                               required minlength="6" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-success w-100 btn-lg btn-touch">Salvar nova senha</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
