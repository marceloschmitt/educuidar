<?php
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$configuracao = new Configuracao($db);
$responsavel = new Responsavel($db);

$cadastro_aberto = $configuracao->isCadastroResponsaveisHabilitado();
$success = '';
$error = '';

function responsavelCaptchaNovo() {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['cadastro_resp_captcha'] = $a + $b;
    $_SESSION['cadastro_resp_captcha_q'] = "$a + $b";
}

function responsavelRateLimitOk() {
    $key = 'cadastro_resp_attempts';
    $now = time();
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    // Mantém só tentativas dos últimos 15 minutos
    $_SESSION[$key] = array_values(array_filter($_SESSION[$key], function ($t) use ($now) {
        return ($now - (int) $t) < 900;
    }));
    if (count($_SESSION[$key]) >= 8) {
        return false;
    }
    $_SESSION[$key][] = $now;
    return true;
}

if (!isset($_SESSION['cadastro_resp_captcha'])) {
    responsavelCaptchaNovo();
}

$parentescos = ['pai' => 'Pai', 'mãe' => 'Mãe', 'tutor' => 'Tutor(a)', 'outro' => 'Outro'];

if ($cadastro_aberto && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!responsavelRateLimitOk()) {
        $error = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
        responsavelCaptchaNovo();
    } elseif (!empty($_POST['website'])) {
        // Honeypot
        $error = 'Não foi possível concluir o cadastro.';
        responsavelCaptchaNovo();
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $cpf = normalizeCpf($_POST['cpf'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $cpf_aluno = normalizeCpf($_POST['cpf_aluno'] ?? '');
        $parentesco = $_POST['parentesco'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $senha2 = $_POST['senha_confirmacao'] ?? '';
        $captcha = trim($_POST['captcha'] ?? '');

        $esperado = $_SESSION['cadastro_resp_captcha'] ?? null;
        responsavelCaptchaNovo();

        if ($esperado === null || (string) $captcha !== (string) $esperado) {
            $error = 'Resposta do desafio anti-robô incorreta.';
        } elseif ($nome === '' || strlen($cpf) !== 11 || $email === '' || strlen($cpf_aluno) !== 11) {
            $error = 'Preencha todos os campos obrigatórios corretamente.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Informe um e-mail válido.';
        } elseif (!isset($parentescos[$parentesco])) {
            $error = 'Selecione o parentesco.';
        } elseif (strlen($senha) < 6) {
            $error = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif ($senha !== $senha2) {
            $error = 'As senhas não coincidem.';
        } elseif ($responsavel->findByCpf($cpf)) {
            $error = 'Já existe um cadastro com este CPF.';
        } elseif ($responsavel->findByEmail($email)) {
            $error = 'Já existe um cadastro com este e-mail.';
        } else {
            $aluno_id = $responsavel->findAlunoIdByCpf($cpf_aluno);
            if (!$aluno_id) {
                $error = 'Não foi possível validar os dados. Verifique o CPF do aluno e tente novamente.';
            } else {
                $responsavel->nome = $nome;
                $responsavel->cpf = $cpf;
                $responsavel->email = $email;
                $responsavel->password = $senha;
                if ($responsavel->create($aluno_id, $parentesco)) {
                    $success = 'Cadastro recebido. Aguarde a liberação da escola para acessar o portal.';
                } else {
                    $error = 'Erro ao salvar o cadastro. Tente novamente.';
                }
            }
        }
    }
}

$captcha_q = $_SESSION['cadastro_resp_captcha_q'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Cadastro de responsável — EduCuidar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(160deg, #e8f0f5 0%, #f7f7f5 50%, #eef2ea 100%); min-height: 100vh; }
        .card-cadastro { max-width: 520px; margin: 1.5rem auto; border: 0; box-shadow: 0 8px 28px rgba(0,0,0,.08); border-radius: 1rem; }
        .hp-field { position: absolute; left: -9999px; opacity: 0; height: 0; width: 0; overflow: hidden; }
        .form-control, .form-select { font-size: 1.05rem; min-height: 2.75rem; }
        .btn-lg-touch { min-height: 3rem; font-size: 1.1rem; }
    </style>
</head>
<body>
<div class="container py-3">
    <div class="text-center mb-3">
        <h1 class="h3 mb-1">Cadastro de responsável</h1>
        <p class="text-muted mb-0">Pai, mãe, tutor ou outro responsável</p>
    </div>

    <div class="card card-cadastro">
        <div class="card-body p-4">
            <?php if (!$cadastro_aberto): ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-lock"></i>
                O cadastro de responsáveis está temporariamente fechado.
                Entre em contato com a escola.
            </div>
            <div class="mt-3 text-center">
                <a href="responsaveis/login.php" class="btn btn-outline-primary">Já tenho conta — Entrar</a>
            </div>
            <?php elseif ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <a href="responsaveis/login.php" class="btn btn-primary w-100 btn-lg-touch">Ir para o login</a>
            <?php else: ?>
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            <form method="POST" action="" autocomplete="off">
                <div class="hp-field" aria-hidden="true">
                    <label>Website</label>
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="nome">Seu nome completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" required
                           value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="cpf">Seu CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" required inputmode="numeric"
                           placeholder="000.000.000-00"
                           value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Seu e-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="cpf_aluno">CPF do aluno</label>
                    <input type="text" class="form-control" id="cpf_aluno" name="cpf_aluno" required inputmode="numeric"
                           placeholder="000.000.000-00"
                           value="<?php echo htmlspecialchars($_POST['cpf_aluno'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="parentesco">Parentesco</label>
                    <select class="form-select" id="parentesco" name="parentesco" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($parentescos as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"
                            <?php echo (($_POST['parentesco'] ?? '') === $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="senha">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="senha_confirmacao">Confirmar senha</label>
                    <input type="password" class="form-control" id="senha_confirmacao" name="senha_confirmacao" required minlength="6">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="captcha">Quanto é <?php echo htmlspecialchars($captcha_q); ?>?</label>
                    <input type="text" class="form-control" id="captcha" name="captcha" required inputmode="numeric" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-lg-touch">
                    Enviar cadastro
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="responsaveis/login.php">Já tenho conta — Entrar</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
