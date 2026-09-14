<?php
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$resp = new Responsavel($db);

if (!$resp->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$responsavel_id = $resp->getLoggedId();
$dados = $resp->getById($responsavel_id);
if (!$dados) {
    $resp->logout();
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'nome') {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            $error = 'Informe o nome.';
        } elseif ($resp->updateNome($responsavel_id, $nome)) {
            $success = 'Nome atualizado com sucesso.';
            $dados = $resp->getById($responsavel_id);
        } else {
            $error = 'Não foi possível atualizar o nome.';
        }
    } elseif ($action === 'senha') {
        $senha_atual = $_POST['senha_atual'] ?? '';
        $senha_nova = $_POST['senha_nova'] ?? '';
        $senha_confirma = $_POST['senha_confirma'] ?? '';
        if ($senha_nova !== $senha_confirma) {
            $error = 'A nova senha e a confirmação não coincidem.';
        } else {
            $result = $resp->updateSenha($responsavel_id, $senha_atual, $senha_nova);
            if ($result === true) {
                $success = 'Senha atualizada com sucesso.';
            } elseif ($result === 'senha_atual') {
                $error = 'Senha atual incorreta.';
            } elseif ($result === 'senha_curta') {
                $error = 'A nova senha deve ter pelo menos 6 caracteres.';
            } else {
                $error = 'Não foi possível atualizar a senha.';
            }
        }
    }
}

$page_title = 'Meus dados';
$show_header = true;
$responsavel_nome = $_SESSION['responsavel_nome'] ?? ($dados['nome'] ?? '');
require __DIR__ . '/header.php';
?>

<div class="mb-3">
    <a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card resp-card mb-3">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Alterar nome</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="nome">
            <div class="mb-3">
                <label class="form-label" for="nome">Nome completo</label>
                <input type="text" class="form-control form-control-lg" id="nome" name="nome" required
                       value="<?php echo htmlspecialchars($dados['nome'] ?? ''); ?>">
            </div>
            <p class="small text-muted mb-3">
                CPF: <?php echo htmlspecialchars($dados['cpf'] ?? ''); ?> ·
                E-mail: <?php echo htmlspecialchars($dados['email'] ?? ''); ?>
            </p>
            <button type="submit" class="btn btn-success btn-touch w-100">Salvar nome</button>
        </form>
    </div>
</div>

<div class="card resp-card mb-3">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Alterar senha</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="senha">
            <div class="mb-3">
                <label class="form-label" for="senha_atual">Senha atual</label>
                <input type="password" class="form-control form-control-lg" id="senha_atual" name="senha_atual" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="senha_nova">Nova senha</label>
                <input type="password" class="form-control form-control-lg" id="senha_nova" name="senha_nova" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label" for="senha_confirma">Confirmar nova senha</label>
                <input type="password" class="form-control form-control-lg" id="senha_confirma" name="senha_confirma" required minlength="6">
            </div>
            <button type="submit" class="btn btn-success btn-touch w-100">Salvar senha</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
