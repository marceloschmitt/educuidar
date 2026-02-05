<?php
$page_title = 'Dashboard';
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();
$evento = new Evento($db);
$user = new User($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

$user_id = $_SESSION['user_id'];

// Get filters
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_tipo_evento = $_GET['filtro_tipo_evento'] ?? '';

// Get ano corrente
$ano_corrente = $configuracao->getAnoCorrente();

// Get courses and turmas for filters
$cursos = $curso->getAll();
$turmas_ano_corrente_lista = $turma->getTurmasPorAnoCorrente($ano_corrente);

// Get turma info if filtered
$turma_filtrada = null;
if ($filtro_turma) {
    $turma_filtrada = $turma->getById($filtro_turma);
}

// Get all event types for display
$tipo_evento_model = new TipoEvento($db);
$todos_tipos = $tipo_evento_model->getAll(true); // Apenas ativos

// Get statistics
if ($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2()) {
    // Nivel2 só vê eventos que ele mesmo registrou
    $registrado_por = ($user->isNivel2()) ? $user_id : null;
    $estatisticas = $evento->getEstatisticas(null, $filtro_turma ?: null, $filtro_curso ?: null, $ano_corrente, $registrado_por);
    $eventos_recentes = $evento->getAll($registrado_por);
    // Filter eventos recentes by ano corrente
    $eventos_recentes = array_filter($eventos_recentes, function($evt) use ($ano_corrente) {
        return !empty($evt['ano_civil']) && $evt['ano_civil'] == $ano_corrente;
    });
    // Filter by curso if selected
    if ($filtro_curso) {
        $eventos_recentes = array_filter($eventos_recentes, function($evt) use ($filtro_curso) {
            return !empty($evt['curso_id']) && $evt['curso_id'] == $filtro_curso;
        });
    }
    // Filter by turma if selected
    if ($filtro_turma) {
        $eventos_recentes = array_filter($eventos_recentes, function($evt) use ($filtro_turma) {
            return !empty($evt['turma_id']) && $evt['turma_id'] == $filtro_turma;
        });
    }
    // Filter by tipo_evento if selected
    if ($filtro_tipo_evento) {
        $eventos_recentes = array_filter($eventos_recentes, function($evt) use ($filtro_tipo_evento) {
            return !empty($evt['tipo_evento_id']) && $evt['tipo_evento_id'] == $filtro_tipo_evento;
        });
    }
    $eventos_recentes = array_slice($eventos_recentes, 0, 10);
} else {
    $estatisticas = $evento->getEstatisticas($user_id, null, null, $ano_corrente);
    $eventos_recentes = $evento->getByAluno($user_id);
    // Filter by tipo_evento if selected
    if ($filtro_tipo_evento) {
        $eventos_recentes = array_filter($eventos_recentes, function($evt) use ($filtro_tipo_evento) {
            return !empty($evt['tipo_evento_id']) && $evt['tipo_evento_id'] == $filtro_tipo_evento;
        });
    }
    $eventos_recentes = array_slice($eventos_recentes, 0, 10);
}
?>

<?php if ($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2()): ?>
<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
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
                    <div class="col-md-4">
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
                    <div class="col-md-4 d-flex align-items-end">
                        <?php if ($filtro_curso || $filtro_turma || $filtro_tipo_evento): ?>
                        <a href="index.php" class="btn btn-secondary btn-sm w-100">
                            <i class="bi bi-x-circle"></i> Limpar Filtros
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <?php if ($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2()): ?>
    <?php 
    // Create a map of statistics by tipo_evento_id
    $estatisticas_map = [];
    foreach ($estatisticas as $stat) {
        $estatisticas_map[$stat['tipo_evento_id']] = $stat['total'];
    }
    
    // Display cards for all event types
    foreach ($todos_tipos as $tipo):
        $total = $estatisticas_map[$tipo['id']] ?? 0;
        $cor = $tipo['cor'] ?? 'secondary';
        // If color starts with #, use inline style, otherwise use Bootstrap class
        $bg_class = (strpos($cor, '#') === 0) ? '' : 'bg-' . $cor;
        $style = (strpos($cor, '#') === 0) ? 'background-color: ' . htmlspecialchars($cor) . ';' : '';
        
        // Build URL with filters
        $url_params = [];
        if ($filtro_curso) $url_params['filtro_curso'] = $filtro_curso;
        if ($filtro_turma) $url_params['filtro_turma'] = $filtro_turma;
        $url_params['filtro_tipo_evento'] = $tipo['id'];
        $card_url = 'index.php?' . http_build_query($url_params);
        
        $is_selected = ($filtro_tipo_evento == $tipo['id']);
    ?>
    <div class="col-md-3 mb-4">
        <a href="<?php echo htmlspecialchars($card_url); ?>" class="text-decoration-none" style="display: block;">
            <div class="card text-white <?php echo $bg_class; ?> <?php echo $is_selected ? 'border border-light border-3' : ''; ?>" <?php if ($style): ?>style="<?php echo $style; ?>"<?php endif; ?>>
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title"><?php echo htmlspecialchars($tipo['nome']); ?></h6>
                            <h3><?php echo $total; ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-event" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <?php if ($is_selected): ?>
                    <div class="mt-2">
                        <small><i class="bi bi-funnel-fill"></i> Filtro ativo</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Minhas Estatísticas</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    $tipos = [
                        'chegada_atrasada' => ['label' => 'Atrasos', 'icon' => 'clock-history', 'color' => 'primary'],
                        'saida_antecipada' => ['label' => 'Saídas Antecipadas', 'icon' => 'arrow-left-circle', 'color' => 'warning'],
                        'falta' => ['label' => 'Faltas', 'icon' => 'x-circle', 'color' => 'danger'],
                        'atendimento' => ['label' => 'Atendimentos', 'icon' => 'person-check', 'color' => 'success']
                    ];
                    
                    foreach ($tipos as $tipo => $info):
                        $total = 0;
                        foreach ($estatisticas as $stat) {
                            if ($stat['tipo_evento'] == $tipo) {
                                $total = $stat['total'];
                                break;
                            }
                        }
                    ?>
                    <div class="col-md-3 mb-3">
                        <div class="card border-<?php echo $info['color']; ?>">
                            <div class="card-body text-center">
                                <i class="bi bi-<?php echo $info['icon']; ?> text-<?php echo $info['color']; ?>" style="font-size: 2rem;"></i>
                                <h5 class="mt-2"><?php echo $total; ?></h5>
                                <small class="text-muted"><?php echo $info['label']; ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Eventos Recentes</h5>
                <?php if ($filtro_tipo_evento): ?>
                    <?php 
                    $tipo_selecionado = null;
                    foreach ($todos_tipos as $t) {
                        if ($t['id'] == $filtro_tipo_evento) {
                            $tipo_selecionado = $t;
                            break;
                        }
                    }
                    if ($tipo_selecionado):
                    ?>
                    <span class="badge <?php echo (strpos($tipo_selecionado['cor'], '#') === 0) ? '' : 'bg-' . htmlspecialchars($tipo_selecionado['cor']); ?>" 
                          <?php if (strpos($tipo_selecionado['cor'], '#') === 0): ?>style="background-color: <?php echo htmlspecialchars($tipo_selecionado['cor']); ?>;"<?php endif; ?>>
                        Filtrando por: <?php echo htmlspecialchars($tipo_selecionado['nome']); ?>
                    </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($eventos_recentes)): ?>
                <p class="text-muted text-center">Nenhum evento registrado ainda.</p>
                <?php else: ?>
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
                                <?php if ($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2()): ?>
                                <th>Aluno</th>
                                <?php endif; ?>
                                <th>Tipo</th>
                                <th>Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos_recentes as $evt): ?>
                            <tr class="row-observacoes" style="cursor: pointer;" data-evento='<?php echo htmlspecialchars(json_encode([
                                'id' => $evt['id'],
                                'data' => date('d/m/Y', strtotime($evt['data_evento'])),
                                'hora' => $evt['hora_evento'] ? date('H:i', strtotime($evt['hora_evento'])) : '-',
                                'aluno' => $evt['aluno_nome'] ?? 'N/A',
                                'tipo' => $evt['tipo_evento_nome'] ?? 'N/A',
                                'registrado_por' => $evt['registrado_por_nome'] ?? '-',
                                'observacoes' => $evt['observacoes'] ?? ''
                            ])); ?>'>
                                <td><?php echo date('d/m/Y', strtotime($evt['data_evento'])); ?></td>
                                <td><?php echo $evt['hora_evento'] ? date('H:i', strtotime($evt['hora_evento'])) : '-'; ?></td>
                                <?php if ($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2()): ?>
                                <td><?php echo htmlspecialchars($evt['aluno_nome'] ?? 'N/A'); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if (!empty($evt['tipo_evento_nome'])): ?>
                                        <?php 
                                        $cor = $evt['tipo_evento_cor'] ?? 'secondary';
                                        if (strpos($cor, '#') === 0) {
                                            echo '<span class="badge" style="background-color: ' . htmlspecialchars($cor) . ';">';
                                        } else {
                                            echo '<span class="badge bg-' . htmlspecialchars($cor) . '">';
                                        }
                                        ?>
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


<?php require_once 'includes/footer.php'; ?>

