<?php
/**
 * Regras configuráveis de alerta
 */

class AlertaRegra {
    private $conn;
    private $table = 'alertas_regras';

    public $id;
    public $nome;
    public $descricao;
    public $tipo_criterio;
    public $quantidade;
    public $intervalo_dias;
    public $ignorar_domingos;
    public $ignorar_sabados;
    public $ativo;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (nome, descricao, tipo_criterio, quantidade, intervalo_dias,
                   ignorar_domingos, ignorar_sabados, ativo)
                  VALUES (:nome, :descricao, :tipo_criterio, :quantidade, :intervalo_dias,
                          :ignorar_domingos, :ignorar_sabados, :ativo)";

        $stmt = $this->conn->prepare($query);
        $this->bindRegraFields($stmt);

        if ($stmt->execute()) {
            $this->id = (int) $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table . "
                  SET nome = :nome,
                      descricao = :descricao,
                      tipo_criterio = :tipo_criterio,
                      quantidade = :quantidade,
                      intervalo_dias = :intervalo_dias,
                      ignorar_domingos = :ignorar_domingos,
                      ignorar_sabados = :ignorar_sabados,
                      ativo = :ativo
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $this->bindRegraFields($stmt);

        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            $row['tipos_evento'] = $this->getTiposEventoIds($id);
            $row['tipos_evento_nomes'] = $this->getTiposEventoNomes($id);
        }
        return $row;
    }

    public function getAll($apenas_ativos = false) {
        $where = $apenas_ativos ? 'WHERE r.ativo = 1' : '';
        $query = "SELECT r.* FROM " . $this->table . " r
                  $where
                  ORDER BY r.ativo DESC, r.nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['tipos_evento'] = $this->getTiposEventoIds($row['id']);
            $row['tipos_evento_nomes'] = $this->getTiposEventoNomes($row['id']);
        }
        unset($row);

        return $rows;
    }

    public function setTiposEvento($regra_id, array $tipo_evento_ids) {
        if (!$regra_id) {
            return false;
        }

        $tipo_evento_ids = array_values(array_unique(array_filter(array_map('intval', $tipo_evento_ids), function ($id) {
            return $id > 0;
        })));

        try {
            $this->conn->beginTransaction();

            $delete = $this->conn->prepare("DELETE FROM alertas_regras_tipos_evento WHERE regra_id = :regra_id");
            $delete->bindParam(':regra_id', $regra_id);
            $delete->execute();

            if (!empty($tipo_evento_ids)) {
                $insert = $this->conn->prepare("INSERT INTO alertas_regras_tipos_evento (regra_id, tipo_evento_id) VALUES (:regra_id, :tipo_evento_id)");
                foreach ($tipo_evento_ids as $tipo_id) {
                    $insert->bindParam(':regra_id', $regra_id);
                    $insert->bindParam(':tipo_evento_id', $tipo_id);
                    $insert->execute();
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

    public function getTiposEventoIds($regra_id) {
        $stmt = $this->conn->prepare("SELECT tipo_evento_id FROM alertas_regras_tipos_evento WHERE regra_id = :regra_id");
        $stmt->bindParam(':regra_id', $regra_id);
        $stmt->execute();
        return array_map('intval', array_column($stmt->fetchAll(), 'tipo_evento_id'));
    }

    private function getTiposEventoNomes($regra_id) {
        $stmt = $this->conn->prepare("SELECT te.nome, te.cor
                                      FROM alertas_regras_tipos_evento arte
                                      INNER JOIN tipos_eventos te ON te.id = arte.tipo_evento_id
                                      WHERE arte.regra_id = :regra_id
                                      ORDER BY te.nome ASC");
        $stmt->bindParam(':regra_id', $regra_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function bindRegraFields($stmt) {
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':descricao', $this->descricao);
        $stmt->bindParam(':tipo_criterio', $this->tipo_criterio);
        $stmt->bindParam(':quantidade', $this->quantidade);
        $intervalo_dias = $this->intervalo_dias;
        if ($intervalo_dias === null || $intervalo_dias === '') {
            $stmt->bindValue(':intervalo_dias', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':intervalo_dias', (int) $intervalo_dias, PDO::PARAM_INT);
        }
        $ignorar_domingos = !empty($this->ignorar_domingos) ? 1 : 0;
        $ignorar_sabados = !empty($this->ignorar_sabados) ? 1 : 0;
        $ativo = !empty($this->ativo) ? 1 : 0;
        $stmt->bindParam(':ignorar_domingos', $ignorar_domingos);
        $stmt->bindParam(':ignorar_sabados', $ignorar_sabados);
        $stmt->bindParam(':ativo', $ativo);
    }
}
?>
