<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$tipo_evento = new TipoEvento($db);

// Only admin can manage tipos de eventos
if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

// Process POST requests before including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create') {
            $tipo_evento->nome = $_POST['nome'] ?? '';
            $tipo_evento->cor = $_POST['cor'] ?? 'secondary';
            $tipo_evento->prontuario_user_type = $_POST['prontuario_user_type'] ?? '';
            $tipo_evento->gera_prontuario_cae = ($tipo_evento->prontuario_user_type === 'assistencia_estudantil') ? 1 : 0;
            $tipo_evento->ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($tipo_evento->nome)) {
                $_SESSION['error'] = 'Por favor, preencha o nome do tipo de evento!';
            } else {
                if ($tipo_evento->create()) {
                    header('Location: tipos_eventos.php?success=created');
                    exit;
                } else {
                    $_SESSION['error'] = 'Erro ao criar tipo de evento.';
                }
            }
        } elseif ($_POST['action'] == 'update' && isset($_POST['id'])) {
            $tipo_evento->id = $_POST['id'];
            $tipo_evento->nome = $_POST['nome'] ?? '';
            $tipo_evento->cor = $_POST['cor'] ?? 'secondary';
            $tipo_evento->prontuario_user_type = $_POST['prontuario_user_type'] ?? '';
            $tipo_evento->gera_prontuario_cae = ($tipo_evento->prontuario_user_type === 'assistencia_estudantil') ? 1 : 0;
            $tipo_evento->ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($tipo_evento->nome)) {
                $_SESSION['error'] = 'Por favor, preencha o nome do tipo de evento!';
            } else {
                if ($tipo_evento->update()) {
                    header('Location: tipos_eventos.php?success=updated');
                    exit;
                } else {
                    $_SESSION['error'] = 'Erro ao atualizar tipo de evento.';
                }
            }
        } elseif ($_POST['action'] == 'delete' && isset($_POST['id'])) {
            $tipo_evento->id = $_POST['id'];
            if ($tipo_evento->getTotalEventos() > 0) {
                $_SESSION['error'] = 'Não é possível excluir um tipo que já possui eventos registrados.';
            } elseif ($tipo_evento->delete()) {
                header('Location: tipos_eventos.php?success=deleted');
                exit;
            } else {
                $_SESSION['error'] = 'Erro ao excluir tipo de evento.';
            }
        }
    }
}

$page_title = 'Tipos de Eventos';
require_once 'includes/header.php';

$success = '';
$error = '';

// Handle success messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'created') {
        $success = 'Tipo de evento criado com sucesso!';
    } elseif ($_GET['success'] == 'updated') {
        $success = 'Tipo de evento atualizado com sucesso!';
    } elseif ($_GET['success'] == 'deleted') {
        $success = 'Tipo de evento excluído com sucesso!';
    }
}

// Handle error messages from session
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get tipo for editing if requested
$tipo_edit = null;
if (isset($_GET['edit'])) {
    $tipo_edit = $tipo_evento->getById($_GET['edit']);
    if (!$tipo_edit) {
        $error = 'Tipo de evento não encontrado!';
    }
}

$tipos = $tipo_evento->getAll();
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
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> <?php echo $tipo_edit ? 'Editar' : 'Novo'; ?> Tipo de Evento</h5>
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
                        <label for="cor" class="form-label">Cor do Badge</label>
                        <select class="form-select" id="cor" name="cor">
                            <option value="primary" <?php echo ($tipo_edit['cor'] ?? '') == 'primary' ? 'selected' : ''; ?>>Azul (Primary)</option>
                            <option value="success" <?php echo ($tipo_edit['cor'] ?? '') == 'success' ? 'selected' : ''; ?>>Verde (Success)</option>
                            <option value="warning" <?php echo ($tipo_edit['cor'] ?? '') == 'warning' ? 'selected' : ''; ?>>Amarelo (Warning)</option>
                            <option value="danger" <?php echo ($tipo_edit['cor'] ?? '') == 'danger' ? 'selected' : ''; ?>>Vermelho (Danger)</option>
                            <option value="info" <?php echo ($tipo_edit['cor'] ?? '') == 'info' ? 'selected' : ''; ?>>Ciano (Info)</option>
                            <option value="secondary" <?php echo ($tipo_edit['cor'] ?? '') == 'secondary' ? 'selected' : ''; ?>>Cinza (Secondary)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" 
                                   <?php echo (!isset($tipo_edit) || $tipo_edit['ativo']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                            <small class="text-muted d-block">Tipos inativos não aparecerão ao registrar eventos</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <?php
                        $prontuario_user_type = $tipo_edit['prontuario_user_type'] ?? '';
                        if (empty($prontuario_user_type) && !empty($tipo_edit['gera_prontuario_cae'])) {
                            $prontuario_user_type = 'assistencia_estudantil';
                        }
                        ?>
                        <label for="prontuario_user_type" class="form-label">Prontuário exclusivo de</label>
                        <select class="form-select" id="prontuario_user_type" name="prontuario_user_type">
                            <option value="">Não gera prontuário</option>
                            <option value="administrador" <?php echo $prontuario_user_type === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
                            <option value="nivel1" <?php echo $prontuario_user_type === 'nivel1' ? 'selected' : ''; ?>>Professor</option>
                            <option value="nivel2" <?php echo $prontuario_user_type === 'nivel2' ? 'selected' : ''; ?>>Nível 2</option>
                            <option value="assistencia_estudantil" <?php echo $prontuario_user_type === 'assistencia_estudantil' ? 'selected' : ''; ?>>Assistência Estudantil</option>
                        </select>
                        <small class="text-muted d-block">Define quem pode visualizar o prontuário deste tipo de evento.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> <?php echo $tipo_edit ? 'Atualizar' : 'Criar'; ?> Tipo
                    </button>
                    <?php if ($tipo_edit): ?>
                    <a href="tipos_eventos.php" class="btn btn-secondary w-100 mt-2">
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
                <h5 class="mb-0"><i class="bi bi-tags"></i> Lista de Tipos de Eventos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($tipos)): ?>
                <p class="text-muted text-center">Nenhum tipo de evento cadastrado ainda.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Prontuário</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos as $t): ?>
                            <tr <?php echo (!$t['ativo']) ? 'class="table-secondary"' : ''; ?>>
                                <td>
                                    <span class="badge bg-<?php echo htmlspecialchars($t['cor']); ?>">
                                        <?php echo htmlspecialchars($t['nome']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $prontuario_tipo = $t['prontuario_user_type'] ?? '';
                                    if (empty($prontuario_tipo) && !empty($t['gera_prontuario_cae'])) {
                                        $prontuario_tipo = 'assistencia_estudantil';
                                    }
                                    $prontuario_labels = [
                                        'administrador' => 'Administrador',
                                        'nivel1' => 'Professor',
                                        'nivel2' => 'Nível 2',
                                        'assistencia_estudantil' => 'Assistência Estudantil'
                                    ];
                                    ?>
                                    <?php if (!empty($prontuario_tipo)): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($prontuario_labels[$prontuario_tipo] ?? $prontuario_tipo); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($t['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="tipos_eventos.php?edit=<?php echo $t['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if (!empty($t['total_eventos'])): ?>
                                        <button type="button" class="btn btn-danger btn-sm" disabled
                                                title="Não é possível excluir um tipo com eventos registrados.">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" action="" style="display: inline;" 
                                              class="form-confirm" data-confirm="Tem certeza que deseja excluir este tipo de evento?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i>
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
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

