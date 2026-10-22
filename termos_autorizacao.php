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

$ano_corrente = $configuracao->getAnoCorrente();
$turmas = $turmaModel->getTurmasPorAnoCorrente($ano_corrente);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'criar') {
    $titulo = trim($_POST['titulo'] ?? '');
    $objetivo = trim($_POST['objetivo'] ?? '');
    $data_raw = trim($_POST['data_evento'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $tipo_conteudo = $_POST['tipo_conteudo'] ?? 'texto';
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
    if ($tipo_conteudo === 'pdf') {
        $arquivo = $_FILES['pdf'] ?? null;
    } else {
        $texto = $texto_autorizacao;
    }

    if ($titulo === '' || $objetivo === '' || $data === null) {
        $_SESSION['error'] = 'Preencha título, objetivo e data.';
    } elseif (empty($turma_ids)) {
        $_SESSION['error'] = 'Selecione ao menos uma turma.';
    } elseif ($tipo_conteudo === 'pdf' && (empty($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
        $_SESSION['error'] = 'Envie o PDF da autorização.';
    } elseif ($tipo_conteudo !== 'pdf' && $texto === '') {
        $_SESSION['error'] = 'Digite o texto da autorização.';
    } else {
        $id = $termoModel->criar(
            $titulo,
            $objetivo,
            $data,
            $hora !== '' ? $hora : null,
            $turma_ids,
            $_SESSION['user_id'] ?? null,
            $arquivo,
            $texto
        );
        if ($id) {
            $_SESSION['success'] = 'Autorização cadastrada.';
            header('Location: termo_autorizacao_ver.php?id=' . $id);
            exit;
        }
        $_SESSION['error'] = 'Não foi possível cadastrar. Verifique o conteúdo (PDF até 10 MB ou texto) e os dados.';
    }
    header('Location: termos_autorizacao.php?novo=1');
    exit;
}

$lista = $termoModel->listParaEscola($ano_corrente);
$mostrar_form = isset($_GET['novo']);
$status_labels = TermoAutorizacao::statusLabels();

$page_title = 'Participação em atividades';
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

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-people"></i> Participação em atividades</h5>
        <?php if (!$mostrar_form): ?>
        <a href="termos_autorizacao.php?novo=1" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Nova autorização
        </a>
        <?php else: ?>
        <a href="termos_autorizacao.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted mb-0">
            Cadastre passeios e eventos com o texto da autorização (digitado ou PDF).
            Os responsáveis das turmas respondem se concordam com a participação.
            Ano corrente: <strong><?php echo (int) $ano_corrente; ?></strong>.
        </p>
    </div>
</div>

<?php if ($mostrar_form): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nova autorização</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" id="form-nova-autorizacao">
            <input type="hidden" name="action" value="criar">
            <div class="mb-3">
                <label class="form-label" for="titulo">Título *</label>
                <input type="text" class="form-control" id="titulo" name="titulo" maxlength="200" required
                       placeholder="Ex.: Visita ao Museu de Ciências">
            </div>
            <div class="mb-3">
                <label class="form-label" for="objetivo">Objetivo *</label>
                <textarea class="form-control" id="objetivo" name="objetivo" rows="3" required
                          placeholder="Descreva o objetivo da atividade"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="data_evento">Data *</label>
                    <input type="date" class="form-control" id="data_evento" name="data_evento" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="hora">Horário</label>
                    <input type="time" class="form-control" id="hora" name="hora">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label d-block">Texto da autorização *</label>
                <div class="btn-group mb-2" role="group" aria-label="Tipo do conteúdo">
                    <input type="radio" class="btn-check" name="tipo_conteudo" id="tipo_texto" value="texto" checked
                           autocomplete="off">
                    <label class="btn btn-outline-primary" for="tipo_texto">Texto digitado</label>
                    <input type="radio" class="btn-check" name="tipo_conteudo" id="tipo_pdf" value="pdf"
                           autocomplete="off">
                    <label class="btn btn-outline-primary" for="tipo_pdf">Arquivo PDF</label>
                </div>
                <div id="bloco-texto">
                    <textarea class="form-control" id="texto_autorizacao" name="texto_autorizacao" rows="8"
                              placeholder="Digite aqui o texto completo da autorização que os responsáveis deverão ler."></textarea>
                </div>
                <div id="bloco-pdf" class="d-none">
                    <input type="file" class="form-control" id="pdf" name="pdf" accept="application/pdf,.pdf">
                    <div class="form-text">Até 10 MB.</div>
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
                               value="<?php echo (int) $t['id']; ?>" id="turma_<?php echo (int) $t['id']; ?>">
                        <label class="form-check-label" for="turma_<?php echo (int) $t['id']; ?>">
                            <?php echo htmlspecialchars(($t['curso_nome'] ?? '') . ' - ' . (int) $t['ano_curso'] . 'º Ano'); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary" <?php echo empty($turmas) ? 'disabled' : ''; ?>>
                <i class="bi bi-save"></i> Cadastrar
            </button>
        </form>
        <script>
        (function () {
            var tipoTexto = document.getElementById('tipo_texto');
            var tipoPdf = document.getElementById('tipo_pdf');
            var blocoTexto = document.getElementById('bloco-texto');
            var blocoPdf = document.getElementById('bloco-pdf');
            var campoTexto = document.getElementById('texto_autorizacao');
            var campoPdf = document.getElementById('pdf');
            function atualizar() {
                var usarPdf = tipoPdf.checked;
                blocoTexto.classList.toggle('d-none', usarPdf);
                blocoPdf.classList.toggle('d-none', !usarPdf);
                campoTexto.required = !usarPdf;
                campoPdf.required = usarPdf;
                if (usarPdf) {
                    campoTexto.value = '';
                } else {
                    campoPdf.value = '';
                }
            }
            tipoTexto.addEventListener('change', atualizar);
            tipoPdf.addEventListener('change', atualizar);
            atualizar();
        })();
        </script>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Autorizações de <?php echo (int) $ano_corrente; ?></h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($lista)): ?>
        <p class="text-muted text-center my-4">Nenhuma autorização cadastrada neste ano.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Data</th>
                        <th>Título</th>
                        <th>Status</th>
                        <th>Visibilidade</th>
                        <th>Turmas</th>
                        <th class="text-center">Concordaram</th>
                        <th class="text-center">Discordaram</th>
                        <th class="text-center">Pendentes</th>
                        <th class="text-center">Discordância</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $t): ?>
                    <?php $st = $t['status'] ?? TermoAutorizacao::STATUS_ATIVO; ?>
                    <tr class="<?php echo $st === TermoAutorizacao::STATUS_SUSPENSO ? 'table-secondary' : ''; ?>">
                        <td class="ps-3 text-nowrap">
                            <?php echo date('d/m/Y', strtotime($t['data_evento'])); ?>
                            <?php if (!empty($t['hora'])): ?>
                            <div class="small text-muted"><?php echo substr($t['hora'], 0, 5); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($t['titulo']); ?></td>
                        <td>
                            <?php if ($st === TermoAutorizacao::STATUS_SUSPENSO): ?>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($status_labels[$st]); ?></span>
                            <?php else: ?>
                            <span class="badge bg-success"><?php echo htmlspecialchars($status_labels[$st] ?? 'Ativa'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (TermoAutorizacao::estaVisivelPais($t)): ?>
                            <span class="badge bg-info">Visível</span>
                            <?php else: ?>
                            <span class="badge bg-dark">Oculta</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($t['turmas_label'] ?: '—'); ?></td>
                        <td class="text-center"><span class="badge bg-success"><?php echo (int) $t['concordaram']; ?></span></td>
                        <td class="text-center"><span class="badge bg-danger"><?php echo (int) $t['discordaram']; ?></span></td>
                        <td class="text-center"><span class="badge bg-warning text-dark"><?php echo (int) $t['pendentes']; ?></span></td>
                        <td class="text-center">
                            <?php if ((int) $t['alunos_com_discordancia'] > 0): ?>
                            <span class="badge bg-danger">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <?php echo (int) $t['alunos_com_discordancia']; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <a href="termo_autorizacao_ver.php?id=<?php echo (int) $t['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
