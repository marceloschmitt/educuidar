<?php
$page_title = 'Turmas';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$turma = new Turma($db);
$curso = new Curso($db);

// Only admin can manage turmas
if (!$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';
$turma_edit = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create') {
            $turma->curso_id = $_POST['curso_id'] ?? '';
            $turma->ano_civil = $_POST['ano_civil'] ?? '';
            $turma->ano_curso = $_POST['ano_curso'] ?? '';
            
            if (empty($turma->curso_id) || empty($turma->ano_civil) || empty($turma->ano_curso)) {
                $error = 'Por favor, preencha todos os campos!';
            } else {
                if ($turma->create()) {
                    $success = 'Turma criada com sucesso!';
                } else {
                    $error = $turma->error_message ?? 'Erro ao criar turma. A turma já pode existir para este curso.';
                }
            }
        } elseif ($_POST['action'] == 'update' && isset($_POST['id'])) {
            $turma->id = $_POST['id'];
            $turma->curso_id = $_POST['curso_id'] ?? '';
            $turma->ano_civil = $_POST['ano_civil'] ?? '';
            $turma->ano_curso = $_POST['ano_curso'] ?? '';
            
            if (empty($turma->curso_id) || empty($turma->ano_civil) || empty($turma->ano_curso)) {
                $error = 'Por favor, preencha todos os campos!';
            } else {
                if ($turma->update()) {
                    $success = 'Turma atualizada com sucesso!';
                } else {
                    $error = $turma->error_message ?? 'Erro ao atualizar turma. A turma já pode existir para este curso.';
                }
            }
        } elseif ($_POST['action'] == 'delete' && isset($_POST['id'])) {
            $turma->id = $_POST['id'];
            if ($turma->delete()) {
                $success = 'Turma excluída com sucesso!';
            } else {
                $error = 'Erro ao excluir turma. Verifique se há alunos associados.';
            }
        }
    }
}

// Get turma for editing if requested
if (isset($_GET['edit'])) {
    $turma_edit = $turma->getById($_GET['edit']);
    if (!$turma_edit) {
        $error = 'Turma não encontrada!';
    }
}

// Get ano corrente for sorting
$configuracao = new Configuracao($db);
$ano_corrente = $configuracao->getAnoCorrente();

// Filter by curso if provided
$filtro_curso = $_GET['filtro_curso'] ?? '';
if (!empty($filtro_curso)) {
    $turmas = $turma->getByCurso($filtro_curso);
} else {
    $turmas = $turma->getAll();
}

// Sort: turmas do ano corrente primeiro, depois por ano civil e ano do curso
usort($turmas, function($a, $b) use ($ano_corrente) {
    $a_is_corrente = ($a['ano_civil'] == $ano_corrente) ? 1 : 0;
    $b_is_corrente = ($b['ano_civil'] == $ano_corrente) ? 1 : 0;
    
    // Primeiro ordena por ano corrente (1 primeiro)
    if ($a_is_corrente != $b_is_corrente) {
        return $b_is_corrente - $a_is_corrente;
    }
    
    // Depois por ano civil (decrescente - anos mais recentes primeiro)
    if ($a['ano_civil'] != $b['ano_civil']) {
        return $b['ano_civil'] - $a['ano_civil'];
    }
    
    // Por fim por ano do curso (crescente - 1º, 2º, 3º)
    return $a['ano_curso'] - $b['ano_curso'];
});
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
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nova Turma</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="curso_id" class="form-label">Curso <span class="text-danger">*</span></label>
                        <?php
                        $cursos = $curso->getAll();
                        ?>
                        <select class="form-select" id="curso_id" name="curso_id" required>
                            <option value="">Selecione um curso...</option>
                            <?php foreach ($cursos as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['nome']); ?>
                                <?php if (!empty($c['codigo'])): ?>
                                    (<?php echo htmlspecialchars($c['codigo']); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ano_civil" class="form-label">Ano Civil <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="ano_civil" name="ano_civil" 
                               value="<?php echo date('Y'); ?>" min="2000" max="2100" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ano_curso" class="form-label">Ano do Curso <span class="text-danger">*</span></label>
                        <select class="form-select" id="ano_curso" name="ano_curso" required>
                            <option value="">Selecione...</option>
                            <option value="1">1º Ano</option>
                            <option value="2">2º Ano</option>
                            <option value="3">3º Ano</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Criar Turma
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-collection"></i> Lista de Turmas</h5>
                <form method="GET" action="" class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="filtro_curso" name="filtro_curso" style="width: auto;">
                        <option value="">Todos os cursos</option>
                        <?php
                        $cursos = $curso->getAll();
                        foreach ($cursos as $c):
                        ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($filtro_curso == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nome']); ?>
                            <?php if (!empty($c['codigo'])): ?>
                                (<?php echo htmlspecialchars($c['codigo']); ?>)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($filtro_curso)): ?>
                    <a href="turmas.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Limpar
                    </a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body">
                <?php if (empty($turmas)): ?>
                <p class="text-muted text-center">Nenhuma turma cadastrada ainda.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Ano Civil</th>
                                <th>Ano do Curso</th>
                                <th>Total de Alunos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($turmas as $t): 
                                $is_ano_corrente = ($t['ano_civil'] == $ano_corrente);
                            ?>
                            <tr class="<?php echo $is_ano_corrente ? 'table-success' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($t['curso_nome']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($t['ano_civil']); ?>
                                    <?php if ($is_ano_corrente): ?>
                                        <span class="badge bg-success ms-1">Ano Corrente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($t['ano_curso']); ?>º Ano</td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($t['total_alunos'] ?? 0); ?></span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $t['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $t['id']; ?>">
                                            <li>
                                                <a class="dropdown-item" href="gerenciar_turmas_alunos.php?turma_id=<?php echo $t['id']; ?>">
                                                    <i class="bi bi-people text-primary"></i> Gerenciar Alunos
                                                </a>
                                            </li>
                                            <li>
                                                <button class="dropdown-item btn-edit-turma" type="button" data-turma='<?php echo htmlspecialchars(json_encode($t)); ?>'>
                                                    <i class="bi bi-pencil text-primary"></i> Editar
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="" class="form-delete-turma" data-confirm="Tem certeza que deseja excluir esta turma? Todas as associações com alunos também serão removidas.">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash"></i> Excluir
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
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

<!-- Modal de Edição -->
<div class="modal fade" id="editTurmaModal" tabindex="-1" aria-labelledby="editTurmaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTurmaModalLabel"><i class="bi bi-pencil"></i> Editar Turma</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_turma_id">
                    
                    <div class="mb-3">
                        <label for="edit_curso_id" class="form-label">Curso <span class="text-danger">*</span></label>
                        <?php
                        $cursos = $curso->getAll();
                        ?>
                        <select class="form-select" id="edit_curso_id" name="curso_id" required>
                            <option value="">Selecione um curso...</option>
                            <?php foreach ($cursos as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['nome']); ?>
                                <?php if (!empty($c['codigo'])): ?>
                                    (<?php echo htmlspecialchars($c['codigo']); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_ano_civil" class="form-label">Ano Civil <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_ano_civil" name="ano_civil" 
                               min="2000" max="2100" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_ano_curso" class="form-label">Ano do Curso <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_ano_curso" name="ano_curso" required>
                            <option value="">Selecione...</option>
                            <option value="1">1º Ano</option>
                            <option value="2">2º Ano</option>
                            <option value="3">3º Ano</option>
                        </select>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?>

