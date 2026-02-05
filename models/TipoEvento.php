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
    public $gera_prontuario_cae;
    public $prontuario_user_type;
    public $ativo;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (nome, cor, gera_prontuario_cae, prontuario_user_type, ativo) 
                  VALUES (:nome, :cor, :gera_prontuario_cae, :prontuario_user_type, :ativo)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':cor', $this->cor);
        $gera_prontuario_cae = isset($this->gera_prontuario_cae) ? ($this->gera_prontuario_cae ? 1 : 0) : 0;
        $stmt->bindParam(':gera_prontuario_cae', $gera_prontuario_cae);
        $prontuario_user_type = !empty($this->prontuario_user_type) ? $this->prontuario_user_type : null;
        $stmt->bindParam(':prontuario_user_type', $prontuario_user_type);
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
                  COUNT(DISTINCT e.id) as total_eventos
                  FROM " . $this->table . " t
                  LEFT JOIN eventos e ON t.id = e.tipo_evento_id
                  $where
                  GROUP BY t.id
                  ORDER BY t.ativo DESC, t.nome ASC";

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
                  SET nome = :nome, 
                      cor = :cor,
                      gera_prontuario_cae = :gera_prontuario_cae,
                      prontuario_user_type = :prontuario_user_type,
                      ativo = :ativo
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':cor', $this->cor);
        $gera_prontuario_cae = isset($this->gera_prontuario_cae) ? ($this->gera_prontuario_cae ? 1 : 0) : 0;
        $stmt->bindParam(':gera_prontuario_cae', $gera_prontuario_cae);
        $prontuario_user_type = !empty($this->prontuario_user_type) ? $this->prontuario_user_type : null;
        $stmt->bindParam(':prontuario_user_type', $prontuario_user_type);
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

