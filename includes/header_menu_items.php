<ul class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
            <i class="bi bi-house-door"></i> Dashboard
        </a>
    </li>
    <?php if ($user->isAdmin() || $user->isNivel1() || $user->isNivel2() || $user->isAssistenciaEstudantil()): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'eventos.php' ? 'active' : ''; ?>" href="eventos.php">
            <i class="bi bi-calendar-event"></i> Eventos
        </a>
    </li>
    <?php endif; ?>
    <?php if ($user->isAdmin()): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cursos.php' ? 'active' : ''; ?>" href="cursos.php">
            <i class="bi bi-book"></i> Cursos
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'turmas.php' ? 'active' : ''; ?>" href="turmas.php">
            <i class="bi bi-collection"></i> Turmas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tipos_eventos.php' ? 'active' : ''; ?>" href="tipos_eventos.php">
            <i class="bi bi-tags"></i> Tipos de Eventos
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'configuracoes.php' ? 'active' : ''; ?>" href="configuracoes.php">
            <i class="bi bi-gear"></i> Configurações
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>" href="usuarios.php">
            <i class="bi bi-people"></i> Usuários
        </a>
    </li>
    <?php endif; ?>
    <?php 
    // Verificar permissão para ver alunos (admin, nivel1, nivel2, assistencia_estudantil)
    $user_type_session = $_SESSION['user_type'] ?? null;
    $is_admin = $user->isAdmin();
    $is_nivel1 = $user->isNivel1();
    $is_nivel2 = $user->isNivel2();
    $is_assistencia = $user->isAssistenciaEstudantil();
    
    $can_view_alunos = $is_admin || $is_nivel1 || $is_nivel2 || $is_assistencia || $user_type_session === 'nivel1' || $user_type_session === 'nivel2' || $user_type_session === 'assistencia_estudantil';
    
    if ($can_view_alunos): 
    ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'alunos.php' ? 'active' : ''; ?>" href="alunos.php">
            <i class="bi bi-person"></i> Alunos
        </a>
    </li>
    <?php endif; ?>
    <?php 
    // Only show "Alterar Senha" for users with local authentication
    $user_data = $user->getById($_SESSION['user_id']);
    $can_change_password = $user_data && ($user_data['auth_type'] ?? 'local') === 'local';
    if ($can_change_password): 
    ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'alterar_senha.php' ? 'active' : ''; ?>" href="alterar_senha.php">
            <i class="bi bi-key"></i> Alterar Senha
        </a>
    </li>
    <?php endif; ?>
    <li class="nav-item">
        <a class="nav-link" href="logout.php">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>
    </li>
</ul>

