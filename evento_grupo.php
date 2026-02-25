<?php
$page_title = 'Evento de Grupo';
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$turma = new Turma($db);
$evento = new Evento($db);
$tipo_evento = new TipoEvento($db);
$configuracao = new Configuracao($db);

if (!$user->isAdmin() && !$user->isNivel0() && !$user->isNivel1() && !$user->isNivel2()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

$current_user_type_id = $_SESSION['user_type_id'] ?? '';
if (empty($current_user_type_id)) {
    $stmt = $db->prepare("SELECT user_type_id FROM user_user_types WHERE user_id = :user_id LIMIT 1");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->fetch();
    $current_user_type_id = $row['user_type_id'] ?? '';
}

$ano_corrente = $configuracao->getAnoCorrente();
$turmas = $turma->getTurmasPorAnoCorrente($ano_corrente);
$tipos_eventos = $tipo_evento->getAll(true);
$tipos_eventos_criacao = array_filter($tipos_eventos, function($te) use ($current_user_type_id) {
    $prontuario_tipo_id = $te['prontuario_user_type_id'] ?? '';
    return empty($prontuario_tipo_id) || (string)$prontuario_tipo_id === (string)$current_user_type_id;
});

$selected_turma_id = $_GET['turma_id'] ?? ($_POST['turma_id'] ?? '');
$selected_tipo_evento_id = $_GET['tipo_evento_id'] ?? ($_POST['tipo_evento_id'] ?? '');
$observacoes_preservar = $_POST['observacoes'] ?? '';
$alunos_turma = [];

if (!empty($selected_turma_id) && !empty($selected_tipo_evento_id)) {
    $alunos_turma = $turma->getAlunos($selected_turma_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $selected_turma_id = $_POST['turma_id'] ?? '';
    $selected_tipo_evento_id = $_POST['tipo_evento_id'] ?? '';
    $selected_alunos = $_POST['alunos'] ?? [];

    $allowed_tipo_ids = array_map(function($te) {
        return (string)($te['id'] ?? '');
    }, $tipos_eventos_criacao);

    if (empty($selected_turma_id) || empty($selected_tipo_evento_id)) {
        $error = 'Selecione a turma e o tipo de evento.';
    } elseif (!in_array((string)$selected_tipo_evento_id, $allowed_tipo_ids, true)) {
        $error = 'Tipo de evento inválido para o seu usuário.';
    } elseif (empty($selected_alunos) || !is_array($selected_alunos)) {
        $error = 'Selecione ao menos um aluno.';
    } else {
        $alunos_turma = $turma->getAlunos($selected_turma_id);
        $alunos_ids_turma = array_map(function($a) {
            return (string)($a['id'] ?? '');
        }, $alunos_turma);

        $selected_alunos = array_filter(array_map('strval', $selected_alunos), function($id) use ($alunos_ids_turma) {
            return in_array($id, $alunos_ids_turma, true);
        });

        if (empty($selected_alunos)) {
            $error = 'Selecione alunos válidos da turma.';
        } else {
            $created = 0;
            $errors = [];
            $user_id = $_SESSION['user_id'] ?? null;
            if (!$user_id) {
                $errors[] = 'Usuário inválido.';
            } else {
                foreach ($selected_alunos as $aluno_id) {
                    $evento->id = null;
                    $evento->aluno_id = $aluno_id;
                    $evento->turma_id = $selected_turma_id;
                    $evento->tipo_evento_id = $selected_tipo_evento_id;
                    $evento->data_evento = date('Y-m-d');
                    $evento->hora_evento = date('H:i');
                    $evento->observacoes = $_POST['observacoes'] ?? '';
                    $evento->prontuario = '';
                    $evento->registrado_por = $user_id;

                    if ($evento->create()) {
                        $created++;
                    } else {
                        $errors[] = "Falha ao criar evento para o aluno ID {$aluno_id}.";
                    }
                }
            }

            if ($created > 0) {
                $success = "Eventos criados para {$created} aluno(s).";
            }
            if (!empty($errors)) {
                $error = implode(' ', $errors);
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
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

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> Evento de Grupo</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filtroEventoGrupo" class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="turma_id" class="form-label">Turma (Ano <?php echo htmlspecialchars($ano_corrente); ?>)</label>
                        <select class="form-select" id="turma_id" name="turma_id">
                            <option value="">Selecione a turma...</option>
                            <?php foreach ($turmas as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['id']); ?>" <?php echo ((string)$selected_turma_id === (string)$t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['curso_nome'] ?? ''); ?> - <?php echo htmlspecialchars($t['ano_curso'] ?? ''); ?>º Ano
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="tipo_evento_id" class="form-label">Tipo de Evento</label>
                        <select class="form-select" id="tipo_evento_id" name="tipo_evento_id">
                            <option value="">Selecione o tipo...</option>
                            <?php foreach ($tipos_eventos_criacao as $te): ?>
                            <option value="<?php echo htmlspecialchars($te['id']); ?>" <?php echo ((string)$selected_tipo_evento_id === (string)$te['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($te['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if (!empty($selected_turma_id) && !empty($selected_tipo_evento_id)): ?>
                    <?php if (empty($alunos_turma)): ?>
                        <p class="text-muted">Nenhum aluno encontrado para a turma selecionada.</p>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_group">
                            <input type="hidden" name="turma_id" value="<?php echo htmlspecialchars($selected_turma_id); ?>">
                            <input type="hidden" name="tipo_evento_id" value="<?php echo htmlspecialchars($selected_tipo_evento_id); ?>">

                            <div class="mb-3">
                                <label for="observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Observações comuns a todos os eventos criados"><?php echo htmlspecialchars($observacoes_preservar); ?></textarea>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;"></th>
                                            <th>Aluno</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alunos_turma as $a): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input" name="alunos[]" value="<?php echo htmlspecialchars($a['id']); ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($a['nome']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Criar eventos
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">Selecione uma turma e um tipo de evento para listar os alunos.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var turmaSelect = document.getElementById('turma_id');
    var tipoSelect = document.getElementById('tipo_evento_id');
    var form = document.getElementById('filtroEventoGrupo');
    if (turmaSelect && tipoSelect && form) {
        turmaSelect.addEventListener('change', function() {
            form.submit();
        });
        tipoSelect.addEventListener('change', function() {
            form.submit();
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
