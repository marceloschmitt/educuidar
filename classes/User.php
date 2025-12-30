<?php
/**
 * User class
 */

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $username;
    public $email;
    public $password;
    public $full_name;
    public $user_type;
    public $auth_type;
    public $turma_corrente_id;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        $query = "SELECT id, username, email, password, full_name, user_type, auth_type 
                  FROM " . $this->table . " 
                  WHERE username = :username OR email = :email 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $auth_type = $row['auth_type'] ?? 'local'; // Default to local for backward compatibility
            
            // If user uses LDAP authentication
            if ($auth_type === 'ldap') {
                $ldap = new LDAPAuth($this->conn);
                $ldap_result = $ldap->authenticate($username, $password);
                
                if ($ldap_result) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['user_type'] = $row['user_type'];
                    $_SESSION['full_name'] = $row['full_name'];
                    return true;
                } else {
                    // Store LDAP error in session for display
                    $ldap_error = $ldap->getLastError();
                    if ($ldap_error) {
                        $_SESSION['ldap_error'] = $ldap_error;
                    }
                }
                return false;
            } else {
                // Check if admin user has no password set (first login)
                if (empty($row['password']) && $row['user_type'] === 'administrador') {
                    // Return special code to indicate password needs to be set
                    return 'SET_PASSWORD';
                }
                
                // Local authentication - verify password from database
                if (!empty($row['password']) && password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['user_type'] = $row['user_type'];
                    $_SESSION['full_name'] = $row['full_name'];
                    return true;
                }
            }
        }
        return false;
    }

    public function logout() {
        session_unset();
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }

    public function isAdmin() {
        return $this->getUserType() === 'administrador';
    }

    public function isNivel1() {
        return $this->getUserType() === 'nivel1';
    }

    public function isNivel2() {
        return $this->getUserType() === 'nivel2';
    }

    public function create() {
        // Set default auth_type if not provided
        if (empty($this->auth_type)) {
            $this->auth_type = ($this->user_type === 'administrador') ? 'local' : 'ldap';
        }
        
        // If using local auth, password is required
        if ($this->auth_type === 'local' && empty($this->password)) {
            return false;
        }
        
        $query = "INSERT INTO " . $this->table . " 
                  (username, email, password, full_name, user_type, auth_type) 
                  VALUES (:username, :email, :password, :full_name, :user_type, :auth_type)";

        $stmt = $this->conn->prepare($query);

        // Hash password only for local authentication
        $hashed_password = null;
        if ($this->auth_type === 'local' && !empty($this->password)) {
            $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
        }

        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':user_type', $this->user_type);
        $stmt->bindParam(':auth_type', $this->auth_type);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getById($id) {
        $query = "SELECT id, username, email, full_name, user_type, auth_type, created_at
                  FROM " . $this->table . " 
                  WHERE id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getAll() {
        $query = "SELECT u.id, u.username, u.email, u.full_name, u.user_type, u.auth_type, u.created_at
                  FROM " . $this->table . " u
                  ORDER BY u.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function update() {
        // Set default auth_type if not provided
        if (empty($this->auth_type)) {
            $this->auth_type = ($this->user_type === 'administrador') ? 'local' : 'ldap';
        }
        
        $query = "UPDATE " . $this->table . " 
                  SET username = :username,
                      email = :email,
                      full_name = :full_name,
                      user_type = :user_type,
                      auth_type = :auth_type
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':user_type', $this->user_type);
        $stmt->bindParam(':auth_type', $this->auth_type);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function updatePassword($user_id, $new_password) {
        // Only update password if user uses local authentication
        $query_check = "SELECT auth_type FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt_check = $this->conn->prepare($query_check);
        $stmt_check->bindParam(':id', $user_id);
        $stmt_check->execute();
        $user = $stmt_check->fetch();
        
        if ($user && $user['auth_type'] === 'ldap') {
            // LDAP users don't have local passwords
            return false;
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE " . $this->table . " 
                  SET password = :password
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->bindParam(':password', $hashed_password);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getTurmasAluno($aluno_id) {
        $query = "SELECT t.*, at.is_corrente, at.created_at as associado_em
                  FROM aluno_turmas at
                  INNER JOIN turmas t ON at.turma_id = t.id
                  WHERE at.aluno_id = :aluno_id
                  ORDER BY at.is_corrente DESC, t.ano_civil DESC, t.ano_curso ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function adicionarTurmaAluno($aluno_id, $turma_id, $is_corrente = false) {
        // Se for turma corrente, desmarcar outras
        if ($is_corrente) {
            $this->definirTurmaCorrente($aluno_id, $turma_id);
        }

        $query = "INSERT INTO aluno_turmas (aluno_id, turma_id, is_corrente) 
                  VALUES (:aluno_id, :turma_id, :is_corrente)
                  ON DUPLICATE KEY UPDATE is_corrente = :is_corrente";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':turma_id', $turma_id);
        $is_corrente_int = $is_corrente ? 1 : 0;
        $stmt->bindParam(':is_corrente', $is_corrente_int);

        return $stmt->execute();
    }

    public function removerTurmaAluno($aluno_id, $turma_id) {
        $query = "DELETE FROM aluno_turmas WHERE aluno_id = :aluno_id AND turma_id = :turma_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':turma_id', $turma_id);

        if ($stmt->execute()) {
            // Se era a turma corrente, atualizar
            $query_check = "SELECT turma_corrente_id FROM users WHERE id = :aluno_id";
            $stmt_check = $this->conn->prepare($query_check);
            $stmt_check->bindParam(':aluno_id', $aluno_id);
            $stmt_check->execute();
            $user = $stmt_check->fetch();
            
            if ($user && $user['turma_corrente_id'] == $turma_id) {
                // Buscar outra turma do aluno para ser corrente
                $query_new = "SELECT turma_id FROM aluno_turmas WHERE aluno_id = :aluno_id LIMIT 1";
                $stmt_new = $this->conn->prepare($query_new);
                $stmt_new->bindParam(':aluno_id', $aluno_id);
                $stmt_new->execute();
                $new_turma = $stmt_new->fetch();
                
                $new_turma_id = $new_turma ? $new_turma['turma_id'] : null;
                $this->definirTurmaCorrente($aluno_id, $new_turma_id);
            }
            
            return true;
        }
        return false;
    }

    public function definirTurmaCorrente($aluno_id, $turma_id) {
        // Atualizar na tabela users
        $query = "UPDATE users SET turma_corrente_id = :turma_id WHERE id = :aluno_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':turma_id', $turma_id);
        $stmt->execute();

        // Atualizar na tabela aluno_turmas (marcar todas como não corrente, depois marcar a selecionada)
        $query2 = "UPDATE aluno_turmas SET is_corrente = 0 WHERE aluno_id = :aluno_id";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->bindParam(':aluno_id', $aluno_id);
        $stmt2->execute();

        if ($turma_id) {
            $query3 = "UPDATE aluno_turmas SET is_corrente = 1 WHERE aluno_id = :aluno_id AND turma_id = :turma_id";
            $stmt3 = $this->conn->prepare($query3);
            $stmt3->bindParam(':aluno_id', $aluno_id);
            $stmt3->bindParam(':turma_id', $turma_id);
            $stmt3->execute();
        }

        return true;
    }

    public function getTurmaCorrente($aluno_id) {
        $query = "SELECT t.* FROM users u
                  INNER JOIN turmas t ON u.turma_corrente_id = t.id
                  WHERE u.id = :aluno_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function changePassword($user_id, $current_password, $new_password) {
        // Verificar se o usuário existe e se a senha atual está correta
        $query = "SELECT id, password, auth_type FROM " . $this->table . " WHERE id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $auth_type = $row['auth_type'] ?? 'local';
            
            // Usuários com autenticação LDAP não podem alterar senha local
            if ($auth_type === 'ldap') {
                return ['success' => false, 'message' => 'Usuários com autenticação LDAP não podem alterar senha local. A senha deve ser alterada no servidor LDAP.'];
            }
            
            // Verificar senha atual
            if (!empty($row['password']) && password_verify($current_password, $row['password'])) {
                // Atualizar senha
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE " . $this->table . " SET password = :password WHERE id = :user_id";
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':user_id', $user_id);
                
                if ($update_stmt->execute()) {
                    return ['success' => true, 'message' => 'Senha alterada com sucesso!'];
                } else {
                    return ['success' => false, 'message' => 'Erro ao alterar senha. Tente novamente.'];
                }
            } else {
                return ['success' => false, 'message' => 'Senha atual incorreta.'];
            }
        }
        
        return ['success' => false, 'message' => 'Usuário não encontrado.'];
    }
}
?>

