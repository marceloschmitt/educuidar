<?php
/**
 * Motor de detecção de alertas conforme regras configuradas
 */

class AlertaDetector {
    private $conn;
    /** @var DisciplinaAula|null */
    private $disciplina_aula;

    public function __construct($db) {
        $this->conn = $db;
        $this->disciplina_aula = new DisciplinaAula($db);
    }

    public function avaliarTodasRegrasAtivas(array $filtros = []) {
        $alerta_regra = new AlertaRegra($this->conn);
        $regras = $alerta_regra->getAll(true);
        $resultados = [];

        foreach ($regras as $regra) {
            $regra = $this->resolverTiposEventoRegra($regra);
            if (empty($regra['tipos_evento'])) {
                continue;
            }
            $ocorrencias = $this->avaliarRegra($regra, $filtros);
            foreach ($ocorrencias as $ocorrencia) {
                $resultados[] = $ocorrencia;
            }
        }

        usort($resultados, function ($a, $b) {
            $date_a = $a['data_fim'] ?? $a['data_inicio'] ?? '';
            $date_b = $b['data_fim'] ?? $b['data_inicio'] ?? '';
            $cmp = strcmp($date_b, $date_a);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['aluno_nome'], $b['aluno_nome']);
        });

        return $resultados;
    }

    public function avaliarRegra(array $regra, array $filtros = []) {
        $regra = $this->resolverTiposEventoRegra($regra);
        $eventos_por_aluno = $this->buscarEventosPorAluno($regra, $filtros);
        $ocorrencias = [];

        foreach ($eventos_por_aluno as $aluno_id => $dados) {
            $matches = [];
            switch ($regra['tipo_criterio']) {
                case 'dias_consecutivos':
                    // Qualquer ocorrência do(s) tipo(s) em cada dia (comportamento original).
                    $matches = $this->avaliarDiasConsecutivos($dados['eventos'], $regra);
                    break;
                case 'faltas_todas_disciplinas':
                    // Dias consecutivos em que faltou a todas as disciplinas previstas (grade).
                    $eventos = $this->filtrarEventosDiaCompleto(
                        $dados['eventos'],
                        (int) $aluno_id,
                        (int) ($filtros['ano_corrente'] ?? date('Y'))
                    );
                    $matches = $this->avaliarDiasConsecutivos($eventos, $regra);
                    break;
                case 'intervalo_dias':
                    $match = $this->avaliarIntervaloDias($dados['eventos'], $regra);
                    if ($match) {
                        $matches[] = $match;
                    }
                    break;
                case 'mesmo_dia':
                    $match = $this->avaliarMesmoDia($dados['eventos'], $regra);
                    if ($match) {
                        $matches[] = $match;
                    }
                    break;
            }

            foreach ($matches as $match) {
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

    /**
     * Faltas completas usam o tipo SIGAA configurado; não dependem da seleção manual.
     */
    private function resolverTiposEventoRegra(array $regra) {
        if (($regra['tipo_criterio'] ?? '') !== 'faltas_todas_disciplinas') {
            return $regra;
        }
        if (!empty($regra['tipos_evento'])) {
            return $regra;
        }
        $config = new Configuracao($this->conn);
        $tipo_id = $config->getApiSigaaTipoEventoFaltaId();
        if ($tipo_id) {
            $regra['tipos_evento'] = [$tipo_id];
        }
        return $regra;
    }

    private function buscarEventosPorAluno(array $regra, array $filtros) {
        $ano_corrente = (int) ($filtros['ano_corrente'] ?? date('Y'));
        $filtro_curso = $filtros['curso_id'] ?? null;
        $filtro_turma = $filtros['turma_id'] ?? null;
        $filtro_aluno = $filtros['aluno_id'] ?? null;
        $cursos_permitidos = $filtros['cursos_permitidos'] ?? null;

        $tipo_ids = array_map('intval', $regra['tipos_evento'] ?? []);
        if (empty($tipo_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tipo_ids), '?'));

        $query = "SELECT e.id, e.aluno_id, e.data_evento, e.tipo_evento_id, e.observacoes,
                         COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome,
                         t.id AS turma_id, t.ano_curso, c.id AS curso_id, c.nome AS curso_nome
                  FROM eventos e
                  INNER JOIN alunos a ON a.id = e.aluno_id
                  LEFT JOIN turmas t ON t.id = e.turma_id
                  LEFT JOIN cursos c ON c.id = t.curso_id
                  WHERE COALESCE(a.desistente, 0) = 0
                    AND e.tipo_evento_id IN ($placeholders)
                    AND YEAR(e.data_evento) = ?";

        $params = array_merge($tipo_ids, [$ano_corrente]);

        if ($filtro_curso) {
            // Curso da turma do aluno no ano corrente (não só da turma do evento)
            $query .= " AND EXISTS (
                SELECT 1 FROM aluno_turmas at2
                INNER JOIN turmas t2 ON t2.id = at2.turma_id
                WHERE at2.aluno_id = e.aluno_id
                  AND t2.ano_civil = ?
                  AND t2.curso_id = ?
            )";
            $params[] = $ano_corrente;
            $params[] = (int) $filtro_curso;
        }

        if ($filtro_turma) {
            $query .= " AND EXISTS (
                SELECT 1 FROM aluno_turmas at3
                WHERE at3.aluno_id = e.aluno_id AND at3.turma_id = ?
            )";
            $params[] = (int) $filtro_turma;
        }

        if ($filtro_aluno) {
            $query .= " AND e.aluno_id = ?";
            $params[] = (int) $filtro_aluno;
        }

        if (is_array($cursos_permitidos)) {
            if (empty($cursos_permitidos)) {
                return [];
            }
            $curso_placeholders = implode(',', array_fill(0, count($cursos_permitidos), '?'));
            // Curso do aluno no ano corrente (não o curso da turma salva no evento)
            $query .= " AND EXISTS (
                SELECT 1 FROM aluno_turmas atp
                INNER JOIN turmas tp ON tp.id = atp.turma_id
                WHERE atp.aluno_id = e.aluno_id
                  AND tp.ano_civil = ?
                  AND tp.curso_id IN ($curso_placeholders)
            )";
            $params[] = $ano_corrente;
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
            // Fallback: qualquer turma do aluno (mais recente por ano civil)
            $stmt = $this->conn->prepare("SELECT t.id AS turma_id, t.ano_curso, c.id AS curso_id, c.nome AS curso_nome
                                          FROM aluno_turmas at
                                          INNER JOIN turmas t ON t.id = at.turma_id
                                          INNER JOIN cursos c ON c.id = t.curso_id
                                          WHERE at.aluno_id = :aluno_id
                                          ORDER BY t.ano_civil DESC, t.ano_curso ASC
                                          LIMIT 1");
            $stmt->bindParam(':aluno_id', $aluno_id);
            $stmt->execute();
            $row = $stmt->fetch();
        }
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

    /**
     * Mantém só eventos de dias em que o aluno faltou a todas as disciplinas
     * com aula prevista (grade) entre as que ele cursa.
     */
    private function filtrarEventosDiaCompleto(array $eventos, $aluno_id, $ano) {
        if (empty($eventos) || !$this->disciplina_aula) {
            return [];
        }
        $matriculadas = $this->disciplina_aula->codigosDoAlunoNoAno($aluno_id, $ano);
        if (empty($matriculadas)) {
            return [];
        }

        $faltas_por_dia = [];
        foreach ($eventos as $evento) {
            $data = $evento['data_evento'] ?? '';
            $codigo = $this->codigoDisciplinaObservacao($evento['observacoes'] ?? '');
            if ($data === '' || $codigo === null) {
                continue;
            }
            $faltas_por_dia[$data][$codigo] = true;
        }

        $dias_completos = [];
        foreach ($faltas_por_dia as $data => $codigos_falta) {
            $previstas = $this->disciplina_aula->codigosComAulaNaData($data, $matriculadas);
            if (empty($previstas)) {
                continue;
            }
            $ok = true;
            foreach ($previstas as $codigo) {
                if (empty($codigos_falta[$codigo])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $dias_completos[$data] = true;
            }
        }

        if (empty($dias_completos)) {
            return [];
        }

        return array_values(array_filter($eventos, function ($evento) use ($dias_completos) {
            return !empty($dias_completos[$evento['data_evento'] ?? '']);
        }));
    }

    private function codigoDisciplinaObservacao($observacoes) {
        $texto = trim((string) $observacoes);
        if (preg_match('/^\[AUTO\]\s*(\S+)\s*-/', $texto, $m)) {
            return $m[1];
        }
        if (preg_match('/^\[AUTO\]\s*(\S+)\s*$/', $texto, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Retorna todas as sequências de dias consecutivos com tamanho >= quantidade da regra.
     * (Antes só devolvia a maior — por isso agosto era ignorado se houvesse sequência maior em maio.)
     *
     * @return array<int, array>
     */
    private function avaliarDiasConsecutivos(array $eventos, array $regra) {
        $datas = [];
        foreach ($eventos as $evento) {
            $datas[$evento['data_evento']] = true;
        }
        $datas_ordenadas = array_keys($datas);
        sort($datas_ordenadas);

        if (empty($datas_ordenadas)) {
            return [];
        }

        $minimo = (int) $regra['quantidade'];
        $sequencias = [];

        $inicio_seq = $datas_ordenadas[0];
        $anterior = $datas_ordenadas[0];
        $tamanho = 1;

        $registrar = function ($inicio, $fim, $tam) use (&$sequencias, $minimo, $datas_ordenadas) {
            if ($tam < $minimo) {
                return;
            }
            $datas_sequencia = array_values(array_filter($datas_ordenadas, function ($data) use ($inicio, $fim) {
                return $data >= $inicio && $data <= $fim;
            }));
            $sequencias[] = [
                'data_inicio' => $inicio,
                'data_fim' => $fim,
                'quantidade_contada' => $tam,
                'datas' => $datas_sequencia,
                'periodo_label' => formatAlertaPeriodoLabel($inicio, $fim, $datas_sequencia),
            ];
        };

        for ($i = 1; $i < count($datas_ordenadas); $i++) {
            $atual = $datas_ordenadas[$i];
            if ($this->saoDiasConsecutivos($anterior, $atual, $regra)) {
                $tamanho++;
            } else {
                $registrar($inicio_seq, $anterior, $tamanho);
                $inicio_seq = $atual;
                $tamanho = 1;
            }
            $anterior = $atual;
        }

        $registrar($inicio_seq, $anterior, $tamanho);

        return $sequencias;
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
            'periodo_label' => formatAlertaPeriodoLabel($melhor_dia, $melhor_dia),
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
