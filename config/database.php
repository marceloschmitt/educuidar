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
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            // Em produção, não exibir detalhes do erro
            // Log do erro pode ser implementado aqui
            error_log("Database connection error: " . $exception->getMessage());
            echo "Erro de conexão com o banco de dados. Verifique as configurações em config/config.php";
        }

        return $this->conn;
    }
}
?>

