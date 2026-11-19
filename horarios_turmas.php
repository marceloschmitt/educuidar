<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$disciplina_aula = new DisciplinaAula($db);
$periodo_horario = new PeriodoHorario($db);

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$disciplina_aula->garantirSchema();
$periodo_horario->garantirSchema();

$cursos_grade = $disciplina_aula->listarCursosGrade();
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

$curso_sel = isset($_GET['curso']) ? (string) $_GET['curso'] : '';
if ($curso_sel === '' && !empty($cursos_grade)) {
    $curso_sel = $cursos_grade[0]['curso_nome'];
}

$curso_ok = false;
foreach ($cursos_grade as $c) {
    if ($c['curso_nome'] === $curso_sel) {
        $curso_ok = true;
        break;
    }
}

$grade = $curso_ok
    ? $disciplina_aula->gradeSemanalCurso($curso_sel)
    : ['linhas' => [], 'celulas' => []];

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
        <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Horários por curso</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="horarios_periodos.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock"></i> Períodos
            </a>
            <a href="api_sigaa_config.php" class="btn btn-outline-secondary btn-sm">
                Grade SIGAA
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($cursos_grade)): ?>
        <div class="alert alert-warning mb-0">
            Nenhuma grade importada. Em
            <a href="api_sigaa_config.php">API SIGAA</a>, use
            <strong>Produzir / atualizar grade e docentes</strong>.
        </div>
        <?php else: ?>
        <form method="GET" action="" class="row g-2 align-items-end mb-3">
            <div class="col-md-8">
                <label for="curso" class="form-label">Curso</label>
                <select class="form-select" id="curso" name="curso">
                    <?php foreach ($cursos_grade as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['curso_nome']); ?>"
                        <?php echo $c['curso_nome'] === $curso_sel ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['label']); ?>
                        (<?php echo (int) $c['disciplinas']; ?> disciplina<?php echo (int) $c['disciplinas'] === 1 ? '' : 's'; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Exibir</button>
            </div>
        </form>

        <?php if (empty($mapa)): ?>
        <div class="alert alert-info">
            Configure os
            <a href="horarios_periodos.php">horários dos períodos</a>
            para ver o relógio em cada linha.
        </div>
        <?php endif; ?>

        <?php if (empty($grade['linhas'])): ?>
        <p class="text-muted mb-0">Nenhum horário encontrado para este curso (faltam turno/períodos na grade).</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 8rem;">Horário</th>
                        <?php foreach ($dias_labels as $dl): ?>
                        <th class="text-center" style="min-width: 9rem;"><?php echo $dl; ?></th>
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
                            <strong><?php echo htmlspecialchars($hora_label); ?></strong>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars($turno_nome); ?> · <?php echo $periodo; ?>º período
                            </div>
                            <?php else: ?>
                            <strong><?php echo htmlspecialchars($turno_nome); ?> · <?php echo $periodo; ?>º</strong>
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
                                    <div class="fw-semibold small">
                                        <?php echo htmlspecialchars($c['disciplina_nome']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($c['codigo_disciplina']); ?>
                                    </div>
                                    <?php if (!empty($c['docentes'])): ?>
                                    <div class="small">
                                        <i class="bi bi-person"></i>
                                        <?php echo htmlspecialchars(implode(', ', $c['docentes'])); ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="small text-muted">Sem docente</div>
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
