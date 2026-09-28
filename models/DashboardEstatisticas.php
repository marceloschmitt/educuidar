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

    public function resumo() {
        $hoje = date('Y-m-d');
        $inicio_mes = date('Y-m-01');
        $inicio_mes_anterior = date('Y-m-01', strtotime('first day of last month'));
        $inicio_7_dias = date('Y-m-d', strtotime('-6 days'));

        $rows = $this->consultar(
            "COUNT(*) AS total,
             COUNT(DISTINCT e.aluno_id) AS alunos,
             SUM(e.data_evento BETWEEN :ini7 AND :hoje1) AS ultimos_7_dias,
             SUM(e.data_evento BETWEEN :ini_mes AND :hoje2) AS mes_atual,
             SUM(e.data_evento >= :ini_mes_ant AND e.data_evento < :ini_mes2) AS mes_anterior,
             SUM(e.data_evento = :hoje3) AS hoje",
            '',
            [],
            [
                ':ini7' => $inicio_7_dias,
                ':hoje1' => $hoje,
                ':ini_mes' => $inicio_mes,
                ':hoje2' => $hoje,
                ':ini_mes_ant' => $inicio_mes_anterior,
                ':ini_mes2' => $inicio_mes,
                ':hoje3' => $hoje,
            ]
        );
        $r = $rows[0] ?? [];
        return [
            'total' => (int) ($r['total'] ?? 0),
            'alunos' => (int) ($r['alunos'] ?? 0),
            'ultimos_7_dias' => (int) ($r['ultimos_7_dias'] ?? 0),
            'mes_atual' => (int) ($r['mes_atual'] ?? 0),
            'mes_anterior' => (int) ($r['mes_anterior'] ?? 0),
            'hoje' => (int) ($r['hoje'] ?? 0),
        ];
    }

    public function porMesETipo() {
        return $this->consultar(
            "MONTH(e.data_evento) AS mes, te.id AS tipo_id, te.nome AS tipo_nome, COUNT(*) AS total",
            "GROUP BY MONTH(e.data_evento), te.id, te.nome ORDER BY mes"
        );
    }

    public function porSemana() {
        return $this->consultar(
            "DATE_SUB(e.data_evento, INTERVAL WEEKDAY(e.data_evento) DAY) AS semana, COUNT(*) AS total",
            "GROUP BY semana ORDER BY semana"
        );
    }

    public function porDiaDaSemana() {
        return $this->consultar(
            "WEEKDAY(e.data_evento) AS dia, COUNT(*) AS total",
            "GROUP BY dia ORDER BY dia"
        );
    }

    public function porCurso() {
        return $this->consultar(
            "c.id AS id, COALESCE(c.nome, 'Sem curso') AS nome, COUNT(*) AS total",
            "GROUP BY c.id, c.nome ORDER BY total DESC"
        );
    }

    public function porTurma() {
        return $this->consultar(
            "t.id AS id, CONCAT(COALESCE(c.nome, ''), ' - ', t.ano_curso, 'º Ano') AS nome, COUNT(*) AS total",
            "GROUP BY t.id, c.nome, t.ano_curso ORDER BY total DESC"
        );
    }

    public function topAlunos($limite = 10) {
        $limite = max(1, (int) $limite);
        return $this->consultar(
            "a.id AS id, MAX(a.nome) AS nome, MAX(c.nome) AS curso_nome, MAX(t.ano_curso) AS ano_curso,
             COUNT(*) AS total, MAX(e.data_evento) AS ultimo_evento",
            "GROUP BY a.id ORDER BY total DESC, nome ASC LIMIT $limite",
            ['e.aluno_id IS NOT NULL']
        );
    }
}
