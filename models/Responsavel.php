<?php
/**
 * Responsavel model — portal separado de staff (sem LDAP).
 */

class Responsavel {
    private $conn;
    private $table = 'responsaveis';

    public $id;
    public $nome;
    public $cpf;
    public $email;
    public $password;
    public $ativo;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($aluno_id, $parentesco = null) {
        try {
            $this->conn->beginTransaction();

            $query = "INSERT INTO " . $this->table . "
                      (nome, cpf, email, password, ativo, status)
                      VALUES (:nome, :cpf, :email, :password, 0, 'pendente')";

            $stmt = $this->conn->prepare($query);
            $hash = password_hash($this->password, PASSWORD_DEFAULT);
            $stmt->bindParam(':nome', $this->nome);
            $stmt->bindParam(':cpf', $this->cpf);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $hash);

            if (!$stmt->execute()) {
                $this->conn->rollBack();
                return false;
            }

            $this->id = (int) $this->conn->lastInsertId();
            if (!$this->vincularAluno($this->id, $aluno_id, $parentesco)) {
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function vincularAluno($responsavel_id, $aluno_id, $parentesco = null) {
        $query = "INSERT INTO responsavel_alunos (responsavel_id, aluno_id, parentesco)
                  VALUES (:responsavel_id, :aluno_id, :parentesco)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $parentesco = $parentesco !== null && $parentesco !== '' ? $parentesco : null;
        $stmt->bindParam(':parentesco', $parentesco);
        return $stmt->execute();
    }

    public function findByCpf($cpf) {
        $cpf = normalizeCpf($cpf);
        $query = "SELECT * FROM " . $this->table . " WHERE cpf = :cpf LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cpf', $cpf);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function findByEmail($email) {
        $email = strtolower(trim((string) $email));
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Login por CPF. Só permite status=aprovado e ativo=1.
     */
    public function login($cpf, $password) {
        $row = $this->findByCpf($cpf);
        if (!$row) {
            return false;
        }
        if (!password_verify($password, $row['password'])) {
            return false;
        }

        $status = $row['status'] ?? '';
        if ($status === 'pendente') {
            return 'pending';
        }
        if ($status === 'rejeitado' || empty($row['ativo'])) {
            return 'rejected';
        }
        if ($status !== 'aprovado') {
            return 'pending';
        }

        $_SESSION['responsavel_id'] = (int) $row['id'];
        $_SESSION['responsavel_nome'] = $row['nome'];
        $_SESSION['responsavel_cpf'] = $row['cpf'];
        return true;
    }

    public function logout() {
        unset($_SESSION['responsavel_id'], $_SESSION['responsavel_nome'], $_SESSION['responsavel_cpf']);
    }

    public function isLoggedIn() {
        return !empty($_SESSION['responsavel_id']);
    }

    public function getLoggedId() {
        return isset($_SESSION['responsavel_id']) ? (int) $_SESSION['responsavel_id'] : null;
    }

    public function listByStatus($status = null) {
        $query = "SELECT r.*,
                  (SELECT COUNT(*) FROM responsavel_alunos ra WHERE ra.responsavel_id = r.id) as total_alunos
                  FROM " . $this->table . " r";
        if ($status !== null && $status !== '') {
            $query .= " WHERE r.status = :status";
        }
        $query .= " ORDER BY
                    CASE r.status
                        WHEN 'pendente' THEN 0
                        WHEN 'aprovado' THEN 1
                        ELSE 2
                    END,
                    r.created_at DESC";

        $stmt = $this->conn->prepare($query);
        if ($status !== null && $status !== '') {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAlunosVinculados($responsavel_id) {
        $query = "SELECT a.id, a.nome, a.nome_social, a.numero_matricula, a.cpf, a.foto,
                  ra.parentesco, ra.id as vinculo_id
                  FROM responsavel_alunos ra
                  INNER JOIN alunos a ON a.id = ra.aluno_id
                  WHERE ra.responsavel_id = :responsavel_id
                  ORDER BY COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function aprovar($id) {
        $query = "UPDATE " . $this->table . "
                  SET status = 'aprovado', ativo = 1
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function rejeitar($id) {
        $query = "UPDATE " . $this->table . "
                  SET status = 'rejeitado', ativo = 0
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    /**
     * Busca aluno pelo CPF (aceita CPF formatado ou só dígitos no banco).
     */
    public function findAlunoIdByCpf($cpf) {
        $cpf = normalizeCpf($cpf);
        if (strlen($cpf) !== 11) {
            return null;
        }

        $query = "SELECT id FROM alunos
                  WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cpf, ''), '.', ''), '-', ''), ' ', ''), '/', '') = :cpf
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cpf', $cpf);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Alunos do ano civil sem responsável aprovado/ativo.
     */
    public function getAlunosSemResponsavel($ano_civil, $curso_id = null, $turma_id = null) {
        $query = "SELECT DISTINCT a.id, a.nome, a.nome_social, a.numero_matricula, a.cpf,
                  t.id as turma_id, t.ano_curso, t.ano_civil,
                  c.id as curso_id, c.nome as curso_nome
                  FROM alunos a
                  INNER JOIN aluno_turmas at ON a.id = at.aluno_id
                  INNER JOIN turmas t ON at.turma_id = t.id
                  INNER JOIN cursos c ON t.curso_id = c.id
                  WHERE t.ano_civil = :ano_civil
                    AND COALESCE(a.desistente, 0) = 0
                    AND NOT EXISTS (
                        SELECT 1
                        FROM responsavel_alunos ra
                        INNER JOIN responsaveis r ON r.id = ra.responsavel_id
                        WHERE ra.aluno_id = a.id
                          AND r.status = 'aprovado'
                          AND r.ativo = 1
                    )";

        $params = [':ano_civil' => $ano_civil];
        if ($curso_id) {
            $query .= " AND c.id = :curso_id";
            $params[':curso_id'] = $curso_id;
        }
        if ($turma_id) {
            $query .= " AND t.id = :turma_id";
            $params[':turma_id'] = $turma_id;
        }

        $query .= " ORDER BY COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
