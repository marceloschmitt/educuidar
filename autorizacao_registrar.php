<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$autorizacao = new AutorizacaoResponsavel($db);
$configuracao = new Configuracao($db);
$alunoModel = new Aluno($db);
$turmaModel = new Turma($db);

if (!$user->isLoggedIn() || !($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2())) {
    header('Location: index.php');
    exit;
}

$aluno_id = (int) ($_GET['aluno_id'] ?? $_POST['aluno_id'] ?? 0);
if ($aluno_id <= 0) {
    $_SESSION['error'] = 'Selecione um aluno na lista de alunos para registrar a autorização.';
    header('Location: alunos.php');
    exit;
}

$aluno = $alunoModel->getById($aluno_id);
if (!$aluno || !empty($aluno['desistente'])) {
    $_SESSION['error'] = 'Aluno não encontrado.';
    header('Location: alunos.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$turmas_aluno = $alunoModel->getTurmasAluno($aluno_id) ?? [];
$turma_corrente = null;
foreach ($turmas_aluno as $t) {
    if ((int) ($t['ano_civil'] ?? 0) === (int) $ano_corrente) {
        $turma_corrente = $t;
        break;
    }
}
if (!$turma_corrente && !empty($turmas_aluno)) {
    $turma_corrente = $turmas_aluno[0];
}
if ($turma_corrente) {
    $turma_completa = $turmaModel->getById((int) $turma_corrente['id']);
    if ($turma_completa) {
        $turma_corrente = array_merge($turma_corrente, $turma_completa);
    }
}

$tipos = AutorizacaoResponsavel::tiposLabels();
$meios = AutorizacaoResponsavel::meiosLabels();
$nome_aluno = !empty($aluno['nome_social']) ? $aluno['nome_social'] : $aluno['nome'];

// Preservar filtros da lista de alunos, se vierem na URL
$voltar_params = [];
foreach (['filtro_curso', 'filtro_turma', 'filtro_ano', 'filtro_nome', 'filtro_status'] as $p) {
    if (isset($_GET[$p]) && $_GET[$p] !== '') {
        $voltar_params[$p] = $_GET[$p];
    }
}
$voltar_qs = $voltar_params ? '?' . http_build_query($voltar_params) : '';
$voltar_alunos = 'alunos.php' . $voltar_qs;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'criar_escola') {
    $tipo = $_POST['tipo'] ?? '';
    $data = trim($_POST['data_autorizacao'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $autorizado_por = trim($_POST['autorizado_por_nome'] ?? '');
    $meio = $_POST['meio_autorizacao'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $professor_ids = isset($_POST['professor_ids']) && is_array($_POST['professor_ids'])
        ? array_map('intval', $_POST['professor_ids'])
        : [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $data = '';
    }

    $redirect = 'autorizacao_registrar.php?aluno_id=' . $aluno_id
        . ($voltar_params ? '&' . http_build_query($voltar_params) : '');

    if (!isset($tipos[$tipo])) {
        $_SESSION['error'] = 'Selecione o tipo de autorização.';
    } elseif ($data === '' || $hora === '') {
        $_SESSION['error'] = 'Informe a data e o horário em que o aluno poderá sair/entrar.';
    } elseif ($autorizado_por === '') {
        $_SESSION['error'] = 'Informe quem autorizou.';
    } elseif (!isset($meios[$meio])) {
        $_SESSION['error'] = 'Informe como a autorização foi dada.';
    } else {
        $novo_id = $autorizacao->createByEscola(
            $aluno_id,
            $tipo,
            $data,
            $hora,
            $autorizado_por,
            $meio,
            $observacoes,
            $_SESSION['user_id']
        );
        if ($novo_id) {
            $msg = 'Autorização registrada.';
            if (!empty($professor_ids)) {
                $stats = $autorizacao->enviarEmailProfessores($novo_id, $professor_ids);
                if ($stats['enviados'] > 0 && $stats['falhas'] === 0) {
                    $msg .= ' E-mail enviado a ' . (int) $stats['enviados'] . ' professor(es).';
                } elseif ($stats['enviados'] > 0) {
                    $msg .= ' E-mail enviado a ' . (int) $stats['enviados']
                        . ' professor(es); ' . (int) $stats['falhas'] . ' falha(s).';
                    if (!empty($stats['mensagens'])) {
                        $_SESSION['error'] = implode(' ', array_slice($stats['mensagens'], -3));
                    }
                } else {
                    $_SESSION['error'] = !empty($stats['mensagens'])
                        ? implode(' ', $stats['mensagens'])
                        : 'Autorização salva, mas nenhum e-mail foi enviado aos professores.';
                }
            }
            $_SESSION['success'] = $msg;
            header('Location: autorizacoes.php');
            exit;
        }
        $_SESSION['error'] = 'Não foi possível registrar a autorização.';
    }
    header('Location: ' . $redirect);
    exit;
}

$professores = $user->listProfessoresComEmail();
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$page_title = 'Registrar autorização — ' . $nome_aluno;
require_once 'includes/header.php';
?>

<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($voltar_alunos); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Alunos
    </a>
    <a href="autorizacoes.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-list-ul"></i> Lista de autorizações
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-telephone"></i> Registrar aviso — <?php echo htmlspecialchars($nome_aluno); ?></h5>
    </div>
    <div class="card-body">
        <div class="mb-3 small text-muted">
            <?php if (!empty($aluno['numero_matricula'])): ?>
            Matrícula: <?php echo htmlspecialchars($aluno['numero_matricula']); ?>
            <?php endif; ?>
            <?php if ($turma_corrente): ?>
            · <?php echo htmlspecialchars(($turma_corrente['curso_nome'] ?? '') . ' - ' . (int) ($turma_corrente['ano_curso'] ?? 0) . 'º'); ?>
            <?php endif; ?>
        </div>
        <p class="text-muted small">
            Use quando o responsável autorizar por telefone, e-mail ou outro meio.
            Informe <strong>quem</strong> autorizou e <strong>como</strong> autorizou.
        </p>

        <form method="POST" action="">
            <input type="hidden" name="action" value="criar_escola">
            <input type="hidden" name="aluno_id" value="<?php echo (int) $aluno_id; ?>">
            <?php foreach ($voltar_params as $k => $v): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars((string) $v); ?>">
            <?php endforeach; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="tipo">Tipo *</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($tipos as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="data_autorizacao">Data da saída/entrada *</label>
                    <input type="date" class="form-control" id="data_autorizacao" name="data_autorizacao"
                           value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="hora">Horário da saída/entrada *</label>
                    <input type="time" class="form-control" id="hora" name="hora" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="meio_autorizacao">Como autorizou *</label>
                    <select class="form-select" id="meio_autorizacao" name="meio_autorizacao" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($meios as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="autorizado_por_nome">Quem autorizou *</label>
                    <input type="text" class="form-control" id="autorizado_por_nome" name="autorizado_por_nome"
                           maxlength="200" required placeholder="Ex.: Maria Silva (mãe)">
                </div>
                <div class="col-12">
                    <label class="form-label" for="observacoes">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="2"
                              placeholder="Detalhes adicionais, se necessário"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Professores que receberão e-mail</label>
                    <?php if (empty($professores)): ?>
                    <div class="alert alert-warning mb-0 py-2 small">
                        Nenhum professor com e-mail cadastrado.
                    </div>
                    <?php else: ?>
                    <div class="border rounded p-3" style="max-height: 220px; overflow-y: auto;">
                        <?php foreach ($professores as $p): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="professor_ids[]"
                                   value="<?php echo (int) $p['id']; ?>"
                                   id="prof_<?php echo (int) $p['id']; ?>">
                            <label class="form-check-label" for="prof_<?php echo (int) $p['id']; ?>">
                                <?php echo htmlspecialchars($p['full_name']); ?>
                                <span class="text-muted small"><?php echo htmlspecialchars($p['email']); ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">
                        Opcional. Se marcar alguém, o e-mail é enviado imediatamente ao salvar.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Registrar
                </button>
                <a href="<?php echo htmlspecialchars($voltar_alunos); ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
