<?php
/**
 * Event model - handles all event types
 */

class Evento {
    private $conn;
    private $table = 'eventos';

    public $id;
    public $aluno_id;
    public $turma_id;
    public $tipo_evento_id;
    public $data_evento;
    public $hora_evento;
    public $observacoes;
    public $prontuario_cae;
    public $registrado_por;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        // Buscar primeira turma do ano corrente do aluno se não foi fornecido
        if (empty($this->turma_id)) {
            // Get ano corrente from configuracoes
            $config_query = "SELECT valor FROM configuracoes WHERE chave = 'ano_corrente' LIMIT 1";
            $config_stmt = $this->conn->prepare($config_query);
            $config_stmt->execute();
            $config = $config_stmt->fetch();
            $ano_corrente = $config ? (int)$config['valor'] : (int)date('Y');
            
            $aluno_query = "SELECT t.id 
                           FROM aluno_turmas at
                           INNER JOIN turmas t ON at.turma_id = t.id
                           WHERE at.aluno_id = :aluno_id AND t.ano_civil = :ano_corrente
                           LIMIT 1";
            $aluno_stmt = $this->conn->prepare($aluno_query);
            $aluno_stmt->bindParam(':aluno_id', $this->aluno_id);
            $aluno_stmt->bindParam(':ano_corrente', $ano_corrente);
            $aluno_stmt->execute();
            $turma = $aluno_stmt->fetch();
            $this->turma_id = $turma['id'] ?? null;
        }

        $query = "INSERT INTO " . $this->table . " 
                  (aluno_id, turma_id, tipo_evento_id, data_evento, hora_evento, observacoes, prontuario_cae, registrado_por) 
                  VALUES (:aluno_id, :turma_id, :tipo_evento_id, :data_evento, :hora_evento, :observacoes, :prontuario_cae, :registrado_por)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':aluno_id', $this->aluno_id);
        $stmt->bindParam(':turma_id', $this->turma_id);
        $stmt->bindParam(':tipo_evento_id', $this->tipo_evento_id);
        $stmt->bindParam(':data_evento', $this->data_evento);
        $stmt->bindParam(':hora_evento', $this->hora_evento);
        $stmt->bindParam(':observacoes', $this->observacoes);
        $stmt->bindParam(':prontuario_cae', $this->prontuario_cae);
        $stmt->bindParam(':registrado_por', $this->registrado_por);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getAll($registrado_por = null, $ano_civil = null) {
        $query = "SELECT e.id, e.aluno_id, e.turma_id, e.tipo_evento_id, 
                  e.data_evento, e.hora_evento, e.observacoes, e.prontuario_cae, e.registrado_por, e.created_at,
                  a.nome as aluno_nome,
                  t.ano_civil, t.ano_curso,
                  c.id as curso_id, c.nome as curso_nome,
                  te.nome as tipo_evento_nome, te.cor as tipo_evento_cor, te.gera_prontuario_cae as tipo_evento_gera_prontuario, ut.slug as tipo_evento_prontuario_user_type,
                  u.full_name as registrado_por_nome
                  FROM " . $this->table . " e
                  LEFT JOIN alunos a ON e.aluno_id = a.id
                  LEFT JOIN turmas t ON e.turma_id = t.id
                  LEFT JOIN cursos c ON t.curso_id = c.id
                  LEFT JOIN tipos_eventos te ON e.tipo_evento_id = te.id
                  LEFT JOIN user_types ut ON te.prontuario_user_type_id = ut.id
                  LEFT JOIN users u ON e.registrado_por = u.id";

        $where = [];
        if ($registrado_por !== null) {
            $where[] = "e.registrado_por = :registrado_por";
        }
        if ($ano_civil !== null) {
            $where[] = "t.ano_civil = :ano_civil";
        }
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " ORDER BY e.data_evento DESC, e.hora_evento DESC";

        $stmt = $this->conn->prepare($query);
        if ($registrado_por !== null) {
            $stmt->bindParam(':registrado_por', $registrado_por);
        }
        if ($ano_civil !== null) {
            $stmt->bindParam(':ano_civil', $ano_civil);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getByAluno($aluno_id) {
        $query = "SELECT e.*, a.nome as aluno_nome,
                  t.ano_civil, t.ano_curso,
                  c.nome as curso_nome,
                  te.nome as tipo_evento_nome, te.cor as tipo_evento_cor, te.gera_prontuario_cae as tipo_evento_gera_prontuario, ut.slug as tipo_evento_prontuario_user_type
                  FROM " . $this->table . " e
                  LEFT JOIN alunos a ON e.aluno_id = a.id
                  LEFT JOIN turmas t ON e.turma_id = t.id
                  LEFT JOIN cursos c ON t.curso_id = c.id
                  LEFT JOIN tipos_eventos te ON e.tipo_evento_id = te.id
                  LEFT JOIN user_types ut ON te.prontuario_user_type_id = ut.id
                  WHERE e.aluno_id = :aluno_id
                  ORDER BY e.data_evento DESC, e.hora_evento DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getByAlunoETurma($aluno_id, $turma_id, $registrado_por = null) {
        $query = "SELECT e.id, e.aluno_id, e.turma_id, e.tipo_evento_id, 
                  e.data_evento, e.hora_evento, e.observacoes, e.prontuario_cae, e.registrado_por, e.created_at,
                  a.nome as aluno_nome,
                  t.ano_civil, t.ano_curso,
                  c.nome as curso_nome,
                  te.nome as tipo_evento_nome, te.cor as tipo_evento_cor, te.gera_prontuario_cae as tipo_evento_gera_prontuario, ut.slug as tipo_evento_prontuario_user_type,
                  u.full_name as registrado_por_nome
                  FROM " . $this->table . " e
                  LEFT JOIN alunos a ON e.aluno_id = a.id
                  LEFT JOIN turmas t ON e.turma_id = t.id
                  LEFT JOIN cursos c ON t.curso_id = c.id
                  LEFT JOIN tipos_eventos te ON e.tipo_evento_id = te.id
                  LEFT JOIN user_types ut ON te.prontuario_user_type_id = ut.id
                  LEFT JOIN users u ON e.registrado_por = u.id
                  WHERE e.aluno_id = :aluno_id AND e.turma_id = :turma_id";
        
        if ($registrado_por !== null) {
            $query .= " AND e.registrado_por = :registrado_por";
        }
        
        $query .= " ORDER BY e.data_evento DESC, e.hora_evento DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':turma_id', $turma_id);
        if ($registrado_por !== null) {
            $stmt->bindParam(':registrado_por', $registrado_por);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id) {
        $query = "SELECT e.*, a.nome as aluno_nome,
                  t.ano_civil, t.ano_curso,
                  c.nome as curso_nome,
                  te.nome as tipo_evento_nome, te.cor as tipo_evento_cor, te.gera_prontuario_cae as tipo_evento_gera_prontuario, ut.slug as tipo_evento_prontuario_user_type
                  FROM " . $this->table . " e
                  LEFT JOIN alunos a ON e.aluno_id = a.id
                  LEFT JOIN turmas t ON e.turma_id = t.id
                  LEFT JOIN cursos c ON t.curso_id = c.id
                  LEFT JOIN tipos_eventos te ON e.tipo_evento_id = te.id
                  LEFT JOIN user_types ut ON te.prontuario_user_type_id = ut.id
                  WHERE e.id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function update($user_id = null, $check_time_limit = false) {
        // Se check_time_limit for true, verificar se o evento foi criado há menos de 1 hora
        if ($check_time_limit && $user_id !== null) {
            $query = "SELECT registrado_por, created_at FROM " . $this->table . " WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            $event = $stmt->fetch();
            
            if (!$event) {
                return false; // Evento não encontrado
            }
            
            // Verificar se o evento foi criado pelo usuário
            if ($event['registrado_por'] != $user_id) {
                return false; // Evento não foi criado por este usuário
            }
            
            // Verificar se foi criado há menos de 1 hora
            $created_at = strtotime($event['created_at']);
            $now = time();
            $diff_seconds = $now - $created_at;
            
            if ($diff_seconds > 3600) { // 3600 segundos = 1 hora
                return false; // Passou mais de 1 hora
            }
        }
        
        // Buscar primeira turma do ano corrente do aluno se não foi fornecido
        if (empty($this->turma_id)) {
            // Get ano corrente from configuracoes
            $config_query = "SELECT valor FROM configuracoes WHERE chave = 'ano_corrente' LIMIT 1";
            $config_stmt = $this->conn->prepare($config_query);
            $config_stmt->execute();
            $config = $config_stmt->fetch();
            $ano_corrente = $config ? (int)$config['valor'] : (int)date('Y');
            
            $aluno_query = "SELECT t.id 
                           FROM aluno_turmas at
                           INNER JOIN turmas t ON at.turma_id = t.id
                           WHERE at.aluno_id = :aluno_id AND t.ano_civil = :ano_corrente
                           LIMIT 1";
            $aluno_stmt = $this->conn->prepare($aluno_query);
            $aluno_stmt->bindParam(':aluno_id', $this->aluno_id);
            $aluno_stmt->bindParam(':ano_corrente', $ano_corrente);
            $aluno_stmt->execute();
            $turma = $aluno_stmt->fetch();
            $this->turma_id = $turma['id'] ?? null;
        }

        $query = "UPDATE " . $this->table . " 
                  SET aluno_id = :aluno_id, 
                      turma_id = :turma_id,
                      tipo_evento_id = :tipo_evento_id, 
                      data_evento = :data_evento, 
                      hora_evento = :hora_evento, 
                      observacoes = :observacoes,
                      prontuario_cae = :prontuario_cae
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':aluno_id', $this->aluno_id);
        $stmt->bindParam(':turma_id', $this->turma_id);
        $stmt->bindParam(':tipo_evento_id', $this->tipo_evento_id);
        $stmt->bindParam(':data_evento', $this->data_evento);
        $stmt->bindParam(':hora_evento', $this->hora_evento);
        $stmt->bindParam(':observacoes', $this->observacoes);
        $stmt->bindParam(':prontuario_cae', $this->prontuario_cae);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete($user_id = null, $check_time_limit = false) {
        // Se check_time_limit for true, verificar se o evento foi criado há menos de 1 hora
        if ($check_time_limit && $user_id !== null) {
            $query = "SELECT registrado_por, created_at FROM " . $this->table . " WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            $event = $stmt->fetch();
            
            if (!$event) {
                return false; // Evento não encontrado
            }
            
            // Verificar se o evento foi criado pelo usuário
            if ($event['registrado_por'] != $user_id) {
                return false; // Evento não foi criado por este usuário
            }
            
            // Verificar se foi criado há menos de 1 hora
            $created_at = strtotime($event['created_at']);
            $now = time();
            $diff_seconds = $now - $created_at;
            
            if ($diff_seconds > 3600) { // 3600 segundos = 1 hora
                return false; // Passou mais de 1 hora
            }
        }
        
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getEstatisticas($aluno_id = null, $turma_id = null, $curso_id = null, $ano_corrente = null, $registrado_por = null) {
        $where = [];
        $params = [];
        
        if ($aluno_id) {
            $where[] = "e.aluno_id = :aluno_id";
            $params[':aluno_id'] = $aluno_id;
        }
        
        if ($turma_id) {
            $where[] = "e.turma_id = :turma_id";
            $params[':turma_id'] = $turma_id;
        }
        
        if ($curso_id) {
            $where[] = "c.id = :curso_id";
            $params[':curso_id'] = $curso_id;
        }
        
        if ($ano_corrente) {
            $where[] = "t.ano_civil = :ano_corrente";
            $params[':ano_corrente'] = $ano_corrente;
        }
        
        if ($registrado_por !== null) {
            $where[] = "e.registrado_por = :registrado_por";
            $params[':registrado_por'] = $registrado_por;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $query = "SELECT te.id as tipo_evento_id, te.nome as tipo_evento_nome, te.cor as tipo_evento_cor, COUNT(*) as total 
                  FROM " . $this->table . " e
                  LEFT JOIN tipos_eventos te ON e.tipo_evento_id = te.id
                  LEFT JOIN turmas t ON e.turma_id = t.id
                  LEFT JOIN cursos c ON t.curso_id = c.id
                  $where_clause
                  GROUP BY te.id
                  ORDER BY te.nome ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByAluno($aluno_id, $registrado_por = null, $ano_civil = null) {
        $query = "SELECT COUNT(*) as total 
                  FROM " . $this->table . " e
                  LEFT JOIN turmas t ON e.turma_id = t.id";

        $where = ["e.aluno_id = :aluno_id"];
        if ($registrado_por !== null) {
            $where[] = "e.registrado_por = :registrado_por";
        }
        if ($ano_civil !== null) {
            $where[] = "t.ano_civil = :ano_civil";
        }

        $query .= " WHERE " . implode(" AND ", $where);
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        if ($registrado_por !== null) {
            $stmt->bindParam(':registrado_por', $registrado_por);
        }
        if ($ano_civil !== null) {
            $stmt->bindParam(':ano_civil', $ano_civil);
        }
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
}
?>

