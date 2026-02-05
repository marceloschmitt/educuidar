<?php
// Process all PHP logic BEFORE any HTML output
require_once __DIR__ . '/../config/init.php';
$user = new User((new Database())->getConnection());

// Check login and redirect if needed (before any output)
$allowed_pages = ['login.php', 'definir_senha_admin.php'];
if (!$user->isLoggedIn() && !in_array(basename($_SERVER['PHP_SELF']), $allowed_pages)) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?php echo isset($page_title) ? $page_title : 'Sistema de Controle - IFRS'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <script src="js/app.js" defer></script>
</head>
<body<?php 
// Se houver aluno_edit na sessão ou GET, passar para JavaScript via data attribute
if (isset($aluno_edit) && $aluno_edit) {
    echo ' data-aluno-edit=\'' . htmlspecialchars(json_encode($aluno_edit), ENT_QUOTES) . '\'';
}
// Passar informação se usuário é admin
if ($user->isAdmin()) {
    echo ' data-is-admin="1"';
}
// Passar tipo de usuário
$user_type_attr = $_SESSION['user_type'] ?? '';
if ($user_type_attr !== '') {
    echo ' data-user-type="' . htmlspecialchars($user_type_attr, ENT_QUOTES) . '"';
}
?>>
    
    <?php if ($user->isLoggedIn()): ?>
    <!-- Mobile menu button -->
    <button class="btn btn-primary d-md-none position-fixed top-0 start-0 m-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" style="z-index: 1050;">
        <i class="bi bi-list"></i> Menu
    </button>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Desktop sidebar (always visible) -->
            <nav class="col-md-3 col-lg-2 d-none d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="px-2 logo-container">
                        <img src="image_bg.png" alt="Logo">
                    </div>
                    <?php include __DIR__ . '/header_menu_items.php'; ?>
                </div>
            </nav>
            
            <!-- Mobile offcanvas sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 sidebar offcanvas offcanvas-start d-md-none" tabindex="-1">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title text-white">Menu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <div class="px-2 logo-container">
                        <img src="image_bg.png" alt="Logo">
                    </div>
                    <?php include __DIR__ . '/header_menu_items.php'; ?>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                    <div class="text-end no-print">
                        <span class="text-muted">Olá, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <span class="badge bg-secondary ms-2"><?php echo ucfirst($_SESSION['user_type']); ?></span>
                    </div>
                </div>
    <?php endif; ?>

