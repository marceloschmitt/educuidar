<?php
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$resp = new Responsavel($db);
$termoModel = new TermoAutorizacao($db);

if (!$resp->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$responsavel_id = $resp->getLoggedId();
$labels = TermoAutorizacao::decisoesLabels();

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decidir') {
    $termo_id = (int) ($_POST['termo_id'] ?? 0);
    $aluno_id = (int) ($_POST['aluno_id'] ?? 0);
    $decisao = $_POST['decisao'] ?? '';

    if (!$resp->jaVinculado($responsavel_id, $aluno_id)) {
        $_SESSION['flash_error'] = 'Aluno inválido.';
    } else {
        $resultado = $termoModel->registrarDecisao($termo_id, $responsavel_id, $aluno_id, $decisao);
        if ($resultado === true) {
            $_SESSION['flash_success'] = 'Sua resposta foi registrada.';
        } elseif ($resultado === 'ja_respondeu') {
            $_SESSION['flash_error'] = 'Você já respondeu este termo. Para alterar, a escola precisa liberar uma nova resposta.';
        } elseif ($resultado === 'aluno_fora') {
            $_SESSION['flash_error'] = 'Este aluno não participa deste termo.';
        } else {
            $_SESSION['flash_error'] = 'Não foi possível registrar a resposta.';
        }
    }
    header('Location: termos.php' . ($termo_id ? '?id=' . $termo_id : ''));
    exit;
}

$termos = $termoModel->listParaResponsavel($responsavel_id);
$termo_id = (int) ($_GET['id'] ?? 0);
$termo = null;
$alunos_termo = [];

if ($termo_id) {
    $termo = $termoModel->getById($termo_id);
    if (!$termo || !$termoModel->responsavelPodeVerTermo($termo_id, $responsavel_id)) {
        $_SESSION['flash_error'] = 'Termo não encontrado.';
        header('Location: termos.php');
        exit;
    }
    $alunos_termo = $termoModel->listAlunosDoResponsavelNoTermo($termo_id, $responsavel_id);
}

$page_title = 'Termos';
$show_header = true;
$responsavel_nome = $_SESSION['responsavel_nome'] ?? '';
$meses = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
require __DIR__ . '/header.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <?php if ($termo): ?>
    <a href="termos.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Termos</a>
    <?php else: ?>
    <a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Ocorrências</a>
    <?php endif; ?>
    <span class="text-muted small">Termos de autorização</span>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($termo): ?>

<div class="card resp-card mb-3">
    <div class="card-body p-4">
        <h2 class="h5 mb-2"><?php echo htmlspecialchars($termo['titulo']); ?></h2>
        <div class="text-muted small mb-2">
            <?php echo date('d/m/Y', strtotime($termo['data_evento'])); ?>
            <?php if (!empty($termo['hora'])): ?>
            · <?php echo substr($termo['hora'], 0, 5); ?>
            <?php endif; ?>
        </div>
        <p class="mb-3"><?php echo nl2br(htmlspecialchars($termo['objetivo'])); ?></p>
        <a class="btn btn-outline-success btn-touch" href="../termo_autorizacao_pdf.php?id=<?php echo (int) $termo['id']; ?>" target="_blank">
            <i class="bi bi-file-pdf"></i> Abrir termo (PDF)
        </a>
    </div>
</div>

<?php if (empty($alunos_termo)): ?>
<div class="alert alert-warning">Nenhum filho seu está nas turmas deste termo.</div>
<?php else: ?>
<?php foreach ($alunos_termo as $aluno): ?>
<?php
$r = $aluno['resposta'] ?? null;
?>
<div class="card resp-card mb-3">
    <div class="card-body p-4">
        <h3 class="h6 mb-1"><?php echo htmlspecialchars($aluno['nome']); ?></h3>
        <div class="small text-muted mb-3"><?php echo htmlspecialchars($aluno['turma_label']); ?></div>

        <?php if ($r && !(int) $r['pode_alterar']): ?>
        <div class="mb-0">
            <span class="badge bg-<?php echo TermoAutorizacao::decisaoBadgeClass($r['decisao']); ?> mb-1">
                <?php echo htmlspecialchars($labels[$r['decisao']] ?? $r['decisao']); ?>
            </span>
            <div class="small text-muted">
                Registrado em <?php echo date('d/m/Y H:i', strtotime($r['decidido_em'])); ?>
            </div>
            <div class="small text-muted mt-2">Para alterar, peça à escola que libere uma nova resposta.</div>
        </div>
        <?php else: ?>
        <?php if ($r && (int) $r['pode_alterar']): ?>
        <div class="alert alert-info py-2 small">
            A escola liberou uma nova resposta. Sua resposta anterior:
            <strong><?php echo htmlspecialchars($labels[$r['decisao']] ?? $r['decisao']); ?></strong>
            (<?php echo date('d/m/Y H:i', strtotime($r['decidido_em'])); ?>).
        </div>
        <?php endif; ?>
        <p class="mb-3">Você autoriza a participação neste evento?</p>
        <div class="d-grid gap-2">
            <form method="POST" action="">
                <input type="hidden" name="action" value="decidir">
                <input type="hidden" name="termo_id" value="<?php echo (int) $termo['id']; ?>">
                <input type="hidden" name="aluno_id" value="<?php echo (int) $aluno['id']; ?>">
                <input type="hidden" name="decisao" value="concordou">
                <button type="submit" class="btn btn-success w-100 btn-touch"
                        onclick="return confirm('Confirmar que CONCORDA com a participação? Esta resposta não poderá ser alterada sem liberação da escola.');">
                    <i class="bi bi-check-lg"></i> Concordo
                </button>
            </form>
            <form method="POST" action="">
                <input type="hidden" name="action" value="decidir">
                <input type="hidden" name="termo_id" value="<?php echo (int) $termo['id']; ?>">
                <input type="hidden" name="aluno_id" value="<?php echo (int) $aluno['id']; ?>">
                <input type="hidden" name="decisao" value="discordou">
                <button type="submit" class="btn btn-outline-danger w-100 btn-touch"
                        onclick="return confirm('Confirmar que NÃO CONCORDA com a participação? Esta resposta não poderá ser alterada sem liberação da escola.');">
                    <i class="bi bi-x-lg"></i> Não concordo
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>

<?php if (empty($termos)): ?>
<div class="alert alert-info">Não há termos de autorização para os seus alunos no momento.</div>
<?php else: ?>
<div class="card resp-card">
    <?php foreach ($termos as $t): ?>
    <?php
    $alunos_t = $termoModel->listAlunosDoResponsavelNoTermo((int) $t['id'], $responsavel_id);
    $pendentes = 0;
    foreach ($alunos_t as $a) {
        $rr = $a['resposta'] ?? null;
        if (!$rr || (int) ($rr['pode_alterar'] ?? 0)) {
            $pendentes++;
        }
    }
    ?>
    <a href="termos.php?id=<?php echo (int) $t['id']; ?>" class="resp-event text-decoration-none text-dark">
        <div class="resp-event-date">
            <span class="day"><?php echo date('d', strtotime($t['data_evento'])); ?></span>
            <span class="month"><?php echo $meses[(int) date('n', strtotime($t['data_evento']))] ?? date('m', strtotime($t['data_evento'])); ?></span>
        </div>
        <div class="flex-grow-1">
            <div class="fw-semibold"><?php echo htmlspecialchars($t['titulo']); ?></div>
            <div class="small text-muted">
                <?php echo count($alunos_t); ?> aluno(s)
                <?php if ($pendentes > 0): ?>
                · <span class="text-warning"><?php echo $pendentes; ?> resposta(s) pendente(s)</span>
                <?php else: ?>
                · <span class="text-success">Respondido</span>
                <?php endif; ?>
            </div>
        </div>
        <i class="bi bi-chevron-right text-muted"></i>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
