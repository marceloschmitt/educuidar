<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Only admin can manage user types
if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

function getUserTypeUsage($db, $id) {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_user_types WHERE user_type_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $users_total = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM tipos_eventos WHERE prontuario_user_type_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $tipos_total = (int)($stmt->fetch()['total'] ?? 0);

    return [$users_total, $tipos_total];
}

function fetchUserTypes($db) {
    $stmt = $db->prepare("SELECT id, nome, nivel, created_at, updated_at FROM user_types ORDER BY nome ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $nome = trim($_POST['nome'] ?? '');
    $nivel = $_POST['nivel'] ?? 'nivel0';

    if ($action === 'create' || $action === 'update') {
        if (empty($nome) || empty($nivel)) {
            $error = 'Por favor, preencha todos os campos obrigatórios.';
        } elseif (!in_array($nivel, ['administrador', 'nivel0', 'nivel1', 'nivel2'], true)) {
            $error = 'Nível inválido.';
        } else {
            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO user_types (nome, nivel) VALUES (:nome, :nivel)");
                $stmt->bindParam(':nome', $nome);
                $stmt->bindParam(':nivel', $nivel);
                if ($stmt->execute()) {
                    $success = 'Tipo de usuário criado com sucesso!';
                } else {
                    $error = 'Erro ao criar tipo de usuário.';
                }
            } else {
                if (empty($id)) {
                    $error = 'ID do tipo não informado.';
                } else {
                    $stmt = $db->prepare("UPDATE user_types SET nome = :nome, nivel = :nivel WHERE id = :id");
                    $stmt->bindParam(':id', $id);
                    $stmt->bindParam(':nome', $nome);
                    $stmt->bindParam(':nivel', $nivel);
                    if ($stmt->execute()) {
                        $success = 'Tipo de usuário atualizado com sucesso!';
                    } else {
                        $error = 'Erro ao atualizar tipo de usuário.';
                    }
                }
            }
        }
    } elseif ($action === 'delete' && !empty($id)) {
        [$users_total, $tipos_total] = getUserTypeUsage($db, $id);
        if ($users_total > 0 || $tipos_total > 0) {
            $error = 'Não é possível excluir um tipo de usuário em uso.';
        } else {
            $stmt = $db->prepare("DELETE FROM user_types WHERE id = :id");
            $stmt->bindParam(':id', $id);
            if ($stmt->execute()) {
                $success = 'Tipo de usuário excluído com sucesso!';
            } else {
                $error = 'Erro ao excluir tipo de usuário.';
            }
        }
    }
}

$tipo_edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT id, nome, nivel FROM user_types WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $_GET['edit']);
    $stmt->execute();
    $tipo_edit = $stmt->fetch();
    if (!$tipo_edit) {
        $error = 'Tipo de usuário não encontrado.';
    }
}

$tipos = fetchUserTypes($db);
$page_title = 'Tipos de Usuário';
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

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> <?php echo $tipo_edit ? 'Editar' : 'Novo'; ?> Tipo</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $tipo_edit ? 'update' : 'create'; ?>">
                    <?php if ($tipo_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $tipo_edit['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($tipo_edit['nome'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="nivel" class="form-label">Nível <span class="text-danger">*</span></label>
                        <select class="form-select" id="nivel" name="nivel" required>
                            <option value="administrador" <?php echo (($tipo_edit['nivel'] ?? '') === 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                            <option value="nivel0" <?php echo ((!isset($tipo_edit) || ($tipo_edit['nivel'] ?? '') === 'nivel0')) ? 'selected' : ''; ?>>Nível 0</option>
                            <option value="nivel1" <?php echo (($tipo_edit['nivel'] ?? '') === 'nivel1') ? 'selected' : ''; ?>>Nível 1</option>
                            <option value="nivel2" <?php echo (($tipo_edit['nivel'] ?? '') === 'nivel2') ? 'selected' : ''; ?>>Nível 2</option>
                        </select>
                        <small class="text-muted">Define as permissões gerais do tipo.</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> <?php echo $tipo_edit ? 'Atualizar' : 'Criar'; ?> Tipo
                    </button>
                    <?php if ($tipo_edit): ?>
                    <a href="user_types.php" class="btn btn-secondary w-100 mt-2">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Tipos de Usuário</h5>
            </div>
            <div class="card-body">
                <?php if (empty($tipos)): ?>
                <p class="text-muted text-center">Nenhum tipo de usuário cadastrado ainda.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Nível</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos as $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['nome']); ?></td>
                                <td>
                                    <?php
                                    $nivel_label = [
                                        'administrador' => 'Administrador',
                                        'nivel0' => 'Nível 0',
                                        'nivel1' => 'Nível 1',
                                        'nivel2' => 'Nível 2'
                                    ];
                                    ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($nivel_label[$t['nivel']] ?? $t['nivel']); ?></span>
                                </td>
                                <td>
                                    <a href="user_types.php?edit=<?php echo $t['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="" style="display: inline;" 
                                          class="form-confirm" data-confirm="Tem certeza que deseja excluir este tipo de usuário?">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
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
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
