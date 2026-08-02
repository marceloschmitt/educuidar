<?php
/**
 * Alertas persistidos — alimenta a lista e o pop-up de login
 */

class AlertaGerado {
    private $conn;
    private $table = 'alertas_gerados';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function sincronizarAlertasAluno($aluno_id, array $alertas_detectados) {
        $aluno_id = (int) $aluno_id;
        if ($aluno_id <= 0) {
            return;
        }

        $chaves_ativas = [];
        foreach ($alertas_detectados as $alerta) {
            $this->registrarOuAtualizar($alerta);
            $chaves_ativas[] = $this->chaveAlerta($alerta);
        }

        $existentes = $this->getPorAluno($aluno_id);
        foreach ($existentes as $row) {
            $chave = $this->chaveAlerta([
                'regra_id' => $row['regra_id'],
                'data_inicio' => $row['data_inicio'],
                'data_fim' => $row['data_fim'],
            ]);
            if (!in_array($chave, $chaves_ativas, true)) {
                $this->removerPorId((int) $row['id']);
            }
        }
    }

    public function registrarOuAtualizar(array $alerta) {
        $aluno_id = (int) ($alerta['aluno_id'] ?? 0);
        $regra_id = (int) ($alerta['regra_id'] ?? 0);
        if ($aluno_id <= 0 || $regra_id <= 0) {
            return false;
        }

        $data_inicio = $alerta['data_inicio'] ?? null;
        $data_fim = $alerta['data_fim'] ?? null;
        $detalhe = $this->montarDetalhe($alerta);
        $quantidade = (int) ($alerta['quantidade_contada'] ?? 0);

        $existente = $this->buscarExistente($aluno_id, $regra_id, $data_inicio, $data_fim);
        if ($existente) {
            $stmt = $this->conn->prepare("UPDATE {$this->table}
                SET quantidade_contada = :quantidade_contada, detalhe = :detalhe
                WHERE id = :id");
            $stmt->bindParam(':quantidade_contada', $quantidade);
            $stmt->bindParam(':detalhe', $detalhe);
            $stmt->bindParam(':id', $existente['id']);
            return $stmt->execute();
        }

        $stmt = $this->conn->prepare("INSERT INTO {$this->table}
            (aluno_id, regra_id, data_inicio, data_fim, quantidade_contada, detalhe)
            VALUES (:aluno_id, :regra_id, :data_inicio, :data_fim, :quantidade_contada, :detalhe)");
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':regra_id', $regra_id);
        $stmt->bindParam(':data_inicio', $data_inicio);
        $stmt->bindParam(':data_fim', $data_fim);
        $stmt->bindParam(':quantidade_contada', $quantidade);
        $stmt->bindParam(':detalhe', $detalhe);
        return $stmt->execute();
    }

    public function getAll(array $filtros = []) {
        $query = "SELECT ag.*,
                         COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome,
                         a.foto,
                         ar.nome AS regra_nome
                  FROM {$this->table} ag
                  INNER JOIN alunos a ON a.id = ag.aluno_id
                  INNER JOIN alertas_regras ar ON ar.id = ag.regra_id
                  WHERE COALESCE(a.desistente, 0) = 0";

        $params = [];

        if (!empty($filtros['regra_id'])) {
            $query .= " AND ag.regra_id = :regra_id";
            $params[':regra_id'] = (int) $filtros['regra_id'];
        }

        $query .= " ORDER BY ag.data_fim DESC, aluno_nome ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $rows = array_map([$this, 'paraExibicao'], $rows);
        return $this->aplicarFiltrosExibicao($rows, $filtros);
    }

    public function getRecentes($horas = 24, $cursos_permitidos = null) {
        $horas = max(1, (int) $horas);

        $query = "SELECT ag.*,
                         COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome,
                         a.foto,
                         ar.nome AS regra_nome
                  FROM {$this->table} ag
                  INNER JOIN alunos a ON a.id = ag.aluno_id
                  INNER JOIN alertas_regras ar ON ar.id = ag.regra_id
                  WHERE ag.created_at >= DATE_SUB(NOW(), INTERVAL :horas HOUR)
                    AND COALESCE(a.desistente, 0) = 0
                  ORDER BY ag.created_at DESC, aluno_nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':horas', $horas, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_map([$this, 'paraExibicao'], $stmt->fetchAll());

        if (!is_array($cursos_permitidos)) {
            return $rows;
        }
        if (empty($cursos_permitidos)) {
            return [];
        }

        $cursos_permitidos = array_map('intval', $cursos_permitidos);
        return array_values(array_filter($rows, function ($row) use ($cursos_permitidos) {
            return in_array((int) ($row['curso_id'] ?? 0), $cursos_permitidos, true);
        }));
    }

    public function paraExibicao(array $row) {
        $detalhe = json_decode($row['detalhe'] ?? '{}', true) ?: [];

        return [
            'id' => (int) ($row['id'] ?? 0),
            'aluno_id' => (int) ($row['aluno_id'] ?? 0),
            'aluno_nome' => $row['aluno_nome'] ?? ($detalhe['aluno_nome'] ?? ''),
            'foto' => $row['foto'] ?? null,
            'regra_id' => (int) ($row['regra_id'] ?? 0),
            'regra_nome' => $row['regra_nome'] ?? ($detalhe['regra_nome'] ?? ''),
            'curso_id' => $detalhe['curso_id'] ?? null,
            'curso_nome' => $detalhe['curso_nome'] ?? '',
            'turma_id' => $detalhe['turma_id'] ?? null,
            'turma_label' => $detalhe['turma_label'] ?? '—',
            'criterio_resumo' => $detalhe['criterio_resumo'] ?? '',
            'periodo_label' => $detalhe['periodo_label'] ?? '',
            'data_inicio' => $row['data_inicio'] ?? null,
            'data_fim' => $row['data_fim'] ?? null,
            'quantidade_contada' => (int) ($row['quantidade_contada'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function aplicarFiltrosExibicao(array $rows, array $filtros) {
        if (!empty($filtros['curso_id'])) {
            $curso_id = (int) $filtros['curso_id'];
            $rows = array_filter($rows, function ($row) use ($curso_id) {
                return (int) ($row['curso_id'] ?? 0) === $curso_id;
            });
        }

        if (!empty($filtros['turma_id'])) {
            $turma_id = (int) $filtros['turma_id'];
            $rows = array_filter($rows, function ($row) use ($turma_id) {
                return (int) ($row['turma_id'] ?? 0) === $turma_id;
            });
        }

        if (is_array($filtros['cursos_permitidos'] ?? null)) {
            $cursos = array_map('intval', $filtros['cursos_permitidos']);
            if (empty($cursos)) {
                return [];
            }
            $rows = array_filter($rows, function ($row) use ($cursos) {
                return in_array((int) ($row['curso_id'] ?? 0), $cursos, true);
            });
        }

        return array_values($rows);
    }

    private function montarDetalhe(array $alerta) {
        return json_encode([
            'aluno_nome' => $alerta['aluno_nome'] ?? '',
            'curso_id' => $alerta['curso_id'] ?? null,
            'curso_nome' => $alerta['curso_nome'] ?? '',
            'turma_id' => $alerta['turma_id'] ?? null,
            'turma_label' => $alerta['turma_label'] ?? '',
            'regra_nome' => $alerta['regra_nome'] ?? '',
            'criterio_resumo' => $alerta['criterio_resumo'] ?? '',
            'periodo_label' => $alerta['periodo_label'] ?? '',
            'quantidade_contada' => (int) ($alerta['quantidade_contada'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function chaveAlerta(array $alerta) {
        return implode('|', [
            (int) ($alerta['regra_id'] ?? 0),
            $alerta['data_inicio'] ?? '',
            $alerta['data_fim'] ?? '',
        ]);
    }

    private function getPorAluno($aluno_id) {
        $stmt = $this->conn->prepare("SELECT id, regra_id, data_inicio, data_fim FROM {$this->table} WHERE aluno_id = :aluno_id");
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function removerPorId($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    private function buscarExistente($aluno_id, $regra_id, $data_inicio, $data_fim) {
        if ($data_inicio === null) {
            $query = "SELECT id FROM {$this->table}
                      WHERE aluno_id = :aluno_id AND regra_id = :regra_id
                        AND data_inicio IS NULL
                        AND " . ($data_fim === null ? 'data_fim IS NULL' : 'data_fim = :data_fim') . "
                      LIMIT 1";
        } else {
            $query = "SELECT id FROM {$this->table}
                      WHERE aluno_id = :aluno_id AND regra_id = :regra_id
                        AND data_inicio = :data_inicio
                        AND " . ($data_fim === null ? 'data_fim IS NULL' : 'data_fim = :data_fim') . "
                      LIMIT 1";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':regra_id', $regra_id);
        if ($data_inicio !== null) {
            $stmt->bindParam(':data_inicio', $data_inicio);
        }
        if ($data_fim !== null) {
            $stmt->bindParam(':data_fim', $data_fim);
        }
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }
}
?>
