<?php
/**
 * Aluno model
 */

class Aluno {
    private $conn;
    private $table = 'alunos';

    public $id;
    public $nome;
    public $nome_social;
    public $email;
    public $telefone_celular;
    public $data_nascimento;
    public $numero_matricula;
    public $endereco;
    public $foto;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (nome, nome_social, email, telefone_celular, data_nascimento, numero_matricula, endereco, foto) 
                  VALUES (:nome, :nome_social, :email, :telefone_celular, :data_nascimento, :numero_matricula, :endereco, :foto)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':nome', $this->nome);
        $nome_social = !empty($this->nome_social) ? $this->nome_social : null;
        $stmt->bindParam(':nome_social', $nome_social);
        $email = !empty($this->email) ? $this->email : null;
        $stmt->bindParam(':email', $email);
        $telefone = !empty($this->telefone_celular) ? $this->telefone_celular : null;
        $stmt->bindParam(':telefone_celular', $telefone);
        $data_nascimento = !empty($this->data_nascimento) ? $this->data_nascimento : null;
        $stmt->bindParam(':data_nascimento', $data_nascimento);
        $numero_matricula = !empty($this->numero_matricula) ? $this->numero_matricula : null;
        $stmt->bindParam(':numero_matricula', $numero_matricula);
        $endereco = !empty($this->endereco) ? $this->endereco : null;
        $stmt->bindParam(':endereco', $endereco);
        $foto = !empty($this->foto) ? $this->foto : null;
        $stmt->bindParam(':foto', $foto);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getAll() {
        $query = "SELECT a.* FROM " . $this->table . " a
                  ORDER BY a.nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id) {
        $query = "SELECT a.* FROM " . $this->table . " a
                  WHERE a.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET nome = :nome,
                      nome_social = :nome_social,
                      email = :email,
                      telefone_celular = :telefone_celular,
                      data_nascimento = :data_nascimento,
                      numero_matricula = :numero_matricula,
                      endereco = :endereco,
                      foto = :foto
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nome', $this->nome);
        $nome_social = !empty($this->nome_social) ? $this->nome_social : null;
        $stmt->bindParam(':nome_social', $nome_social);
        $email = !empty($this->email) ? $this->email : null;
        $stmt->bindParam(':email', $email);
        $telefone = !empty($this->telefone_celular) ? $this->telefone_celular : null;
        $stmt->bindParam(':telefone_celular', $telefone);
        $data_nascimento = !empty($this->data_nascimento) ? $this->data_nascimento : null;
        $stmt->bindParam(':data_nascimento', $data_nascimento);
        $numero_matricula = !empty($this->numero_matricula) ? $this->numero_matricula : null;
        $stmt->bindParam(':numero_matricula', $numero_matricula);
        $endereco = !empty($this->endereco) ? $this->endereco : null;
        $stmt->bindParam(':endereco', $endereco);
        $foto = !empty($this->foto) ? $this->foto : null;
        $stmt->bindParam(':foto', $foto);

        if ($stmt->execute()) {
            return true;
        }
        return false;
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

    public function getTurmasAluno($aluno_id) {
        $query = "SELECT t.*, at.created_at as associado_em
                  FROM aluno_turmas at
                  INNER JOIN turmas t ON at.turma_id = t.id
                  WHERE at.aluno_id = :aluno_id
                  ORDER BY t.ano_civil DESC, t.ano_curso ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function adicionarTurmaAluno($aluno_id, $turma_id) {
        $query = "INSERT INTO aluno_turmas (aluno_id, turma_id) 
                  VALUES (:aluno_id, :turma_id)
                  ON DUPLICATE KEY UPDATE aluno_id = aluno_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':turma_id', $turma_id);

        return $stmt->execute();
    }

    public function removerTurmaAluno($aluno_id, $turma_id) {
        $query = "DELETE FROM aluno_turmas WHERE aluno_id = :aluno_id AND turma_id = :turma_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':turma_id', $turma_id);

        return $stmt->execute();
    }

    public function getAlunosTurmasAnoCorrente($ano_corrente, $turma_id = null, $curso_id = null) {
        $query = "SELECT DISTINCT a.*, 
                  t.id as turma_id, t.ano_civil, t.ano_curso,
                  c.id as curso_id, c.nome as curso_nome
                  FROM " . $this->table . " a
                  INNER JOIN aluno_turmas at ON a.id = at.aluno_id
                  INNER JOIN turmas t ON at.turma_id = t.id
                  INNER JOIN cursos c ON t.curso_id = c.id
                  WHERE t.ano_civil = :ano_corrente";
        
        $params = [':ano_corrente' => $ano_corrente];
        
        if ($turma_id) {
            $query .= " AND t.id = :turma_id";
            $params[':turma_id'] = $turma_id;
        }
        
        if ($curso_id) {
            $query .= " AND c.id = :curso_id";
            $params[':curso_id'] = $curso_id;
        }
        
        $query .= " ORDER BY c.nome ASC, t.ano_curso ASC, a.nome ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
?>

