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
            $error = 'Seu cadastro ainda não foi liberado pela escola.';
        } else {
            $error = 'CPF ou senha incorretos.';
        }
    }
}

$page_title = 'Login — Responsável';
$show_header = false;
require __DIR__ . '/header.php';
?>

<div class="text-center mb-4 mt-4">
    <h1 class="h3">Portal do responsável</h1>
    <p class="text-muted">Acompanhe os eventos do seu aluno</p>
</div>

<div class="card resp-card">
    <div class="card-body p-4">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label" for="cpf">CPF</label>
                <input type="text" class="form-control form-control-lg" id="cpf" name="cpf"
                       required inputmode="numeric" placeholder="000.000.000-00"
                       value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>">
            </div>
            <div class="mb-4">
                <label class="form-label" for="senha">Senha</label>
                <input type="password" class="form-control form-control-lg" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-success w-100 btn-lg btn-touch">Entrar</button>
        </form>
        <div class="text-center mt-3">
            <a href="../cadastro_responsavel.php">Quero me cadastrar</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
