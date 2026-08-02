<?php
/**
 * Curso model
 */

class Curso {
    private $conn;
    private $table = 'cursos';

    public $id;
    public $nome;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (nome) 
                  VALUES (:nome)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':nome', $this->nome);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getAll() {
        $query = "SELECT c.*, 
                  COUNT(DISTINCT t.id) as total_turmas
                  FROM " . $this->table . " c
                  LEFT JOIN turmas t ON c.id = t.curso_id
                  GROUP BY c.id
                  ORDER BY c.nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET nome = :nome
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nome', $this->nome);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
    
    public function getTurmasDoCurso($curso_id) {
        $query = "SELECT t.* 
                  FROM turmas t
                  WHERE t.curso_id = :curso_id
                  ORDER BY t.ano_civil DESC, t.ano_curso ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':curso_id', $curso_id);
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
}
?>

