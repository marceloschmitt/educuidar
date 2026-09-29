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
    $enabledCoordenadores = isset($_POST['email_coordenadores_enabled']);
    $horaResponsaveis = trim($_POST['email_hora_resumo_responsaveis'] ?? '');
    $horaCoordenadores = trim($_POST['email_hora_resumo_coordenadores'] ?? '');
    $algumEnvio = $enabled || $enabledCoordenadores;
    $horaValida = static function ($hora) {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora);
    };

    if (!$horaValida($horaResponsaveis) || !$horaValida($horaCoordenadores)) {
        $error = 'Informe os horários dos resumos no formato HH:MM.';
    } elseif ($algumEnvio && ($host === '' || $fromAddress === '')) {
        $error = 'Com algum envio habilitado, informe o host SMTP e o e-mail remetente.';
    } elseif ($algumEnvio && $fromAddress !== '' && !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail de remetente válido.';
    } else {
        $dados = [
            'enabled' => $enabled,
            'enabled_coordenadores' => $enabledCoordenadores,
            'hora_responsaveis' => $horaResponsaveis,
            'hora_coordenadores' => $horaCoordenadores,
            'host' => $host,
            'port' => $port > 0 ? $port : 587,
            'encryption' => $encryption,
            'username' => $username,
            'from_address' => $fromAddress,
            'from_name' => $fromName !== '' ? $fromName : 'EduCuidar',
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
ob_end_flush();

$page_title = 'Configuração de e-mail';
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-envelope"></i> Configuração de e-mail</h5>
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
                    Uma vez por dia, a partir do horário configurado, cada responsável recebe um resumo dos eventos
                    <strong>ocorridos no dia</strong> (data do evento) dos seus alunos, e cada coordenador recebe
                    o resumo dos seus cursos. Só entram tipos com e-mail habilitado em
                    <a href="tipos_eventos.php">Tipos de eventos</a>. Quem não tem ocorrência no dia não recebe nada.
                </div>

                <form method="POST" action="">
                    <?php
                    $envios = [
                        [
                            'titulo' => 'Responsáveis',
                            'icone' => 'bi-people',
                            'descricao' => 'Eventos do dia dos alunos vinculados a cada responsável aprovado.',
                            'switch' => 'email_enabled',
                            'ligado' => !empty($email['enabled']),
                            'hora_campo' => 'email_hora_resumo_responsaveis',
                            'hora' => $_POST['email_hora_resumo_responsaveis'] ?? $email['hora_responsaveis'],
                        ],
                        [
                            'titulo' => 'Coordenadores',
                            'icone' => 'bi-person-badge',
                            'descricao' => 'Eventos do dia dos cursos que cada coordenador coordena.',
                            'switch' => 'email_coordenadores_enabled',
                            'ligado' => !empty($email['enabled_coordenadores']),
                            'hora_campo' => 'email_hora_resumo_coordenadores',
                            'hora' => $_POST['email_hora_resumo_coordenadores'] ?? $email['hora_coordenadores'],
                        ],
                    ];
                    if ($error && $_SERVER['REQUEST_METHOD'] === 'POST') {
                        $envios[0]['ligado'] = isset($_POST['email_enabled']);
                        $envios[1]['ligado'] = isset($_POST['email_coordenadores_enabled']);
                    }
                    ?>
                    <h6 class="text-muted mb-2">Resumo diário</h6>
                    <div class="row g-3 mb-4">
                        <?php foreach ($envios as $envio): ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="<?php echo $envio['switch']; ?>" name="<?php echo $envio['switch']; ?>" value="1"
                                           <?php echo $envio['ligado'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="<?php echo $envio['switch']; ?>">
                                        <i class="bi <?php echo $envio['icone']; ?>"></i>
                                        Enviar aos <?php echo strtolower($envio['titulo']); ?>
                                    </label>
                                </div>
                                <div class="small text-muted mb-2"><?php echo $envio['descricao']; ?></div>
                                <label for="<?php echo $envio['hora_campo']; ?>" class="form-label small mb-1">A partir de</label>
                                <input type="time" class="form-control form-control-sm" style="max-width: 140px;"
                                       id="<?php echo $envio['hora_campo']; ?>" name="<?php echo $envio['hora_campo']; ?>"
                                       value="<?php echo htmlspecialchars($envio['hora']); ?>" required>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <h6 class="text-muted mb-2">Servidor de envio (SMTP)</h6>
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
                    O envio roda ao final da coleta geral: cada resumo sai na primeira coleta depois do seu horário.
                    Eventos registrados depois disso não entram no resumo daquele dia.
                    Consulte os disparos em <a href="emails_enviados.php">E-mails enviados</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
