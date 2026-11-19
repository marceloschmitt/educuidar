<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$disciplina_aula = new DisciplinaAula($db);
$periodo_horario = new PeriodoHorario($db);
$turma_model = new Turma($db);
$configuracao = new Configuracao($db);

if (!$user->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$disciplina_aula->garantirSchema();
$periodo_horario->garantirSchema();

$ano_corrente = (int) $configuracao->getAnoCorrente();
$turmas = $turma_model->getTurmasPorAnoCorrente($ano_corrente);
if (empty($turmas)) {
    $turmas = $turma_model->getAll();
}

$mapa = $periodo_horario->getAll();
$mapa_por_turno = [];
foreach ($mapa as $linha) {
    $t = strtoupper((string) ($linha['turno'] ?? ''));
    $p = (int) ($linha['periodo'] ?? 0);
    if ($t === '' || $p < 1) {
        continue;
    }
    $mapa_por_turno[$t][$p] = $linha;
}

$turma_sel = isset($_GET['turma_id']) ? (int) $_GET['turma_id'] : 0;
if ($turma_sel < 1 && !empty($turmas)) {
    $turma_sel = (int) $turmas[0]['id'];
}

$turma_ok = false;
$turma_label = '';
foreach ($turmas as $t) {
    if ((int) $t['id'] === $turma_sel) {
        $turma_ok = true;
        $turma_label = ($t['curso_nome'] ?? '') . ' — ' . (int) ($t['ano_curso'] ?? 0) . 'º Ano ('
            . (int) ($t['ano_civil'] ?? 0) . ')';
        break;
    }
}

$grade = $turma_ok
    ? $disciplina_aula->gradeSemanalTurmaEducuidar($turma_sel)
    : ['linhas' => [], 'celulas' => [], 'disciplinas' => 0, 'motivo_vazio' => 'Selecione uma turma.'];

$dias_labels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex'];
$turnos_labels = PeriodoHorario::TURNOS;

$page_title = 'Horários das turmas';
require_once 'includes/header.php';

function fmt_hora($valor) {
    $valor = trim((string) $valor);
    if (preg_match('/^(\d{2}:\d{2})/', $valor, $m)) {
        return $m[1];
    }
    return $valor;
}
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Horários das turmas</h5>
        <?php if ($user->isAdmin()): ?>
        <div class="d-flex flex-wrap gap-2">
            <a href="horarios_periodos.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock"></i> Períodos
            </a>
            <a href="api_sigaa_config.php" class="btn btn-outline-secondary btn-sm">
                Grade SIGAA
            </a>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($turmas)): ?>
        <div class="alert alert-warning mb-0">
            Nenhuma turma cadastrada. Cadastre em <a href="turmas.php">Turmas</a>.
        </div>
        <?php else: ?>
        <form method="GET" action="" class="mb-3" id="formTurmaHorario">
            <label for="turma_id" class="form-label">Turma (EduCuidar)</label>
            <select class="form-select" id="turma_id" name="turma_id" onchange="this.form.submit()">
                <?php foreach ($turmas as $t): ?>
                <option value="<?php echo (int) $t['id']; ?>"
                    <?php echo (int) $t['id'] === $turma_sel ? 'selected' : ''; ?>>
                    <?php
                    echo htmlspecialchars(
                        ($t['curso_nome'] ?? '') . ' — '
                        . (int) ($t['ano_curso'] ?? 0) . 'º Ano ('
                        . (int) ($t['ano_civil'] ?? 0) . ')'
                    );
                    ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($turma_label !== ''): ?>
        <p class="text-muted small mb-2">
            <?php echo htmlspecialchars($turma_label); ?>
            <?php if (!empty($grade['disciplinas'])): ?>
            — <?php echo (int) $grade['disciplinas']; ?> disciplina(s)
            <?php endif; ?>
            <?php if (!empty($grade['janela_inicio']) && !empty($grade['janela_fim'])): ?>
            <br>Horário atual (não mistura mudanças antigas):
            <?php echo date('d/m/Y', strtotime($grade['janela_inicio'])); ?>
            a
            <?php echo date('d/m/Y', strtotime($grade['janela_fim'])); ?>
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <?php if (empty($mapa) && $user->isAdmin()): ?>
        <div class="alert alert-info">
            Configure os
            <a href="horarios_periodos.php">horários dos períodos</a>
            para ver o relógio em cada linha.
        </div>
        <?php endif; ?>

        <?php if (empty($grade['linhas'])): ?>
        <div class="alert alert-warning mb-0">
            <?php echo htmlspecialchars($grade['motivo_vazio'] ?: 'Nenhum horário encontrado.'); ?>
        </div>
        <?php else: ?>
        <style>
            .grade-turma-table { font-size: 0.72rem; }
            .grade-turma-table th,
            .grade-turma-table td { padding: 0.25rem 0.35rem; vertical-align: top; }
            .grade-turma-table .hora-principal { font-weight: 600; font-size: 0.78rem; }
            .grade-turma-table .disc-nome { font-weight: 600; line-height: 1.2; }
            .grade-turma-table .disc-meta { color: #6c757d; font-size: 0.65rem; line-height: 1.2; }
            .grade-turma-table .disc-docente { font-size: 0.68rem; line-height: 1.2; }
        </style>
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0 grade-turma-table">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 5.5rem;">Horário</th>
                        <?php foreach ($dias_labels as $dl): ?>
                        <th class="text-center" style="min-width: 6.5rem;"><?php echo $dl; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grade['linhas'] as $linha): ?>
                    <?php
                    $turno = $linha['turno'];
                    $periodo = (int) $linha['periodo'];
                    $faixa = $mapa_por_turno[$turno][$periodo] ?? null;
                    $hora_label = $faixa
                        ? fmt_hora($faixa['hora_inicio']) . '–' . fmt_hora($faixa['hora_fim'])
                        : '';
                    $turno_nome = $turnos_labels[$turno] ?? $turno;
                    ?>
                    <tr>
                        <td>
                            <?php if ($hora_label !== ''): ?>
                            <div class="hora-principal"><?php echo htmlspecialchars($hora_label); ?></div>
                            <div class="disc-meta">
                                <?php echo htmlspecialchars($turno_nome); ?> · <?php echo $periodo; ?>º
                            </div>
                            <?php else: ?>
                            <div class="hora-principal"><?php echo htmlspecialchars($turno_nome); ?> · <?php echo $periodo; ?>º</div>
                            <?php endif; ?>
                        </td>
                        <?php for ($wd = 0; $wd < 5; $wd++): ?>
                        <?php
                        $chave = $wd . '|' . $turno . '|' . $periodo;
                        $celulas = $grade['celulas'][$chave] ?? [];
                        ?>
                        <td>
                            <?php if (empty($celulas)): ?>
                            <span class="text-muted">—</span>
                            <?php else: ?>
                                <?php foreach ($celulas as $c): ?>
                                <div class="mb-1">
                                    <div class="disc-nome"><?php echo htmlspecialchars($c['disciplina_nome']); ?></div>
                                    <div class="disc-meta"><?php echo htmlspecialchars($c['codigo_disciplina']); ?></div>
                                    <?php if (!empty($c['docentes'])): ?>
                                    <div class="disc-docente">
                                        <?php echo htmlspecialchars(implode(', ', $c['docentes'])); ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="disc-meta">Sem docente</div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
