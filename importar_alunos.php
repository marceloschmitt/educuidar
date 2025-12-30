<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$aluno = new Aluno($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

// Only admin can import alunos
if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';
$import_result = null;

// Process POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'import') {
    $turma_id = $_POST['turma_id'] ?? '';
    
    if (empty($turma_id)) {
        $error = 'Por favor, selecione uma turma!';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Por favor, selecione um arquivo CSV válido!';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if ($handle === false) {
            $error = 'Erro ao abrir o arquivo CSV!';
        } else {
            $imported = 0;
            $skipped = 0;
            $errors = [];
            $line_number = 0;
            
            // Skip header row (first line)
            $first_line = fgetcsv($handle);
            if ($first_line === false) {
                $error = 'O arquivo CSV está vazio!';
                fclose($handle);
            } else {
                // Process remaining lines (skip header)
                while (($data = fgetcsv($handle)) !== false) {
                    $line_number++;
                    
                    // Skip empty lines
                    if (empty($data) || (count($data) < 3)) {
                        continue;
                    }
                    
                    // Get data from CSV
                    // Column 0: Nome
                    // Column 1: Sobrenome
                    // Column 2: Email
                    // Column 3: (ignored)
                    $nome = trim($data[0] ?? '');
                    $sobrenome = trim($data[1] ?? '');
                    $email = trim($data[2] ?? '');
                    
                    // Skip if nome is empty
                    if (empty($nome)) {
                        $skipped++;
                        continue;
                    }
                    
                    // Combine nome and sobrenome
                    $nome_completo = trim($nome . ' ' . $sobrenome);
                    
                    // Validate email format (optional - can be empty)
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Linha $line_number: Email inválido ($email)";
                        $skipped++;
                        continue;
                    }
                    
                    // Check if aluno already exists by email (if email provided)
                    $aluno_existente = null;
                    if (!empty($email)) {
                        $check_query = "SELECT id FROM alunos WHERE email = :email LIMIT 1";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(':email', $email);
                        $check_stmt->execute();
                        $aluno_existente = $check_stmt->fetch();
                    }
                    
                    try {
                        if ($aluno_existente) {
                            // Aluno already exists, just add to turma if not already there
                            $aluno_id = $aluno_existente['id'];
                            
                            // Check if already in this turma
                            $check_turma_query = "SELECT id FROM aluno_turmas WHERE aluno_id = :aluno_id AND turma_id = :turma_id LIMIT 1";
                            $check_turma_stmt = $db->prepare($check_turma_query);
                            $check_turma_stmt->bindParam(':aluno_id', $aluno_id);
                            $check_turma_stmt->bindParam(':turma_id', $turma_id);
                            $check_turma_stmt->execute();
                            
                            if (!$check_turma_stmt->fetch()) {
                                // Add to turma
                                if ($aluno->adicionarTurmaAluno($aluno_id, $turma_id)) {
                                    $imported++;
                                } else {
                                    $errors[] = "Linha $line_number: Erro ao associar aluno existente à turma ($nome_completo)";
                                    $skipped++;
                                }
                            } else {
                                // Already in turma
                                $skipped++;
                            }
                        } else {
                            // Create new aluno
                            $aluno->nome = $nome_completo;
                            $aluno->email = !empty($email) ? $email : null;
                            $aluno->telefone_celular = null;
                            
                            if ($aluno->create()) {
                                // Add to turma
                                if ($aluno->adicionarTurmaAluno($aluno->id, $turma_id)) {
                                    $imported++;
                                } else {
                                    $errors[] = "Linha $line_number: Erro ao associar aluno à turma ($nome_completo)";
                                    $skipped++;
                                }
                            } else {
                                $errors[] = "Linha $line_number: Erro ao criar aluno ($nome_completo)";
                                $skipped++;
                            }
                        }
                    } catch (Exception $e) {
                        $errors[] = "Linha $line_number: " . $e->getMessage();
                        $skipped++;
                    }
                }
                
                fclose($handle);
                
                // Prepare result message
                $import_result = [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors
                ];
                
                if ($imported > 0) {
                    $success = "Importação concluída! $imported aluno(s) importado(s) com sucesso.";
                    if ($skipped > 0) {
                        $success .= " $skipped linha(s) ignorada(s).";
                    }
                } else {
                    $error = "Nenhum aluno foi importado. $skipped linha(s) ignorada(s).";
                }
            }
        }
    }
}

// Get all turmas for selection
$ano_corrente = $configuracao->getAnoCorrente();
$todas_turmas = $turma->getAll();

$page_title = 'Importar Alunos';
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

<?php if ($import_result && !empty($import_result['errors'])): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <h6><i class="bi bi-exclamation-triangle"></i> Erros durante a importação:</h6>
    <ul class="mb-0">
        <?php foreach ($import_result['errors'] as $err): ?>
        <li><?php echo htmlspecialchars($err); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-upload"></i> Importar Alunos de Arquivo CSV</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info" role="alert">
                    <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Formato do arquivo CSV:</h6>
                    <p class="mb-0">
                        O arquivo CSV deve ter as seguintes colunas (separadas por vírgula ou ponto e vírgula):<br>
                        <strong>Coluna 1:</strong> Nome<br>
                        <strong>Coluna 2:</strong> Sobrenome<br>
                        <strong>Coluna 3:</strong> Email<br>
                        <strong>Coluna 4:</strong> (será ignorada)
                    </p>
                    <hr>
                    <p class="mb-0">
                        <small>
                            <strong>Observações:</strong><br>
                            - O nome e sobrenome serão combinados para formar o nome completo do aluno.<br>
                            - Se um aluno com o mesmo email já existir, ele será apenas associado à turma selecionada (se ainda não estiver associado).<br>
                            - Linhas com nome vazio serão ignoradas.
                        </small>
                    </p>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import">
                    
                    <div class="mb-3">
                        <label for="turma_id" class="form-label">Turma <span class="text-danger">*</span></label>
                        <select class="form-select" id="turma_id" name="turma_id" required>
                            <option value="">Selecione uma turma...</option>
                            <?php 
                            // Group turmas by curso and ano_civil for better organization
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
                            
                            // Sort by curso name and ano_civil DESC
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
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo htmlspecialchars($t['ano_curso']); ?>º Ano
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Todas as turmas estão disponíveis para importação.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Arquivo CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,.txt" required>
                        <small class="text-muted">Selecione um arquivo CSV (.csv ou .txt)</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Importar Alunos
                        </button>
                        <a href="alunos.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar para Alunos
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

