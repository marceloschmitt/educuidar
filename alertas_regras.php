<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$alerta_regra = new AlertaRegra($db);
$tipo_evento = new TipoEvento($db);

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $parsed = parseAlertaRegraFromPost();

    if (!empty($parsed['errors'])) {
        $_SESSION['error'] = implode(' ', $parsed['errors']);
        $redirect = 'alertas_regras.php';
        if ($_POST['action'] === 'update' && !empty($_POST['id'])) {
            $redirect .= '?edit=' . (int) $_POST['id'];
        }
        header('Location: ' . $redirect);
        exit;
    }

    $alerta_regra->nome = $parsed['nome'];
    $alerta_regra->descricao = $parsed['descricao'] ?: null;
    $alerta_regra->tipo_criterio = $parsed['tipo_criterio'];
    $alerta_regra->quantidade = $parsed['quantidade'];
    $alerta_regra->intervalo_dias = $parsed['intervalo_dias'];
    $alerta_regra->ignorar_domingos = $parsed['ignorar_domingos'];
    $alerta_regra->ignorar_sabados = $parsed['ignorar_sabados'];
    $alerta_regra->ativo = $parsed['ativo'];

    if ($_POST['action'] === 'create') {
        if ($alerta_regra->create() && $alerta_regra->setTiposEvento($alerta_regra->id, $parsed['tipos_evento'])) {
            header('Location: alertas_regras.php?success=created');
            exit;
        }
        $_SESSION['error'] = 'Erro ao criar regra de alerta.';
    } elseif ($_POST['action'] === 'update' && !empty($_POST['id'])) {
        $alerta_regra->id = (int) $_POST['id'];
        if ($alerta_regra->update() && $alerta_regra->setTiposEvento($alerta_regra->id, $parsed['tipos_evento'])) {
            header('Location: alertas_regras.php?success=updated');
            exit;
        }
        $_SESSION['error'] = 'Erro ao atualizar regra de alerta.';
    } elseif ($_POST['action'] === 'delete' && !empty($_POST['id'])) {
        if ($alerta_regra->delete((int) $_POST['id'])) {
            header('Location: alertas_regras.php?success=deleted');
            exit;
        }
        $_SESSION['error'] = 'Erro ao excluir regra de alerta.';
    }

    header('Location: alertas_regras.php');
    exit;
}

$page_title = 'Regras de Alerta';
require_once 'includes/header.php';

$success = '';
$error = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') {
        $success = 'Regra de alerta criada com sucesso!';
    } elseif ($_GET['success'] === 'updated') {
        $success = 'Regra de alerta atualizada com sucesso!';
    } elseif ($_GET['success'] === 'deleted') {
        $success = 'Regra de alerta excluída com sucesso!';
    }
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$regra_edit = null;
if (isset($_GET['edit'])) {
    $regra_edit = $alerta_regra->getById((int) $_GET['edit']);
    if (!$regra_edit) {
        $error = 'Regra de alerta não encontrada.';
    }
}

$regras = $alerta_regra->getAll();
$tipos_eventos = $tipo_evento->getAll(true);
$tipos_selecionados = $regra_edit['tipos_evento'] ?? [];
?>

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

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bell"></i> <?php echo $regra_edit ? 'Editar' : 'Nova'; ?> Regra de Alerta</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="alertaRegraForm">
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
                        <label class="form-label">Tipos de evento <span class="text-danger">*</span></label>
                        <div class="border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                            <?php foreach ($tipos_eventos as $te): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tipos_evento[]"
                                       value="<?php echo (int) $te['id']; ?>"
                                       id="tipo_evento_<?php echo (int) $te['id']; ?>"
                                       <?php echo in_array((int) $te['id'], $tipos_selecionados, true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tipo_evento_<?php echo (int) $te['id']; ?>">
                                    <span class="badge bg-<?php echo htmlspecialchars($te['cor'] ?? 'secondary'); ?> me-1">
                                        <?php echo htmlspecialchars($te['nome']); ?>
                                    </span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Critério de disparo <span class="text-danger">*</span></label>
                        <?php
                        $tipo_atual = $regra_edit['tipo_criterio'] ?? 'dias_consecutivos';
                        $criterios = [
                            'dias_consecutivos' => 'Dias consecutivos',
                            'intervalo_dias' => 'Intervalo de dias',
                            'mesmo_dia' => 'Mesmo dia',
                        ];
                        foreach ($criterios as $valor => $label):
                        ?>
                        <div class="form-check">
                            <input class="form-check-input alerta-tipo-criterio" type="radio"
                                   name="tipo_criterio" id="criterio_<?php echo $valor; ?>"
                                   value="<?php echo $valor; ?>"
                                   <?php echo $tipo_atual === $valor ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="criterio_<?php echo $valor; ?>">
                                <?php echo $label; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3" id="campo_quantidade_consecutivos">
                        <label for="quantidade_consecutivos" class="form-label">Dias seguidos</label>
                        <input type="number" class="form-control alerta-quantidade-input" id="quantidade_consecutivos"
                               min="1" value="<?php echo (int) ($regra_edit['quantidade'] ?? 3); ?>">
                    </div>

                    <div class="mb-3 d-none" id="campo_intervalo">
                        <label class="form-label">Ocorrências no intervalo</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" class="form-control alerta-quantidade-input" id="quantidade_intervalo"
                                       min="1" placeholder="Ocorrências"
                                       value="<?php echo (int) ($regra_edit['quantidade'] ?? 5); ?>">
                            </div>
                            <div class="col-6">
                                <input type="number" class="form-control" name="intervalo_dias" id="intervalo_dias"
                                       min="1" placeholder="Dias"
                                       value="<?php echo (int) ($regra_edit['intervalo_dias'] ?? 7); ?>">
                            </div>
                        </div>
                        <small class="text-muted">Ex.: 5 ocorrências em 7 dias</small>
                    </div>

                    <div class="mb-3 d-none" id="campo_mesmo_dia">
                        <label for="quantidade_mesmo_dia" class="form-label">Ocorrências no mesmo dia</label>
                        <input type="number" class="form-control alerta-quantidade-input" id="quantidade_mesmo_dia"
                               min="1" value="<?php echo (int) ($regra_edit['quantidade'] ?? 2); ?>">
                    </div>

                    <input type="hidden" name="quantidade" id="quantidade" value="<?php echo (int) ($regra_edit['quantidade'] ?? 3); ?>">

                    <div class="mb-3" id="campo_calendario">
                        <label class="form-label">Calendário</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ignorar_domingos" name="ignorar_domingos" value="1"
                                   <?php echo (!isset($regra_edit) || !empty($regra_edit['ignorar_domingos'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ignorar_domingos">Ignorar domingos (dias consecutivos)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ignorar_sabados" name="ignorar_sabados" value="1"
                                   <?php echo (!empty($regra_edit['ignorar_sabados'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ignorar_sabados">Ignorar sábados (dias consecutivos)</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                                   <?php echo (!isset($regra_edit) || !empty($regra_edit['ativo'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">Regra ativa</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> <?php echo $regra_edit ? 'Atualizar' : 'Criar'; ?> Regra
                    </button>
                    <?php if ($regra_edit): ?>
                    <a href="alertas_regras.php" class="btn btn-secondary w-100 mt-2">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Regras Cadastradas</h5>
                <a href="alertas.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-exclamation-triangle"></i> Ver alertas
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($regras)): ?>
                <p class="text-muted text-center mb-0">Nenhuma regra cadastrada ainda.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Critério</th>
                                <th>Ativo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regras as $r): ?>
                            <tr <?php echo empty($r['ativo']) ? 'class="table-secondary"' : ''; ?>>
                                <td>
                                    <div><?php echo htmlspecialchars($r['nome']); ?></div>
                                    <?php if (!empty($r['tipos_evento_nomes'])): ?>
                                    <div class="mt-1">
                                        <?php foreach ($r['tipos_evento_nomes'] as $te): ?>
                                        <span class="badge bg-<?php echo htmlspecialchars($te['cor'] ?? 'secondary'); ?> me-1 mb-1">
                                            <?php echo htmlspecialchars($te['nome']); ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(formatAlertaCriterioResumo($r)); ?></td>
                                <td>
                                    <?php if (!empty($r['ativo'])): ?>
                                        <span class="badge bg-success">Sim</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex gap-2">
                                    <a href="alertas_regras.php?edit=<?php echo (int) $r['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="" onsubmit="return confirm('Excluir esta regra de alerta?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var form = document.getElementById('alertaRegraForm');
    if (!form) return;

    var radios = form.querySelectorAll('.alerta-tipo-criterio');
    var campoConsecutivos = document.getElementById('campo_quantidade_consecutivos');
    var campoIntervalo = document.getElementById('campo_intervalo');
    var campoMesmoDia = document.getElementById('campo_mesmo_dia');
    var campoCalendario = document.getElementById('campo_calendario');
    var quantidadeHidden = document.getElementById('quantidade');

    function tipoSelecionado() {
        var checked = form.querySelector('.alerta-tipo-criterio:checked');
        return checked ? checked.value : 'dias_consecutivos';
    }

    function syncQuantidadeHidden() {
        var tipo = tipoSelecionado();
        if (tipo === 'dias_consecutivos') {
            quantidadeHidden.value = document.getElementById('quantidade_consecutivos').value;
        } else if (tipo === 'intervalo_dias') {
            quantidadeHidden.value = document.getElementById('quantidade_intervalo').value;
        } else {
            quantidadeHidden.value = document.getElementById('quantidade_mesmo_dia').value;
        }
    }

    function atualizarCampos() {
        var tipo = tipoSelecionado();
        campoConsecutivos.classList.toggle('d-none', tipo !== 'dias_consecutivos');
        campoIntervalo.classList.toggle('d-none', tipo !== 'intervalo_dias');
        campoMesmoDia.classList.toggle('d-none', tipo !== 'mesmo_dia');
        campoCalendario.classList.toggle('d-none', tipo !== 'dias_consecutivos');
        syncQuantidadeHidden();
    }

    radios.forEach(function(radio) {
        radio.addEventListener('change', atualizarCampos);
    });

    form.querySelectorAll('.alerta-quantidade-input').forEach(function(input) {
        input.addEventListener('input', syncQuantidadeHidden);
    });

    form.addEventListener('submit', syncQuantidadeHidden);
    atualizarCampos();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
