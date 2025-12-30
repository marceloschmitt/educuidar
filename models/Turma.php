<?php
/**
 * Turma model
 */

class Turma {
    private $conn;
    private $table = 'turmas';

    public $id;
    public $curso_id;
    public $ano_civil;
    public $ano_curso;
    public $created_at;
    public $error_message;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        // Check if turma already exists before trying to insert
        $check_query = "SELECT id FROM " . $this->table . " 
                       WHERE curso_id = :curso_id 
                       AND ano_civil = :ano_civil 
                       AND ano_curso = :ano_curso
                       LIMIT 1";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':curso_id', $this->curso_id);
        $check_stmt->bindParam(':ano_civil', $this->ano_civil);
        $check_stmt->bindParam(':ano_curso', $this->ano_curso);
        $check_stmt->execute();
        
        if ($check_stmt->fetch()) {
            $this->error_message = 'Esta turma já existe para este curso, ano civil e ano do curso.';
            return false;
        }

        try {
            $query = "INSERT INTO " . $this->table . " 
                      (curso_id, ano_civil, ano_curso) 
                      VALUES (:curso_id, :ano_civil, :ano_curso)";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':curso_id', $this->curso_id);
            $stmt->bindParam(':ano_civil', $this->ano_civil);
            $stmt->bindParam(':ano_curso', $this->ano_curso);

            if ($stmt->execute()) {
                return true;
            }
            return false;
        } catch (PDOException $e) {
            // Check if it's a duplicate entry error (SQLSTATE 23000)
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            if ($errorCode == 23000 || strpos($errorMessage, 'Duplicate entry') !== false || strpos($errorMessage, 'unique_turma') !== false) {
                $this->error_message = 'Esta turma já existe para este curso, ano civil e ano do curso.';
            } else {
                $this->error_message = 'Erro ao criar turma: ' . $errorMessage;
            }
            error_log("Erro ao criar turma: " . $errorMessage);
            return false;
        }
    }

    public function getAll() {
        $query = "SELECT t.*, 
                  c.nome as curso_nome,
                  COUNT(DISTINCT at.aluno_id) as total_alunos
                  FROM " . $this->table . " t
                  INNER JOIN cursos c ON t.curso_id = c.id
                  LEFT JOIN aluno_turmas at ON t.id = at.turma_id
                  GROUP BY t.id
                  ORDER BY c.nome ASC, t.ano_civil DESC, t.ano_curso ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id) {
        $query = "SELECT t.*, c.nome as curso_nome
                  FROM " . $this->table . " t
                  INNER JOIN cursos c ON t.curso_id = c.id
                  WHERE t.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function update() {
        try {
            // Check if the new combination already exists (excluding current record)
            $check_query = "SELECT id FROM " . $this->table . " 
                           WHERE curso_id = :curso_id 
                           AND ano_civil = :ano_civil 
                           AND ano_curso = :ano_curso 
                           AND id != :id
                           LIMIT 1";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':curso_id', $this->curso_id);
            $check_stmt->bindParam(':ano_civil', $this->ano_civil);
            $check_stmt->bindParam(':ano_curso', $this->ano_curso);
            $check_stmt->bindParam(':id', $this->id);
            $check_stmt->execute();
            
            if ($check_stmt->fetch()) {
                $this->error_message = 'Esta turma já existe para este curso, ano civil e ano do curso.';
                return false;
            }

            $query = "UPDATE " . $this->table . " 
                      SET curso_id = :curso_id,
                          ano_civil = :ano_civil, 
                          ano_curso = :ano_curso
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':id', $this->id);
            $stmt->bindParam(':curso_id', $this->curso_id);
            $stmt->bindParam(':ano_civil', $this->ano_civil);
            $stmt->bindParam(':ano_curso', $this->ano_curso);

            if ($stmt->execute()) {
                return true;
            }
            return false;
        } catch (PDOException $e) {
            // Check if it's a duplicate entry error
            if ($e->getCode() == 23000) {
                $this->error_message = 'Esta turma já existe para este curso, ano civil e ano do curso.';
            } else {
                $this->error_message = 'Erro ao atualizar turma: ' . $e->getMessage();
            }
            error_log("Erro ao atualizar turma: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTurmasPorAnoCorrente($ano_corrente) {
        $query = "SELECT t.*, 
                  c.nome as curso_nome
                  FROM " . $this->table . " t
                  INNER JOIN cursos c ON t.curso_id = c.id
                  WHERE t.ano_civil = :ano_corrente
                  ORDER BY c.nome ASC, t.ano_curso ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ano_corrente', $ano_corrente);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getAlunos($turma_id) {
        $query = "SELECT a.id, a.nome, a.email, a.telefone_celular 
                  FROM alunos a
                  INNER JOIN aluno_turmas at ON a.id = at.aluno_id
                  WHERE at.turma_id = :turma_id
                  ORDER BY a.nome";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':turma_id', $turma_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getNomeFormatado() {
        return $this->ano_curso . "º Ano - " . $this->ano_civil;
    }
    
    public function getByCurso($curso_id) {
        $query = "SELECT t.*, 
                  c.nome as curso_nome,
                  COUNT(DISTINCT at.aluno_id) as total_alunos
                  FROM " . $this->table . " t
                  INNER JOIN cursos c ON t.curso_id = c.id
                  LEFT JOIN aluno_turmas at ON t.id = at.turma_id
                  WHERE t.curso_id = :curso_id
                  GROUP BY t.id
                  ORDER BY t.ano_civil DESC, t.ano_curso ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':curso_id', $curso_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
?>

