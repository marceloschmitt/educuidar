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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = normalizeCpf($_POST['cpf'] ?? '');
    if (strlen($cpf) !== 11) {
        $error = 'Informe um CPF válido (11 números).';
    } else {
        $resultado = $resp->solicitarRedefinicaoSenha($cpf, Responsavel::urlRedefinirSenha());
        if ($resultado === 'envio' || $resultado === 'email_config') {
            $error = 'Não foi possível enviar o e-mail. Tente mais tarde ou fale com a escola.';
        } elseif ($resultado === false) {
            $error = 'Não foi possível processar o pedido. Tente novamente.';
        } else {
            $success = 'Se o CPF estiver cadastrado e liberado pela escola, enviaremos um link para o e-mail cadastrado. Verifique também a caixa de spam.';
        }
    }
}

$page_title = 'Esqueci a senha — Responsável';
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
                <h1 class="h4 mb-2">Esqueci a senha</h1>
                <p class="text-muted mb-3">
                    Informe o CPF da conta. Enviaremos um link de redefinição para o e-mail cadastrado.
                </p>

                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php if (!$success): ?>
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
                    <button type="submit" class="btn btn-success w-100 btn-lg btn-touch">Enviar link</button>
                </form>
                <?php else: ?>
                <a href="login.php" class="btn btn-outline-success w-100 btn-touch">Ir para o login</a>
                <?php endif; ?>
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
