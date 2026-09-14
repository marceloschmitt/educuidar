<?php
/**
 * Autorizações de entrada/saída fora do horário criadas por responsáveis.
 * Status controla se o fato previsto (entrada/saída) já ocorreu.
 */

class AutorizacaoResponsavel {
    private $conn;
    private $table = 'autorizacoes_responsavel';

    public const TIPO_ENTRADA = 'entrada_fora_horario';
    public const TIPO_SAIDA = 'saida_fora_horario';

    public const STATUS_PREVISTO = 'previsto';
    public const STATUS_OCORRIDO = 'ocorrido';
    public const STATUS_CANCELADA = 'cancelada';

    public function __construct($db) {
        $this->conn = $db;
    }

    public static function tiposLabels() {
        return [
            self::TIPO_ENTRADA => 'Entrada fora do horário',
            self::TIPO_SAIDA => 'Saída fora do horário',
        ];
    }

    public static function statusLabels() {
        return [
            self::STATUS_PREVISTO => 'Não ocorrido',
            self::STATUS_OCORRIDO => 'Ocorrido',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public function create($responsavel_id, $aluno_id, $tipo, $data, $hora, $justificativa) {
        if (!isset(self::tiposLabels()[$tipo])) {
            return false;
        }
        $justificativa = trim((string) $justificativa);
        if ($justificativa === '' || empty($data) || empty($hora)) {
            return false;
        }

        $query = "INSERT INTO " . $this->table . "
                  (responsavel_id, aluno_id, tipo, data_autorizacao, hora, justificativa, status)
                  VALUES (:responsavel_id, :aluno_id, :tipo, :data_autorizacao, :hora, :justificativa, 'previsto')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':data_autorizacao', $data);
        $stmt->bindParam(':hora', $hora);
        $stmt->bindParam(':justificativa', $justificativa);
        if (!$stmt->execute()) {
            return false;
        }
        return (int) $this->conn->lastInsertId();
    }

    public function getById($id) {
        $query = "SELECT a.*,
                  r.nome as responsavel_nome, r.email as responsavel_email,
                  al.nome as aluno_nome, al.nome_social as aluno_nome_social,
                  u.full_name as confirmado_por_nome
                  FROM " . $this->table . " a
                  INNER JOIN responsaveis r ON r.id = a.responsavel_id
                  INNER JOIN alunos al ON al.id = a.aluno_id
                  LEFT JOIN users u ON u.id = a.confirmado_por
                  WHERE a.id = :id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function listByResponsavel($responsavel_id, $limit = 50) {
        $query = "SELECT a.*,
                  al.nome as aluno_nome, al.nome_social as aluno_nome_social
                  FROM " . $this->table . " a
                  INNER JOIN alunos al ON al.id = a.aluno_id
                  WHERE a.responsavel_id = :responsavel_id
                  ORDER BY a.data_autorizacao DESC, a.hora DESC, a.id DESC
                  LIMIT " . (int) $limit;
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listForStaff($filtros = []) {
        $query = "SELECT a.*,
                  r.nome as responsavel_nome,
                  al.nome as aluno_nome, al.nome_social as aluno_nome_social, al.numero_matricula,
                  u.full_name as confirmado_por_nome
                  FROM " . $this->table . " a
                  INNER JOIN responsaveis r ON r.id = a.responsavel_id
                  INNER JOIN alunos al ON al.id = a.aluno_id
                  LEFT JOIN users u ON u.id = a.confirmado_por
                  WHERE 1=1";

        $params = [];
        if (!empty($filtros['status'])) {
            $query .= " AND a.status = :status";
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['tipo'])) {
            $query .= " AND a.tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['data'])) {
            $query .= " AND a.data_autorizacao = :data";
            $params[':data'] = $filtros['data'];
        }
        if (!empty($filtros['nome'])) {
            $query .= " AND (al.nome LIKE :nome OR al.nome_social LIKE :nome2 OR r.nome LIKE :nome3)";
            $like = '%' . $filtros['nome'] . '%';
            $params[':nome'] = $like;
            $params[':nome2'] = $like;
            $params[':nome3'] = $like;
        }

        $query .= " ORDER BY
                    CASE a.status WHEN 'previsto' THEN 0 WHEN 'ocorrido' THEN 1 ELSE 2 END,
                    a.data_autorizacao DESC, a.hora DESC, a.id DESC
                    LIMIT 200";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Marca que o fato previsto (entrada/saída) ocorreu. */
    public function marcarOcorrido($id, $user_id) {
        $query = "UPDATE " . $this->table . "
                  SET status = 'ocorrido',
                      confirmado_por = :user_id,
                      confirmado_em = NOW()
                  WHERE id = :id AND status = 'previsto'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function cancelar($id, $responsavel_id) {
        $query = "UPDATE " . $this->table . "
                  SET status = 'cancelada'
                  WHERE id = :id
                    AND responsavel_id = :responsavel_id
                    AND status = 'previsto'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
