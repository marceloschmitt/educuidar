<?php
/**
 * Classe base para controllers
 * Fornece funcionalidades comuns para todos os controllers
 */
class Controller {
    protected $db;
    protected $user;
    
    public function __construct() {
        // init.php já foi incluído no arquivo principal
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }
    
    /**
     * Verifica se o usuário está logado
     */
    protected function requireLogin() {
        if (!$this->user->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    /**
     * Verifica se o usuário é administrador
     */
    protected function requireAdmin() {
        $this->requireLogin();
        if (!$this->user->isAdmin()) {
            header('Location: index.php');
            exit;
        }
    }
    
    /**
     * Verifica se o usuário tem permissão (admin, nivel1, nivel2 ou assistencia_estudantil)
     */
    protected function requirePermission() {
        $this->requireLogin();
        if (!$this->user->isAdmin() && !$this->user->isNivel1() && !$this->user->isNivel2()) {
            header('Location: index.php');
            exit;
        }
    }
    
    /**
     * Renderiza uma view
     */
    protected function render($view, $data = []) {
        extract($data);
        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            die("View não encontrada: {$view}");
        }
    }
    
    /**
     * Redireciona para uma URL
     */
    protected function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Define mensagem de sucesso na sessão
     */
    protected function setSuccess($message) {
        $_SESSION['success'] = $message;
    }
    
    /**
     * Define mensagem de erro na sessão
     */
    protected function setError($message) {
        $_SESSION['error'] = $message;
    }
    
    /**
     * Obtém mensagem de sucesso da sessão e limpa
     */
    protected function getSuccess() {
        if (isset($_SESSION['success'])) {
            $message = $_SESSION['success'];
            unset($_SESSION['success']);
            return $message;
        }
        return '';
    }
    
    /**
     * Obtém mensagem de erro da sessão e limpa
     */
    protected function getError() {
        if (isset($_SESSION['error'])) {
            $message = $_SESSION['error'];
            unset($_SESSION['error']);
            return $message;
        }
        return '';
    }
}

