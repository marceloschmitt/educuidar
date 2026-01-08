<?php
$page_title = 'Eventos de Aluno';
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$evento = new Evento($db);
$tipo_evento = new TipoEvento($db);
$aluno = new Aluno($db);
$turma = new Turma($db);
$curso = new Curso($db);
$configuracao = new Configuracao($db);

// Only admin, nivel1, nivel2 and assistencia_estudantil can register events
if (!$user->isAdmin() && !$user->isNivel1() && !$user->isNivel2() && !$user->isAssistenciaEstudantil()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// Get aluno_id early for delete/update redirect
$aluno_id = $_GET['aluno_id'] ?? '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $evento->id = $_POST['id'] ?? '';
    $evento->aluno_id = $_POST['aluno_id'] ?? '';
    $evento->turma_id = $_POST['turma_id'] ?? '';
    $evento->tipo_evento_id = $_POST['tipo_evento_id'] ?? '';
    $evento->data_evento = $_POST['data_evento'] ?? '';
    $evento->hora_evento = $_POST['hora_evento'] ?? '';
    $evento->observacoes = $_POST['observacoes'] ?? '';
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (empty($evento->id) || empty($evento->aluno_id) || empty($evento->tipo_evento_id) || empty($evento->data_evento)) {
        $_SESSION['error'] = 'Por favor, preencha todos os campos obrigatórios!';
    } else {
        if ($user->isAdmin()) {
            // Admin pode editar qualquer evento
            if ($evento->update()) {
                $_SESSION['success'] = 'Evento atualizado com sucesso!';
            } else {
                $_SESSION['error'] = 'Erro ao atualizar evento.';
            }
        } elseif (($user->isNivel1() || $user->isNivel2() || $user->isAssistenciaEstudantil()) && $user_id) {
            // Nivel1 e Nivel2 podem editar apenas seus próprios eventos criados há menos de 1 hora
            if ($evento->update($user_id, true)) {
                $_SESSION['success'] = 'Evento atualizado com sucesso!';
            } else {
                $_SESSION['error'] = 'Não é possível editar este evento. Você só pode editar eventos criados por você há menos de 1 hora.';
            }
        }
    }
    
    header('Location: registrar_evento.php?aluno_id=' . urlencode($evento->aluno_id));
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $evento->id = $_GET['delete'];
    $user_id = $_SESSION['user_id'] ?? null;
    $delete_aluno_id = $_GET['aluno_id'] ?? $aluno_id;
    
    if ($user->isAdmin()) {
        // Admin pode deletar qualquer evento
        if ($evento->delete()) {
            $_SESSION['success'] = 'Evento excluído com sucesso!';
            header('Location: registrar_evento.php?aluno_id=' . urlencode($delete_aluno_id));
            exit;
        }
    } elseif (($user->isNivel1() || $user->isNivel2() || $user->isAssistenciaEstudantil()) && $user_id) {
        // Nivel1, Nivel2 e Assistência Estudantil podem deletar apenas seus próprios eventos criados há menos de 1 hora
        if ($evento->delete($user_id, true)) {
            $_SESSION['success'] = 'Evento excluído com sucesso!';
            header('Location: registrar_evento.php?aluno_id=' . urlencode($delete_aluno_id));
            exit;
        } else {
            $_SESSION['error'] = 'Não é possível excluir este evento. Você só pode excluir eventos criados por você há menos de 1 hora.';
            header('Location: registrar_evento.php?aluno_id=' . urlencode($delete_aluno_id));
            exit;
        }
    }
}

// Process POST request (register new event)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $evento->aluno_id = $_POST['aluno_id'] ?? '';
    $evento->turma_id = $_POST['turma_id'] ?? '';
    $evento->tipo_evento_id = $_POST['tipo_evento_id'] ?? '';
    $evento->data_evento = $_POST['data_evento'] ?? '';
    $evento->hora_evento = $_POST['hora_evento'] ?? '';
    $evento->observacoes = $_POST['observacoes'] ?? '';
    $evento->registrado_por = $_SESSION['user_id'];
    
    if (empty($evento->aluno_id) || empty($evento->tipo_evento_id) || empty($evento->data_evento)) {
        $_SESSION['error'] = 'Por favor, preencha todos os campos obrigatórios!';
    } else {
        if ($evento->create()) {
            $_SESSION['success'] = 'Evento registrado com sucesso!';
            // Redirecionar de volta para a tela de registrar evento do mesmo aluno
            header('Location: registrar_evento.php?aluno_id=' . urlencode($evento->aluno_id));
            exit;
        } else {
            $_SESSION['error'] = 'Erro ao registrar evento. Tente novamente.';
        }
    }
}

// Get success/error messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

require_once 'includes/header.php';
?>
<?php
// Get filters
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_nome = $_GET['filtro_nome'] ?? '';
$aluno_id = $_GET['aluno_id'] ?? '';

// Get ano corrente
$ano_corrente = $configuracao->getAnoCorrente();

// Get all classes from ano corrente and courses for filters
$cursos = $curso->getAll();
$turmas_ano_corrente_lista = $turma->getTurmasPorAnoCorrente($ano_corrente);

// If aluno_id is provided, show student's events
if ($aluno_id) {
    $aluno_data = $aluno->getById($aluno_id);
    if (!$aluno_data) {
        header('Location: registrar_evento.php');
        exit;
    }
    
    // Buscar primeira turma do ano corrente do aluno
    $ano_corrente = $configuracao->getAnoCorrente();
    $turmas_aluno = $aluno->getTurmasAluno($aluno_id);
    $turma_corrente = null;
    foreach ($turmas_aluno as $ta) {
        if ($ta['ano_civil'] == $ano_corrente) {
            $turma_corrente = $ta;
            break;
        }
    }
    
    if (!$turma_corrente) {
        echo '<div class="alert alert-warning">Este aluno não está associado a nenhuma turma do ano corrente (' . $ano_corrente . ').</div>';
        echo '<a href="alunos.php" class="btn btn-secondary">Voltar para Alunos</a>';
        require_once 'includes/footer.php';
        exit;
    }
    
    // Nivel2 só vê eventos que ele mesmo registrou
    $user_id = $_SESSION['user_id'] ?? null;
    $registrado_por = ($user->isNivel2()) ? $user_id : null;
    $eventos_aluno = $evento->getByAlunoETurma($aluno_id, $turma_corrente['id'], $registrado_por);
    $tipos_eventos = $tipo_evento->getAll(true); // Apenas ativos
    ?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-3 no-print">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-person"></i> <?php echo htmlspecialchars(!empty($aluno_data['nome_social']) ? $aluno_data['nome_social'] : ($aluno_data['nome'] ?? '')); ?>
                </h5>
                <a href="alunos.php" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar para Alunos
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 text-center mb-3">
                        <?php if (!empty($aluno_data['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($aluno_data['foto']); ?>" 
                                 alt="Foto de <?php echo htmlspecialchars($aluno_data['nome'] ?? ''); ?>" 
                                 class="img-thumbnail" 
                                 style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 120px; height: 120px;">
                                <i class="bi bi-person" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <p class="mb-2">
                            <strong>Turma:</strong> 
                            <?php echo htmlspecialchars($turma_corrente['curso_nome'] ?? ''); ?> - 
                            <?php echo htmlspecialchars($turma_corrente['ano_curso'] ?? ''); ?>º Ano - 
                            <?php echo htmlspecialchars($turma_corrente['ano_civil'] ?? ''); ?>
                        </p>
                        <?php if (!empty($aluno_data['email'])): ?>
                        <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($aluno_data['email'] ?? ''); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($aluno_data['telefone_celular'])): ?>
                        <p class="mb-0"><strong>Telefone:</strong> <?php echo htmlspecialchars($aluno_data['telefone_celular'] ?? ''); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

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

        <!-- List of student's events -->
        <div class="card printable-area">
            <div class="card-header d-flex justify-content-between align-items-center no-print">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Eventos Registrados</h5>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm me-2" id="btnImprimir">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRegistrarEvento">
                        <i class="bi bi-plus-circle"></i> Registrar Novo Evento
                    </button>
                </div>
            </div>
            <div class="card-header printable-header" style="display: none;">
                <div class="row">
                    <div class="col-md-2 text-center mb-2">
                        <?php if (!empty($aluno_data['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($aluno_data['foto']); ?>" 
                                 alt="Foto de <?php echo htmlspecialchars($aluno_data['nome'] ?? ''); ?>" 
                                 class="img-thumbnail" 
                                 style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 100px; height: 100px;">
                                <i class="bi bi-person" style="font-size: 2.5rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <h4 class="mb-2">Eventos de <?php echo htmlspecialchars(!empty($aluno_data['nome_social']) ? $aluno_data['nome_social'] : ($aluno_data['nome'] ?? '')); ?></h4>
                        <p class="mb-1"><strong>Turma:</strong> <?php echo htmlspecialchars($turma_corrente['curso_nome'] ?? ''); ?> - <?php echo htmlspecialchars($turma_corrente['ano_curso'] ?? ''); ?>º Ano - Ano <?php echo htmlspecialchars($turma_corrente['ano_civil'] ?? ''); ?></p>
                        <?php if (!empty($aluno_data['email'])): ?>
                        <p class="mb-1"><strong>E-mail:</strong> <?php echo htmlspecialchars($aluno_data['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($aluno_data['telefone_celular'])): ?>
                        <p class="mb-1"><strong>Telefone:</strong> <?php echo htmlspecialchars($aluno_data['telefone_celular']); ?></p>
                        <?php endif; ?>
                        <p class="mb-0"><strong>Data de impressão:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($eventos_aluno)): ?>
                <p class="text-muted">Nenhum evento registrado para este aluno nesta turma.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Observações</th>
                                <th>Registrado por</th>
                                <th class="no-print" style="display: none;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos_aluno as $ev): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($ev['data_evento'])); ?></td>
                                <td><?php echo $ev['hora_evento'] ? date('H:i', strtotime($ev['hora_evento'])) : '-'; ?></td>
                                <td>
                                    <?php if (!empty($ev['tipo_evento_nome'])): ?>
                                        <?php 
                                        $cor = $ev['tipo_evento_cor'] ?? 'secondary';
                                        // Se a cor começa com #, usar style, senão usar classe Bootstrap
                                        if (strpos($cor, '#') === 0) {
                                            echo '<span class="badge" style="background-color: ' . htmlspecialchars($cor) . ';">';
                                        } else {
                                            echo '<span class="badge bg-' . htmlspecialchars($cor) . '">';
                                        }
                                        ?>
                                            <?php echo htmlspecialchars($ev['tipo_evento_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($ev['observacoes'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($ev['registrado_por_nome'] ?? '-'); ?></td>
                                <td class="no-print">
                                    <?php 
                                    $user_id = $_SESSION['user_id'] ?? null;
                                    $can_edit = false;
                                    $can_delete = false;
                                    
                                    if ($user->isAdmin()) {
                                        // Admin pode editar e deletar qualquer evento
                                        $can_edit = true;
                                        $can_delete = true;
                                    } elseif (($user->isNivel1() || $user->isNivel2() || $user->isAssistenciaEstudantil()) && $user_id) {
                                        // Nivel1 e Nivel2 só podem editar/deletar seus próprios eventos criados há menos de 1 hora
                                        if ($ev['registrado_por'] == $user_id) {
                                            $created_at = strtotime($ev['created_at'] ?? '');
                                            $now = time();
                                            $diff_seconds = $now - $created_at;
                                            $can_edit = ($diff_seconds <= 3600); // 1 hora = 3600 segundos
                                            $can_delete = ($diff_seconds <= 3600);
                                        }
                                    }
                                    
                                    if ($can_edit || $can_delete):
                                    ?>
                                    <div class="dropdown">
                                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $ev['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $ev['id']; ?>">
                                            <?php if ($can_edit): ?>
                                            <li>
                                                <button type="button" class="dropdown-item btn-edit-evento" data-evento='<?php echo htmlspecialchars(json_encode([
                                                    'id' => $ev['id'],
                                                    'aluno_id' => $ev['aluno_id'],
                                                    'turma_id' => $ev['turma_id'],
                                                    'tipo_evento_id' => $ev['tipo_evento_id'],
                                                    'data_evento' => $ev['data_evento'],
                                                    'hora_evento' => $ev['hora_evento'],
                                                    'observacoes' => $ev['observacoes'] ?? ''
                                                ])); ?>'>
                                                    <i class="bi bi-pencil text-primary me-2"></i> Editar
                                                </button>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($can_delete): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a href="registrar_evento.php?delete=<?php echo $ev['id']; ?>&aluno_id=<?php echo urlencode($aluno_id); ?>" 
                                                   class="dropdown-item text-danger btn-delete-evento">
                                                    <i class="bi bi-trash me-2"></i> Excluir
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para Editar Evento -->
        <div class="modal fade" id="editEventoModal" tabindex="-1" aria-labelledby="editEventoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEventoModalLabel"><i class="bi bi-pencil"></i> Editar Evento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" id="edit_evento_id">
                            <input type="hidden" name="aluno_id" id="edit_aluno_id" value="<?php echo htmlspecialchars($aluno_id); ?>">
                            <input type="hidden" name="turma_id" id="edit_turma_id" value="<?php echo htmlspecialchars($turma_corrente['id'] ?? ''); ?>">
                            
                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <label for="edit_tipo_evento_id" class="form-label">Tipo de Evento <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_tipo_evento_id" name="tipo_evento_id" required>
                                        <option value="">Selecione o tipo...</option>
                                        <?php foreach ($tipos_eventos as $te): ?>
                                        <option value="<?php echo $te['id']; ?>">
                                            <?php echo htmlspecialchars($te['nome']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3 mb-3">
                                    <label for="edit_data_evento" class="form-label">Data <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="edit_data_evento" name="data_evento" required>
                                </div>
                                <div class="col-12 col-md-3 mb-3">
                                    <label for="edit_hora_evento" class="form-label">Hora</label>
                                    <input type="time" class="form-control" id="edit_hora_evento" name="hora_evento">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" id="edit_observacoes" name="observacoes" rows="3"></textarea>
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

        <!-- Modal para Registrar Novo Evento -->
        <div class="modal fade" id="modalRegistrarEvento" tabindex="-1" aria-labelledby="modalRegistrarEventoLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalRegistrarEventoLabel">
                            <i class="bi bi-plus-circle"></i> Registrar Novo Evento
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <input type="hidden" name="aluno_id" value="<?php echo htmlspecialchars($aluno_id); ?>">
                            <input type="hidden" name="turma_id" value="<?php echo htmlspecialchars($turma_corrente['id'] ?? ''); ?>">
                            
                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <label for="modal_tipo_evento_id" class="form-label">Tipo de Evento <span class="text-danger">*</span></label>
                                    <select class="form-select" id="modal_tipo_evento_id" name="tipo_evento_id" required>
                                        <option value="">Selecione o tipo...</option>
                                        <?php foreach ($tipos_eventos as $te): ?>
                                        <option value="<?php echo $te['id']; ?>">
                                            <?php echo htmlspecialchars($te['nome']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3 mb-3">
                                    <label for="modal_data_evento" class="form-label">Data <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="modal_data_evento" name="data_evento" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-12 col-md-3 mb-3">
                                    <label for="modal_hora_evento" class="form-label">Hora</label>
                                    <input type="time" class="form-control" id="modal_hora_evento" name="hora_evento" value="<?php echo date('H:i'); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="modal_observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" id="modal_observacoes" name="observacoes" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Registrar Evento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
} else {
    // Show list of students from ano corrente classes
    $ano_corrente = $configuracao->getAnoCorrente();
    $alunos_raw = $aluno->getAlunosTurmasAnoCorrente($ano_corrente, $filtro_turma ?: null, $filtro_curso ?: null);
    
    // Process students data and apply nome filter
    $alunos = [];
    foreach ($alunos_raw as $a) {
        // Apply nome filter if set
        if ($filtro_nome) {
            $filtro_nome_lower = mb_strtolower($filtro_nome, 'UTF-8');
            $nome_lower = mb_strtolower($a['nome'] ?? '', 'UTF-8');
            if (mb_strpos($nome_lower, $filtro_nome_lower) === false) {
                continue;
            }
        }
        
        $alunos[] = [
            'id' => $a['id'],
            'nome' => $a['nome'] ?? '',
            'email' => $a['email'] ?? '',
            'telefone_celular' => $a['telefone_celular'] ?? '',
            'curso_nome' => $a['curso_nome'] ?? '',
            'curso_id' => $a['curso_id'] ?? '',
            'ano_curso' => $a['ano_curso'] ?? '',
            'ano_civil' => $a['ano_civil'] ?? '',
            'is_ano_corrente' => ($a['ano_civil'] == $ano_corrente)
        ];
    }
    ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Eventos de Aluno</h5>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form method="GET" action="" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="filtro_curso" class="form-label">Filtrar por Curso</label>
                        <select class="form-select form-select-sm" id="filtro_curso" name="filtro_curso">
                            <option value="">Todos os cursos</option>
                            <?php foreach ($cursos as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($filtro_curso == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filtro_turma" class="form-label">Filtrar por Turma (Ano <?php echo $ano_corrente; ?>)</label>
                        <select class="form-select form-select-sm" id="filtro_turma" name="filtro_turma">
                            <option value="">Todas as turmas</option>
                            <?php 
                            $turmas_filtradas = $turmas_ano_corrente_lista;
                            if ($filtro_curso) {
                                $turmas_filtradas = array_filter($turmas_ano_corrente_lista, function($t) use ($filtro_curso) {
                                    return $t['curso_id'] == $filtro_curso;
                                });
                            }
                            foreach ($turmas_filtradas as $t): 
                            ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($filtro_turma == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['curso_nome'] ?? ''); ?> - 
                                <?php echo htmlspecialchars($t['ano_curso']); ?>º Ano
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filtro_nome" class="form-label">Filtrar por Nome</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="filtro_nome" name="filtro_nome" 
                                   value="<?php echo htmlspecialchars($filtro_nome); ?>" 
                                   placeholder="Digite o nome do aluno..."
                                   onkeypress="if(event.key === 'Enter') { this.form.submit(); }">
                            <button class="btn btn-outline-secondary" type="submit" title="Buscar">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <?php if ($filtro_curso || $filtro_turma || $filtro_nome): ?>
                        <a href="registrar_evento.php" class="btn btn-secondary btn-sm w-100">
                            <i class="bi bi-x-circle"></i> Limpar Filtros
                        </a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (empty($alunos)): ?>
                <p class="text-muted text-center">Nenhum aluno encontrado<?php echo ($filtro_curso || $filtro_turma || $filtro_nome) ? ' com os filtros selecionados' : ' nas turmas do ano corrente (' . $ano_corrente . ')'; ?>.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>Curso</th>
                                <th>Turma</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunos as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['nome']); ?></td>
                                <td><?php echo htmlspecialchars($a['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($a['telefone_celular'] ?? '-'); ?></td>
                                <td>
                                    <?php if (!empty($a['curso_nome'])): ?>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($a['curso_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['ano_curso'])): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($a['ano_curso']); ?>º Ano - 
                                            <?php echo htmlspecialchars($a['ano_civil']); ?>
                                            <?php if (!empty($a['is_ano_corrente'])): ?>
                                                <span class="badge bg-success ms-1">Corrente</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="registrar_evento.php?aluno_id=<?php echo $a['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-eye"></i> Ver Eventos
                                    </a>
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


<?php
}
?>

<?php
require_once 'includes/footer.php';
?>
