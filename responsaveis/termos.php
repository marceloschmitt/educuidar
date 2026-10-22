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
$responsavel_nome = $_SESSION['responsavel_nome'] ?? '';
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
            $_SESSION['flash_error'] = 'Você já respondeu. Para alterar, a escola precisa liberar uma nova resposta.';
        } elseif ($resultado === 'aluno_fora') {
            $_SESSION['flash_error'] = 'Este aluno não participa desta autorização.';
        } elseif ($resultado === 'suspenso') {
            $_SESSION['flash_error'] = 'Esta autorização está suspensa. Não é possível responder no momento.';
        } else {
            $_SESSION['flash_error'] = 'Não foi possível registrar a resposta.';
        }
    }
    $redir = 'termos.php';
    if ($termo_id && $aluno_id) {
        $redir .= '?id=' . $termo_id . '&aluno_id=' . $aluno_id;
    }
    header('Location: ' . $redir);
    exit;
}

$itens = $termoModel->listItensParaResponsavel($responsavel_id);
$termo_id = (int) ($_GET['id'] ?? 0);
$aluno_id = (int) ($_GET['aluno_id'] ?? 0);
$termo = null;
$aluno = null;
$termo_ativo = true;

if ($termo_id && $aluno_id) {
    $termo = $termoModel->getById($termo_id);
    if (!$termo || !TermoAutorizacao::estaVisivelPais($termo) || !$termoModel->responsavelPodeVerTermo($termo_id, $responsavel_id)) {
        $_SESSION['flash_error'] = 'Autorização não encontrada.';
        header('Location: termos.php');
        exit;
    }
    if (!$resp->jaVinculado($responsavel_id, $aluno_id) || !$termoModel->alunoPertenceAoTermo($termo_id, $aluno_id)) {
        $_SESSION['flash_error'] = 'Aluno inválido para esta autorização.';
        header('Location: termos.php');
        exit;
    }
    $termo_ativo = TermoAutorizacao::estaAtivo($termo);
    $alunos_termo = $termoModel->listAlunosDoResponsavelNoTermo($termo_id, $responsavel_id);
    foreach ($alunos_termo as $a) {
        if ((int) $a['id'] === $aluno_id) {
            $aluno = $a;
            break;
        }
    }
    if (!$aluno) {
        $_SESSION['flash_error'] = 'Aluno inválido para esta autorização.';
        header('Location: termos.php');
        exit;
    }
} elseif ($termo_id && !$aluno_id) {
    // Fluxo antigo: redireciona para o primeiro aluno pendente, se houver
    $alunos_termo = $termoModel->listAlunosDoResponsavelNoTermo($termo_id, $responsavel_id);
    if (!empty($alunos_termo)) {
        header('Location: termos.php?id=' . $termo_id . '&aluno_id=' . (int) $alunos_termo[0]['id']);
        exit;
    }
    header('Location: termos.php');
    exit;
}

$page_title = 'Participação em atividades';
$show_header = true;
$meses = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
require __DIR__ . '/header.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <?php if ($termo && $aluno): ?>
    <a href="termos.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Participação em atividades</a>
    <?php else: ?>
    <a href="autorizacoes_menu.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Autorizações</a>
    <?php endif; ?>
    <span class="text-muted small">Participação em atividades</span>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($termo && $aluno): ?>

<?php
$r = $aluno['resposta'] ?? null;
$pode_responder = $termo_ativo && (!$r || (int) $r['pode_alterar']);
$texto_personalizado = TermoAutorizacao::temTexto($termo)
    ? TermoAutorizacao::renderTextoPersonalizado(
        $termo['texto_autorizacao'],
        $aluno['nome'],
        $responsavel_nome,
        $aluno['turma_label']
    )
    : '';
?>

<div class="card resp-card mb-3">
    <div class="card-body p-4">
        <h2 class="h5 mb-2"><?php echo htmlspecialchars($termo['titulo']); ?></h2>
        <div class="text-muted small mb-2">
            <?php echo date('d/m/Y', strtotime($termo['data_evento'])); ?>
            <?php if (!empty($termo['hora'])): ?>
            · <?php echo substr($termo['hora'], 0, 5); ?>
            <?php endif; ?>
            <?php if (!$termo_ativo): ?>
            · <span class="badge bg-secondary">Suspensa</span>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <div class="fw-semibold"><?php echo htmlspecialchars($aluno['nome']); ?></div>
            <div class="small text-muted"><?php echo htmlspecialchars($aluno['turma_label']); ?></div>
        </div>
        <?php if (TermoAutorizacao::temTexto($termo)): ?>
        <div class="border rounded p-3 bg-light mb-0">
            <div class="small text-muted mb-2">Texto da autorização</div>
            <div><?php echo nl2br(htmlspecialchars($texto_personalizado)); ?></div>
        </div>
        <?php elseif (TermoAutorizacao::temPdf($termo)): ?>
        <a class="btn btn-outline-success btn-touch" href="../termo_autorizacao_pdf.php?id=<?php echo (int) $termo['id']; ?>" target="_blank">
            <i class="bi bi-file-pdf"></i> Abrir autorização (PDF)
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$termo_ativo): ?>
<div class="alert alert-warning">Esta autorização está suspensa. Não é possível responder no momento.</div>
<?php endif; ?>

<div class="card resp-card mb-3">
    <div class="card-body p-4">
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
        <?php elseif ($pode_responder): ?>
        <?php if ($r && (int) $r['pode_alterar']): ?>
        <div class="alert alert-info py-2 small">
            A escola liberou uma nova resposta. Sua resposta anterior:
            <strong><?php echo htmlspecialchars($labels[$r['decisao']] ?? $r['decisao']); ?></strong>
            (<?php echo date('d/m/Y H:i', strtotime($r['decidido_em'])); ?>).
        </div>
        <?php endif; ?>
        <p class="mb-3">Você autoriza a participação de <strong><?php echo htmlspecialchars($aluno['nome']); ?></strong> neste evento?</p>
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
        <?php elseif (!$r && !empty($aluno['aluno_tem_resposta'])): ?>
        <div class="small text-muted">Outro responsável já registrou uma resposta para este aluno.</div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<?php if (empty($itens)): ?>
<div class="alert alert-info">Não há autorizações de participação em atividades para os seus alunos no momento.</div>
<?php else: ?>
<div class="card resp-card">
    <?php foreach ($itens as $item): ?>
    <?php
    $t = $item['termo'];
    $a = $item['aluno'];
    $rr = $a['resposta'] ?? null;
    $eu_ok = $rr && !(int) ($rr['pode_alterar'] ?? 0);
    $pendente = !$eu_ok && empty($a['aluno_tem_resposta']);
    $aguardando = !$eu_ok && !empty($a['aluno_tem_resposta']);
    ?>
    <a href="termos.php?id=<?php echo (int) $t['id']; ?>&aluno_id=<?php echo (int) $a['id']; ?>"
       class="resp-event text-decoration-none text-dark">
        <div class="resp-event-date">
            <span class="day"><?php echo date('d', strtotime($t['data_evento'])); ?></span>
            <span class="month"><?php echo $meses[(int) date('n', strtotime($t['data_evento']))] ?? date('m', strtotime($t['data_evento'])); ?></span>
        </div>
        <div class="flex-grow-1">
            <div class="fw-semibold"><?php echo htmlspecialchars($t['titulo']); ?></div>
            <div class="small"><?php echo htmlspecialchars($a['nome']); ?></div>
            <div class="small text-muted">
                <?php echo htmlspecialchars($a['turma_label']); ?>
                <?php if (!empty($t['status']) && $t['status'] === TermoAutorizacao::STATUS_SUSPENSO): ?>
                · <span class="text-secondary">Suspensa</span>
                <?php elseif ($pendente): ?>
                · <span class="text-warning">Pendente</span>
                <?php elseif ($eu_ok): ?>
                · <span class="text-success">Respondido</span>
                <?php elseif ($aguardando): ?>
                · <span class="text-muted">Aguardando sua resposta</span>
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
