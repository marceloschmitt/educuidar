<?php
ob_start();

require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$configuracao = new Configuracao($db);

if (!$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['email_host'] ?? '');
    $port = (int) ($_POST['email_port'] ?? 587);
    $encryption = strtolower(trim($_POST['email_encryption'] ?? 'tls'));
    $username = trim($_POST['email_username'] ?? '');
    $password = $_POST['email_password'] ?? '';
    $fromAddress = trim($_POST['email_from_address'] ?? '');
    $fromName = trim($_POST['email_from_name'] ?? 'EduCuidar');
    $enabled = isset($_POST['email_enabled']);
    $eventosDesde = trim($_POST['email_eventos_desde'] ?? date('Y-m-d'));

    if ($enabled && ($host === '' || $fromAddress === '')) {
        $error = 'Com o envio habilitado, informe o host SMTP e o e-mail remetente.';
    } elseif ($enabled && $fromAddress !== '' && !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail de remetente válido.';
    } elseif ($eventosDesde === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventosDesde)) {
        $error = 'Informe a data inicial dos e-mails no formato válido.';
    } else {
        $dados = [
            'enabled' => $enabled,
            'host' => $host,
            'port' => $port > 0 ? $port : 587,
            'encryption' => $encryption,
            'username' => $username,
            'from_address' => $fromAddress,
            'from_name' => $fromName !== '' ? $fromName : 'EduCuidar',
            'eventos_desde' => $eventosDesde,
        ];
        if ($password !== '') {
            $dados['password'] = $password;
        }
        if ($configuracao->saveEmailConfig($dados)) {
            $success = 'Configurações de e-mail salvas com sucesso!';
        } else {
            $error = 'Erro ao salvar as configurações de e-mail.';
        }
    }
}

$email = $configuracao->getEmailConfig();
$eventos_desde = $configuracao->get('email_eventos_desde');
if ($eventos_desde === null || $eventos_desde === '') {
    $configuracao->setEmailEventosDesde(date('Y-m-d'));
    $eventos_desde = date('Y-m-d');
}
ob_end_flush();

$page_title = 'Configuração de e-mail';
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-envelope"></i> E-mail aos responsáveis</h5>
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

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Aos responsáveis: envio cerca de <strong>2 horas</strong> após o registro,
                    só para tipos com e-mail em <a href="tipos_eventos.php">Tipos de eventos</a>.
                    Aos coordenadores: <strong>um resumo diário após as 19:30</strong> com a lista do dia.
                    Eventos anteriores à data inicial não são notificados.
                </div>

                <form method="POST" action="">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="email_enabled" name="email_enabled" value="1"
                               <?php echo !empty($email['enabled']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="email_enabled">
                            Habilitar envio automático de e-mails
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="email_eventos_desde" class="form-label">Data inicial dos e-mails</label>
                        <input type="date" class="form-control" id="email_eventos_desde" name="email_eventos_desde"
                               required value="<?php echo htmlspecialchars($eventos_desde); ?>" style="max-width: 14rem;">
                        <div class="form-text">
                            Só eventos registrados a partir desta data (e com pelo menos 2 horas) serão notificados.
                            Assim o histórico anterior não gera disparo em massa.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="email_host" class="form-label">Host SMTP</label>
                            <input type="text" class="form-control" id="email_host" name="email_host"
                                   value="<?php echo htmlspecialchars($email['host']); ?>"
                                   placeholder="smtp.exemplo.gov.br">
                        </div>
                        <div class="col-md-4">
                            <label for="email_port" class="form-label">Porta</label>
                            <input type="number" class="form-control" id="email_port" name="email_port"
                                   value="<?php echo (int) $email['port']; ?>" min="1" max="65535">
                        </div>
                        <div class="col-md-4">
                            <label for="email_encryption" class="form-label">Criptografia</label>
                            <select class="form-select" id="email_encryption" name="email_encryption">
                                <?php foreach (['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', 'none' => 'Nenhuma'] as $k => $label): ?>
                                <option value="<?php echo $k; ?>" <?php echo ($email['encryption'] === $k) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="email_username" class="form-label">Usuário SMTP</label>
                            <input type="text" class="form-control" id="email_username" name="email_username"
                                   value="<?php echo htmlspecialchars($email['username']); ?>"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="email_password" class="form-label">Senha SMTP</label>
                            <input type="password" class="form-control" id="email_password" name="email_password"
                                   value="" autocomplete="new-password"
                                   placeholder="<?php echo $email['password'] !== '' ? '•••••••• (deixe em branco para manter)' : ''; ?>">
                            <div class="form-text">Deixe em branco para manter a senha atual.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="email_from_address" class="form-label">Remetente (e-mail)</label>
                            <input type="email" class="form-control" id="email_from_address" name="email_from_address"
                                   value="<?php echo htmlspecialchars($email['from_address']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email_from_name" class="form-label">Nome do remetente</label>
                            <input type="text" class="form-control" id="email_from_name" name="email_from_name"
                                   value="<?php echo htmlspecialchars($email['from_name']); ?>">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar
                        </button>
                        <a href="configuracoes.php" class="btn btn-secondary">Voltar</a>
                    </div>
                </form>

                <hr class="my-4">
                <p class="small text-muted mb-0">
                    O envio roda ao final da coleta geral.
                    Consulte os disparos em <a href="emails_enviados.php">E-mails enviados</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
