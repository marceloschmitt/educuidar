<?php
/**
 * TipoEvento model
 */

class TipoEvento {
    private $conn;
    private $table = 'tipos_eventos';

    public $id;
    public $nome;
    public $cor;
    public $gera_prontuario;
    public $prontuario_user_type_id;
    public $ativo;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (nome, cor, gera_prontuario, prontuario_user_type_id, ativo) 
                  VALUES (:nome, :cor, :gera_prontuario, :prontuario_user_type_id, :ativo)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':cor', $this->cor);
        $gera_prontuario = isset($this->gera_prontuario) ? ($this->gera_prontuario ? 1 : 0) : 0;
        $stmt->bindParam(':gera_prontuario', $gera_prontuario);
        $prontuario_user_type_id = !empty($this->prontuario_user_type_id) ? $this->prontuario_user_type_id : null;
        $stmt->bindParam(':prontuario_user_type_id', $prontuario_user_type_id);
        $ativo = isset($this->ativo) ? ($this->ativo ? 1 : 0) : 1;
        $stmt->bindParam(':ativo', $ativo);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getAll($apenas_ativos = false) {
        $where = $apenas_ativos ? "WHERE ativo = 1" : "";
        $query = "SELECT t.*, 
                  COUNT(DISTINCT e.id) as total_eventos,
                  ut.nome as prontuario_user_type_nome
                  FROM " . $this->table . " t
                  LEFT JOIN eventos e ON t.id = e.tipo_evento_id
                  LEFT JOIN user_types ut ON t.prontuario_user_type_id = ut.id
                  $where
                  GROUP BY t.id
                  ORDER BY t.ativo DESC, t.nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id) {
        $query = "SELECT t.*, ut.nome as prontuario_user_type_nome
                  FROM " . $this->table . " t
                  LEFT JOIN user_types ut ON t.prontuario_user_type_id = ut.id
                  WHERE t.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET nome = :nome, 
                      cor = :cor,
                      gera_prontuario = :gera_prontuario,
                      prontuario_user_type_id = :prontuario_user_type_id,
                      ativo = :ativo
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':cor', $this->cor);
        $gera_prontuario = isset($this->gera_prontuario) ? ($this->gera_prontuario ? 1 : 0) : 0;
        $stmt->bindParam(':gera_prontuario', $gera_prontuario);
        $prontuario_user_type_id = !empty($this->prontuario_user_type_id) ? $this->prontuario_user_type_id : null;
        $stmt->bindParam(':prontuario_user_type_id', $prontuario_user_type_id);
        $ativo = isset($this->ativo) ? ($this->ativo ? 1 : 0) : 1;
        $stmt->bindParam(':ativo', $ativo);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        if ($this->getTotalEventos() > 0) {
            return false;
        }

        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getTotalEventos($id = null) {
        $target_id = $id ?? $this->id;
        if (!$target_id) {
            return 0;
        }

        $check_query = "SELECT COUNT(*) as total FROM eventos WHERE tipo_evento_id = :id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':id', $target_id);
        $check_stmt->execute();
        $result = $check_stmt->fetch();

        return isset($result['total']) ? (int) $result['total'] : 0;
    }
}
?>

