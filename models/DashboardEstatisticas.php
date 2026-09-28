<?php
/**
 * Consultas agregadas para os gráficos do dashboard.
 *
 * Filtros aceitos: ano, curso_id, turma_id, tipo_evento_id, registrado_por, incluir_sabados.
 */
class DashboardEstatisticas {
    private $conn;
    private $filtros;

    public function __construct($db, array $filtros) {
        $this->conn = $db;
        $this->filtros = $filtros;
    }

    private function montarWhere(array $condicoes_extras = []) {
        $where = [
            "(a.id IS NULL OR COALESCE(a.desistente, 0) = 0)",
            "t.ano_civil = :ano",
            "YEAR(e.data_evento) = :ano_data",
        ];
        $params = [
            ':ano' => (int) $this->filtros['ano'],
            ':ano_data' => (int) $this->filtros['ano'],
        ];

        if (!empty($this->filtros['curso_id'])) {
            $where[] = "c.id = :curso_id";
            $params[':curso_id'] = (int) $this->filtros['curso_id'];
        }
        if (!empty($this->filtros['turma_id'])) {
            $where[] = "e.turma_id = :turma_id";
            $params[':turma_id'] = (int) $this->filtros['turma_id'];
        }
        if (!empty($this->filtros['tipo_evento_id'])) {
            $where[] = "e.tipo_evento_id = :tipo_evento_id";
            $params[':tipo_evento_id'] = (int) $this->filtros['tipo_evento_id'];
        }
        if (!empty($this->filtros['registrado_por'])) {
            $where[] = "e.registrado_por = :registrado_por";
            $params[':registrado_por'] = (int) $this->filtros['registrado_por'];
        }
        if (empty($this->filtros['incluir_sabados'])) {
            $where[] = "DAYOFWEEK(e.data_evento) != 7";
        }

        foreach ($condicoes_extras as $condicao) {
            $where[] = $condicao;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * $params_select: parâmetros usados em expressões do SELECT (placeholders com nomes únicos).
     */
    private function consultar($select, $group_order = '', array $condicoes_extras = [], array $params_select = []) {
        [$where, $params] = $this->montarWhere($condicoes_extras);
        $params = array_merge($params_select, $params);
        $query = "SELECT $select
                  FROM eventos e
                  LEFT JOIN alunos a ON e.aluno_id = a.id
                  LEFT JOIN tipos_eventos te ON e.tipo_evento_id = te.id
                  LEFT JOIN turmas t ON e.turma_id = t.id
                  LEFT JOIN cursos c ON t.curso_id = c.id
                  WHERE $where
                  $group_order";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $chave => $valor) {
            $stmt->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tipo de evento com mais faltas gravadas pela coleta automática (observações com prefixo [AUTO]).
     */
    public function tipoDoRegistroAutomatico() {
        $stmt = $this->conn->query(
            "SELECT tipo_evento_id FROM eventos
             WHERE observacoes LIKE '[AUTO]%' AND tipo_evento_id IS NOT NULL
             GROUP BY tipo_evento_id
             ORDER BY COUNT(*) DESC
             LIMIT 1"
        );
        $id = $stmt ? $stmt->fetchColumn() : false;
        return $id ? (int) $id : null;
    }

    public function totaisPorTipo() {
        return $this->consultar(
            "te.id AS tipo_id, COALESCE(te.nome, 'Sem tipo') AS tipo_nome, COUNT(*) AS total",
            "GROUP BY te.id, te.nome ORDER BY total DESC, tipo_nome ASC"
        );
    }

    public function porTurma() {
        return $this->consultar(
            "t.id AS id, COALESCE(c.nome, '') AS curso_nome, t.ano_curso AS ano_curso, COUNT(*) AS total",
            "GROUP BY t.id, c.nome, t.ano_curso ORDER BY curso_nome ASC, ano_curso ASC"
        );
    }

    public function porMesETurma() {
        return $this->consultar(
            "MONTH(e.data_evento) AS mes, t.id AS turma_id, COUNT(*) AS total",
            "GROUP BY MONTH(e.data_evento), t.id"
        );
    }

    public function porDiaETurma() {
        return $this->consultar(
            "WEEKDAY(e.data_evento) AS dia, t.id AS turma_id, COUNT(*) AS total",
            "GROUP BY WEEKDAY(e.data_evento), t.id"
        );
    }

    /**
     * Os $limite alunos com mais registros em cada turma, agrupados por turma_id.
     */
    public function topAlunosPorTurma($limite = 5) {
        $limite = max(1, (int) $limite);
        $rows = $this->consultar(
            "t.id AS turma_id, a.id AS id, MAX(a.nome) AS nome, COUNT(*) AS total, MAX(e.data_evento) AS ultimo_evento",
            "GROUP BY t.id, a.id ORDER BY t.id, total DESC, nome ASC",
            ['e.aluno_id IS NOT NULL']
        );
        $por_turma = [];
        foreach ($rows as $row) {
            $tid = (int) $row['turma_id'];
            if (count($por_turma[$tid] ?? []) < $limite) {
                $por_turma[$tid][] = $row;
            }
        }
        return $por_turma;
    }
}
