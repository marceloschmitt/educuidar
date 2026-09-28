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

$ano_corrente = (int) $configuracao->getAnoCorrente();
$cursos = $curso->getAll();
$turmas_ano_corrente_lista = $turma->getTurmasPorAnoCorrente($ano_corrente);
$todos_tipos = $tipo_evento_model->getAll(false);

$tipos_por_id = [];
foreach ($todos_tipos as $t) {
    $tipos_por_id[(int) $t['id']] = $t['nome'];
}

$tipo_sigaa_id = $configuracao->getApiSigaaTipoEventoFaltaId();
if ($tipo_sigaa_id !== null && !isset($tipos_por_id[(int) $tipo_sigaa_id])) {
    $tipo_sigaa_id = null;
}
$tipo_sigaa_configurado = $tipo_sigaa_id !== null;
if (!$tipo_sigaa_configurado) {
    $tipo_detectado = (new DashboardEstatisticas($db, []))->tipoDoRegistroAutomatico();
    if ($tipo_detectado !== null && isset($tipos_por_id[$tipo_detectado])) {
        $tipo_sigaa_id = $tipo_detectado;
    }
}

$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$apenas_meus_eventos = $user->isNivel2() || ($_GET['apenas_meus_eventos'] ?? '') === '1';

// Sem parâmetro, o filtro de tipo começa no tipo do registro automático (SIGAA); "todos" remove o filtro.
$filtro_tipo_param = $_GET['filtro_tipo_evento'] ?? '';
if ($filtro_tipo_param === 'todos') {
    $tipo_selecionado_id = null;
} elseif ($filtro_tipo_param !== '' && isset($tipos_por_id[(int) $filtro_tipo_param])) {
    $tipo_selecionado_id = (int) $filtro_tipo_param;
} else {
    $tipo_selecionado_id = $tipo_sigaa_id;
}
$tipo_padrao_id = $tipo_sigaa_id;
$tipo_e_padrao = $tipo_selecionado_id === $tipo_padrao_id;
$tipo_e_sigaa = $tipo_selecionado_id !== null && $tipo_selecionado_id === $tipo_sigaa_id;
$tipo_selecionado_nome = $tipo_selecionado_id !== null ? $tipos_por_id[$tipo_selecionado_id] : 'Todos os tipos';
$rotulo_ocorrencias = $tipo_e_sigaa ? 'Faltas' : 'Eventos';

$filtros_base = [
    'ano' => $ano_corrente,
    'curso_id' => $filtro_curso,
    'turma_id' => $filtro_turma,
    'registrado_por' => $apenas_meus_eventos ? $user_id : null,
    'incluir_sabados' => $incluir_sabados,
];
$stats = new DashboardEstatisticas($db, $filtros_base + ['tipo_evento_id' => $tipo_selecionado_id]);
$stats_todos_tipos = new DashboardEstatisticas($db, $filtros_base);

$por_turma = $stats->porTurma();
$por_mes_turma = $stats->porMesETurma();
$por_dia_turma = $stats->porDiaETurma();
$top_alunos_por_turma = $stats->topAlunosPorTurma(5);
$totais_por_tipo = $stats_todos_tipos->totaisPorTipo();

$meses_nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$ano_atual_real = (int) date('Y');
$ultimo_mes = $ano_corrente === $ano_atual_real ? (int) date('n') : 12;
$labels_meses = array_slice($meses_nomes, 0, $ultimo_mes);

// Paleta categórica: a mesma cor identifica a turma em todos os gráficos.
$paleta = [
    '#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f', '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#17becf',
    '#8c564b', '#bcbd22', '#1f77b4', '#d62728', '#9467bd', '#2ca02c', '#ff7f0e', '#7f7f7f', '#e377c2', '#393b79',
];

$series_turma = [];
$total_selecionado = 0;
foreach ($por_turma as $i => $row) {
    $tid = (int) $row['id'];
    $series_turma[$tid] = [
        'id' => $tid,
        'label' => trim($row['curso_nome'] . ' ' . (int) $row['ano_curso'] . 'º'),
        'cor' => $paleta[$i % count($paleta)],
        'total' => (int) $row['total'],
        'mensal' => array_fill(0, $ultimo_mes, 0),
        'dias' => array_fill(0, 7, 0),
    ];
    $total_selecionado += (int) $row['total'];
}
foreach ($por_mes_turma as $row) {
    $tid = (int) $row['turma_id'];
    $mes_idx = (int) $row['mes'] - 1;
    if (isset($series_turma[$tid]) && $mes_idx >= 0 && $mes_idx < $ultimo_mes) {
        $series_turma[$tid]['mensal'][$mes_idx] = (int) $row['total'];
    }
}
$tem_domingo = false;
foreach ($por_dia_turma as $row) {
    $tid = (int) $row['turma_id'];
    $dia = (int) $row['dia'];
    if (isset($series_turma[$tid])) {
        $series_turma[$tid]['dias'][$dia] = (int) $row['total'];
        if ($dia === 6 && (int) $row['total'] > 0) {
            $tem_domingo = true;
        }
    }
}

$dias_nomes = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
$indices_dias = [0, 1, 2, 3, 4];
if ($incluir_sabados) $indices_dias[] = 5;
if ($tem_domingo) $indices_dias[] = 6;
$labels_dias = array_map(function ($i) use ($dias_nomes) { return $dias_nomes[$i]; }, $indices_dias);

$params_base = [];
if ($filtro_curso) $params_base['filtro_curso'] = $filtro_curso;
if ($filtro_turma) $params_base['filtro_turma'] = $filtro_turma;
if (!$tipo_e_padrao) $params_base['filtro_tipo_evento'] = $tipo_selecionado_id ?? 'todos';
if (!$incluir_sabados) $params_base['incluir_sabados'] = '0';
if ($apenas_meus_eventos && !$user->isNivel2()) $params_base['apenas_meus_eventos'] = '1';

$url_dashboard = function (array $sobrescrever) use ($params_base) {
    $p = array_filter(array_merge($params_base, $sobrescrever), function ($v) {
        return $v !== '' && $v !== null;
    });
    return 'index.php' . ($p ? '?' . http_build_query($p) : '');
};
$url_eventos_por_tipo = function ($tipo_id) use ($params_base) {
    $p = $params_base;
    $p['filtro_tipo_evento'] = $tipo_id;
    return 'eventos_por_tipo.php?' . http_build_query($p);
};

$distribuicao = [];
foreach ($totais_por_tipo as $row) {
    $tid = $row['tipo_id'] !== null ? (int) $row['tipo_id'] : null;
    if ($tid !== null && $tid === $tipo_sigaa_id) {
        continue;
    }
    $distribuicao[] = [
        'label' => $row['tipo_nome'],
        'total' => (int) $row['total'],
        'selecionado' => $tid !== null && $tid === $tipo_selecionado_id,
        'url' => $tid !== null ? $url_dashboard(['filtro_tipo_evento' => $tid === $tipo_padrao_id ? '' : $tid]) : null,
    ];
}

$filtros_alterados = $filtro_curso || $filtro_turma || !$tipo_e_padrao || !$incluir_sabados || ($apenas_meus_eventos && !$user->isNivel2());

$chart_data = [
    'rotulo' => $rotulo_ocorrencias,
    'meses' => $labels_meses,
    'dias' => $labels_dias,
    'distribuicao' => $distribuicao,
    'turmas' => array_values(array_map(function ($s) use ($indices_dias, $url_dashboard) {
        return [
            'label' => $s['label'],
            'cor' => $s['cor'],
            'total' => $s['total'],
            'mensal' => $s['mensal'],
            'dias' => array_map(function ($i) use ($s) { return $s['dias'][$i]; }, $indices_dias),
            'url' => $url_dashboard(['filtro_turma' => $s['id']]),
        ];
    }, $series_turma)),
];

$sufixo_titulo = ' — ' . htmlspecialchars($tipo_selecionado_nome);

$filtros_descricao = [$tipo_selecionado_nome];
if ($filtro_curso) {
    foreach ($cursos as $c) {
        if ((string) $c['id'] === (string) $filtro_curso) {
            $filtros_descricao[] = $c['nome'];
            break;
        }
    }
} else {
    $filtros_descricao[] = 'Todos os cursos';
}
if ($filtro_turma) {
    foreach ($turmas_ano_corrente_lista as $t) {
        if ((string) $t['id'] === (string) $filtro_turma) {
            $filtros_descricao[] = ($filtro_curso ? '' : ($t['curso_nome'] ?? '') . ' ') . $t['ano_curso'] . 'º Ano';
            break;
        }
    }
}
$filtros_descricao[] = $incluir_sabados ? 'com sábados' : 'sem sábados';
if ($apenas_meus_eventos) {
    $filtros_descricao[] = 'apenas meus eventos';
}
$titulo_filtros = htmlspecialchars(implode(' · ', array_map('trim', $filtros_descricao)));
$altura_barras_turma = max(120, count($series_turma) * 30 + 30);
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="incluir_sabados" id="incluir_sabados_value" value="<?php echo $incluir_sabados ? '1' : '0'; ?>">
            <input type="hidden" name="apenas_meus_eventos" id="apenas_meus_eventos_value" value="<?php echo $apenas_meus_eventos ? '1' : '0'; ?>">
            <div class="col-md-3">
                <label for="filtro_tipo_evento" class="form-label">Tipo de evento</label>
                <select class="form-select form-select-sm" id="filtro_tipo_evento" name="filtro_tipo_evento" onchange="this.form.submit();">
                    <option value="todos" <?php echo $tipo_selecionado_id === null ? 'selected' : ''; ?>>Todos os tipos</option>
                    <?php foreach ($todos_tipos as $t): ?>
                    <option value="<?php echo (int) $t['id']; ?>" <?php echo $tipo_selecionado_id === (int) $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['nome']); ?><?php echo (int) $t['id'] === $tipo_sigaa_id ? ' (registro automático)' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
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
            <?php if ($filtros_alterados): ?>
            <div class="col-12">
                <a href="index.php?limpar_filtros=1" class="btn btn-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Filtros padrão
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!$tipo_sigaa_configurado && $user->isAdmin()): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    O tipo de evento das faltas do SIGAA não está configurado.
    <?php if ($tipo_sigaa_id !== null): ?>
    O dashboard está usando "<?php echo htmlspecialchars($tipos_por_id[$tipo_sigaa_id]); ?>", identificado pelas faltas automáticas já registradas.
    <?php else: ?>
    Por isso o dashboard começa com todos os tipos.
    <?php endif; ?>
    Configure em <a href="api_sigaa_config.php">Configurações &gt; API SIGAA</a>.
</div>
<?php endif; ?>

<?php if ($total_selecionado === 0): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> Nenhum evento de <strong><?php echo htmlspecialchars($tipo_selecionado_nome); ?></strong> em <?php echo $ano_corrente; ?> com os filtros selecionados.
</div>
<?php else: ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">Evolução mensal por turma em <?php echo $ano_corrente; ?> <span class="text-muted fw-normal">— <?php echo $titulo_filtros; ?></span></h6>
        <?php if ($tipo_selecionado_id !== null): ?>
        <a href="<?php echo htmlspecialchars($url_eventos_por_tipo($tipo_selecionado_id)); ?>" class="btn btn-sm btn-outline-secondary">
            <?php echo number_format($total_selecionado, 0, ',', '.'); ?> no ano <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <?php else: ?>
        <span class="text-muted small"><?php echo number_format($total_selecionado, 0, ',', '.'); ?> no ano</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="dashboard-chart" style="height: 280px;"><canvas id="chartMensal"></canvas></div>
        <div class="small text-muted mt-2">Clique na legenda para ligar ou desligar uma linha.</div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Total de <?php echo strtolower($rotulo_ocorrencias); ?> por turma<?php echo $sufixo_titulo; ?></h6>
    </div>
    <div class="card-body">
        <div class="dashboard-chart" style="height: <?php echo $altura_barras_turma; ?>px;"><canvas id="chartTurmas"></canvas></div>
        <div class="small text-muted mt-2">Clique em uma barra para filtrar o dashboard pela turma.</div>
    </div>
</div>

<h6 class="mb-3">Top 5 alunos por turma<?php echo $sufixo_titulo; ?></h6>
<div class="row g-3 mb-4">
    <?php foreach ($series_turma as $tid => $serie): ?>
    <?php $top = $top_alunos_por_turma[$tid] ?? []; ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h6 class="mb-0 d-flex align-items-center gap-2">
                    <span class="dashboard-turma-cor" style="background-color: <?php echo $serie['cor']; ?>;"></span>
                    <?php echo htmlspecialchars($serie['label']); ?>
                </h6>
                <span class="text-muted small"><?php echo number_format($serie['total'], 0, ',', '.'); ?> <?php echo strtolower($rotulo_ocorrencias); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top)): ?>
                <p class="text-muted text-center my-3">Nenhum aluno encontrado.</p>
                <?php else: ?>
                <table class="table table-hover table-sm mb-0 align-middle">
                    <tbody>
                        <?php $max_top = max(1, (int) $top[0]['total']); ?>
                        <?php foreach ($top as $pos => $al): ?>
                        <tr class="dashboard-top-aluno" data-aluno-id="<?php echo (int) $al['id']; ?>" style="cursor: pointer;" title="Ver ficha do aluno">
                            <td class="text-muted ps-3" style="width: 2rem;"><?php echo $pos + 1; ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($al['nome'] ?? '-'); ?></div>
                                <div class="small text-muted">Último: <?php echo !empty($al['ultimo_evento']) ? date('d/m/Y', strtotime($al['ultimo_evento'])) : '-'; ?></div>
                            </td>
                            <td class="text-end pe-3" style="width: 150px;">
                                <div class="d-flex align-items-center gap-2 justify-content-end">
                                    <div class="progress flex-grow-1" style="height: 6px; max-width: 90px;">
                                        <div class="progress-bar" style="width: <?php echo round(((int) $al['total'] / $max_top) * 100); ?>%; background-color: <?php echo $serie['cor']; ?>;"></div>
                                    </div>
                                    <strong><?php echo (int) $al['total']; ?></strong>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><?php echo $rotulo_ocorrencias; ?> por dia da semana e turma<?php echo $sufixo_titulo; ?></h6>
    </div>
    <div class="card-body">
        <div class="dashboard-chart dashboard-chart-lg"><canvas id="chartDias"></canvas></div>
        <div class="small text-muted mt-2">Clique na legenda para ligar ou desligar uma turma.</div>
    </div>
</div>

<?php endif; ?>

<?php if (!empty($distribuicao)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Distribuição dos demais eventos</h6>
    </div>
    <div class="card-body">
        <div class="dashboard-chart" style="height: <?php echo max(120, count($distribuicao) * 30 + 30); ?>px;"><canvas id="chartTipos"></canvas></div>
        <div class="small text-muted mt-2">Clique em uma barra para ver a evolução daquele tipo no dashboard.</div>
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
    var urlsTurmas = dados.turmas.map(function (t) { return t.url; });

    function criarGrafico(id, config) {
        var canvas = document.getElementById(id);
        return canvas ? new Chart(canvas, config) : null;
    }

    function transparente(hex, alfa) {
        var n = parseInt(hex.slice(1), 16);
        return 'rgba(' + ((n >> 16) & 255) + ', ' + ((n >> 8) & 255) + ', ' + (n & 255) + ', ' + alfa + ')';
    }

    function cursorLink(urls) {
        return function (evt, elementos) {
            var url = elementos.length ? urls[elementos[0].index] : null;
            evt.native.target.style.cursor = url ? 'pointer' : 'default';
        };
    }

    function cliqueLink(urls) {
        return function (evt, elementos) {
            if (!elementos.length) return;
            var url = urls[elementos[0].index];
            if (url) window.location.href = url;
        };
    }

    function somar(valores) {
        return valores.reduce(function (acc, v) { return acc + v; }, 0);
    }

    function formatarPercentual(valor, soma) {
        var pct = soma ? (valor / soma) * 100 : 0;
        return pct.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
    }

    // Escreve "valor (percentual)" na ponta de cada barra horizontal.
    function rotulosBarras(soma) {
        return {
            id: 'rotulosBarras',
            afterDatasetsDraw: function (chart) {
                var ctx = chart.ctx;
                ctx.save();
                ctx.font = '600 12px ' + Chart.defaults.font.family;
                ctx.fillStyle = '#495057';
                ctx.textBaseline = 'middle';
                chart.getDatasetMeta(0).data.forEach(function (barra, idx) {
                    var valor = chart.data.datasets[0].data[idx];
                    ctx.fillText(valor + '  (' + formatarPercentual(valor, soma) + ')', barra.x + 6, barra.y);
                });
                ctx.restore();
            }
        };
    }

    // Mesma marca de cor nos gráficos de linha e de barra: bloco sólido, sem a borda que só as linhas desenham.
    var legendaTurmas = {
        position: 'bottom',
        labels: {
            boxWidth: 28,
            boxHeight: 8,
            padding: 14,
            generateLabels: function (chart) {
                return Chart.defaults.plugins.legend.labels.generateLabels(chart).map(function (item) {
                    var ds = chart.data.datasets[item.datasetIndex];
                    var corTurma = ds.borderColor || ds.backgroundColor;
                    item.fillStyle = corTurma;
                    item.strokeStyle = corTurma;
                    item.lineWidth = 0;
                    item.lineDash = [];
                    return item;
                });
            }
        }
    };

    criarGrafico('chartMensal', {
        type: 'line',
        data: {
            labels: dados.meses,
            datasets: dados.turmas.map(function (t) {
                return {
                    label: t.label,
                    data: t.mensal,
                    borderColor: t.cor,
                    backgroundColor: t.cor,
                    fill: false,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    borderWidth: 2
                };
            })
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: { x: { grid: { display: false } }, y: eixoInteiro },
            plugins: {
                legend: legendaTurmas,
                tooltip: {
                    itemSort: function (a, b) { return b.parsed.y - a.parsed.y; }
                }
            }
        }
    });

    var totaisTurmas = dados.turmas.map(function (t) { return t.total; });
    var somaTurmas = somar(totaisTurmas);
    criarGrafico('chartTurmas', {
        type: 'bar',
        data: {
            labels: dados.turmas.map(function (t) { return t.label; }),
            datasets: [{
                label: dados.rotulo,
                data: totaisTurmas,
                backgroundColor: dados.turmas.map(function (t) { return t.cor; }),
                borderRadius: 3,
                maxBarThickness: 22
            }]
        },
        plugins: [rotulosBarras(somaTurmas)],
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            layout: { padding: { right: 110 } },
            scales: {
                x: Object.assign({ grid: { color: '#f1f3f5' } }, eixoInteiro),
                y: { grid: { display: false } }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.parsed.x + ' — ' + formatarPercentual(ctx.parsed.x, somaTurmas);
                        }
                    }
                }
            },
            onClick: cliqueLink(urlsTurmas),
            onHover: cursorLink(urlsTurmas)
        }
    });

    criarGrafico('chartDias', {
        type: 'bar',
        data: {
            labels: dados.dias,
            datasets: dados.turmas.map(function (t) {
                return { label: t.label, data: t.dias, backgroundColor: t.cor, borderRadius: 2 };
            })
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: { x: { grid: { display: false } }, y: eixoInteiro },
            plugins: {
                legend: legendaTurmas,
                tooltip: {
                    itemSort: function (a, b) { return b.parsed.y - a.parsed.y; }
                }
            }
        }
    });

    var somaDistribuicao = somar(dados.distribuicao.map(function (d) { return d.total; }));
    var algumSelecionado = dados.distribuicao.some(function (d) { return d.selecionado; });
    function percentual(valor) {
        return formatarPercentual(valor, somaDistribuicao);
    }

    var urlsDistribuicao = dados.distribuicao.map(function (d) { return d.url; });
    criarGrafico('chartTipos', {
        type: 'bar',
        data: {
            labels: dados.distribuicao.map(function (d) { return d.label; }),
            datasets: [{
                label: 'Eventos',
                data: dados.distribuicao.map(function (d) { return d.total; }),
                backgroundColor: dados.distribuicao.map(function (d) {
                    if (d.selecionado) return '#f28e2b';
                    return algumSelecionado ? transparente('#4e79a7', 0.45) : '#4e79a7';
                }),
                borderRadius: 3,
                maxBarThickness: 22
            }]
        },
        plugins: [rotulosBarras(somaDistribuicao)],
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            layout: { padding: { right: 110 } },
            scales: {
                x: Object.assign({ grid: { color: '#f1f3f5' } }, eixoInteiro),
                y: { grid: { display: false } }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.parsed.x + ' evento(s) — ' + percentual(ctx.parsed.x);
                        }
                    }
                }
            },
            onClick: cliqueLink(urlsDistribuicao),
            onHover: cursorLink(urlsDistribuicao)
        }
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
