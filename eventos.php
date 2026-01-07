<?php
// Process POST requests before including header (to allow redirects)
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$evento = new Evento($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

// Only admin, nivel1, nivel2 and assistencia_estudantil can view all events
if (!$user->isAdmin() && !$user->isNivel1() && !$user->isNivel2() && !$user->isAssistenciaEstudantil()) {
    header('Location: index.php');
    exit;
}

// Get filters
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_nome = $_GET['filtro_nome'] ?? '';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$evento = new Evento($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

// Only admin, nivel1, nivel2 and assistencia_estudantil can view all events
if (!$user->isAdmin() && !$user->isNivel1() && !$user->isNivel2() && !$user->isAssistenciaEstudantil()) {
    header('Location: index.php');
    exit;
}

// Get filters
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_nome = $_GET['filtro_nome'] ?? '';

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
    
    // Preserve filters in redirect
    $params = [];
    if ($filtro_curso) $params['filtro_curso'] = $filtro_curso;
    if ($filtro_turma) $params['filtro_turma'] = $filtro_turma;
    if ($filtro_nome) $params['filtro_nome'] = $filtro_nome;
    $redirect_url = 'eventos.php?' . http_build_query($params);
    header('Location: ' . $redirect_url);
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $evento->id = $_GET['delete'];
    $user_id = $_SESSION['user_id'] ?? null;
    
    if ($user->isAdmin()) {
        // Admin pode deletar qualquer evento
        if ($evento->delete()) {
            // Preserve filters in redirect
            $params = [];
            if ($filtro_curso) $params['filtro_curso'] = $filtro_curso;
            if ($filtro_turma) $params['filtro_turma'] = $filtro_turma;
            if ($filtro_nome) $params['filtro_nome'] = $filtro_nome;
            $params['success'] = 'deleted';
            $redirect_url = 'eventos.php?' . http_build_query($params);
            header('Location: ' . $redirect_url);
            exit;
        }
    } elseif (($user->isNivel1() || $user->isNivel2() || $user->isAssistenciaEstudantil()) && $user_id) {
        // Nivel1, Nivel2 e Assistência Estudantil podem deletar apenas seus próprios eventos criados há menos de 1 hora
        if ($evento->delete($user_id, true)) {
            // Preserve filters in redirect
            $params = [];
            if ($filtro_curso) $params['filtro_curso'] = $filtro_curso;
            if ($filtro_turma) $params['filtro_turma'] = $filtro_turma;
            if ($filtro_nome) $params['filtro_nome'] = $filtro_nome;
            $params['success'] = 'deleted';
            $redirect_url = 'eventos.php?' . http_build_query($params);
            header('Location: ' . $redirect_url);
            exit;
        } else {
            $_SESSION['error'] = 'Não é possível excluir este evento. Você só pode excluir eventos criados por você há menos de 1 hora.';
        }
    }
}

// Nivel2 só vê eventos que ele mesmo registrou
$user_id = $_SESSION['user_id'] ?? null;
$registrado_por = ($user->isNivel2()) ? $user_id : null;
$eventos = $evento->getAll($registrado_por);
$cursos = $curso->getAll();
$ano_corrente = $configuracao->getAnoCorrente();
$turmas_ano_corrente_lista = $turma->getTurmasPorAnoCorrente($ano_corrente);
$tipo_evento_model = new TipoEvento($db);
$tipos_eventos = $tipo_evento_model->getAll(true); // Apenas ativos

// Get curso info if filtered
$curso_filtrado = null;
if ($filtro_curso) {
    $curso_filtrado = $curso->getById($filtro_curso);
}

// Get turma info if filtered
$turma_filtrada = null;
if ($filtro_turma) {
    $turma_filtrada = $turma->getById($filtro_turma);
}

// Apply filters
if ($filtro_curso) {
    $eventos = array_filter($eventos, function($evt) use ($filtro_curso) {
        return !empty($evt['curso_id']) && $evt['curso_id'] == $filtro_curso;
    });
}

if ($filtro_turma) {
    $eventos = array_filter($eventos, function($evt) use ($filtro_turma) {
        return !empty($evt['turma_id']) && $evt['turma_id'] == $filtro_turma;
    });
}

if ($filtro_nome) {
    $filtro_nome_lower = mb_strtolower($filtro_nome, 'UTF-8');
    $eventos = array_filter($eventos, function($evt) use ($filtro_nome_lower) {
        $nome_lower = mb_strtolower($evt['aluno_nome'] ?? '', 'UTF-8');
        return mb_strpos($nome_lower, $filtro_nome_lower) !== false;
    });
}

// Re-index array after filtering
$eventos = array_values($eventos);

// Get success/error messages from session
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_GET['success'])) {
    $success_msg = $_GET['success'] == 'deleted' ? 'Evento excluído com sucesso!' : '';
}

if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

$page_title = 'Eventos';
require_once 'includes/header.php';
?>

<?php if (isset($success_msg)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error_msg)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Todos os Eventos</h5>
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
                <label for="filtro_nome" class="form-label">Filtrar por Nome do Aluno</label>
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
                <a href="eventos.php" class="btn btn-secondary btn-sm w-100">
                    <i class="bi bi-x-circle"></i> Limpar Filtros
                </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($eventos)): ?>
        <p class="text-muted text-center">Nenhum evento encontrado<?php echo ($filtro_curso || $filtro_turma || $filtro_nome) ? ' com os filtros selecionados' : ' registrado ainda'; ?>.</p>
        <?php else: ?>
        <?php if ($curso_filtrado && !$turma_filtrada): ?>
        <div class="alert alert-primary mb-3" role="alert">
            <h5 class="alert-heading mb-0">
                <i class="bi bi-book"></i> 
                Curso: <strong><?php echo htmlspecialchars($curso_filtrado['nome'] ?? ''); ?></strong>
            </h5>
        </div>
        <?php endif; ?>
        <?php if ($turma_filtrada): ?>
        <div class="alert alert-info mb-3" role="alert">
            <h5 class="alert-heading mb-0">
                <i class="bi bi-collection"></i> 
                Turma: <strong><?php echo htmlspecialchars($turma_filtrada['curso_nome'] ?? ''); ?></strong> - 
                <?php echo htmlspecialchars($turma_filtrada['ano_curso'] ?? ''); ?>º Ano - 
                Ano <?php echo htmlspecialchars($turma_filtrada['ano_civil'] ?? ''); ?>
            </h5>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Aluno</th>
                                <th>Tipo</th>
                                <th>Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos as $evt): ?>
                            <tr class="row-observacoes" style="cursor: pointer;" data-evento='<?php echo htmlspecialchars(json_encode([
                                'id' => $evt['id'],
                                'data' => date('d/m/Y', strtotime($evt['data_evento'])),
                                'hora' => $evt['hora_evento'] ? date('H:i', strtotime($evt['hora_evento'])) : '-',
                                'aluno' => $evt['aluno_nome'] ?? 'N/A',
                                'tipo' => $evt['tipo_evento_nome'] ?? 'N/A',
                                'registrado_por' => $evt['registrado_por_nome'] ?? '-',
                                'observacoes' => $evt['observacoes'] ?? '',
                                'aluno_id' => $evt['aluno_id'] ?? '',
                                'turma_id' => $evt['turma_id'] ?? '',
                                'tipo_evento_id' => $evt['tipo_evento_id'] ?? '',
                                'data_evento' => $evt['data_evento'] ?? '',
                                'hora_evento' => $evt['hora_evento'] ?? '',
                                'registrado_por_id' => $evt['registrado_por'] ?? '',
                                'created_at' => $evt['created_at'] ?? ''
                            ])); ?>'>
                                <td><?php echo date('d/m/Y', strtotime($evt['data_evento'])); ?></td>
                                <td><?php echo $evt['hora_evento'] ? date('H:i', strtotime($evt['hora_evento'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($evt['aluno_nome'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($evt['tipo_evento_nome'])): ?>
                                        <span class="badge bg-<?php echo htmlspecialchars($evt['tipo_evento_cor'] ?? 'secondary'); ?>">
                                            <?php echo htmlspecialchars($evt['tipo_evento_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($evt['registrado_por_nome'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Ver Observações -->
<div class="modal fade" id="observacoesModal" tabindex="-1" aria-labelledby="observacoesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="observacoesModalLabel"><i class="bi bi-info-circle"></i> Observações do Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Data:</strong> <span id="obs_data"></span><br>
                    <strong>Hora:</strong> <span id="obs_hora"></span><br>
                    <strong>Aluno:</strong> <span id="obs_aluno"></span><br>
                    <strong>Tipo:</strong> <span id="obs_tipo"></span><br>
                    <strong>Registrado por:</strong> <span id="obs_registrado_por"></span>
                </div>
                <hr>
                <div>
                    <strong>Observações:</strong>
                    <p id="obs_texto" class="mt-2"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
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
                    <input type="hidden" name="aluno_id" id="edit_aluno_id">
                    <input type="hidden" name="turma_id" id="edit_turma_id">
                    
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

<?php require_once 'includes/footer.php'; ?>

