    <?php if ($user->isLoggedIn()): ?>
            </main>
        </div>
    </div>
    <?php
    $alertas_login_popup = [];
    if (!empty($_SESSION['verificar_alertas_login']) && usuarioPodeVerPopupAlertas($user)) {
        $db_alertas = (new Database())->getConnection();
        $alertas_login_popup = obterAlertasLoginPopup($db_alertas, $user);
        unset($_SESSION['verificar_alertas_login']);
    }
    if (!empty($alertas_login_popup)) {
        require_once __DIR__ . '/../views/alertas/login_popup.php';
    }
    ?>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

