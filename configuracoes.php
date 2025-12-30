<?php
$page_title = 'Configurações do Sistema';
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$configuracao = new Configuracao($db);

// Only admin can access
if (!$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// Process POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ano_corrente = $_POST['ano_corrente'] ?? '';
    
    if (empty($ano_corrente) || !is_numeric($ano_corrente)) {
        $error = 'Por favor, informe um ano válido!';
    } else {
        $ano_corrente = (int)$ano_corrente;
        if ($ano_corrente < 2000 || $ano_corrente > 2100) {
            $error = 'Por favor, informe um ano entre 2000 e 2100!';
        } else {
            if ($configuracao->setAnoCorrente($ano_corrente)) {
                $success = 'Ano corrente atualizado com sucesso!';
            } else {
                $error = 'Erro ao atualizar ano corrente. Tente novamente.';
            }
        }
    }
}

$ano_corrente_atual = $configuracao->getAnoCorrente();

require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Configurações do Sistema</h5>
            </div>
            <div class="card-body">
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
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="ano_corrente" class="form-label">
                            <strong>Ano Corrente</strong>
                        </label>
                        <p class="text-muted small mb-2">
                            O ano corrente determina quais turmas aparecerão no controle de eventos. 
                            Todas as turmas do ano corrente estarão disponíveis para registro de eventos.
                        </p>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               id="ano_corrente" 
                               name="ano_corrente" 
                               value="<?php echo htmlspecialchars($ano_corrente_atual); ?>" 
                               min="2000" 
                               max="2100" 
                               required>
                        <div class="form-text">
                            Ano atual: <strong><?php echo date('Y'); ?></strong>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <hr>
                        <h6 class="mb-3"><i class="bi bi-server"></i> Outras Configurações</h6>
                        <a href="ldap_config.php" class="btn btn-outline-primary">
                            <i class="bi bi-server"></i> Configuração LDAP
                        </a>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Configuração
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

