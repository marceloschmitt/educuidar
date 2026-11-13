<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$alerta_regra = new AlertaRegra($db);
$config = new Configuracao($db);

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$tipo_falta_id = $config->getApiSigaaTipoEventoFaltaId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $parsed = parseAlertaFaltasDiaCompletoFromPost();

    if (!empty($parsed['errors'])) {
        $_SESSION['error'] = implode(' ', $parsed['errors']);
        $redirect = 'alertas_faltas_dia_completo.php';
        if ($action === 'update' && !empty($_POST['id'])) {
            $redirect .= '?edit=' . (int) $_POST['id'];
        }
        header('Location: ' . $redirect);
        exit;
    }

    if (!$tipo_falta_id) {
        $_SESSION['error'] = 'Configure o tipo de evento das faltas do SIGAA em Configurações → API SIGAA antes de criar esta regra.';
        header('Location: alertas_faltas_dia_completo.php');
        exit;
    }

    $alerta_regra->nome = $parsed['nome'];
    $alerta_regra->descricao = $parsed['descricao'] ?: null;
    $alerta_regra->tipo_criterio = 'faltas_todas_disciplinas';
    $alerta_regra->quantidade = $parsed['quantidade'];
    $alerta_regra->intervalo_dias = null;
    $alerta_regra->ignorar_domingos = $parsed['ignorar_domingos'];
    $alerta_regra->ignorar_sabados = $parsed['ignorar_sabados'];
    $alerta_regra->ativo = $parsed['ativo'];

    if ($action === 'create') {
        if ($alerta_regra->create() && $alerta_regra->setTiposEvento($alerta_regra->id, [$tipo_falta_id])) {
            header('Location: alertas_regras.php?success=created');
            exit;
        }
        $_SESSION['error'] = 'Erro ao criar regra.';
    } elseif ($action === 'update' && !empty($_POST['id'])) {
        $existente = $alerta_regra->getById((int) $_POST['id']);
        if (!$existente || ($existente['tipo_criterio'] ?? '') !== 'faltas_todas_disciplinas') {
            $_SESSION['error'] = 'Regra não encontrada.';
        } else {
            $alerta_regra->id = (int) $_POST['id'];
            if ($alerta_regra->update() && $alerta_regra->setTiposEvento($alerta_regra->id, [$tipo_falta_id])) {
                header('Location: alertas_regras.php?success=updated');
                exit;
            }
            $_SESSION['error'] = 'Erro ao atualizar regra.';
        }
    }

    header('Location: alertas_faltas_dia_completo.php');
    exit;
}

$regra_edit = null;
if (isset($_GET['edit'])) {
    $regra_edit = $alerta_regra->getById((int) $_GET['edit']);
    if (!$regra_edit || ($regra_edit['tipo_criterio'] ?? '') !== 'faltas_todas_disciplinas') {
        $_SESSION['error'] = 'Regra não encontrada.';
        header('Location: alertas_regras.php');
        exit;
    }
}

$page_title = 'Faltas completas';
require_once 'includes/header.php';

$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$tipo_falta_nome = null;
if ($tipo_falta_id) {
    $tipo_evento = new TipoEvento($db);
    $te = $tipo_evento->getById($tipo_falta_id);
    $tipo_falta_nome = $te['nome'] ?? ('#' . $tipo_falta_id);
}
?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-x"></i>
                    <?php echo $regra_edit ? 'Editar' : 'Nova'; ?> regra de faltas completas
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Dispara quando o aluno falta a <strong>todas</strong> as disciplinas com aula prevista na grade,
                    por N dias consecutivos. Usa o tipo de falta do SIGAA
                    <?php if ($tipo_falta_nome): ?>
                        (<strong><?php echo htmlspecialchars($tipo_falta_nome); ?></strong>)
                    <?php else: ?>
                        (ainda não configurado em <a href="api_sigaa_config.php">API SIGAA</a>)
                    <?php endif; ?>
                    — sem selecionar tipos de evento.
                </p>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $regra_edit ? 'update' : 'create'; ?>">
                    <?php if ($regra_edit): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $regra_edit['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome"
                               value="<?php echo htmlspecialchars($regra_edit['nome'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"><?php echo htmlspecialchars($regra_edit['descricao'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="quantidade" class="form-label">Dias seguidos <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quantidade" name="quantidade" min="1"
                               value="<?php echo (int) ($regra_edit['quantidade'] ?? 3); ?>" required>
                        <small class="text-muted">
                            Conta só dias em que faltou a todas as disciplinas previstas naquele dia (grade SIGAA).
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Calendário</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ignorar_domingos" name="ignorar_domingos" value="1"
                                   <?php echo (!isset($regra_edit) || !empty($regra_edit['ignorar_domingos'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ignorar_domingos">Ignorar domingos</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ignorar_sabados" name="ignorar_sabados" value="1"
                                   <?php echo (!empty($regra_edit['ignorar_sabados'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ignorar_sabados">Ignorar sábados</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                                   <?php echo (!isset($regra_edit) || !empty($regra_edit['ativo'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">Regra ativa</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" <?php echo $tipo_falta_id ? '' : 'disabled'; ?>>
                        <i class="bi bi-save"></i> <?php echo $regra_edit ? 'Atualizar' : 'Criar'; ?> regra
                    </button>
                    <a href="alertas_regras.php" class="btn btn-secondary w-100 mt-2">
                        <i class="bi bi-x-circle"></i> Voltar à lista
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
