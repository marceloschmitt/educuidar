<?php
/**
 * Database configuration
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // Load configuration from config file
        $config_file = __DIR__ . '/config.php';
        
        if (!file_exists($config_file)) {
            throw new Exception('Configuration file not found. Please create config/config.php with your database settings. See documentation for the required structure.');
        }
        
        $config = require $config_file;
        
        if (!isset($config['database'])) {
            throw new Exception('Database configuration not found in config file.');
        }
        
        $db_config = $config['database'];
        
        if (!isset($db_config['host']) || !isset($db_config['db_name']) || 
            !isset($db_config['username']) || !isset($db_config['password'])) {
            throw new Exception('Database configuration incomplete. Please check config/config.php and ensure all required fields (host, db_name, username, password) are set.');
        }
        
        $this->host = $db_config['host'];
        $this->db_name = $db_config['db_name'];
        $this->username = $db_config['username'];
        $this->password = $db_config['password'];
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            $error_message = $exception->getMessage();
            error_log("Database connection error: " . $error_message);
            
            // Check if it's an authentication method issue (MySQL 8.0+ caching_sha2_password)
            if (stripos($error_message, 'caching_sha2_password') !== false || 
                stripos($error_message, 'authentication') !== false ||
                stripos($error_message, 'Access denied') !== false) {
                
                echo "Erro de conexão com o banco de dados. Verifique as configurações em config/config.php";
                echo "<br><br><strong>Possível problema de autenticação do MySQL:</strong>";
                echo "<br><small>Se você está usando MySQL 8.0+, o usuário pode estar usando 'caching_sha2_password'.";
                echo "<br>Para resolver, execute no MySQL:</small>";
                echo "<br><code>ALTER USER '" . htmlspecialchars($this->username) . "'@'localhost' IDENTIFIED WITH mysql_native_password BY 'sua_senha';</code>";
                echo "<br><small>Ou atualize a extensão PHP MySQL (mysqlnd) para uma versão que suporte caching_sha2_password.</small>";
                echo "<br><br><small>Erro detalhado: " . htmlspecialchars($error_message) . "</small>";
            } else {
                echo "Erro de conexão com o banco de dados. Verifique as configurações em config/config.php";
                echo "<br><small>Erro detalhado: " . htmlspecialchars($error_message) . "</small>";
            }
        }

        return $this->conn;
    }
}
?>

