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

$por_mes = $stats->porMes();
$por_dia_semana = $stats->porDiaDaSemana();
$agrupar_por_turma = (bool) $filtro_curso;
$por_grupo = array_slice($agrupar_por_turma ? $stats->porTurma() : $stats->porCurso(), 0, 12);
$top_alunos = $stats->topAlunos(10);
$totais_por_tipo = $stats_todos_tipos->totaisPorTipo();

$meses_nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$ano_atual_real = (int) date('Y');
$ultimo_mes = $ano_corrente === $ano_atual_real ? (int) date('n') : 12;
$labels_meses = array_slice($meses_nomes, 0, $ultimo_mes);

$mensal_total = array_fill(0, $ultimo_mes, 0);
$mensal_alunos = array_fill(0, $ultimo_mes, 0);
$total_selecionado = 0;
foreach ($por_mes as $row) {
    $total_selecionado += (int) $row['total'];
    $mes_idx = (int) $row['mes'] - 1;
    if ($mes_idx >= 0 && $mes_idx < $ultimo_mes) {
        $mensal_total[$mes_idx] = (int) $row['total'];
        $mensal_alunos[$mes_idx] = (int) $row['alunos'];
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
    'meses' => $labels_meses,
    'mensal' => [
        'rotulo' => $rotulo_ocorrencias,
        'total' => $mensal_total,
        'alunos' => $mensal_alunos,
        'cor' => $tipo_e_sigaa ? '#e15759' : '#4e79a7',
    ],
    'distribuicao' => $distribuicao,
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

$icone_tipo = $tipo_e_sigaa ? '<i class="bi bi-cloud-download"></i> ' : '';
$sufixo_titulo = ' — ' . htmlspecialchars($tipo_selecionado_nome);
?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="eventos.php" class="btn btn-primary">
        <i class="bi bi-calendar-event"></i> Eventos
    </a>
    <a href="eventos_por_tipo.php" class="btn btn-primary">
        <i class="bi bi-grid-3x3-gap"></i> Eventos por tipo
    </a>
    <a href="evento_grupo.php" class="btn btn-primary">
        <i class="bi bi-people"></i> Evento de grupo
    </a>
</div>

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

<?php if ($tipo_sigaa_id === null && $user->isAdmin()): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    O tipo de evento das faltas do SIGAA não está configurado, por isso o dashboard começa com todos os tipos.
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
        <h6 class="mb-0"><?php echo $icone_tipo; ?>Evolução mensal<?php echo $sufixo_titulo; ?></h6>
        <?php if ($tipo_selecionado_id !== null): ?>
        <a href="<?php echo htmlspecialchars($url_eventos_por_tipo($tipo_selecionado_id)); ?>" class="btn btn-sm btn-outline-secondary">
            <?php echo number_format($total_selecionado, 0, ',', '.'); ?> no ano <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <?php else: ?>
        <span class="text-muted small"><?php echo number_format($total_selecionado, 0, ',', '.'); ?> no ano</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="dashboard-chart dashboard-chart-lg"><canvas id="chartMensal"></canvas></div>
        <div class="small text-muted mt-2">Clique na legenda para ligar ou desligar uma linha.</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><?php echo $icone_tipo; ?><?php echo $rotulo_ocorrencias; ?> por <?php echo $agrupar_por_turma ? 'turma' : 'curso'; ?></h6>
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
                <h6 class="mb-0"><?php echo $icone_tipo; ?><?php echo $rotulo_ocorrencias; ?> por dia da semana</h6>
            </div>
            <div class="card-body">
                <div class="dashboard-chart dashboard-chart-md"><canvas id="chartDias"></canvas></div>
            </div>
        </div>
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

<?php if ($total_selecionado > 0): ?>
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Alunos com mais <?php echo strtolower($rotulo_ocorrencias); ?> em <?php echo $ano_corrente; ?><?php echo $sufixo_titulo; ?></h6>
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
                        <th class="text-end"><?php echo $rotulo_ocorrencias; ?></th>
                        <th>Último registro</th>
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
                                    <div class="progress-bar" style="width: <?php echo round(((int) $al['total'] / $max_top) * 100); ?>%; background-color: <?php echo $chart_data['mensal']['cor']; ?>;"></div>
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
    var cor = dados.mensal.cor;

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

    criarGrafico('chartMensal', {
        type: 'line',
        data: {
            labels: dados.meses,
            datasets: [
                {
                    label: dados.mensal.rotulo,
                    data: dados.mensal.total,
                    borderColor: cor,
                    backgroundColor: transparente(cor, 0.12),
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    borderWidth: 2
                },
                {
                    label: 'Alunos envolvidos',
                    data: dados.mensal.alunos,
                    borderColor: '#6c757d',
                    backgroundColor: '#6c757d',
                    borderDash: [6, 4],
                    fill: false,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    borderWidth: 2
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: { x: { grid: { display: false } }, y: eixoInteiro },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'line' } }
            }
        }
    });

    criarGrafico('chartGrupos', {
        type: 'bar',
        data: {
            labels: dados.grupos.labels,
            datasets: [{ label: dados.mensal.rotulo, data: dados.grupos.dados, backgroundColor: cor, borderRadius: 3 }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            scales: { x: eixoInteiro, y: { grid: { display: false } } },
            plugins: { legend: { display: false } },
            onClick: cliqueLink(dados.grupos.urls),
            onHover: cursorLink(dados.grupos.urls)
        }
    });

    criarGrafico('chartDias', {
        type: 'bar',
        data: {
            labels: dados.dias.labels,
            datasets: [{ label: dados.mensal.rotulo, data: dados.dias.dados, backgroundColor: cor, borderRadius: 3 }]
        },
        options: {
            maintainAspectRatio: false,
            scales: { x: { grid: { display: false } }, y: eixoInteiro },
            plugins: { legend: { display: false } }
        }
    });

    var somaDistribuicao = dados.distribuicao.reduce(function (acc, d) { return acc + d.total; }, 0);
    var algumSelecionado = dados.distribuicao.some(function (d) { return d.selecionado; });
    function percentual(valor) {
        var pct = somaDistribuicao ? (valor / somaDistribuicao) * 100 : 0;
        return pct.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
    }

    var rotulosBarras = {
        id: 'rotulosBarras',
        afterDatasetsDraw: function (chart) {
            var ctx = chart.ctx;
            ctx.save();
            ctx.font = '600 12px ' + Chart.defaults.font.family;
            ctx.fillStyle = '#495057';
            ctx.textBaseline = 'middle';
            chart.getDatasetMeta(0).data.forEach(function (barra, idx) {
                var valor = chart.data.datasets[0].data[idx];
                ctx.fillText(valor + '  (' + percentual(valor) + ')', barra.x + 6, barra.y);
            });
            ctx.restore();
        }
    };

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
        plugins: [rotulosBarras],
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
