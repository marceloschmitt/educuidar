<?php
/**
 * Motor de detecção de alertas conforme regras configuradas
 */

class AlertaDetector {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function avaliarTodasRegrasAtivas(array $filtros = []) {
        $alerta_regra = new AlertaRegra($this->conn);
        $regras = $alerta_regra->getAll(true);
        $resultados = [];

        foreach ($regras as $regra) {
            if (empty($regra['tipos_evento'])) {
                continue;
            }
            $ocorrencias = $this->avaliarRegra($regra, $filtros);
            foreach ($ocorrencias as $ocorrencia) {
                $resultados[] = $ocorrencia;
            }
        }

        usort($resultados, function ($a, $b) {
            $cmp = strcmp($a['aluno_nome'], $b['aluno_nome']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['regra_nome'], $b['regra_nome']);
        });

        return $resultados;
    }

    public function avaliarRegra(array $regra, array $filtros = []) {
        $eventos_por_aluno = $this->buscarEventosPorAluno($regra, $filtros);
        $ocorrencias = [];

        foreach ($eventos_por_aluno as $aluno_id => $dados) {
            $match = null;
            switch ($regra['tipo_criterio']) {
                case 'dias_consecutivos':
                    $match = $this->avaliarDiasConsecutivos($dados['eventos'], $regra);
                    break;
                case 'intervalo_dias':
                    $match = $this->avaliarIntervaloDias($dados['eventos'], $regra);
                    break;
                case 'mesmo_dia':
                    $match = $this->avaliarMesmoDia($dados['eventos'], $regra);
                    break;
            }

            if ($match) {
                $ocorrencias[] = array_merge([
                    'aluno_id' => $aluno_id,
                    'aluno_nome' => $dados['aluno_nome'],
                    'curso_id' => $dados['curso_id'],
                    'curso_nome' => $dados['curso_nome'],
                    'turma_id' => $dados['turma_id'],
                    'turma_label' => $dados['turma_label'],
                    'regra_id' => (int) $regra['id'],
                    'regra_nome' => $regra['nome'],
                    'criterio_resumo' => formatAlertaCriterioResumo($regra),
                ], $match);
            }
        }

        return $ocorrencias;
    }

    private function buscarEventosPorAluno(array $regra, array $filtros) {
        $ano_corrente = (int) ($filtros['ano_corrente'] ?? date('Y'));
        $filtro_curso = $filtros['curso_id'] ?? null;
        $filtro_turma = $filtros['turma_id'] ?? null;
        $cursos_permitidos = $filtros['cursos_permitidos'] ?? null;

        $tipo_ids = array_map('intval', $regra['tipos_evento'] ?? []);
        if (empty($tipo_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tipo_ids), '?'));

        $query = "SELECT e.id, e.aluno_id, e.data_evento, e.tipo_evento_id,
                         COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome,
                         t.id AS turma_id, t.ano_curso, c.id AS curso_id, c.nome AS curso_nome
                  FROM eventos e
                  INNER JOIN alunos a ON a.id = e.aluno_id
                  LEFT JOIN turmas t ON t.id = e.turma_id
                  LEFT JOIN cursos c ON c.id = t.curso_id
                  WHERE COALESCE(a.desistente, 0) = 0
                    AND e.tipo_evento_id IN ($placeholders)
                    AND (t.ano_civil = ? OR (e.turma_id IS NULL AND YEAR(e.data_evento) = ?))";

        $params = array_merge($tipo_ids, [$ano_corrente, $ano_corrente]);

        if ($filtro_curso) {
            $query .= " AND c.id = ?";
            $params[] = (int) $filtro_curso;
        }

        if ($filtro_turma) {
            $query .= " AND t.id = ?";
            $params[] = (int) $filtro_turma;
        }

        if (is_array($cursos_permitidos)) {
            if (empty($cursos_permitidos)) {
                return [];
            }
            $curso_placeholders = implode(',', array_fill(0, count($cursos_permitidos), '?'));
            $query .= " AND c.id IN ($curso_placeholders)";
            $params = array_merge($params, array_map('intval', $cursos_permitidos));
        }

        $query .= " ORDER BY e.aluno_id ASC, e.data_evento ASC, e.id ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $por_aluno = [];
        foreach ($rows as $row) {
            $aluno_id = (int) $row['aluno_id'];
            if (!isset($por_aluno[$aluno_id])) {
                $turma_info = $this->getTurmaAnoCorrente($aluno_id, $ano_corrente);
                $por_aluno[$aluno_id] = [
                    'aluno_nome' => $row['aluno_nome'],
                    'curso_id' => $turma_info['curso_id'] ?? $row['curso_id'],
                    'curso_nome' => $turma_info['curso_nome'] ?? $row['curso_nome'],
                    'turma_id' => $turma_info['turma_id'] ?? $row['turma_id'],
                    'turma_label' => $turma_info['turma_label'] ?? $this->formatTurmaLabel($row),
                    'eventos' => [],
                ];
            }
            $por_aluno[$aluno_id]['eventos'][] = $row;
        }

        return $por_aluno;
    }

    private function getTurmaAnoCorrente($aluno_id, $ano_corrente) {
        $stmt = $this->conn->prepare("SELECT t.id AS turma_id, t.ano_curso, c.id AS curso_id, c.nome AS curso_nome
                                      FROM aluno_turmas at
                                      INNER JOIN turmas t ON t.id = at.turma_id
                                      INNER JOIN cursos c ON c.id = t.curso_id
                                      WHERE at.aluno_id = :aluno_id AND t.ano_civil = :ano_corrente
                                      ORDER BY t.ano_curso ASC
                                      LIMIT 1");
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':ano_corrente', $ano_corrente);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return [];
        }
        return [
            'turma_id' => $row['turma_id'],
            'curso_id' => $row['curso_id'],
            'curso_nome' => $row['curso_nome'],
            'turma_label' => $row['curso_nome'] . ' - ' . $row['ano_curso'] . 'º Ano',
        ];
    }

    private function formatTurmaLabel($row) {
        if (empty($row['curso_nome'])) {
            return '—';
        }
        $ano = $row['ano_curso'] ?? '';
        return trim($row['curso_nome'] . ($ano !== '' ? ' - ' . $ano . 'º Ano' : ''));
    }

    private function avaliarDiasConsecutivos(array $eventos, array $regra) {
        $datas = [];
        foreach ($eventos as $evento) {
            $datas[$evento['data_evento']] = true;
        }
        $datas_ordenadas = array_keys($datas);
        sort($datas_ordenadas);

        if (empty($datas_ordenadas)) {
            return null;
        }

        $minimo = (int) $regra['quantidade'];
        $melhor_inicio = null;
        $melhor_fim = null;
        $melhor_tamanho = 0;

        $inicio_seq = $datas_ordenadas[0];
        $anterior = $datas_ordenadas[0];
        $tamanho = 1;

        for ($i = 1; $i < count($datas_ordenadas); $i++) {
            $atual = $datas_ordenadas[$i];
            if ($this->saoDiasConsecutivos($anterior, $atual, $regra)) {
                $tamanho++;
            } else {
                if ($tamanho > $melhor_tamanho) {
                    $melhor_tamanho = $tamanho;
                    $melhor_inicio = $inicio_seq;
                    $melhor_fim = $anterior;
                }
                $inicio_seq = $atual;
                $tamanho = 1;
            }
            $anterior = $atual;
        }

        if ($tamanho > $melhor_tamanho) {
            $melhor_tamanho = $tamanho;
            $melhor_inicio = $inicio_seq;
            $melhor_fim = $anterior;
        }

        if ($melhor_tamanho < $minimo) {
            return null;
        }

        $datas_sequencia = array_values(array_filter($datas_ordenadas, function ($data) use ($melhor_inicio, $melhor_fim) {
            return $data >= $melhor_inicio && $data <= $melhor_fim;
        }));

        return [
            'data_inicio' => $melhor_inicio,
            'data_fim' => $melhor_fim,
            'quantidade_contada' => $melhor_tamanho,
            'datas' => $datas_sequencia,
            'periodo_label' => formatAlertaPeriodoLabel($melhor_inicio, $melhor_fim, $datas_sequencia),
        ];
    }

    private function avaliarIntervaloDias(array $eventos, array $regra) {
        $minimo = (int) $regra['quantidade'];
        $janela = (int) $regra['intervalo_dias'];
        if ($janela < 1 || $minimo < 1 || empty($eventos)) {
            return null;
        }

        $melhor = null;
        foreach ($eventos as $evento_fim) {
            $fim = $evento_fim['data_evento'];
            $inicio = date('Y-m-d', strtotime($fim . ' -' . ($janela - 1) . ' days'));
            $contagem = 0;
            $datas = [];
            foreach ($eventos as $ev) {
                if ($ev['data_evento'] >= $inicio && $ev['data_evento'] <= $fim) {
                    $contagem++;
                    $datas[$ev['data_evento']] = true;
                }
            }
            if ($contagem >= $minimo && ($melhor === null || $contagem > $melhor['quantidade_contada'])) {
                $datas_lista = array_keys($datas);
                sort($datas_lista);
                $melhor = [
                    'data_inicio' => $inicio,
                    'data_fim' => $fim,
                    'quantidade_contada' => $contagem,
                    'datas' => $datas_lista,
                    'periodo_label' => formatAlertaPeriodoLabel($inicio, $fim, $datas_lista),
                ];
            }
        }

        return $melhor;
    }

    private function avaliarMesmoDia(array $eventos, array $regra) {
        $minimo = (int) $regra['quantidade'];
        $por_dia = [];
        foreach ($eventos as $evento) {
            $dia = $evento['data_evento'];
            if (!isset($por_dia[$dia])) {
                $por_dia[$dia] = 0;
            }
            $por_dia[$dia]++;
        }

        $melhor_dia = null;
        $melhor_qtd = 0;
        foreach ($por_dia as $dia => $qtd) {
            if ($qtd >= $minimo && $qtd > $melhor_qtd) {
                $melhor_dia = $dia;
                $melhor_qtd = $qtd;
            }
        }

        if ($melhor_dia === null) {
            return null;
        }

        return [
            'data_inicio' => $melhor_dia,
            'data_fim' => $melhor_dia,
            'quantidade_contada' => $melhor_qtd,
            'datas' => [$melhor_dia],
            'periodo_label' => date('d/m/Y', strtotime($melhor_dia)) . ' (' . $melhor_qtd . ' ocorrências)',
        ];
    }

    private function saoDiasConsecutivos($data_anterior, $data_atual, array $regra) {
        $cursor = strtotime($data_anterior . ' +1 day');
        $fim = strtotime($data_atual);
        $ignorar_domingos = !empty($regra['ignorar_domingos']);
        $ignorar_sabados = !empty($regra['ignorar_sabados']);

        while ($cursor < $fim) {
            $dow = (int) date('N', $cursor);
            $ignorado = ($ignorar_domingos && $dow === 7) || ($ignorar_sabados && $dow === 6);
            if (!$ignorado) {
                return false;
            }
            $cursor = strtotime(date('Y-m-d', $cursor) . ' +1 day');
        }

        return true;
    }
}
?>
