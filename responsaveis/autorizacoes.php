<?php
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$resp = new Responsavel($db);
$autorizacao = new AutorizacaoResponsavel($db);

if (!$resp->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$responsavel_id = $resp->getLoggedId();
$alunos = $resp->getAlunosVinculados($responsavel_id);
$tipos = AutorizacaoResponsavel::tiposLabels();
$status_labels = AutorizacaoResponsavel::statusLabels();

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'criar';

    if ($action === 'remover' && !empty($_POST['id'])) {
        if ($autorizacao->remover((int) $_POST['id'], $responsavel_id)) {
            $_SESSION['flash_success'] = 'Autorização removida.';
        } else {
            $_SESSION['flash_error'] = 'Não foi possível remover. Só é permitido antes da data prevista e se ainda não ocorreu.';
        }
    } else {
        $aluno_id = (int) ($_POST['aluno_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $data = $_POST['data_autorizacao'] ?? '';
        $hora = $_POST['hora'] ?? '';
        $justificativa = trim($_POST['justificativa'] ?? '');

        if (!$resp->jaVinculado($responsavel_id, $aluno_id)) {
            $_SESSION['flash_error'] = 'Aluno inválido.';
        } elseif (!isset($tipos[$tipo])) {
            $_SESSION['flash_error'] = 'Selecione o tipo de autorização.';
        } elseif ($data === '' || $hora === '') {
            $_SESSION['flash_error'] = 'Informe data e horário.';
        } elseif ($justificativa === '') {
            $_SESSION['flash_error'] = 'Informe a justificativa.';
        } elseif ($autorizacao->create($responsavel_id, $aluno_id, $tipo, $data, $hora, $justificativa)) {
            $_SESSION['flash_success'] = 'Autorização registrada. A escola marcará quando a entrada/saída ocorrer.';
        } else {
            $_SESSION['flash_error'] = 'Erro ao salvar a autorização.';
        }
    }

    header('Location: autorizacoes.php');
    exit;
}

$lista = $autorizacao->listByResponsavel($responsavel_id);

$page_title = 'Autorizações';
$show_header = true;
$responsavel_nome = $_SESSION['responsavel_nome'] ?? '';
require __DIR__ . '/header.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Eventos</a>
    <span class="text-muted small">Autorizações</span>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (empty($alunos)): ?>
<div class="alert alert-warning">Nenhum aluno vinculado à sua conta.</div>
<?php else: ?>

<div class="card resp-card mb-3">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Nova autorização</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="criar">
            <div class="mb-3">
                <label class="form-label" for="aluno_id">Aluno</label>
                <select class="form-select form-select-lg" id="aluno_id" name="aluno_id" required>
                    <?php foreach ($alunos as $a): ?>
                    <?php $nome = !empty($a['nome_social']) ? $a['nome_social'] : $a['nome']; ?>
                    <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars($nome); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="tipo">Tipo</label>
                <select class="form-select form-select-lg" id="tipo" name="tipo" required>
                    <option value="">Selecione…</option>
                    <?php foreach ($tipos as $k => $label): ?>
                    <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="data_autorizacao">Data</label>
                    <input type="date" class="form-control form-control-lg" id="data_autorizacao" name="data_autorizacao"
                           required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label" for="hora">Horário</label>
                    <input type="time" class="form-control form-control-lg" id="hora" name="hora" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="justificativa">Justificativa</label>
                <textarea class="form-control" id="justificativa" name="justificativa" rows="3" required
                          placeholder="Motivo da entrada ou saída fora do horário"></textarea>
            </div>
            <button type="submit" class="btn btn-success w-100 btn-lg btn-touch">Enviar autorização</button>
        </form>
    </div>
</div>

<div class="card resp-card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <h2 class="h5 mb-0">Minhas autorizações</h2>
        </div>
        <?php if (empty($lista)): ?>
        <p class="text-muted text-center p-4 mb-0">Nenhuma autorização ainda.</p>
        <?php else: ?>
            <?php foreach ($lista as $item): ?>
            <?php
            $nome_aluno = !empty($item['aluno_nome_social']) ? $item['aluno_nome_social'] : $item['aluno_nome'];
            $tipo_label = $tipos[$item['tipo']] ?? $item['tipo'];
            $st = $item['status'] ?? 'previsto';
            $badge = $st === 'ocorrido' ? 'success' : 'warning';
            ?>
            <div class="p-3 border-bottom">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <strong><?php echo htmlspecialchars($tipo_label); ?></strong>
                    <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($status_labels[$st] ?? $st); ?></span>
                </div>
                <div class="small"><?php echo htmlspecialchars($nome_aluno); ?></div>
                <div class="small text-muted">
                    <?php echo date('d/m/y', strtotime($item['data_autorizacao'])); ?>
                    às <?php echo substr($item['hora'], 0, 5); ?>
                </div>
                <div class="mt-1"><?php echo nl2br(htmlspecialchars($item['justificativa'])); ?></div>
                <?php if (AutorizacaoResponsavel::podeRemover($item)): ?>
                <form method="POST" class="mt-2">
                    <input type="hidden" name="action" value="remover">
                    <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('Remover esta autorização?');">Remover</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
