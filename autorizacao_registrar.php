<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$autorizacao = new AutorizacaoResponsavel($db);
$configuracao = new Configuracao($db);
$alunoModel = new Aluno($db);
$cursoModel = new Curso($db);
$turmaModel = new Turma($db);

if (!$user->isLoggedIn() || !($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2())) {
    header('Location: index.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$tipos = AutorizacaoResponsavel::tiposLabels();
$meios = AutorizacaoResponsavel::meiosLabels();

$filtro_curso = $_GET['curso_id'] ?? '';
$filtro_turma = $_GET['turma_id'] ?? '';
$filtro_nome = trim($_GET['nome'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'criar_escola') {
    $aluno_id = (int) ($_POST['aluno_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $data = trim($_POST['data_autorizacao'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $hora_registro = trim($_POST['hora_registro'] ?? '');
    $autorizado_por = trim($_POST['autorizado_por_nome'] ?? '');
    $meio = $_POST['meio_autorizacao'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $professor_ids = isset($_POST['professor_ids']) && is_array($_POST['professor_ids'])
        ? array_map('intval', $_POST['professor_ids'])
        : [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $data = '';
    }

    $alunos_validos = array_map('intval', array_column(
        $alunoModel->getAlunosTurmasAnoCorrente($ano_corrente),
        'id'
    ));

    $qs = http_build_query(array_filter([
        'curso_id' => $_POST['curso_id'] ?? '',
        'turma_id' => $_POST['turma_id'] ?? '',
        'nome' => $_POST['nome'] ?? '',
    ], static function ($v) { return $v !== '' && $v !== null; }));
    $voltar = 'autorizacao_registrar.php' . ($qs !== '' ? '?' . $qs : '');

    if (!in_array($aluno_id, $alunos_validos, true)) {
        $_SESSION['error'] = 'Selecione um aluno válido.';
    } elseif (!isset($tipos[$tipo])) {
        $_SESSION['error'] = 'Selecione o tipo de autorização.';
    } elseif ($data === '' || $hora === '' || $hora_registro === '') {
        $_SESSION['error'] = 'Informe a data, o horário do registro e o horário previsto.';
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
            $hora_registro,
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
    header('Location: ' . $voltar);
    exit;
}

$cursos = $cursoModel->getAll();
$turmas = $turmaModel->getTurmasPorAnoCorrente($ano_corrente);
if ($filtro_curso !== '') {
    $turmas = array_values(array_filter($turmas, static function ($t) use ($filtro_curso) {
        return (int) $t['curso_id'] === (int) $filtro_curso;
    }));
}

$alunos = $alunoModel->getAlunosTurmasAnoCorrente(
    $ano_corrente,
    $filtro_turma !== '' ? (int) $filtro_turma : null,
    $filtro_curso !== '' ? (int) $filtro_curso : null
);
if ($filtro_nome !== '') {
    $termo = mb_strtolower($filtro_nome, 'UTF-8');
    $alunos = array_values(array_filter($alunos, static function ($a) use ($termo) {
        $nome = mb_strtolower((string) (!empty($a['nome_social']) ? $a['nome_social'] : $a['nome']), 'UTF-8');
        $mat = mb_strtolower((string) ($a['numero_matricula'] ?? ''), 'UTF-8');
        return str_contains($nome, $termo) || ($mat !== '' && str_contains($mat, $termo));
    }));
}

$professores = $user->listProfessoresComEmail();
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$page_title = 'Registrar aviso de autorização';
require_once 'includes/header.php';
?>

<div class="mb-3">
    <a href="autorizacoes.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
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
        <h5 class="mb-0"><i class="bi bi-telephone"></i> Registrar aviso recebido</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Use quando o responsável autorizar por telefone, e-mail ou outro meio.
            Informe <strong>quem</strong> autorizou e <strong>como</strong> autorizou.
        </p>

        <form method="GET" class="row g-2 align-items-end mb-4 p-3 border rounded bg-light">
            <div class="col-12"><strong class="small">Filtrar alunos</strong></div>
            <div class="col-md-3">
                <label class="form-label" for="curso_id">Curso</label>
                <select class="form-select form-select-sm" id="curso_id" name="curso_id" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>" <?php echo (string) $filtro_curso === (string) $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="turma_id">Turma (<?php echo (int) $ano_corrente; ?>)</label>
                <select class="form-select form-select-sm" id="turma_id" name="turma_id" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?php echo (int) $t['id']; ?>" <?php echo (string) $filtro_turma === (string) $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(($t['curso_nome'] ?? '') . ' - ' . (int) $t['ano_curso'] . 'º'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="nome">Nome ou matrícula</label>
                <input type="text" class="form-control form-control-sm" id="nome" name="nome"
                       value="<?php echo htmlspecialchars($filtro_nome); ?>" placeholder="Buscar…">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
            </div>
        </form>

        <form method="POST" action="">
            <input type="hidden" name="action" value="criar_escola">
            <input type="hidden" name="curso_id" value="<?php echo htmlspecialchars((string) $filtro_curso); ?>">
            <input type="hidden" name="turma_id" value="<?php echo htmlspecialchars((string) $filtro_turma); ?>">
            <input type="hidden" name="nome" value="<?php echo htmlspecialchars($filtro_nome); ?>">

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="aluno_id">Aluno *</label>
                    <select class="form-select" id="aluno_id" name="aluno_id" required <?php echo empty($alunos) ? 'disabled' : ''; ?>>
                        <option value="">Selecione…</option>
                        <?php foreach ($alunos as $a): ?>
                        <?php
                        $nome_a = !empty($a['nome_social']) ? $a['nome_social'] : $a['nome'];
                        $label_turma = ($a['curso_nome'] ?? '') . ' - ' . (int) ($a['ano_curso'] ?? 0) . 'º';
                        $mat = $a['numero_matricula'] ?? '';
                        ?>
                        <option value="<?php echo (int) $a['id']; ?>">
                            <?php echo htmlspecialchars($nome_a . ($mat !== '' ? ' (' . $mat . ')' : '') . ' · ' . $label_turma); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?php echo count($alunos); ?> aluno(s) no filtro atual.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="tipo">Tipo *</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($tipos as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="data_autorizacao">Data *</label>
                    <input type="date" class="form-control" id="data_autorizacao" name="data_autorizacao"
                           value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="hora_registro">Horário do registro *</label>
                    <input type="time" class="form-control" id="hora_registro" name="hora_registro"
                           value="<?php echo htmlspecialchars(date('H:i')); ?>" required>
                    <div class="form-text">Quando o pedido foi recebido.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="hora">Horário previsto *</label>
                    <input type="time" class="form-control" id="hora" name="hora" required>
                    <div class="form-text">Quando o aluno deve entrar/sair.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="meio_autorizacao">Como autorizou *</label>
                    <select class="form-select" id="meio_autorizacao" name="meio_autorizacao" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($meios as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="autorizado_por_nome">Quem autorizou *</label>
                    <input type="text" class="form-control" id="autorizado_por_nome" name="autorizado_por_nome"
                           maxlength="200" required placeholder="Ex.: Maria Silva (mãe)">
                </div>
                <div class="col-md-6">
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
                <button type="submit" class="btn btn-primary" <?php echo empty($alunos) ? 'disabled' : ''; ?>>
                    <i class="bi bi-save"></i> Registrar
                </button>
                <a href="autorizacoes.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
            <?php if (empty($alunos)): ?>
            <div class="alert alert-warning mt-3 mb-0">Nenhum aluno encontrado com os filtros atuais.</div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
