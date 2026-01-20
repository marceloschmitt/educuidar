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
    public $pessoa_referencia;
    public $telefone_pessoa_referencia;
    public $rede_atendimento;
    public $auxilio_estudantil;
    public $nee;
    public $indigena;
    public $pei;
    public $profissionais_referencia;
    public $outras_observacoes;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (nome, nome_social, email, telefone_celular, data_nascimento, numero_matricula, endereco, foto, 
                   pessoa_referencia, telefone_pessoa_referencia, rede_atendimento, auxilio_estudantil, nee, 
                   indigena, pei, profissionais_referencia, outras_observacoes) 
                  VALUES (:nome, :nome_social, :email, :telefone_celular, :data_nascimento, :numero_matricula, :endereco, :foto, 
                          :pessoa_referencia, :telefone_pessoa_referencia, :rede_atendimento, :auxilio_estudantil, :nee, 
                          :indigena, :pei, :profissionais_referencia, :outras_observacoes)";

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
        
        $pessoa_referencia = !empty($this->pessoa_referencia) ? $this->pessoa_referencia : null;
        $stmt->bindParam(':pessoa_referencia', $pessoa_referencia);
        $telefone_pessoa_referencia = !empty($this->telefone_pessoa_referencia) ? $this->telefone_pessoa_referencia : null;
        $stmt->bindParam(':telefone_pessoa_referencia', $telefone_pessoa_referencia);
        $rede_atendimento = !empty($this->rede_atendimento) ? $this->rede_atendimento : null;
        $stmt->bindParam(':rede_atendimento', $rede_atendimento);
        $auxilio_estudantil = isset($this->auxilio_estudantil) ? ($this->auxilio_estudantil ? 1 : 0) : 0;
        $stmt->bindParam(':auxilio_estudantil', $auxilio_estudantil, PDO::PARAM_INT);
        $nee = !empty($this->nee) ? $this->nee : null;
        $stmt->bindParam(':nee', $nee);
        $indigena = isset($this->indigena) ? ($this->indigena ? 1 : 0) : 0;
        $stmt->bindParam(':indigena', $indigena, PDO::PARAM_INT);
        $pei = isset($this->pei) ? ($this->pei ? 1 : 0) : 0;
        $stmt->bindParam(':pei', $pei, PDO::PARAM_INT);
        $profissionais_referencia = !empty($this->profissionais_referencia) ? $this->profissionais_referencia : null;
        $stmt->bindParam(':profissionais_referencia', $profissionais_referencia);
        $outras_observacoes = !empty($this->outras_observacoes) ? $this->outras_observacoes : null;
        $stmt->bindParam(':outras_observacoes', $outras_observacoes);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getAll() {
        $query = "SELECT a.* FROM " . $this->table . " a
                  ORDER BY COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC";

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
                      foto = :foto,
                      pessoa_referencia = :pessoa_referencia,
                      telefone_pessoa_referencia = :telefone_pessoa_referencia,
                      rede_atendimento = :rede_atendimento,
                      auxilio_estudantil = :auxilio_estudantil,
                      nee = :nee,
                      indigena = :indigena,
                      pei = :pei,
                      profissionais_referencia = :profissionais_referencia,
                      outras_observacoes = :outras_observacoes
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
        
        $pessoa_referencia = !empty($this->pessoa_referencia) ? $this->pessoa_referencia : null;
        $stmt->bindParam(':pessoa_referencia', $pessoa_referencia);
        $telefone_pessoa_referencia = !empty($this->telefone_pessoa_referencia) ? $this->telefone_pessoa_referencia : null;
        $stmt->bindParam(':telefone_pessoa_referencia', $telefone_pessoa_referencia);
        $rede_atendimento = !empty($this->rede_atendimento) ? $this->rede_atendimento : null;
        $stmt->bindParam(':rede_atendimento', $rede_atendimento);
        $auxilio_estudantil = isset($this->auxilio_estudantil) ? ($this->auxilio_estudantil ? 1 : 0) : 0;
        $stmt->bindParam(':auxilio_estudantil', $auxilio_estudantil, PDO::PARAM_INT);
        $nee = !empty($this->nee) ? $this->nee : null;
        $stmt->bindParam(':nee', $nee);
        $indigena = isset($this->indigena) ? ($this->indigena ? 1 : 0) : 0;
        $stmt->bindParam(':indigena', $indigena, PDO::PARAM_INT);
        $pei = isset($this->pei) ? ($this->pei ? 1 : 0) : 0;
        $stmt->bindParam(':pei', $pei, PDO::PARAM_INT);
        $profissionais_referencia = !empty($this->profissionais_referencia) ? $this->profissionais_referencia : null;
        $stmt->bindParam(':profissionais_referencia', $profissionais_referencia);
        $outras_observacoes = !empty($this->outras_observacoes) ? $this->outras_observacoes : null;
        $stmt->bindParam(':outras_observacoes', $outras_observacoes);

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
        
        $query .= " ORDER BY c.nome ASC, t.ano_curso ASC, COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
?>

