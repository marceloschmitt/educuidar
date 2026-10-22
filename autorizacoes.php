<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$autorizacao = new AutorizacaoResponsavel($db);
$configuracao = new Configuracao($db);
$tipo_evento = new TipoEvento($db);
$alunoModel = new Aluno($db);

if (!$user->isLoggedIn() || !($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2())) {
    header('Location: index.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$tipos = AutorizacaoResponsavel::tiposLabels();
$meios = AutorizacaoResponsavel::meiosLabels();
$mostrar_form = isset($_GET['novo']);

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

    if ($action === 'criar_escola') {
        $aluno_id = (int) ($_POST['aluno_id'] ?? 0);
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

        $alunos_validos = array_map('intval', array_column(
            $alunoModel->getAlunosTurmasAnoCorrente($ano_corrente),
            'id'
        ));

        if (!in_array($aluno_id, $alunos_validos, true)) {
            $_SESSION['error'] = 'Selecione um aluno válido.';
        } elseif (!isset($tipos[$tipo])) {
            $_SESSION['error'] = 'Selecione o tipo de autorização.';
        } elseif ($data === '' || $hora === '') {
            $_SESSION['error'] = 'Informe a data e o horário.';
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
        header('Location: autorizacoes.php?novo=1');
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
            $_SESSION['error'] = 'Não foi possível marcar (já resolvido ou inexistente).';
        }
        header('Location: autorizacoes.php' . (!empty($_POST['return_query']) ? '?' . ltrim($_POST['return_query'], '?') : ''));
        exit;
    }

    if ($action === 'marcar_nao_ocorrido') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && $autorizacao->marcarNaoOcorrido($id, $_SESSION['user_id'])) {
            $_SESSION['success'] = 'Marcado como não ocorrido.';
        } else {
            $_SESSION['error'] = 'Não foi possível marcar (já resolvido ou inexistente).';
        }
        header('Location: autorizacoes.php' . (!empty($_POST['return_query']) ? '?' . ltrim($_POST['return_query'], '?') : ''));
        exit;
    }
}

$filtro_status = $_GET['status'] ?? '';
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
$status_labels = AutorizacaoResponsavel::statusLabels();
$tipos_eventos = $tipo_evento->getAll(false);
$tipo_entrada_cfg = $configuracao->getAutorizacaoTipoEventoId('entrada_fora_horario');
$tipo_saida_cfg = $configuracao->getAutorizacaoTipoEventoId('saida_fora_horario');
$alunos = $mostrar_form ? $alunoModel->getAlunosTurmasAnoCorrente($ano_corrente) : [];
$professores = $mostrar_form ? $user->listProfessoresComEmail() : [];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$return_query = http_build_query(array_filter([
    'status' => $filtro_status,
    'tipo' => $filtro_tipo,
    'data' => $filtro_data,
    'nome' => $filtro_nome,
], function ($v) { return $v !== '' && $v !== null; }));

$page_title = 'Autorizações — Entrada e saída';
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
            do tipo escolhido, com as observações, quem autorizou e como autorizou.
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
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-door-open"></i> Entrada e saída</h5>
        <?php if (!$mostrar_form): ?>
        <a href="autorizacoes.php?novo=1" class="btn btn-primary btn-sm">
            <i class="bi bi-telephone"></i> Registrar aviso recebido
        </a>
        <?php else: ?>
        <a href="autorizacoes.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-0">
            Autorizações feitas pelos responsáveis no portal ou registradas pela escola
            (telefone, e-mail etc.). Marque com <i class="bi bi-check-lg"></i> se ocorreu
            (cria o evento) ou com <i class="bi bi-x-lg text-danger"></i> se não ocorreu.
        </p>
    </div>
</div>

<?php if ($mostrar_form): ?>
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-telephone"></i> Registrar aviso recebido</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Use quando o responsável autorizar por telefone, e-mail ou outro meio.
            Informe <strong>quem</strong> autorizou e <strong>como</strong> autorizou.
        </p>
        <form method="POST" action="">
            <input type="hidden" name="action" value="criar_escola">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="aluno_id">Aluno *</label>
                    <select class="form-select" id="aluno_id" name="aluno_id" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($alunos as $a): ?>
                        <?php
                        $nome_a = !empty($a['nome_social']) ? $a['nome_social'] : $a['nome'];
                        $label_turma = ($a['curso_nome'] ?? '') . ' - ' . (int) ($a['ano_curso'] ?? 0) . 'º';
                        ?>
                        <option value="<?php echo (int) $a['id']; ?>">
                            <?php echo htmlspecialchars($nome_a . ' · ' . $label_turma); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                    <label class="form-label" for="data_autorizacao">Data *</label>
                    <input type="date" class="form-control" id="data_autorizacao" name="data_autorizacao"
                           value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="hora">Horário *</label>
                    <input type="time" class="form-control" id="hora" name="hora" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="autorizado_por_nome">Quem autorizou *</label>
                    <input type="text" class="form-control" id="autorizado_por_nome" name="autorizado_por_nome"
                           maxlength="200" required placeholder="Ex.: Maria Silva (mãe)">
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
                <div class="col-12">
                    <label class="form-label" for="observacoes">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"
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
            <div class="mt-3">
                <button type="submit" class="btn btn-primary" <?php echo empty($alunos) ? 'disabled' : ''; ?>>
                    <i class="bi bi-save"></i> Registrar
                </button>
            </div>
            <?php if (empty($alunos)): ?>
            <div class="alert alert-warning mt-3 mb-0">Nenhum aluno nas turmas do ano corrente.</div>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Autorizações</h5>
    </div>
    <div class="card-body">
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
                <label class="form-label" for="filtro_tipo">Tipo</label>
                <select class="form-select form-select-sm" id="filtro_tipo" name="tipo" onchange="this.form.submit()">
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
                <label class="form-label" for="nome">Aluno / quem autorizou</label>
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
                        <th>Autorizado por</th>
                        <th>Observações</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $item): ?>
                    <?php
                    $nome_aluno = !empty($item['aluno_nome_social']) ? $item['aluno_nome_social'] : $item['aluno_nome'];
                    $st = $item['status'] ?? 'pendente';
                    $badge = AutorizacaoResponsavel::statusBadgeClass($st);
                    $escola = AutorizacaoResponsavel::ehRegistroEscola($item);
                    $quem = AutorizacaoResponsavel::nomeQuemAutorizou($item);
                    $meio_label = $meios[$item['meio_autorizacao'] ?? ''] ?? '';
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
                        <td>
                            <?php echo htmlspecialchars($quem); ?>
                            <?php if ($escola): ?>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars($meio_label); ?>
                                <?php if (!empty($item['registrado_por_nome'])): ?>
                                · registrado por <?php echo htmlspecialchars($item['registrado_por_nome']); ?>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="small text-muted">Portal</div>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 280px;"><?php echo nl2br(htmlspecialchars($item['justificativa'])); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($status_labels[$st] ?? $st); ?></span>
                            <?php if (($st === 'ocorrido' || $st === 'nao_ocorrido') && !empty($item['confirmado_por_nome'])): ?>
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
                            <?php if ($st === 'pendente'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="marcar_ocorrido">
                                <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($return_query); ?>">
                                <button type="submit" class="btn btn-success btn-sm"
                                        title="Marcar como ocorrido e criar evento"
                                        onclick="return confirm('Marcar que a entrada/saída ocorreu e criar o evento?');">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="marcar_nao_ocorrido">
                                <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($return_query); ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        title="Marcar como não ocorrido"
                                        onclick="return confirm('Marcar que a entrada/saída NÃO ocorreu?');">
                                    <i class="bi bi-x-lg"></i>
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
