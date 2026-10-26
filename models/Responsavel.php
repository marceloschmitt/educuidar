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
        $this->garantirColunasResetSenha();
    }

    private function garantirColunasResetSenha() {
        static $ok = false;
        if ($ok) {
            return;
        }
        try {
            $cols = $this->conn->query("SHOW COLUMNS FROM " . $this->table)->fetchAll(PDO::FETCH_COLUMN);
            if ($cols && !in_array('reset_token_hash', $cols, true)) {
                $this->conn->exec(
                    "ALTER TABLE " . $this->table . "
                     ADD COLUMN reset_token_hash VARCHAR(64) NULL DEFAULT NULL AFTER password,
                     ADD COLUMN reset_token_expires DATETIME NULL DEFAULT NULL AFTER reset_token_hash,
                     ADD INDEX idx_reset_token_hash (reset_token_hash)"
                );
            }
            $ok = true;
        } catch (Exception $e) {
            // Mantém o fluxo mesmo se a migração falhar (ex.: permissão).
        }
    }

    public function create(array $vinculos) {
        if (empty($vinculos)) {
            return false;
        }

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
            foreach ($vinculos as $vinculo) {
                $aluno_id = (int) ($vinculo['aluno_id'] ?? 0);
                $parentesco = $vinculo['parentesco'] ?? null;
                if ($aluno_id <= 0 || !$this->vincularAluno($this->id, $aluno_id, $parentesco)) {
                    $this->conn->rollBack();
                    return false;
                }
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

    public function findByCpf($cpf, $exclude_id = null) {
        $cpf = normalizeCpf($cpf);
        $query = "SELECT * FROM " . $this->table . " WHERE cpf = :cpf";
        if ($exclude_id !== null) {
            $query .= " AND id != :exclude_id";
        }
        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cpf', $cpf);
        if ($exclude_id !== null) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        return $stmt->fetch();
    }

    public function findByEmail($email, $exclude_id = null) {
        $email = strtolower(trim((string) $email));
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email";
        if ($exclude_id !== null) {
            $query .= " AND id != :exclude_id";
        }
        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        if ($exclude_id !== null) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
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
        if ($status === 'suspendido') {
            return 'suspended';
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
                  (SELECT COUNT(*) FROM responsavel_alunos ra WHERE ra.responsavel_id = r.id) as total_alunos,
                  (SELECT COUNT(*) FROM autorizacoes_responsavel ar WHERE ar.responsavel_id = r.id) as total_autorizacoes
                  FROM " . $this->table . " r";
        if ($status !== null && $status !== '') {
            $query .= " WHERE r.status = :status";
        }
        $query .= " ORDER BY
                    (
                        SELECT COALESCE(NULLIF(a.nome_social, ''), a.nome)
                        FROM responsavel_alunos ra
                        INNER JOIN alunos a ON a.id = ra.aluno_id
                        WHERE ra.responsavel_id = r.id
                        ORDER BY COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC
                        LIMIT 1
                    ) ASC,
                    r.nome ASC";

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

    /** Responsáveis aprovados/ativos vinculados ao aluno (para e-mail). */
    public function getAprovadosByAlunoId($aluno_id) {
        $query = "SELECT r.id, r.nome, r.email, ra.parentesco
                  FROM responsaveis r
                  INNER JOIN responsavel_alunos ra ON ra.responsavel_id = r.id
                  WHERE ra.aluno_id = :aluno_id
                    AND r.status = 'aprovado'
                    AND r.ativo = 1
                    AND r.email <> ''
                  ORDER BY r.nome ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
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

    /** Bloqueia o acesso sem apagar vínculos nem autorizações. */
    public function suspender($id) {
        $query = "UPDATE " . $this->table . "
                  SET status = 'suspendido', ativo = 0
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function countAutorizacoes($id) {
        $query = "SELECT COUNT(*) FROM autorizacoes_responsavel WHERE responsavel_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
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

    public function updateNome($id, $nome) {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return false;
        }
        $query = "UPDATE " . $this->table . " SET nome = :nome WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':id', $id);
        if (!$stmt->execute()) {
            return false;
        }
        if ($this->getLoggedId() === (int) $id) {
            $_SESSION['responsavel_nome'] = $nome;
        }
        return true;
    }

    public function updateSenha($id, $senha_atual, $senha_nova) {
        $row = $this->getById($id);
        if (!$row || !password_verify($senha_atual, $row['password'])) {
            return 'senha_atual';
        }
        if (strlen($senha_nova) < 6) {
            return 'senha_curta';
        }
        $hash = password_hash($senha_nova, PASSWORD_DEFAULT);
        $query = "UPDATE " . $this->table . " SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hash);
        $stmt->bindParam(':id', $id);
        return $stmt->execute() ? true : false;
    }

    /**
     * Gera token de redefinição e envia e-mail com o link.
     * Sempre retorna true no fluxo público (não revela se o CPF existe).
     * @return true|'email_config'|'envio'|false
     */
    public function solicitarRedefinicaoSenha($cpf, $link_base) {
        $this->garantirColunasResetSenha();
        $row = $this->findByCpf($cpf);
        if (
            !$row
            || ($row['status'] ?? '') !== 'aprovado'
            || empty($row['ativo'])
            || empty($row['email'])
        ) {
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table . "
             SET reset_token_hash = :hash, reset_token_expires = :expires
             WHERE id = :id"
        );
        $stmt->bindValue(':hash', $token_hash);
        $stmt->bindValue(':expires', $expires);
        $stmt->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return false;
        }

        $link = rtrim($link_base, '?&') . (strpos($link_base, '?') === false ? '?' : '&') . 'token=' . urlencode($token);
        $corpo = self::montarCorpoEmailRedefinicao($row['nome'] ?? '', $link);

        try {
            $configuracao = new Configuracao($this->conn);
            $mailer = new SmtpMailer($configuracao->getEmailConfig());
            $mailer->send([(string) $row['email']], 'EduCuidar — Redefinição de senha', $corpo);
        } catch (Exception $e) {
            return 'envio';
        }

        return true;
    }

    public static function montarCorpoEmailRedefinicao($nome, $link) {
        $nome = trim((string) $nome);
        $saudacao = $nome !== '' ? ('Olá, ' . $nome . '.') : 'Olá.';
        $linhas = [
            $saudacao,
            '',
            'Recebemos um pedido para redefinir a senha do portal de responsáveis do EduCuidar.',
            'Use o link abaixo (válido por 1 hora):',
            '',
            $link,
            '',
            'Se você não solicitou esta alteração, ignore este e-mail.',
            '',
            'EduCuidar',
        ];
        return implode("\n", $linhas);
    }

    public static function urlRedefinirSenha() {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim($dir, '/');
        return $scheme . '://' . $host . $dir . '/redefinir_senha.php';
    }

    /** @return array|null */
    public function findByResetToken($token) {
        $this->garantirColunasResetSenha();
        $token = trim((string) $token);
        if ($token === '' || strlen($token) < 32) {
            return null;
        }
        $hash = hash('sha256', $token);
        $stmt = $this->conn->prepare(
            "SELECT * FROM " . $this->table . "
             WHERE reset_token_hash = :hash
               AND reset_token_expires IS NOT NULL
               AND reset_token_expires >= NOW()
             LIMIT 1"
        );
        $stmt->bindValue(':hash', $hash);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return true|'token'|'senha_curta'|false
     */
    public function redefinirSenhaComToken($token, $senha_nova) {
        $this->garantirColunasResetSenha();
        if (strlen((string) $senha_nova) < 6) {
            return 'senha_curta';
        }
        $row = $this->findByResetToken($token);
        if (!$row) {
            return 'token';
        }
        if (($row['status'] ?? '') !== 'aprovado' || empty($row['ativo'])) {
            return 'token';
        }

        $hash = password_hash($senha_nova, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table . "
             SET password = :password,
                 reset_token_hash = NULL,
                 reset_token_expires = NULL
             WHERE id = :id"
        );
        $stmt->bindValue(':password', $hash);
        $stmt->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
        return $stmt->execute() ? true : false;
    }

    /**
     * Atualização administrativa: nome, cpf, email, status e senha opcional.
     * @return true|string true ou código de erro
     */
    public function updateByAdmin($id, array $dados) {
        $nome = trim((string) ($dados['nome'] ?? ''));
        $cpf = normalizeCpf($dados['cpf'] ?? '');
        $email = strtolower(trim((string) ($dados['email'] ?? '')));
        $status = $dados['status'] ?? 'pendente';
        $senha_nova = $dados['senha_nova'] ?? '';

        if ($nome === '' || strlen($cpf) !== 11 || $email === '') {
            return 'dados';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (!in_array($status, ['pendente', 'aprovado', 'rejeitado', 'suspendido'], true)) {
            return 'status';
        }
        if ($this->findByCpf($cpf, $id)) {
            return 'cpf_duplicado';
        }
        if ($this->findByEmail($email, $id)) {
            return 'email_duplicado';
        }
        if ($senha_nova !== '' && strlen($senha_nova) < 6) {
            return 'senha_curta';
        }

        $ativo = ($status === 'aprovado') ? 1 : 0;

        $query = "UPDATE " . $this->table . "
                  SET nome = :nome, cpf = :cpf, email = :email,
                      status = :status, ativo = :ativo
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':cpf', $cpf);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':ativo', $ativo);
        $stmt->bindParam(':id', $id);
        if (!$stmt->execute()) {
            return false;
        }

        if ($senha_nova !== '') {
            $hash = password_hash($senha_nova, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE " . $this->table . " SET password = :password WHERE id = :id");
            $stmt->bindParam(':password', $hash);
            $stmt->bindParam(':id', $id);
            if (!$stmt->execute()) {
                return false;
            }
        }

        return true;
    }

    public function desvincularAluno($vinculo_id, $responsavel_id) {
        $query = "DELETE FROM responsavel_alunos
                  WHERE id = :id AND responsavel_id = :responsavel_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $vinculo_id);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        return $stmt->execute();
    }

    public function updateParentescoVinculo($vinculo_id, $responsavel_id, $parentesco) {
        $parentesco = $parentesco !== null && $parentesco !== '' ? $parentesco : null;
        $query = "UPDATE responsavel_alunos
                  SET parentesco = :parentesco
                  WHERE id = :id AND responsavel_id = :responsavel_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':parentesco', $parentesco);
        $stmt->bindParam(':id', $vinculo_id);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        return $stmt->execute();
    }

    public function jaVinculado($responsavel_id, $aluno_id) {
        $query = "SELECT id FROM responsavel_alunos
                  WHERE responsavel_id = :responsavel_id AND aluno_id = :aluno_id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->execute();
        return (bool) $stmt->fetch();
    }
}
