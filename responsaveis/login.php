<?php
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$resp = new Responsavel($db);

if ($resp->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$info = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = $_POST['cpf'] ?? '';
    $senha = $_POST['senha'] ?? '';
    if ($cpf === '' || $senha === '') {
        $error = 'Informe CPF e senha.';
    } else {
        $result = $resp->login($cpf, $senha);
        if ($result === true) {
            header('Location: index.php');
            exit;
        }
        if ($result === 'pending') {
            $info = 'Seu cadastro ainda não foi autorizado pela escola. Aguarde a liberação para acessar o portal.';
        } elseif ($result === 'suspended') {
            $error = 'Sua conta está suspensa. Entre em contato com a escola.';
        } elseif ($result === 'rejected') {
            $error = 'Seu cadastro não foi autorizado. Entre em contato com a escola.';
        } else {
            $error = 'CPF ou senha incorretos.';
        }
    }
}

$page_title = 'Login — Responsável';
$show_header = false;
$layout_full = true;
require __DIR__ . '/header.php';
?>

<div class="row justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <div class="text-center mb-2">
                    <img src="../image_white.png" alt="Logo" class="mb-0" style="max-width: 400px; width: 100%; height: auto;">
                </div>
                <div class="mb-2">
                    <a href="../login.php" class="text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
                <p class="text-center text-muted mb-2">Login de responsáveis</p>

                <?php if ($info): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-hourglass-split"></i> <?php echo htmlspecialchars($info); ?>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label" for="cpf">CPF</label>
                        <input type="text" class="form-control form-control-lg cpf-digitos" id="cpf" name="cpf"
                               required inputmode="numeric" pattern="[0-9]{11}" maxlength="11"
                               placeholder="00000000000" autocomplete="username"
                               title="Apenas 11 números, sem pontos ou traços"
                               value="<?php echo htmlspecialchars(normalizeCpf($_POST['cpf'] ?? '')); ?>">
                        <div class="form-text">Apenas números, sem pontos ou traços.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="senha">Senha</label>
                        <input type="password" class="form-control form-control-lg" id="senha" name="senha" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 btn-lg btn-touch">Entrar</button>
                </form>

                <div class="d-grid gap-2 mt-3">
                    <a href="../cadastro_responsavel.php" class="btn btn-outline-primary btn-lg btn-touch py-3">
                        <i class="bi bi-person-plus"></i> Quero me cadastrar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.cpf-digitos').forEach(function (el) {
    el.addEventListener('input', function () {
        this.value = this.value.replace(/\D+/g, '').slice(0, 11);
    });
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
