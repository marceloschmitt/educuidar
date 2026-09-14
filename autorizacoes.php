<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$autorizacao = new AutorizacaoResponsavel($db);
$configuracao = new Configuracao($db);
$tipo_evento = new TipoEvento($db);

if (!$user->isLoggedIn() || !($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2())) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar_vinculo_tipos' && $user->isAdmin()) {
        $entrada = $_POST['tipo_entrada'] ?? '';
        $saida = $_POST['tipo_saida'] ?? '';
        $configuracao->setAutorizacaoTipoEventoId('entrada_fora_horario', $entrada);
        $configuracao->setAutorizacaoTipoEventoId('saida_fora_horario', $saida);
        $_SESSION['success'] = 'Vínculo com tipos de evento atualizado.';
        header('Location: autorizacoes.php');
        exit;
    }

    if ($action === 'marcar_ocorrido') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = $id ? $autorizacao->marcarOcorrido($id, $_SESSION['user_id']) : false;
        if ($result === true) {
            $_SESSION['success'] = 'Marcado como ocorrido e evento criado.';
        } elseif ($result === 'sem_tipo_evento') {
            $_SESSION['error'] = 'Configure o tipo de evento correspondente (admin) antes de marcar como ocorrido.';
        } else {
            $_SESSION['error'] = 'Não foi possível marcar (já ocorrido ou inexistente).';
        }
        header('Location: autorizacoes.php' . (!empty($_POST['return_query']) ? '?' . ltrim($_POST['return_query'], '?') : ''));
        exit;
    }
}

$filtro_status = $_GET['status'] ?? 'previsto';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_data = $_GET['data'] ?? '';
$filtro_nome = trim($_GET['nome'] ?? '');

$filtros = [];
if ($filtro_status !== '') {
    $filtros['status'] = $filtro_status;
}
if ($filtro_tipo !== '') {
    $filtros['tipo'] = $filtro_tipo;
}
if ($filtro_data !== '') {
    $filtros['data'] = $filtro_data;
}
if ($filtro_nome !== '') {
    $filtros['nome'] = $filtro_nome;
}

$lista = $autorizacao->listForStaff($filtros);
$tipos = AutorizacaoResponsavel::tiposLabels();
$status_labels = AutorizacaoResponsavel::statusLabels();
$tipos_eventos = $tipo_evento->getAll(false);
$tipo_entrada_cfg = $configuracao->getAutorizacaoTipoEventoId('entrada_fora_horario');
$tipo_saida_cfg = $configuracao->getAutorizacaoTipoEventoId('saida_fora_horario');

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$return_query = http_build_query(array_filter([
    'status' => $filtro_status,
    'tipo' => $filtro_tipo,
    'data' => $filtro_data,
    'nome' => $filtro_nome,
], function ($v) { return $v !== '' && $v !== null; }));

$page_title = 'Autorizações';
require_once 'includes/header.php';
?>

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

<?php if ($user->isAdmin()): ?>
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-link-45deg"></i> Relacionar com tipos de evento</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Ao marcar uma autorização como <strong>ocorrida</strong>, o sistema cria automaticamente um evento
            do tipo escolhido, com a justificativa, nome e parentesco de quem autorizou e a data da autorização nas observações.
        </p>
        <form method="POST" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="salvar_vinculo_tipos">
            <div class="col-md-5">
                <label class="form-label" for="tipo_entrada">Entrada fora do horário → tipo de evento</label>
                <select class="form-select form-select-sm" id="tipo_entrada" name="tipo_entrada" required>
                    <option value="">Selecione…</option>
                    <?php foreach ($tipos_eventos as $te): ?>
                    <option value="<?php echo (int) $te['id']; ?>" <?php echo ((int)$tipo_entrada_cfg === (int)$te['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($te['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="tipo_saida">Saída fora do horário → tipo de evento</label>
                <select class="form-select form-select-sm" id="tipo_saida" name="tipo_saida" required>
                    <option value="">Selecione…</option>
                    <?php foreach ($tipos_eventos as $te): ?>
                    <option value="<?php echo (int) $te['id']; ?>" <?php echo ((int)$tipo_saida_cfg === (int)$te['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($te['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Salvar</button>
            </div>
        </form>
        <?php if (!$tipo_entrada_cfg || !$tipo_saida_cfg): ?>
        <div class="alert alert-warning mt-3 mb-0 py-2 small">
            Configure os dois tipos acima para que servidores possam marcar autorizações como ocorridas.
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Autorizações de responsáveis</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Autorizações de entrada ou saída fora do horário.
            Marque <strong>Ocorreu</strong> quando o aluno tiver chegado ou saído conforme previsto — isso cria o evento automaticamente.
        </p>
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select form-select-sm" id="status" name="status" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($status_labels as $k => $label): ?>
                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $filtro_status === $k ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="tipo">Tipo</label>
                <select class="form-select form-select-sm" id="tipo" name="tipo" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $k => $label): ?>
                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $filtro_tipo === $k ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="data">Data</label>
                <input type="date" class="form-control form-control-sm" id="data" name="data"
                       value="<?php echo htmlspecialchars($filtro_data); ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="nome">Aluno / responsável</label>
                <input type="text" class="form-control form-control-sm" id="nome" name="nome"
                       value="<?php echo htmlspecialchars($filtro_nome); ?>" placeholder="Buscar…">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
            </div>
        </form>

        <?php if (empty($lista)): ?>
        <p class="text-muted text-center mb-0">Nenhuma autorização encontrada.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Data / Hora</th>
                        <th>Tipo</th>
                        <th>Aluno</th>
                        <th>Responsável</th>
                        <th>Justificativa</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $item): ?>
                    <?php
                    $nome_aluno = !empty($item['aluno_nome_social']) ? $item['aluno_nome_social'] : $item['aluno_nome'];
                    $st = $item['status'] ?? 'previsto';
                    $badge = $st === 'ocorrido' ? 'success' : 'warning';
                    ?>
                    <tr>
                        <td class="text-nowrap">
                            <?php echo date('d/m/Y', strtotime($item['data_autorizacao'])); ?><br>
                            <span class="text-muted small"><?php echo substr($item['hora'], 0, 5); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($tipos[$item['tipo']] ?? $item['tipo']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($nome_aluno); ?>
                            <?php if (!empty($item['numero_matricula'])): ?>
                            <div class="small text-muted"><?php echo htmlspecialchars($item['numero_matricula']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['responsavel_nome'] ?? ''); ?></td>
                        <td style="max-width: 280px;"><?php echo nl2br(htmlspecialchars($item['justificativa'])); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($status_labels[$st] ?? $st); ?></span>
                            <?php if ($st === 'ocorrido' && !empty($item['confirmado_por_nome'])): ?>
                            <div class="small text-muted mt-1">
                                <?php echo htmlspecialchars($item['confirmado_por_nome']); ?>
                                <?php if (!empty($item['confirmado_em'])): ?>
                                · <?php echo date('d/m/Y H:i', strtotime($item['confirmado_em'])); ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($item['evento_id'])): ?>
                            <div class="small"><span class="badge bg-info">Evento #<?php echo (int) $item['evento_id']; ?></span></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($st === 'previsto'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="marcar_ocorrido">
                                <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($return_query); ?>">
                                <button type="submit" class="btn btn-success btn-sm"
                                        title="Marcar que o fato ocorreu e criar evento"
                                        onclick="return confirm('Marcar que a entrada/saída ocorreu e criar o evento?');">
                                    <i class="bi bi-check-lg"></i> Ocorreu
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
