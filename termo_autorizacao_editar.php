<?php
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$configuracao = new Configuracao($db);
$turmaModel = new Turma($db);
$termoModel = new TermoAutorizacao($db);

if (!$user->isLoggedIn() || !($user->isAdmin() || $user->isNivel0() || $user->isNivel1())) {
    header('Location: index.php');
    exit;
}

$termo_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$termo = $termo_id ? $termoModel->getById($termo_id) : null;
if (!$termo) {
    $_SESSION['error'] = 'Autorização não encontrada.';
    header('Location: termos_autorizacao.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$turmas = $turmaModel->getTurmasPorAnoCorrente($ano_corrente);
$turma_ids_atuais = array_map('intval', array_column($termo['turmas'] ?? [], 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'atualizar') {
    $titulo = trim($_POST['titulo'] ?? '');
    $objetivo = trim($_POST['objetivo'] ?? '');
    $data_raw = trim($_POST['data_evento'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $tipo_conteudo = $_POST['tipo_conteudo'] ?? 'manter';
    $texto_autorizacao = trim($_POST['texto_autorizacao'] ?? '');
    $turma_ids = isset($_POST['turma_ids']) && is_array($_POST['turma_ids']) ? $_POST['turma_ids'] : [];

    $data = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_raw)) {
        $data = $data_raw;
    }

    $turmas_validas = array_map('intval', array_column($turmas, 'id'));
    $turma_ids = array_values(array_intersect(array_map('intval', $turma_ids), $turmas_validas));

    $arquivo = null;
    $texto = null;
    $trocar = false;
    if ($tipo_conteudo === 'pdf') {
        $arquivo = $_FILES['pdf'] ?? null;
        $trocar = true;
    } elseif ($tipo_conteudo === 'texto') {
        $texto = $texto_autorizacao;
        $trocar = true;
    } elseif (TermoAutorizacao::temTexto($termo)) {
        // Manter texto, mas permitir edição do texto atual
        $texto = $texto_autorizacao !== '' ? $texto_autorizacao : ($termo['texto_autorizacao'] ?? '');
    }

    if ($titulo === '' || $objetivo === '' || $data === null) {
        $_SESSION['error'] = 'Preencha título, objetivo e data.';
    } elseif (empty($turma_ids)) {
        $_SESSION['error'] = 'Selecione ao menos uma turma.';
    } elseif ($tipo_conteudo === 'pdf' && (empty($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
        $_SESSION['error'] = 'Envie o novo PDF da autorização.';
    } elseif ($tipo_conteudo === 'texto' && trim((string) $texto) === '') {
        $_SESSION['error'] = 'Digite o texto da autorização.';
    } elseif ($termoModel->atualizar(
        $termo_id,
        $titulo,
        $objetivo,
        $data,
        $hora !== '' ? $hora : null,
        $turma_ids,
        $arquivo,
        $texto,
        $trocar
    )) {
        $_SESSION['success'] = 'Autorização atualizada.';
        header('Location: termo_autorizacao_ver.php?id=' . $termo_id);
        exit;
    } else {
        $_SESSION['error'] = 'Não foi possível atualizar. Verifique os dados.';
    }
    header('Location: termo_autorizacao_editar.php?id=' . $termo_id);
    exit;
}

$eh_pdf = TermoAutorizacao::temPdf($termo);
$eh_texto = TermoAutorizacao::temTexto($termo);

$page_title = 'Editar autorização';
require __DIR__ . '/includes/header.php';
?>

<?php if (!empty($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="mb-3">
    <a href="termo_autorizacao_ver.php?id=<?php echo (int) $termo_id; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-pencil"></i> Editar autorização</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" id="form-editar-autorizacao">
            <input type="hidden" name="action" value="atualizar">
            <input type="hidden" name="id" value="<?php echo (int) $termo_id; ?>">
            <div class="mb-3">
                <label class="form-label" for="titulo">Título *</label>
                <input type="text" class="form-control" id="titulo" name="titulo" maxlength="200" required
                       value="<?php echo htmlspecialchars($termo['titulo']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="objetivo">Objetivo *</label>
                <textarea class="form-control" id="objetivo" name="objetivo" rows="3" required><?php echo htmlspecialchars($termo['objetivo']); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="data_evento">Data *</label>
                    <input type="date" class="form-control" id="data_evento" name="data_evento" required
                           value="<?php echo htmlspecialchars($termo['data_evento']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="hora">Horário</label>
                    <input type="time" class="form-control" id="hora" name="hora"
                           value="<?php echo !empty($termo['hora']) ? htmlspecialchars(substr($termo['hora'], 0, 5)) : ''; ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label d-block">Texto da autorização *</label>
                <div class="btn-group mb-2" role="group" aria-label="Tipo do conteúdo">
                    <input type="radio" class="btn-check" name="tipo_conteudo" id="tipo_manter" value="manter" checked
                           autocomplete="off">
                    <label class="btn btn-outline-secondary" for="tipo_manter">Manter atual</label>
                    <input type="radio" class="btn-check" name="tipo_conteudo" id="tipo_texto" value="texto"
                           autocomplete="off">
                    <label class="btn btn-outline-primary" for="tipo_texto">Texto digitado</label>
                    <input type="radio" class="btn-check" name="tipo_conteudo" id="tipo_pdf" value="pdf"
                           autocomplete="off">
                    <label class="btn btn-outline-primary" for="tipo_pdf">Arquivo PDF</label>
                </div>
                <div id="bloco-manter" class="border rounded p-3 bg-light">
                    <?php if ($eh_texto): ?>
                    <div class="small text-muted mb-1">Texto atual (edite abaixo ou troque o tipo):</div>
                    <textarea class="form-control" id="texto_manter" name="texto_autorizacao" rows="6"><?php echo htmlspecialchars($termo['texto_autorizacao']); ?></textarea>
                    <div class="form-text mt-1">
                        Variáveis:
                        <code>{{nome_aluno}}</code>,
                        <code>{{nome_responsavel}}</code>,
                        <code>{{turma_aluno}}</code>.
                    </div>
                    <?php elseif ($eh_pdf): ?>
                    <div class="small">
                        PDF atual:
                        <a href="termo_autorizacao_pdf.php?id=<?php echo (int) $termo_id; ?>" target="_blank">
                            <?php echo htmlspecialchars($termo['pdf_nome_original'] ?: 'Abrir PDF'); ?>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="text-warning small">Sem conteúdo. Escolha texto ou PDF.</div>
                    <?php endif; ?>
                </div>
                <div id="bloco-texto" class="d-none">
                    <textarea class="form-control" id="texto_autorizacao" rows="8"
                              placeholder="Digite aqui o texto completo da autorização."><?php echo $eh_texto ? htmlspecialchars($termo['texto_autorizacao']) : ''; ?></textarea>
                    <div class="form-text">
                        Variáveis:
                        <code>{{nome_aluno}}</code>,
                        <code>{{nome_responsavel}}</code>,
                        <code>{{turma_aluno}}</code>.
                    </div>
                </div>
                <div id="bloco-pdf" class="d-none">
                    <input type="file" class="form-control" id="pdf" name="pdf" accept="application/pdf,.pdf">
                    <div class="form-text">Até 10 MB. Substitui o conteúdo atual.</div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Turmas *</label>
                <?php if (empty($turmas)): ?>
                <div class="alert alert-warning mb-0">Nenhuma turma no ano corrente.</div>
                <?php else: ?>
                <div class="border rounded p-3" style="max-height: 220px; overflow-y: auto;">
                    <?php foreach ($turmas as $t): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="turma_ids[]"
                               value="<?php echo (int) $t['id']; ?>" id="turma_<?php echo (int) $t['id']; ?>"
                               <?php echo in_array((int) $t['id'], $turma_ids_atuais, true) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="turma_<?php echo (int) $t['id']; ?>">
                            <?php echo htmlspecialchars(($t['curso_nome'] ?? '') . ' - ' . (int) $t['ano_curso'] . 'º Ano'); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary" <?php echo empty($turmas) ? 'disabled' : ''; ?>>
                <i class="bi bi-save"></i> Salvar alterações
            </button>
        </form>
        <script>
        (function () {
            var tipoManter = document.getElementById('tipo_manter');
            var tipoTexto = document.getElementById('tipo_texto');
            var tipoPdf = document.getElementById('tipo_pdf');
            var blocoManter = document.getElementById('bloco-manter');
            var blocoTexto = document.getElementById('bloco-texto');
            var blocoPdf = document.getElementById('bloco-pdf');
            var textoManter = document.getElementById('texto_manter');
            var campoTexto = document.getElementById('texto_autorizacao');
            var campoPdf = document.getElementById('pdf');
            function atualizar() {
                var manter = tipoManter.checked;
                var usarPdf = tipoPdf.checked;
                blocoManter.classList.toggle('d-none', !manter);
                blocoTexto.classList.toggle('d-none', !tipoTexto.checked);
                blocoPdf.classList.toggle('d-none', !usarPdf);
                if (campoTexto) {
                    campoTexto.name = tipoTexto.checked ? 'texto_autorizacao' : '';
                    campoTexto.required = tipoTexto.checked;
                }
                if (textoManter) {
                    textoManter.name = manter ? 'texto_autorizacao' : '';
                }
                if (campoPdf) {
                    campoPdf.required = usarPdf;
                    if (!usarPdf) campoPdf.value = '';
                }
            }
            tipoManter.addEventListener('change', atualizar);
            tipoTexto.addEventListener('change', atualizar);
            tipoPdf.addEventListener('change', atualizar);
            atualizar();
        })();
        </script>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
