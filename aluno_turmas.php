<?php
$page_title = 'Gerenciar Turmas do Aluno';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$aluno = new Aluno($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

// Only admin can manage aluno turmas
if (!$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$aluno_id = $_GET['id'] ?? 0;
if (empty($aluno_id)) {
    header('Location: alunos.php');
    exit;
}

// Verificar se o aluno existe
$aluno_data = $aluno->getById($aluno_id);

if (!$aluno_data) {
    header('Location: alunos.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'adicionar') {
            $turma_id = $_POST['turma_id'] ?? '';
            
            if (empty($turma_id)) {
                $error = 'Selecione uma turma!';
            } else {
                if ($aluno->adicionarTurmaAluno($aluno_id, $turma_id)) {
                    $success = 'Turma adicionada com sucesso!';
                } else {
                    $error = 'Erro ao adicionar turma. A turma já pode estar associada.';
                }
            }
        } elseif ($_POST['action'] == 'remover') {
            $turma_id = $_POST['turma_id'] ?? '';
            if ($aluno->removerTurmaAluno($aluno_id, $turma_id)) {
                $success = 'Turma removida com sucesso!';
            } else {
                $error = 'Erro ao remover turma.';
            }
        }
    }
}

$turmas_aluno = $aluno->getTurmasAluno($aluno_id);
$todas_turmas = $turma->getAll();
$ano_corrente = $configuracao->getAnoCorrente();

// Filtrar turmas já associadas
$turmas_disponiveis = array_filter($todas_turmas, function($t) use ($turmas_aluno) {
    foreach ($turmas_aluno as $ta) {
        if ($ta['id'] == $t['id']) {
            return false;
        }
    }
    return true;
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

<div class="row mb-3">
    <div class="col-12">
        <a href="alunos.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Adicionar Turma</h5>
            </div>
            <div class="card-body">
                <p class="text-muted"><strong>Aluno:</strong> <?php echo htmlspecialchars($aluno_data['nome']); ?></p>
                
                <?php if (empty($turmas_disponiveis)): ?>
                <div class="alert alert-info">
                    Todas as turmas já estão associadas a este aluno.
                </div>
                <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="adicionar">
                    
                    <div class="mb-3">
                        <label for="turma_id" class="form-label">Turma <span class="text-danger">*</span></label>
                        <select class="form-select" id="turma_id" name="turma_id" required>
                            <option value="">Selecione uma turma...</option>
                            <?php foreach ($turmas_disponiveis as $t): ?>
                            <option value="<?php echo $t['id']; ?>">
                                <?php echo htmlspecialchars($t['ano_curso']); ?>º Ano - <?php echo htmlspecialchars($t['ano_civil']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus"></i> Adicionar Turma
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-collection"></i> Turmas do Aluno</h5>
            </div>
            <div class="card-body">
                <?php if (empty($turmas_aluno)): ?>
                <p class="text-muted text-center">Nenhuma turma associada ainda.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Ano Civil</th>
                                <th>Ano do Curso</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($turmas_aluno as $ta): 
                                $is_ano_corrente = ($ta['ano_civil'] == $ano_corrente);
                            ?>
                            <tr class="<?php echo $is_ano_corrente ? 'table-success' : ''; ?>">
                                <td><?php echo htmlspecialchars($ta['ano_civil']); ?></td>
                                <td><?php echo htmlspecialchars($ta['ano_curso']); ?>º Ano</td>
                                <td>
                                    <?php if ($is_ano_corrente): ?>
                                        <span class="badge bg-success">Ano Corrente (<?php echo $ano_corrente; ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ano <?php echo htmlspecialchars($ta['ano_civil']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="" style="display: inline;" 
                                          class="form-confirm" data-confirm="Tem certeza que deseja remover esta turma?">
                                        <input type="hidden" name="action" value="remover">
                                        <input type="hidden" name="turma_id" value="<?php echo $ta['id']; ?>">
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

<?php require_once 'includes/footer.php'; ?>

