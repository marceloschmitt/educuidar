<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$configuracao = new Configuracao($db);
$responsavel = new Responsavel($db);

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_cadastro') {
        $habilitado = isset($_POST['cadastro_habilitado']) ? 1 : 0;
        if ($configuracao->setCadastroResponsaveisHabilitado($habilitado)) {
            $_SESSION['success'] = $habilitado
                ? 'Cadastro de responsáveis aberto.'
                : 'Cadastro de responsáveis fechado.';
        } else {
            $_SESSION['error'] = 'Não foi possível atualizar a configuração.';
        }
        header('Location: admin_responsaveis.php');
        exit;
    }

    if ($action === 'aprovar' && !empty($_POST['id'])) {
        if ($responsavel->aprovar((int) $_POST['id'])) {
            $_SESSION['success'] = 'Responsável aprovado.';
        } else {
            $_SESSION['error'] = 'Erro ao aprovar responsável.';
        }
        header('Location: admin_responsaveis.php');
        exit;
    }

    if ($action === 'rejeitar' && !empty($_POST['id'])) {
        if ($responsavel->rejeitar((int) $_POST['id'])) {
            $_SESSION['success'] = 'Responsável rejeitado.';
        } else {
            $_SESSION['error'] = 'Erro ao rejeitar responsável.';
        }
        header('Location: admin_responsaveis.php');
        exit;
    }

    if ($action === 'suspender' && !empty($_POST['id'])) {
        if ($responsavel->suspender((int) $_POST['id'])) {
            $_SESSION['success'] = 'Responsável suspenso. Os vínculos e as autorizações foram mantidos.';
        } else {
            $_SESSION['error'] = 'Erro ao suspender responsável.';
        }
        header('Location: admin_responsaveis.php');
        exit;
    }

    if ($action === 'excluir' && !empty($_POST['id'])) {
        if ($responsavel->delete((int) $_POST['id'])) {
            $_SESSION['success'] = 'Responsável removido.';
        } else {
            $_SESSION['error'] = 'Erro ao remover responsável.';
        }
        header('Location: admin_responsaveis.php');
        exit;
    }
}

$page_title = 'Responsáveis';
require_once 'includes/header.php';

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$filtro_status = $_GET['status'] ?? '';
$lista = $responsavel->listByStatus($filtro_status !== '' ? $filtro_status : null);
$cadastro_aberto = $configuracao->isCadastroResponsaveisHabilitado();

$linhas = [];
$alunos_contemplados = [];
foreach ($lista as $r) {
    $alunos = $responsavel->getAlunosVinculados($r['id']);
    foreach ($alunos as $a) {
        $alunos_contemplados[(int) $a['id']] = true;
    }
    $linhas[] = ['r' => $r, 'alunos' => $alunos];
}
$total_responsaveis = count($linhas);
$total_alunos = count($alunos_contemplados);
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

<div class="row mb-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-toggle-on"></i> Cadastro público</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Quando aberto, responsáveis podem se cadastrar em
                    <code>cadastro_responsavel.php</code>. Mantenha fechado fora do período de matrícula.
                </p>
                <form method="POST" class="d-flex align-items-center gap-3 flex-wrap">
                    <input type="hidden" name="action" value="toggle_cadastro">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="cadastro_habilitado" name="cadastro_habilitado" value="1"
                               <?php echo $cadastro_aberto ? 'checked' : ''; ?>
                               onchange="this.form.submit()">
                        <label class="form-check-label" for="cadastro_habilitado">
                            <?php echo $cadastro_aberto ? 'Cadastro aberto' : 'Cadastro fechado'; ?>
                        </label>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column justify-content-center gap-2">
                <a href="alunos_sem_responsavel.php" class="btn btn-outline-primary">
                    <i class="bi bi-person-x"></i> Alunos sem responsável
                </a>
                <a href="responsaveis/login.php" class="btn btn-outline-secondary" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i> Abrir portal do responsável
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0"><i class="bi bi-people"></i> Responsáveis cadastrados</h5>
            <div class="small text-muted mt-1">
                <?php echo (int) $total_responsaveis; ?> responsável(is)
                · <?php echo (int) $total_alunos; ?> aluno(s) contemplado(s)
            </div>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="pendente" <?php echo $filtro_status === 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                <option value="aprovado" <?php echo $filtro_status === 'aprovado' ? 'selected' : ''; ?>>Aprovados</option>
                <option value="suspendido" <?php echo $filtro_status === 'suspendido' ? 'selected' : ''; ?>>Suspensos</option>
                <option value="rejeitado" <?php echo $filtro_status === 'rejeitado' ? 'selected' : ''; ?>>Rejeitados</option>
            </select>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($linhas)): ?>
        <p class="text-muted text-center mb-0">Nenhum responsável encontrado.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Alunos</th>
                        <th>Responsável</th>
                        <th>E-mail</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linhas as $linha): ?>
                    <?php
                    $r = $linha['r'];
                    $alunos = $linha['alunos'];
                    $status = $r['status'] ?? 'pendente';
                    $badge = $status === 'aprovado' ? 'success'
                        : ($status === 'rejeitado' ? 'danger'
                        : ($status === 'suspendido' ? 'secondary' : 'warning'));
                    $n_auth = (int) ($r['total_autorizacoes'] ?? 0);
                    $msg_excluir = 'ATENÇÃO: a remoção é permanente. Serão apagados os vínculos com alunos'
                        . ($n_auth > 0 ? ' e ' . $n_auth . ' autorização(ões)' : '')
                        . '. Prefira Suspender para manter o histórico. Deseja continuar?';
                    ?>
                    <tr>
                        <td>
                            <?php if (empty($alunos)): ?>
                                <span class="text-muted">—</span>
                            <?php else: ?>
                                <ul class="list-unstyled mb-0 small">
                                    <?php foreach ($alunos as $a): ?>
                                    <li>
                                        <?php echo htmlspecialchars(!empty($a['nome_social']) ? $a['nome_social'] : $a['nome']); ?>
                                        <?php if (!empty($a['parentesco'])): ?>
                                            <span class="text-muted">(<?php echo htmlspecialchars($a['parentesco']); ?>)</span>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($r['nome']); ?></td>
                        <td><?php echo htmlspecialchars($r['email']); ?></td>
                        <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                        <td class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></td>
                        <td>
                            <a href="admin_responsavel_edit.php?id=<?php echo (int) $r['id']; ?><?php echo $filtro_status !== '' ? '&status=' . urlencode($filtro_status) : ''; ?>"
                               class="btn btn-primary btn-sm" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($status === 'pendente'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="aprovar">
                                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="Aprovar">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline form-confirm" data-confirm="Rejeitar este responsável?">
                                <input type="hidden" name="action" value="rejeitar">
                                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Rejeitar">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                            <?php elseif ($status === 'aprovado'): ?>
                            <form method="POST" class="d-inline form-confirm"
                                  data-confirm="Suspender este responsável? O acesso será bloqueado, mas vínculos e autorizações serão mantidos.">
                                <input type="hidden" name="action" value="suspender">
                                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Suspender">
                                    <i class="bi bi-pause-circle"></i>
                                </button>
                            </form>
                            <?php elseif ($status === 'suspendido' || $status === 'rejeitado'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="aprovar">
                                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="Reativar / aprovar">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline form-confirm"
                                  data-confirm="<?php echo htmlspecialchars($msg_excluir, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="excluir">
                                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Remover">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
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
