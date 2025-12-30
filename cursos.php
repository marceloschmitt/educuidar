<?php
$page_title = 'Cursos';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

// Only admin can manage cursos
if (!$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create') {
            $curso->nome = $_POST['nome'] ?? '';
            
            if (empty($curso->nome)) {
                $error = 'Por favor, preencha o nome do curso!';
            } else {
                if ($curso->create()) {
                    $success = 'Curso criado com sucesso!';
                } else {
                    $error = 'Erro ao criar curso.';
                }
            }
        } elseif ($_POST['action'] == 'update' && isset($_POST['id'])) {
            $curso->id = $_POST['id'];
            $curso->nome = $_POST['nome'] ?? '';
            
            if (empty($curso->nome)) {
                $error = 'Por favor, preencha o nome do curso!';
            } else {
                if ($curso->update()) {
                    $success = 'Curso atualizado com sucesso!';
                } else {
                    $error = 'Erro ao atualizar curso.';
                }
            }
        } elseif ($_POST['action'] == 'delete' && isset($_POST['id'])) {
            $curso->id = $_POST['id'];
            if ($curso->delete()) {
                $success = 'Curso excluído com sucesso!';
            } else {
                $error = 'Erro ao excluir curso. Verifique se há turmas associadas.';
            }
        }
    }
}

$cursos = $curso->getAll();
$ano_corrente = $configuracao->getAnoCorrente();
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
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Novo Curso</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do Curso <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Criar Curso
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-book"></i> Lista de Cursos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($cursos)): ?>
                <p class="text-muted text-center">Nenhum curso cadastrado ainda.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Total de Turmas</th>
                                <th>Turmas do Ano Corrente (<?php echo $ano_corrente; ?>)</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cursos as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['nome']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($c['total_turmas'] ?? 0); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $turmas_ano_corrente = $turma->getTurmasPorAnoCorrente($ano_corrente);
                                    $turmas_curso_ano = array_filter($turmas_ano_corrente, function($t) use ($c) {
                                        return $t['curso_id'] == $c['id'];
                                    });
                                    if (!empty($turmas_curso_ano)): 
                                    ?>
                                        <?php foreach ($turmas_curso_ano as $tc): ?>
                                            <span class="badge bg-success me-1 mb-1">
                                                <?php echo htmlspecialchars($tc['ano_curso']); ?>º Ano
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Nenhuma</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" 
                                            class="btn-edit-curso" data-curso='<?php echo htmlspecialchars(json_encode($c)); ?>'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="" style="display: inline;" 
                                          class="form-delete-curso" data-confirm="Tem certeza que deseja excluir este curso? Todas as turmas associadas também serão excluídas.">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
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

<!-- Modal para Editar Curso -->
<div class="modal fade" id="editCursoModal" tabindex="-1" aria-labelledby="editCursoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCursoModalLabel"><i class="bi bi-pencil"></i> Editar Curso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_curso_id">
                    
                    <div class="mb-3">
                        <label for="edit_nome" class="form-label">Nome do Curso <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_nome" name="nome" required>
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

