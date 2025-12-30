<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$aluno = new Aluno($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

// Only admin can manage turmas alunos
if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';
$turma_selecionada = null;
$alunos_turma = [];
$todas_turmas = [];

// Get all turmas
$todas_turmas = $turma->getAll();
$ano_corrente = $configuracao->getAnoCorrente();

// Process POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'remover_alunos' && isset($_POST['turma_id']) && isset($_POST['alunos'])) {
            $turma_id = $_POST['turma_id'];
            $alunos_ids = $_POST['alunos'];
            
            if (empty($turma_id) || empty($alunos_ids)) {
                $error = 'Por favor, selecione uma turma e pelo menos um aluno!';
            } else {
                $removidos = 0;
                $erros = [];
                
                foreach ($alunos_ids as $aluno_id) {
                    if ($aluno->removerTurmaAluno($aluno_id, $turma_id)) {
                        $removidos++;
                    } else {
                        $erros[] = "Erro ao remover aluno ID: $aluno_id";
                    }
                }
                
                if ($removidos > 0) {
                    $success = "$removidos aluno(s) removido(s) da turma com sucesso!";
                    if (!empty($erros)) {
                        $error = "Alguns alunos não puderam ser removidos: " . implode(', ', $erros);
                    }
                } else {
                    $error = 'Nenhum aluno foi removido.';
                }
            }
        } elseif ($_POST['action'] == 'copiar_turma' && isset($_POST['turma_origem']) && isset($_POST['turma_destino'])) {
            $turma_origem = $_POST['turma_origem'];
            $turma_destino = $_POST['turma_destino'];
            
            if (empty($turma_origem) || empty($turma_destino)) {
                $error = 'Por favor, selecione a turma de origem e a turma de destino!';
            } elseif ($turma_origem == $turma_destino) {
                $error = 'A turma de origem e destino não podem ser a mesma!';
            } else {
                // Get alunos from origem turma
                $alunos_origem = $turma->getAlunos($turma_origem);
                
                if (empty($alunos_origem)) {
                    $error = 'A turma de origem não possui alunos!';
                } else {
                    $copiados = 0;
                    $ja_existiam = 0;
                    $erros = [];
                    
                    foreach ($alunos_origem as $aluno_origem) {
                        // Check if aluno already in destino turma
                        $check_query = "SELECT id FROM aluno_turmas WHERE aluno_id = :aluno_id AND turma_id = :turma_id LIMIT 1";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(':aluno_id', $aluno_origem['id']);
                        $check_stmt->bindParam(':turma_id', $turma_destino);
                        $check_stmt->execute();
                        
                        if ($check_stmt->fetch()) {
                            $ja_existiam++;
                        } else {
                            if ($aluno->adicionarTurmaAluno($aluno_origem['id'], $turma_destino)) {
                                $copiados++;
                            } else {
                                $erros[] = "Erro ao copiar aluno: " . htmlspecialchars($aluno_origem['nome']);
                            }
                        }
                    }
                    
                    if ($copiados > 0) {
                        $success = "$copiados aluno(s) copiado(s) com sucesso!";
                        if ($ja_existiam > 0) {
                            $success .= " $ja_existiam aluno(s) já estavam na turma de destino.";
                        }
                        if (!empty($erros)) {
                            $error = "Alguns alunos não puderam ser copiados: " . implode(', ', $erros);
                        }
                    } else {
                        if ($ja_existiam > 0) {
                            $error = "Todos os alunos já estavam na turma de destino.";
                        } else {
                            $error = 'Nenhum aluno foi copiado.';
                        }
                    }
                }
            }
        }
    }
}

// Get turma selecionada if provided
$turma_id_selecionada = $_GET['turma_id'] ?? $_POST['turma_id'] ?? '';
if (!empty($turma_id_selecionada)) {
    $turma_selecionada = $turma->getById($turma_id_selecionada);
    if ($turma_selecionada) {
        $alunos_turma = $turma->getAlunos($turma_id_selecionada);
    }
}

$page_title = 'Gerenciar Alunos em Turmas';
require_once 'includes/header.php';
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
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people"></i> Gerenciar Alunos em Turmas</h5>
                <a href="turmas.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar para Turmas
                </a>
            </div>
            <div class="card-body">
                <!-- Seleção de Turma -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" action="" class="mb-3">
                            <label for="turma_id" class="form-label">Selecionar Turma</label>
                            <div class="input-group">
                                <select class="form-select" id="turma_id" name="turma_id">
                                    <option value="">Selecione uma turma...</option>
                                    <?php 
                                    // Group turmas by curso and ano_civil
                                    $turmas_agrupadas = [];
                                    foreach ($todas_turmas as $t) {
                                        $curso_nome = $t['curso_nome'] ?? 'Sem Curso';
                                        $ano_civil = $t['ano_civil'] ?? 'N/A';
                                        $key = $curso_nome . '_' . $ano_civil;
                                        if (!isset($turmas_agrupadas[$key])) {
                                            $turmas_agrupadas[$key] = [
                                                'curso' => $curso_nome,
                                                'ano_civil' => $ano_civil,
                                                'turmas' => []
                                            ];
                                        }
                                        $turmas_agrupadas[$key]['turmas'][] = $t;
                                    }
                                    
                                    uksort($turmas_agrupadas, function($a, $b) use ($turmas_agrupadas) {
                                        $comp = strcmp($turmas_agrupadas[$a]['curso'], $turmas_agrupadas[$b]['curso']);
                                        if ($comp == 0) {
                                            return $turmas_agrupadas[$b]['ano_civil'] - $turmas_agrupadas[$a]['ano_civil'];
                                        }
                                        return $comp;
                                    });
                                    
                                    foreach ($turmas_agrupadas as $grupo):
                                        $is_ano_corrente = ($grupo['ano_civil'] == $ano_corrente);
                                    ?>
                                    <optgroup label="<?php echo htmlspecialchars($grupo['curso']); ?> - Ano <?php echo htmlspecialchars($grupo['ano_civil']); ?><?php echo $is_ano_corrente ? ' (Corrente)' : ''; ?>">
                                        <?php foreach ($grupo['turmas'] as $t): ?>
                                        <option value="<?php echo $t['id']; ?>" <?php echo ($turma_id_selecionada == $t['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['ano_curso']); ?>º Ano
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($turma_selecionada): ?>
                <!-- Informações da Turma -->
                <div class="alert alert-info mb-3">
                    <h6 class="mb-0">
                        <i class="bi bi-collection"></i> 
                        <strong><?php echo htmlspecialchars($turma_selecionada['curso_nome'] ?? ''); ?></strong> - 
                        <?php echo htmlspecialchars($turma_selecionada['ano_curso']); ?>º Ano - 
                        Ano <?php echo htmlspecialchars($turma_selecionada['ano_civil']); ?>
                        <?php if ($turma_selecionada['ano_civil'] == $ano_corrente): ?>
                            <span class="badge bg-success ms-2">Corrente</span>
                        <?php endif; ?>
                    </h6>
                </div>

                <!-- Remover Alunos -->
                <?php if (!empty($alunos_turma)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-person-dash"></i> Remover Alunos da Turma</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formRemoverAlunos" class="form-confirm" data-confirm="Tem certeza que deseja remover os alunos selecionados desta turma?">
                            <input type="hidden" name="action" value="remover_alunos">
                            <input type="hidden" name="turma_id" value="<?php echo $turma_selecionada['id']; ?>">
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-outline-primary mb-2 btn-select-all">
                                    <i class="bi bi-check-all"></i> Selecionar Todos
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary mb-2 btn-deselect-all">
                                    <i class="bi bi-x-square"></i> Desmarcar Todos
                                </button>
                            </div>
                            
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAll">
                                            </th>
                                            <th>Nome</th>
                                            <th>E-mail</th>
                                            <th>Telefone</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alunos_turma as $a): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="alunos[]" value="<?php echo $a['id']; ?>" class="aluno-checkbox">
                                            </td>
                                            <td><?php echo htmlspecialchars($a['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($a['email'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($a['telefone_celular'] ?? '-'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Remover Alunos Selecionados
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle"></i> Esta turma não possui alunos.
                </div>
                <?php endif; ?>

                <!-- Copiar Turma -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-files"></i> Copiar Todos os Alunos para Outra Turma</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-confirm" data-confirm="Tem certeza que deseja copiar todos os alunos desta turma para a turma de destino?">
                            <input type="hidden" name="action" value="copiar_turma">
                            <input type="hidden" name="turma_origem" value="<?php echo $turma_selecionada['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Turma de Origem</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($turma_selecionada['curso_nome'] ?? ''); ?> - <?php echo htmlspecialchars($turma_selecionada['ano_curso']); ?>º Ano - Ano <?php echo htmlspecialchars($turma_selecionada['ano_civil']); ?>" readonly>
                                    <small class="text-muted"><?php echo count($alunos_turma); ?> aluno(s) nesta turma</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="turma_destino" class="form-label">Turma de Destino <span class="text-danger">*</span></label>
                                    <select class="form-select" id="turma_destino" name="turma_destino" required>
                                        <option value="">Selecione a turma de destino...</option>
                                        <?php 
                                        foreach ($turmas_agrupadas as $grupo):
                                            $is_ano_corrente = ($grupo['ano_civil'] == $ano_corrente);
                                        ?>
                                        <optgroup label="<?php echo htmlspecialchars($grupo['curso']); ?> - Ano <?php echo htmlspecialchars($grupo['ano_civil']); ?><?php echo $is_ano_corrente ? ' (Corrente)' : ''; ?>">
                                            <?php foreach ($grupo['turmas'] as $t): ?>
                                                <?php if ($t['id'] != $turma_selecionada['id']): ?>
                                                <option value="<?php echo $t['id']; ?>">
                                                    <?php echo htmlspecialchars($t['ano_curso']); ?>º Ano
                                                </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <small>
                                    <i class="bi bi-info-circle"></i> 
                                    Todos os alunos da turma de origem serão copiados para a turma de destino. 
                                    Alunos que já estiverem na turma de destino serão ignorados.
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" <?php echo empty($alunos_turma) ? 'disabled' : ''; ?>>
                                <i class="bi bi-files"></i> Copiar Todos os Alunos
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?>

