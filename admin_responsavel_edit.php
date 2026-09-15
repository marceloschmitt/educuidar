<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$responsavel = new Responsavel($db);

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);
$dados = $id ? $responsavel->getById($id) : null;
if (!$dados) {
    $_SESSION['error'] = 'Responsável não encontrado.';
    header('Location: admin_responsaveis.php');
    exit;
}

$parentescos = ['pai' => 'Pai', 'mãe' => 'Mãe', 'tutor' => 'Tutor(a)', 'outro' => 'Outro'];
$voltar_status = $_GET['status'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar') {
        $cpf_raw = trim($_POST['cpf'] ?? '');
        $cpf = normalizeCpf($cpf_raw);
        $result = $responsavel->updateByAdmin($id, [
            'nome' => $_POST['nome'] ?? '',
            'cpf' => $cpf,
            'email' => $_POST['email'] ?? '',
            'status' => $_POST['status'] ?? 'pendente',
            'senha_nova' => $_POST['senha_nova'] ?? '',
        ]);

        if ($cpf_raw !== '' && $cpf_raw !== $cpf) {
            $_SESSION['error'] = 'Informe o CPF apenas com números, sem pontos ou traços.';
        } elseif ($result === true) {
            $_SESSION['success'] = 'Dados do responsável atualizados.';
            $qs = $voltar_status !== '' ? '&status=' . urlencode($voltar_status) : '';
            header('Location: admin_responsavel_edit.php?id=' . $id . $qs);
            exit;
        } else {
            $msgs = [
                'dados' => 'Preencha nome, CPF (11 dígitos) e e-mail.',
                'email' => 'E-mail inválido.',
                'status' => 'Status inválido.',
                'cpf_duplicado' => 'Já existe outro responsável com este CPF.',
                'email_duplicado' => 'Já existe outro responsável com este e-mail.',
                'senha_curta' => 'A nova senha deve ter pelo menos 6 caracteres.',
            ];
            $_SESSION['error'] = $msgs[$result] ?? 'Erro ao salvar.';
        }
        header('Location: admin_responsavel_edit.php?id=' . $id);
        exit;
    }

    if ($action === 'add_aluno') {
        $cpf_aluno_raw = trim($_POST['cpf_aluno'] ?? '');
        $cpf_aluno = normalizeCpf($cpf_aluno_raw);
        $parentesco = $_POST['parentesco'] ?? '';
        if ($cpf_aluno_raw !== $cpf_aluno || strlen($cpf_aluno) !== 11) {
            $_SESSION['error'] = 'CPF do aluno deve ter 11 números, sem pontos ou traços.';
        } elseif (!isset($parentescos[$parentesco])) {
            $_SESSION['error'] = 'Selecione o parentesco.';
        } else {
            $aluno_id = $responsavel->findAlunoIdByCpf($cpf_aluno);
            if (!$aluno_id) {
                $_SESSION['error'] = 'Aluno não encontrado com este CPF.';
            } elseif ($responsavel->jaVinculado($id, $aluno_id)) {
                $_SESSION['error'] = 'Este aluno já está vinculado.';
            } elseif ($responsavel->vincularAluno($id, $aluno_id, $parentesco)) {
                $_SESSION['success'] = 'Aluno vinculado.';
            } else {
                $_SESSION['error'] = 'Erro ao vincular aluno.';
            }
        }
        header('Location: admin_responsavel_edit.php?id=' . $id);
        exit;
    }

    if ($action === 'remover_vinculo' && !empty($_POST['vinculo_id'])) {
        if ($responsavel->desvincularAluno((int) $_POST['vinculo_id'], $id)) {
            $_SESSION['success'] = 'Vínculo removido.';
        } else {
            $_SESSION['error'] = 'Erro ao remover vínculo.';
        }
        header('Location: admin_responsavel_edit.php?id=' . $id);
        exit;
    }

    if ($action === 'parentesco' && !empty($_POST['vinculo_id'])) {
        $parentesco = $_POST['parentesco'] ?? '';
        if (!isset($parentescos[$parentesco])) {
            $_SESSION['error'] = 'Parentesco inválido.';
        } elseif ($responsavel->updateParentescoVinculo((int) $_POST['vinculo_id'], $id, $parentesco)) {
            $_SESSION['success'] = 'Parentesco atualizado.';
        } else {
            $_SESSION['error'] = 'Erro ao atualizar parentesco.';
        }
        header('Location: admin_responsavel_edit.php?id=' . $id);
        exit;
    }
}

$dados = $responsavel->getById($id);
$alunos = $responsavel->getAlunosVinculados($id);
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$page_title = 'Editar responsável';
require_once 'includes/header.php';

$lista_url = 'admin_responsaveis.php';
if ($voltar_status !== '') {
    $lista_url .= '?status=' . urlencode($voltar_status);
}
?>

<div class="mb-3">
    <a href="<?php echo htmlspecialchars($lista_url); ?>" class="btn btn-sm btn-secondary">
        <i class="bi bi-arrow-left"></i> Voltar à lista
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

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Dados da conta</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="salvar">
                    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                    <div class="mb-3">
                        <label class="form-label" for="nome">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" required
                               value="<?php echo htmlspecialchars($dados['nome'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cpf">CPF</label>
                        <input type="text" class="form-control cpf-digitos" id="cpf" name="cpf" required
                               inputmode="numeric" pattern="[0-9]{11}" maxlength="11"
                               value="<?php echo htmlspecialchars(normalizeCpf($dados['cpf'] ?? '')); ?>">
                        <div class="form-text">Apenas números, sem pontos ou traços.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo htmlspecialchars($dados['email'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <?php foreach (['pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'suspendido' => 'Suspenso', 'rejeitado' => 'Rejeitado'] as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo (($dados['status'] ?? '') === $k) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="senha_nova">Nova senha (opcional)</label>
                        <input type="password" class="form-control" id="senha_nova" name="senha_nova" minlength="6"
                               autocomplete="new-password">
                        <div class="form-text">Deixe em branco para manter a senha atual.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-3">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> Alunos vinculados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($alunos)): ?>
                <p class="text-muted">Nenhum aluno vinculado.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Parentesco</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunos as $a): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars(!empty($a['nome_social']) ? $a['nome_social'] : $a['nome']); ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars(normalizeCpf($a['cpf'] ?? '') ?: 'sem CPF'); ?></div>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="action" value="parentesco">
                                        <input type="hidden" name="vinculo_id" value="<?php echo (int) $a['vinculo_id']; ?>">
                                        <select name="parentesco" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <?php foreach ($parentescos as $k => $label): ?>
                                            <option value="<?php echo htmlspecialchars($k); ?>"
                                                <?php echo (($a['parentesco'] ?? '') === $k) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" class="form-confirm" data-confirm="Remover este vínculo?">
                                        <input type="hidden" name="action" value="remover_vinculo">
                                        <input type="hidden" name="vinculo_id" value="<?php echo (int) $a['vinculo_id']; ?>">
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

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Vincular aluno</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_aluno">
                    <div class="mb-3">
                        <label class="form-label" for="cpf_aluno">CPF do aluno</label>
                        <input type="text" class="form-control cpf-digitos" id="cpf_aluno" name="cpf_aluno" required
                               inputmode="numeric" pattern="[0-9]{11}" maxlength="11" placeholder="00000000000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="parentesco_novo">Parentesco</label>
                        <select class="form-select" id="parentesco_novo" name="parentesco" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($parentescos as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-link-45deg"></i> Vincular
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.cpf-digitos').forEach(function (el) {
    el.addEventListener('input', function () {
        this.value = this.value.replace(/\D+/g, '').slice(0, 11);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
