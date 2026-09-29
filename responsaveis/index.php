<?php
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$resp = new Responsavel($db);
$evento = new Evento($db);
$configuracao = new Configuracao($db);

if (!$resp->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$responsavel_id = $resp->getLoggedId();
$alunos = $resp->getAlunosVinculados($responsavel_id);
$ano_corrente = $configuracao->getAnoCorrente();

$aluno_id = isset($_GET['aluno_id']) ? (int) $_GET['aluno_id'] : 0;
$ids_ok = array_map(function ($a) { return (int) $a['id']; }, $alunos);

if (count($alunos) === 1) {
    $aluno_id = (int) $alunos[0]['id'];
} elseif ($aluno_id && !in_array($aluno_id, $ids_ok, true)) {
    $aluno_id = 0;
}

$eventos = [];
$frequencias = [];
$frequencia_geral = null;
$frequencia_atualizada_em = null;
$aluno_sel = null;
if ($aluno_id) {
    foreach ($alunos as $a) {
        if ((int) $a['id'] === $aluno_id) {
            $aluno_sel = $a;
            break;
        }
    }
    $eventos = $evento->getParaResponsavel($aluno_id, $ano_corrente);
    $frequencias = (new FrequenciaDisciplina($db))->getPorAluno($aluno_id, $ano_corrente);
    $frequencia_geral = FrequenciaDisciplina::resumoGeral($frequencias);
    foreach ($frequencias as $f) {
        if ($frequencia_atualizada_em === null || $f['atualizado_em'] > $frequencia_atualizada_em) {
            $frequencia_atualizada_em = $f['atualizado_em'];
        }
    }
}

$page_title = 'Ocorrências — Responsável';
$show_header = true;
$responsavel_nome = $_SESSION['responsavel_nome'] ?? '';
require __DIR__ . '/header.php';

$meses = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
?>

<?php if (empty($alunos)): ?>
<div class="alert alert-warning">Nenhum aluno vinculado à sua conta.</div>
<?php elseif (!$aluno_id): ?>
<h2 class="h5 mb-3">Selecione o aluno</h2>
<?php foreach ($alunos as $a): ?>
    <?php $nome = !empty($a['nome_social']) ? $a['nome_social'] : $a['nome']; ?>
    <a class="aluno-chip" href="index.php?aluno_id=<?php echo (int) $a['id']; ?>">
        <strong><?php echo htmlspecialchars($nome); ?></strong>
        <?php if (!empty($a['parentesco'])): ?>
        <div class="small text-muted text-capitalize"><?php echo htmlspecialchars($a['parentesco']); ?></div>
        <?php endif; ?>
    </a>
<?php endforeach; ?>
<?php else: ?>
    <?php $nome = !empty($aluno_sel['nome_social']) ? $aluno_sel['nome_social'] : ($aluno_sel['nome'] ?? ''); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-0"><?php echo htmlspecialchars($nome); ?></h2>
            <div class="small text-muted"><?php echo !empty($frequencias) ? 'Frequência e ocorrências' : 'Ocorrências'; ?> de <?php echo (int) $ano_corrente; ?></div>
        </div>
        <?php if (count($alunos) > 1): ?>
        <a href="index.php" class="btn btn-outline-secondary btn-sm btn-touch">Trocar</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($frequencias)): ?>
    <h3 class="h6 mb-1">Frequência por disciplina</h3>
    <div class="small text-muted mb-2">
        Percentual de faltas em cada disciplina, segundo o SIGAA<?php echo $frequencia_atualizada_em ? ' (atualizado em ' . date('d/m/Y', strtotime($frequencia_atualizada_em)) . ')' : ''; ?>.
        A frequência mínima exigida é <?php echo 100 - FrequenciaDisciplina::LIMITE_FALTAS; ?>%.
    </div>
    <div class="card resp-card mb-4">
        <?php if ($frequencia_geral !== null): ?>
        <?php $geral_critica = $frequencia_geral['frequencia'] < 100 - FrequenciaDisciplina::LIMITE_FALTAS; ?>
        <div class="resp-freq resp-freq-geral">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
                <span class="fw-bold">Frequência geral</span>
                <span class="fs-5 fw-bold text-nowrap<?php echo $geral_critica ? ' text-danger' : ''; ?>"><?php echo number_format($frequencia_geral['frequencia'], 1, ',', '.'); ?>%</span>
            </div>
            <div class="small text-muted">
                <?php echo (int) $frequencia_geral['faltas']; ?> falta<?php echo (int) $frequencia_geral['faltas'] === 1 ? '' : 's'; ?>
                em <?php echo number_format($frequencia_geral['aulas'], 0, ',', '.'); ?> períodos, somando todas as disciplinas
            </div>
        </div>
        <?php endif; ?>
        <?php foreach ($frequencias as $f): ?>
        <?php
        $pct = (float) $f['percentual'];
        $critico = $pct > FrequenciaDisciplina::LIMITE_FALTAS;
        $frequencia = $f['percentual_frequencia'] !== null ? (float) $f['percentual_frequencia'] : 100 - $pct;
        ?>
        <div class="resp-freq">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
                <span class="fw-semibold"><?php echo htmlspecialchars(FrequenciaDisciplina::nomeLegivel($f['disciplina_nome'])); ?></span>
                <span class="fw-bold text-nowrap<?php echo $critico ? ' text-danger' : ''; ?>"><?php echo number_format($pct, 1, ',', '.'); ?>% faltas</span>
            </div>
            <div class="progress my-1" style="height: 6px;" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?php echo min(100, $pct); ?>%; background-color: <?php echo $critico ? '#dc3545' : 'var(--resp-accent)'; ?>;"></div>
            </div>
            <div class="small text-muted">
                <?php echo (int) $f['faltas']; ?> falta<?php echo (int) $f['faltas'] === 1 ? '' : 's'; ?>
                em <?php echo (int) $f['aulas']; ?> período<?php echo (int) $f['aulas'] === 1 ? '' : 's'; ?>
                · frequência <?php echo number_format($frequencia, 1, ',', '.'); ?>%
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <h3 class="h6 mb-2">Ocorrências</h3>
    <?php endif; ?>

    <div class="card resp-card">
        <?php if (empty($eventos)): ?>
        <div class="p-4 text-muted text-center">Nenhum evento para exibir.</div>
        <?php else: ?>
            <?php foreach ($eventos as $ev): ?>
            <?php
            $ts = strtotime($ev['data_evento']);
            $dia = date('d', $ts);
            $mes = $meses[(int) date('n', $ts)] ?? date('m', $ts);
            $hora = !empty($ev['hora_evento']) ? substr($ev['hora_evento'], 0, 5) : '';
            $cor = preg_replace('/[^a-z]/', '', strtolower($ev['tipo_evento_cor'] ?? 'secondary')) ?: 'secondary';
            ?>
            <div class="resp-event">
                <div class="resp-event-date">
                    <span class="day"><?php echo $dia; ?></span>
                    <span class="month"><?php echo $mes; ?></span>
                </div>
                <div class="flex-grow-1">
                    <span class="badge bg-<?php echo htmlspecialchars($cor); ?> mb-1">
                        <?php echo htmlspecialchars($ev['tipo_evento_nome'] ?? ''); ?>
                    </span>
                    <?php if ($hora !== ''): ?>
                    <div class="text-muted small"><i class="bi bi-clock"></i> <?php echo htmlspecialchars($hora); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($ev['observacoes'])): ?>
                    <div class="mt-1"><?php echo nl2br(htmlspecialchars($ev['observacoes'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
