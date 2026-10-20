<?php
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$resp = new Responsavel($db);

if (!$resp->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Autorizações';
$show_header = true;
$responsavel_nome = $_SESSION['responsavel_nome'] ?? '';
require __DIR__ . '/header.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Ocorrências</a>
    <span class="text-muted small">Autorizações</span>
</div>

<div class="card resp-card mb-3">
    <div class="card-body p-4">
        <h1 class="h5 mb-3">Autorizações</h1>
        <p class="text-muted mb-0">Escolha o tipo de autorização:</p>
    </div>
</div>

<a href="autorizacoes.php" class="card resp-card mb-3 text-decoration-none text-dark">
    <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
        <div>
            <div class="fw-semibold"><i class="bi bi-door-open text-success me-1"></i> Entrada e saída</div>
            <div class="small text-muted mt-1">Quem pode buscar o aluno na escola</div>
        </div>
        <i class="bi bi-chevron-right text-muted"></i>
    </div>
</a>

<a href="termos.php" class="card resp-card mb-3 text-decoration-none text-dark">
    <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
        <div>
            <div class="fw-semibold"><i class="bi bi-people text-success me-1"></i> Participação em atividades</div>
            <div class="small text-muted mt-1">Passeios, eventos e atividades escolares</div>
        </div>
        <i class="bi bi-chevron-right text-muted"></i>
    </div>
</a>

<?php require __DIR__ . '/footer.php'; ?>
