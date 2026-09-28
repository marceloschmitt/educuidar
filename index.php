<?php
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$user->ensureUserTypeSession();

if ($user->isLoggedIn() && !($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2())) {
    header('Location: eventos_por_tipo.php');
    exit;
}

$page_title = 'Dashboard';
require_once 'includes/header.php';

$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);
$tipo_evento_model = new TipoEvento($db);

$user_id = $_SESSION['user_id'];
$incluir_sabados = resolveIncluirSabadosSession();

$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_tipo_evento = $_GET['filtro_tipo_evento'] ?? '';
$apenas_meus_eventos = $user->isNivel2() || ($_GET['apenas_meus_eventos'] ?? '') === '1';

$ano_corrente = (int) $configuracao->getAnoCorrente();
$cursos = $curso->getAll();
$turmas_ano_corrente_lista = $turma->getTurmasPorAnoCorrente($ano_corrente);
$todos_tipos = $tipo_evento_model->getAll(false);

$stats = new DashboardEstatisticas($db, [
    'ano' => $ano_corrente,
    'curso_id' => $filtro_curso,
    'turma_id' => $filtro_turma,
    'tipo_evento_id' => $filtro_tipo_evento,
    'registrado_por' => $apenas_meus_eventos ? $user_id : null,
    'incluir_sabados' => $incluir_sabados,
]);

$resumo = $stats->resumo();
$por_mes_tipo = $stats->porMesETipo();
$por_semana = $stats->porSemana();
$por_dia_semana = $stats->porDiaDaSemana();
$agrupar_por_turma = (bool) $filtro_curso;
$por_grupo = $agrupar_por_turma ? $stats->porTurma() : $stats->porCurso();
$top_alunos = $stats->topAlunos(10);

$meses_nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$ano_atual_real = (int) date('Y');
$ultimo_mes = $ano_corrente === $ano_atual_real ? (int) date('n') : 12;
$labels_meses = array_slice($meses_nomes, 0, $ultimo_mes);

// Paleta categórica própria: as cores dos tipos são classes Bootstrap e se repetem muito.
$paleta = [
    '#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f', '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#17becf',
    '#8c564b', '#bcbd22', '#1f77b4', '#d62728', '#9467bd', '#2ca02c', '#ff7f0e', '#7f7f7f', '#e377c2', '#393b79',
];

$tipo_sigaa_id = $configuracao->getApiSigaaTipoEventoFaltaId();
$tipo_sigaa_nome = null;
foreach ($todos_tipos as $t) {
    if ((int) $t['id'] === (int) $tipo_sigaa_id) {
        $tipo_sigaa_nome = $t['nome'];
        break;
    }
}
if ($tipo_sigaa_nome === null) {
    $tipo_sigaa_id = null;
}

$sigaa_faltas = array_fill(0, $ultimo_mes, 0);
$sigaa_alunos = array_fill(0, $ultimo_mes, 0);
$sigaa_total = 0;

$totais_tipo = [];
$nomes_tipo = [];
$mensal_tipo = [];
foreach ($por_mes_tipo as $row) {
    $tid = (int) $row['tipo_id'];
    $mes_idx = (int) $row['mes'] - 1;
    $qtd = (int) $row['total'];
    if ($tipo_sigaa_id !== null && $tid === (int) $tipo_sigaa_id) {
        $sigaa_total += $qtd;
        if ($mes_idx >= 0 && $mes_idx < $ultimo_mes) {
            $sigaa_faltas[$mes_idx] += $qtd;
        }
        continue;
    }
    $totais_tipo[$tid] = ($totais_tipo[$tid] ?? 0) + $qtd;
    $nomes_tipo[$tid] = $row['tipo_nome'] ?? 'Sem tipo';
    if (!isset($mensal_tipo[$tid])) {
        $mensal_tipo[$tid] = array_fill(0, $ultimo_mes, 0);
    }
    if ($mes_idx >= 0 && $mes_idx < $ultimo_mes) {
        $mensal_tipo[$tid][$mes_idx] += $qtd;
    }
}
arsort($totais_tipo);

if ($tipo_sigaa_id !== null && $sigaa_total > 0) {
    foreach ($stats->alunosDistintosPorMes($tipo_sigaa_id) as $row) {
        $mes_idx = (int) $row['mes'] - 1;
        if ($mes_idx >= 0 && $mes_idx < $ultimo_mes) {
            $sigaa_alunos[$mes_idx] = (int) $row['total'];
        }
    }
}

$series_tipo = [];
$i = 0;
foreach ($totais_tipo as $tid => $total) {
    $series_tipo[] = [
        'id' => $tid,
        'label' => $nomes_tipo[$tid],
        'cor' => $paleta[$i % count($paleta)],
        'dados' => $mensal_tipo[$tid],
        'total' => $total,
    ];
    $i++;
}

$semanas_map = [];
foreach ($por_semana as $row) {
    $semanas_map[$row['semana']] = (int) $row['total'];
}
$labels_semanas = [];
$dados_semanas = [];
if (!empty($semanas_map)) {
    $inicio = new DateTime(min(array_keys($semanas_map)));
    $fim_ref = $ano_corrente === $ano_atual_real ? new DateTime('today') : new DateTime(max(array_keys($semanas_map)));
    $fim = (clone $fim_ref)->modify('-' . ((int) $fim_ref->format('N') - 1) . ' days');
    for ($d = clone $inicio; $d <= $fim; $d->modify('+7 days')) {
        $chave = $d->format('Y-m-d');
        $labels_semanas[] = $d->format('d/m');
        $dados_semanas[] = $semanas_map[$chave] ?? 0;
    }
}

$dias_nomes = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
$dias_map = [];
foreach ($por_dia_semana as $row) {
    $dias_map[(int) $row['dia']] = (int) $row['total'];
}
$labels_dias = [];
$dados_dias = [];
foreach ($dias_nomes as $idx => $nome) {
    if ($idx === 5 && !$incluir_sabados) {
        continue;
    }
    if ($idx === 6 && empty($dias_map[6])) {
        continue;
    }
    $labels_dias[] = $nome;
    $dados_dias[] = $dias_map[$idx] ?? 0;
}

$por_grupo = array_slice($por_grupo, 0, 12);

$params_base = [];
if ($filtro_curso) $params_base['filtro_curso'] = $filtro_curso;
if ($filtro_turma) $params_base['filtro_turma'] = $filtro_turma;
if ($filtro_tipo_evento) $params_base['filtro_tipo_evento'] = $filtro_tipo_evento;
if (!$incluir_sabados) $params_base['incluir_sabados'] = '0';
if ($apenas_meus_eventos && !$user->isNivel2()) $params_base['apenas_meus_eventos'] = '1';

$url_eventos_por_tipo = function ($tipo_id) use ($params_base) {
    $p = $params_base;
    $p['filtro_tipo_evento'] = $tipo_id;
    return 'eventos_por_tipo.php?' . http_build_query($p);
};
$url_dashboard = function (array $sobrescrever) use ($params_base) {
    return 'index.php?' . http_build_query(array_merge($params_base, $sobrescrever));
};

$variacao_mes = null;
if ($resumo['mes_anterior'] > 0) {
    $variacao_mes = (int) round((($resumo['mes_atual'] - $resumo['mes_anterior']) / $resumo['mes_anterior']) * 100);
}

$tipo_filtrado_nome = null;
foreach ($todos_tipos as $t) {
    if ((string) $t['id'] === (string) $filtro_tipo_evento) {
        $tipo_filtrado_nome = $t['nome'];
        break;
    }
}

$chart_data = [
    'meses' => $labels_meses,
    'sigaa' => [
        'faltas' => $sigaa_faltas,
        'alunos' => $sigaa_alunos,
        'total' => $sigaa_total,
    ],
    'seriesTipo' => array_map(function ($s) use ($url_eventos_por_tipo) {
        return [
            'label' => $s['label'],
            'cor' => $s['cor'],
            'dados' => $s['dados'],
            'total' => $s['total'],
            'url' => $s['id'] ? $url_eventos_por_tipo($s['id']) : null,
        ];
    }, $series_tipo),
    'semanas' => ['labels' => $labels_semanas, 'dados' => $dados_semanas],
    'dias' => ['labels' => $labels_dias, 'dados' => $dados_dias],
    'grupos' => [
        'labels' => array_column($por_grupo, 'nome'),
        'dados' => array_map('intval', array_column($por_grupo, 'total')),
        'urls' => array_map(function ($g) use ($agrupar_por_turma, $url_dashboard) {
            if (empty($g['id'])) {
                return null;
            }
            return $agrupar_por_turma
                ? $url_dashboard(['filtro_turma' => $g['id']])
                : $url_dashboard(['filtro_curso' => $g['id'], 'filtro_turma' => '']);
        }, $por_grupo),
    ],
];
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="incluir_sabados" id="incluir_sabados_value" value="<?php echo $incluir_sabados ? '1' : '0'; ?>">
            <input type="hidden" name="apenas_meus_eventos" id="apenas_meus_eventos_value" value="<?php echo $apenas_meus_eventos ? '1' : '0'; ?>">
            <div class="col-md-3">
                <label for="filtro_curso" class="form-label">Curso</label>
                <select class="form-select form-select-sm" id="filtro_curso" name="filtro_curso" onchange="document.getElementById('filtro_turma').value=''; this.form.submit();">
                    <option value="">Todos os cursos</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>" <?php echo ((string) $filtro_curso === (string) $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtro_turma" class="form-label">Turma (Ano <?php echo $ano_corrente; ?>)</label>
                <select class="form-select form-select-sm" id="filtro_turma" name="filtro_turma" onchange="this.form.submit();">
                    <option value="">Todas as turmas</option>
                    <?php foreach ($turmas_ano_corrente_lista as $t): ?>
                    <?php if ($filtro_curso && (string) $t['curso_id'] !== (string) $filtro_curso) continue; ?>
                    <option value="<?php echo (int) $t['id']; ?>" <?php echo ((string) $filtro_turma === (string) $t['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(($t['curso_nome'] ?? '') . ' - ' . $t['ano_curso'] . 'º Ano'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtro_tipo_evento" class="form-label">Tipo de evento</label>
                <select class="form-select form-select-sm" id="filtro_tipo_evento" name="filtro_tipo_evento" onchange="this.form.submit();">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($todos_tipos as $t): ?>
                    <option value="<?php echo (int) $t['id']; ?>" <?php echo ((string) $filtro_tipo_evento === (string) $t['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex flex-column gap-1">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="incluir_sabados_cb" <?php echo $incluir_sabados ? 'checked' : ''; ?>
                           onchange="document.getElementById('incluir_sabados_value').value = this.checked ? '1' : '0'; this.form.submit();">
                    <label class="form-check-label" for="incluir_sabados_cb">Incluir sábados</label>
                </div>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="apenas_meus_eventos_cb" <?php echo $apenas_meus_eventos ? 'checked' : ''; ?>
                           <?php echo $user->isNivel2() ? 'disabled' : ''; ?>
                           onchange="document.getElementById('apenas_meus_eventos_value').value = this.checked ? '1' : '0'; this.form.submit();">
                    <label class="form-check-label" for="apenas_meus_eventos_cb">Apenas meus eventos</label>
                </div>
            </div>
            <?php if ($filtro_curso || $filtro_turma || $filtro_tipo_evento || !$incluir_sabados || ($apenas_meus_eventos && !$user->isNivel2())): ?>
            <div class="col-12">
                <a href="index.php?limpar_filtros=1" class="btn btn-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Filtros padrão
                </a>
                <?php if ($tipo_filtrado_nome): ?>
                <span class="badge bg-primary ms-2">Tipo: <?php echo htmlspecialchars($tipo_filtrado_nome); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card dashboard-kpi h-100">
            <div class="card-body">
                <div class="dashboard-kpi-label"><i class="bi bi-calendar3"></i> Eventos em <?php echo $ano_corrente; ?></div>
                <div class="dashboard-kpi-valor"><?php echo number_format($resumo['total'], 0, ',', '.'); ?></div>
                <div class="dashboard-kpi-detalhe"><?php echo number_format($resumo['alunos'], 0, ',', '.'); ?> aluno(s) envolvido(s)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card dashboard-kpi h-100">
            <div class="card-body">
                <div class="dashboard-kpi-label"><i class="bi bi-calendar-month"></i> Mês atual</div>
                <div class="dashboard-kpi-valor"><?php echo number_format($resumo['mes_atual'], 0, ',', '.'); ?></div>
                <div class="dashboard-kpi-detalhe">
                    <?php if ($variacao_mes === null): ?>
                        Mês anterior: <?php echo $resumo['mes_anterior']; ?>
                    <?php else: ?>
                        <span class="<?php echo $variacao_mes > 0 ? 'text-danger' : ($variacao_mes < 0 ? 'text-success' : 'text-muted'); ?>">
                            <i class="bi bi-arrow-<?php echo $variacao_mes > 0 ? 'up' : ($variacao_mes < 0 ? 'down' : 'right'); ?>"></i>
                            <?php echo ($variacao_mes > 0 ? '+' : '') . $variacao_mes; ?>%
                        </span>
                        vs. mês anterior (<?php echo $resumo['mes_anterior']; ?>)
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card dashboard-kpi h-100">
            <div class="card-body">
                <div class="dashboard-kpi-label"><i class="bi bi-calendar-week"></i> Últimos 7 dias</div>
                <div class="dashboard-kpi-valor"><?php echo number_format($resumo['ultimos_7_dias'], 0, ',', '.'); ?></div>
                <div class="dashboard-kpi-detalhe">Hoje: <?php echo $resumo['hoje']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card dashboard-kpi h-100">
            <div class="card-body">
                <div class="dashboard-kpi-label"><i class="bi bi-graph-up"></i> Média mensal</div>
                <div class="dashboard-kpi-valor"><?php echo $ultimo_mes > 0 ? number_format($resumo['total'] / $ultimo_mes, 1, ',', '.') : '0'; ?></div>
                <div class="dashboard-kpi-detalhe">de janeiro a <?php echo strtolower($meses_nomes[$ultimo_mes - 1]); ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($resumo['total'] === 0): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> Nenhum evento encontrado em <?php echo $ano_corrente; ?> com os filtros selecionados.
</div>
<?php else: ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">
            <i class="bi bi-cloud-download"></i> Evolução mensal das faltas (coleta automática do SIGAA)
            <?php if ($tipo_sigaa_nome !== null): ?>
            <span class="text-muted fw-normal">— <?php echo htmlspecialchars($tipo_sigaa_nome); ?></span>
            <?php endif; ?>
        </h6>
        <?php if ($tipo_sigaa_id !== null && $sigaa_total > 0): ?>
        <a href="<?php echo htmlspecialchars($url_eventos_por_tipo($tipo_sigaa_id)); ?>" class="btn btn-sm btn-outline-secondary">
            <?php echo number_format($sigaa_total, 0, ',', '.'); ?> falta(s) no ano <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($tipo_sigaa_id === null): ?>
        <p class="text-muted mb-0">
            O tipo de evento das faltas do SIGAA não está configurado.
            <?php if ($user->isAdmin()): ?>
            Configure em <a href="api_sigaa_config.php">Configurações &gt; API SIGAA</a>.
            <?php endif; ?>
        </p>
        <?php elseif ($sigaa_total === 0): ?>
        <p class="text-muted mb-0">Nenhuma falta do SIGAA com os filtros selecionados.</p>
        <?php else: ?>
        <div class="dashboard-chart dashboard-chart-md"><canvas id="chartSigaa"></canvas></div>
        <div class="small text-muted text-center mt-2">Clique na legenda para ligar ou desligar uma linha.</div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Evolução mensal dos demais eventos</h6>
                <?php if (!empty($series_tipo)): ?>
                <div class="btn-group btn-group-sm" role="group" aria-label="Linhas do gráfico">
                    <button type="button" class="btn btn-outline-secondary" data-linhas-outros="todas">Todas</button>
                    <button type="button" class="btn btn-outline-secondary" data-linhas-outros="nenhuma">Nenhuma</button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($series_tipo)): ?>
                <p class="text-muted mb-0">Nenhum outro evento com os filtros selecionados.</p>
                <?php else: ?>
                <div class="dashboard-chart dashboard-chart-lg"><canvas id="chartOutros"></canvas></div>
                <div class="small text-muted text-center mt-2">Clique na legenda para ligar ou desligar uma linha.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0">Distribuição dos demais eventos</h6>
            </div>
            <div class="card-body">
                <?php if (empty($series_tipo)): ?>
                <p class="text-muted mb-0">Sem dados.</p>
                <?php else: ?>
                <div class="dashboard-chart dashboard-chart-lg"><canvas id="chartTipos"></canvas></div>
                <div class="small text-muted text-center mt-2">Clique em um tipo para ver os eventos.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Eventos por semana</h6>
            </div>
            <div class="card-body">
                <div class="dashboard-chart dashboard-chart-md"><canvas id="chartSemanal"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><?php echo $agrupar_por_turma ? 'Eventos por turma' : 'Eventos por curso'; ?></h6>
            </div>
            <div class="card-body">
                <div class="dashboard-chart dashboard-chart-md"><canvas id="chartGrupos"></canvas></div>
                <div class="small text-muted text-center mt-2">Clique em uma barra para filtrar o dashboard.</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0">Eventos por dia da semana</h6>
            </div>
            <div class="card-body">
                <div class="dashboard-chart dashboard-chart-md"><canvas id="chartDias"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Alunos com mais eventos em <?php echo $ano_corrente; ?></h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($top_alunos)): ?>
        <p class="text-muted text-center my-3">Nenhum aluno encontrado.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 3rem;">#</th>
                        <th>Aluno</th>
                        <th>Curso / Turma</th>
                        <th class="text-end">Eventos</th>
                        <th>Último evento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $max_top = max(1, (int) $top_alunos[0]['total']); ?>
                    <?php foreach ($top_alunos as $pos => $al): ?>
                    <tr class="dashboard-top-aluno" data-aluno-id="<?php echo (int) $al['id']; ?>" style="cursor: pointer;" title="Ver ficha do aluno">
                        <td class="text-muted"><?php echo $pos + 1; ?></td>
                        <td><?php echo htmlspecialchars($al['nome'] ?? '-'); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($al['curso_nome'] ?? '-'); ?></div>
                            <div class="small text-muted"><?php echo !empty($al['ano_curso']) ? (int) $al['ano_curso'] . 'º Ano' : '-'; ?></div>
                        </td>
                        <td class="text-end" style="min-width: 160px;">
                            <div class="d-flex align-items-center gap-2 justify-content-end">
                                <div class="progress flex-grow-1" style="height: 6px; max-width: 120px;">
                                    <div class="progress-bar" style="width: <?php echo round(((int) $al['total'] / $max_top) * 100); ?>%; background-color: #4e79a7;"></div>
                                </div>
                                <strong><?php echo (int) $al['total']; ?></strong>
                            </div>
                        </td>
                        <td><?php echo !empty($al['ultimo_evento']) ? date('d/m/Y', strtotime($al['ultimo_evento'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/views/eventos/view_modal.php'; ?>
<?php require_once __DIR__ . '/views/alunos/ficha_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var dados = <?php echo json_encode($chart_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    document.querySelectorAll('.dashboard-top-aluno').forEach(function (tr) {
        tr.addEventListener('click', function () {
            var alunoId = tr.getAttribute('data-aluno-id');
            fetch('api/get_aluno_ficha.php?id=' + encodeURIComponent(alunoId))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    viewFichaAluno(data);
                })
                .catch(function () {
                    alert('Erro ao carregar dados do aluno.');
                });
        });
    });

    if (typeof Chart === 'undefined') {
        return;
    }

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = '#6c757d';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;

    var eixoInteiro = { beginAtZero: true, ticks: { precision: 0 } };

    function criarGrafico(id, config) {
        var canvas = document.getElementById(id);
        return canvas ? new Chart(canvas, config) : null;
    }

    function linha(label, valores, cor, extras) {
        return Object.assign({
            label: label,
            data: valores,
            borderColor: cor,
            backgroundColor: cor,
            tension: 0.3,
            fill: false,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 2
        }, extras || {});
    }

    function opcoesLinhas() {
        return {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: { x: { grid: { display: false } }, y: eixoInteiro },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'line' } },
                tooltip: {
                    itemSort: function (a, b) { return b.parsed.y - a.parsed.y; }
                }
            }
        };
    }

    criarGrafico('chartSigaa', {
        type: 'line',
        data: {
            labels: dados.meses,
            datasets: [
                linha('Faltas registradas', dados.sigaa.faltas, '#e15759', {
                    fill: true,
                    backgroundColor: 'rgba(225, 87, 89, 0.12)'
                }),
                linha('Alunos com falta', dados.sigaa.alunos, '#4e79a7', { borderDash: [6, 4] })
            ]
        },
        options: opcoesLinhas()
    });

    var chartOutros = criarGrafico('chartOutros', {
        type: 'line',
        data: {
            labels: dados.meses,
            datasets: dados.seriesTipo.map(function (s) { return linha(s.label, s.dados, s.cor); })
        },
        options: opcoesLinhas()
    });

    document.querySelectorAll('[data-linhas-outros]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!chartOutros) return;
            var mostrar = btn.getAttribute('data-linhas-outros') === 'todas';
            chartOutros.data.datasets.forEach(function (ds, idx) {
                chartOutros.setDatasetVisibility(idx, mostrar);
            });
            chartOutros.update();
        });
    });

    criarGrafico('chartTipos', {
        type: 'doughnut',
        data: {
            labels: dados.seriesTipo.map(function (s) { return s.label; }),
            datasets: [{
                data: dados.seriesTipo.map(function (s) { return s.total; }),
                backgroundColor: dados.seriesTipo.map(function (s) { return s.cor; }),
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var soma = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                            var pct = soma ? Math.round((ctx.parsed / soma) * 100) : 0;
                            return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            },
            onClick: function (evt, elementos) {
                if (!elementos.length) return;
                var url = dados.seriesTipo[elementos[0].index].url;
                if (url) window.location.href = url;
            },
            onHover: function (evt, elementos) {
                var url = elementos.length ? dados.seriesTipo[elementos[0].index].url : null;
                evt.native.target.style.cursor = url ? 'pointer' : 'default';
            }
        }
    });

    criarGrafico('chartSemanal', {
        type: 'line',
        data: {
            labels: dados.semanas.labels,
            datasets: [{
                label: 'Eventos na semana',
                data: dados.semanas.dados,
                borderColor: '#4e79a7',
                backgroundColor: 'rgba(78, 121, 167, 0.15)',
                fill: true,
                tension: 0.3,
                pointRadius: 2
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: { x: { grid: { display: false } }, y: eixoInteiro },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function (itens) { return 'Semana de ' + itens[0].label; }
                    }
                }
            }
        }
    });

    criarGrafico('chartGrupos', {
        type: 'bar',
        data: {
            labels: dados.grupos.labels,
            datasets: [{ label: 'Eventos', data: dados.grupos.dados, backgroundColor: '#59a14f', borderRadius: 3 }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            scales: { x: eixoInteiro, y: { grid: { display: false } } },
            plugins: { legend: { display: false } },
            onClick: function (evt, elementos) {
                if (!elementos.length) return;
                var url = dados.grupos.urls[elementos[0].index];
                if (url) window.location.href = url;
            },
            onHover: function (evt, elementos) {
                var url = elementos.length ? dados.grupos.urls[elementos[0].index] : null;
                evt.native.target.style.cursor = url ? 'pointer' : 'default';
            }
        }
    });

    criarGrafico('chartDias', {
        type: 'bar',
        data: {
            labels: dados.dias.labels,
            datasets: [{ label: 'Eventos', data: dados.dias.dados, backgroundColor: '#f28e2b', borderRadius: 3 }]
        },
        options: {
            maintainAspectRatio: false,
            scales: { x: { grid: { display: false } }, y: eixoInteiro },
            plugins: { legend: { display: false } }
        }
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
