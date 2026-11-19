<?php
/**
 * Grade de aulas importada do SIGAA (turno_turma → disciplina_aulas).
 */

class DisciplinaAula {
    private $conn;
    private $table = 'disciplina_aulas';
    private $schema_ok = false;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function garantirSchema() {
        if ($this->schema_ok) {
            return;
        }
        try {
            $sql = @file_get_contents(__DIR__ . '/../sql/disciplina_aulas.sql');
            if ($sql !== false) {
                $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $comando) {
                    $this->conn->exec($comando);
                }
            }
            $this->garantirColunasExtras();
        } catch (Throwable $e) {
            // sem permissão CREATE
        }
        $this->schema_ok = true;
    }

    private function garantirColunasExtras() {
        foreach (
            [
                'turno' => 'CHAR(1) NULL',
                'periodos_nums' => 'VARCHAR(64) NULL',
            ] as $coluna => $definicao
        ) {
            try {
                $stmt = $this->conn->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = :tabela
                       AND COLUMN_NAME = :coluna"
                );
                $stmt->execute([':tabela' => $this->table, ':coluna' => $coluna]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $this->conn->exec("ALTER TABLE {$this->table} ADD COLUMN {$coluna} {$definicao}");
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    public function resumo() {
        $this->garantirSchema();
        try {
            $stmt = $this->conn->query(
                "SELECT COUNT(*) AS encontros,
                        COUNT(DISTINCT codigo_disciplina) AS disciplinas,
                        COUNT(DISTINCT data_aula) AS dias,
                        COUNT(DISTINCT NULLIF(id_turma_sigaa, 0)) AS turmas
                 FROM {$this->table}"
            );
            $row = $stmt ? $stmt->fetch() : null;
        } catch (Throwable $e) {
            return null;
        }
        if (!$row || (int) ($row['encontros'] ?? 0) === 0) {
            return null;
        }
        return [
            'encontros' => (int) $row['encontros'],
            'disciplinas' => (int) $row['disciplinas'],
            'dias' => (int) $row['dias'],
            'turmas' => (int) $row['turmas'],
        ];
    }

    /**
     * Códigos de disciplina com aula prevista na data, entre os códigos informados.
     * @param list<string> $codigos
     * @return list<string>
     */
    public function codigosComAulaNaData($data_aula, array $codigos) {
        $this->garantirSchema();
        $codigos = array_values(array_unique(array_filter(array_map('strval', $codigos))));
        if ($data_aula === '' || empty($codigos)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT codigo_disciplina
             FROM {$this->table}
             WHERE data_aula = ?
               AND codigo_disciplina IN ($placeholders)"
        );
        $stmt->execute(array_merge([$data_aula], $codigos));
        return array_column($stmt->fetchAll(), 'codigo_disciplina');
    }

    /**
     * Encontros do aluno na data cuja faixa de períodos contém a hora.
     * @return list<array>
     */
    public function encontrosDoAlunoNaHora($aluno_id, $data_aula, $hora) {
        $this->garantirSchema();
        $codigos = $this->codigosDoAlunoNoAno($aluno_id, (int) date('Y', strtotime($data_aula)));
        if (empty($codigos)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE data_aula = ?
               AND codigo_disciplina IN ($placeholders)"
        );
        $stmt->execute(array_merge([$data_aula], $codigos));
        $encontros = $stmt->fetchAll() ?: [];
        if (empty($encontros)) {
            return [];
        }

        $mapa = new PeriodoHorario($this->conn);
        if (!$mapa->temMapa()) {
            return [];
        }

        $hora_norm = $this->normalizarHora($hora);
        if ($hora_norm === null) {
            return [];
        }

        $filtrados = [];
        foreach ($encontros as $encontro) {
            $turno = strtoupper(substr(trim((string) ($encontro['turno'] ?? '')), 0, 1));
            $nums = $this->parsePeriodosNums($encontro['periodos_nums'] ?? '');
            if ($turno === '' || empty($nums)) {
                continue;
            }
            $periodos_hora = $mapa->periodosQueContemHora($turno, $hora_norm);
            if (empty($periodos_hora)) {
                continue;
            }
            $nums_hora = array_map(function ($p) {
                return (int) $p['periodo'];
            }, $periodos_hora);
            if (count(array_intersect($nums, $nums_hora)) === 0) {
                continue;
            }
            $filtrados[] = $encontro;
        }
        return $filtrados;
    }

    /**
     * IDs de users (professores) que dão aula ao aluno na data/hora.
     * @return list<int>
     */
    public function professorIdsNaHora($aluno_id, $data_aula, $hora) {
        $encontros = $this->encontrosDoAlunoNaHora($aluno_id, $data_aula, $hora);
        if (empty($encontros)) {
            return [];
        }

        $turmas = [];
        $chaves = [];
        foreach ($encontros as $e) {
            $chave = ($e['codigo_disciplina'] ?? '') . '|' . (int) ($e['id_turma_sigaa'] ?? 0);
            if (isset($chaves[$chave])) {
                continue;
            }
            $chaves[$chave] = true;
            $turmas[] = [
                'codigo_disciplina' => (string) ($e['codigo_disciplina'] ?? ''),
                'id_turma_sigaa' => (int) ($e['id_turma_sigaa'] ?? 0),
            ];
        }

        $docenteModel = new DisciplinaDocente($this->conn);
        $docentes = $docenteModel->porTurmas($turmas);
        $ids = [];
        foreach ($docentes as $d) {
            $uid = (int) ($d['user_id'] ?? 0);
            if ($uid > 0) {
                $ids[$uid] = true;
            }
        }
        return array_map('intval', array_keys($ids));
    }

    /**
     * Turmas SIGAA distintas presentes na grade.
     * @return list<array{curso_nome:string,id_turma_sigaa:int,disciplinas:int,encontros:int,label:string}>
     */
    public function listarTurmasSigaa() {
        $this->garantirSchema();
        try {
            $stmt = $this->conn->query(
                "SELECT curso_nome,
                        id_turma_sigaa,
                        COUNT(DISTINCT codigo_disciplina) AS disciplinas,
                        COUNT(*) AS encontros,
                        MIN(disciplina_nome) AS amostra_disciplina
                 FROM {$this->table}
                 GROUP BY curso_nome, id_turma_sigaa
                 ORDER BY curso_nome ASC, id_turma_sigaa ASC"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
        } catch (Throwable $e) {
            return [];
        }
        $lista = [];
        foreach ($rows as $row) {
            $curso = trim((string) ($row['curso_nome'] ?? ''));
            $id = (int) ($row['id_turma_sigaa'] ?? 0);
            $label = $curso !== '' ? $curso : 'Curso não informado';
            if ($id > 0) {
                $label .= ' — turma SIGAA #' . $id;
            }
            $lista[] = [
                'curso_nome' => $curso,
                'id_turma_sigaa' => $id,
                'disciplinas' => (int) ($row['disciplinas'] ?? 0),
                'encontros' => (int) ($row['encontros'] ?? 0),
                'label' => $label,
            ];
        }
        return $lista;
    }

    /**
     * Grade semanal da turma SIGAA: weekday (0=seg…6=dom) → período → células.
     * Cada célula: codigo, disciplina, turno, docentes[].
     *
     * @return array{dias: array<int, array<int, list<array>>>, turnos: list<string>}
     */
    public function gradeSemanalTurma($curso_nome, $id_turma_sigaa) {
        $this->garantirSchema();
        $curso_nome = (string) $curso_nome;
        $id_turma_sigaa = (int) $id_turma_sigaa;

        $stmt = $this->conn->prepare(
            "SELECT codigo_disciplina, disciplina_nome, turno, periodos_nums, data_aula
             FROM {$this->table}
             WHERE curso_nome = :curso
               AND id_turma_sigaa = :id_turma"
        );
        $stmt->bindValue(':curso', $curso_nome);
        $stmt->bindValue(':id_turma', $id_turma_sigaa, PDO::PARAM_INT);
        $stmt->execute();
        $encontros = $stmt->fetchAll() ?: [];

        $docenteModel = new DisciplinaDocente($this->conn);
        $docentes_rows = $docenteModel->porCursoTurma($curso_nome, $id_turma_sigaa);
        $docentes_por_codigo = [];
        foreach ($docentes_rows as $d) {
            $cod = (string) ($d['codigo_disciplina'] ?? '');
            if ($cod === '') {
                continue;
            }
            if (!isset($docentes_por_codigo[$cod])) {
                $docentes_por_codigo[$cod] = [];
            }
            $nome = trim((string) ($d['docente_nome'] ?? ''));
            if ($nome !== '' && !in_array($nome, $docentes_por_codigo[$cod], true)) {
                $docentes_por_codigo[$cod][] = $nome;
            }
        }

        // chave weekday|periodo|codigo → célula (agrega semanas)
        $celulas = [];
        $turnos_usados = [];
        foreach ($encontros as $e) {
            $data = $e['data_aula'] ?? '';
            if ($data === '') {
                continue;
            }
            $ts = strtotime($data);
            if ($ts === false) {
                continue;
            }
            // PHP: 0=dom…6=sáb → converter para 0=seg…5=sáb (ignora domingo)
            $w = (int) date('w', $ts); // 0=dom
            if ($w === 0) {
                continue;
            }
            $weekday = $w - 1; // 0=seg … 5=sáb

            $turno = strtoupper(substr(trim((string) ($e['turno'] ?? '')), 0, 1));
            $nums = $this->parsePeriodosNums($e['periodos_nums'] ?? '');
            if ($turno === '' || empty($nums)) {
                continue;
            }
            $turnos_usados[$turno] = true;
            $codigo = (string) ($e['codigo_disciplina'] ?? '');
            $nome = (string) ($e['disciplina_nome'] ?? $codigo);
            foreach ($nums as $periodo) {
                $chave = $weekday . '|' . $turno . '|' . $periodo . '|' . $codigo;
                if (!isset($celulas[$chave])) {
                    $celulas[$chave] = [
                        'weekday' => $weekday,
                        'turno' => $turno,
                        'periodo' => (int) $periodo,
                        'codigo_disciplina' => $codigo,
                        'disciplina_nome' => $nome,
                        'docentes' => $docentes_por_codigo[$codigo] ?? [],
                    ];
                }
            }
        }

        // dias[weekday][turno][periodo] = list de células
        $dias = [];
        foreach ($celulas as $c) {
            $wd = $c['weekday'];
            $t = $c['turno'];
            $p = $c['periodo'];
            if (!isset($dias[$wd])) {
                $dias[$wd] = [];
            }
            if (!isset($dias[$wd][$t])) {
                $dias[$wd][$t] = [];
            }
            if (!isset($dias[$wd][$t][$p])) {
                $dias[$wd][$t][$p] = [];
            }
            $dias[$wd][$t][$p][] = $c;
        }

        $ordem_turno = ['M' => 0, 'T' => 1, 'N' => 2];
        $turnos = array_keys($turnos_usados);
        usort($turnos, function ($a, $b) use ($ordem_turno) {
            return ($ordem_turno[$a] ?? 9) <=> ($ordem_turno[$b] ?? 9);
        });

        return [
            'dias' => $dias,
            'turnos' => $turnos,
        ];
    }

    /**
     * Códigos em que o aluno tem frequência no ano (matrícula na disciplina).
     * @return list<string>
     */
    public function codigosDoAlunoNoAno($aluno_id, $ano) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT DISTINCT cod_disciplina
                 FROM frequencia_disciplina
                 WHERE aluno_id = :aluno_id AND ano = :ano
                   AND cod_disciplina IS NOT NULL AND cod_disciplina <> ''"
            );
            $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
            $stmt->bindValue(':ano', (int) $ano, PDO::PARAM_INT);
            $stmt->execute();
            return array_column($stmt->fetchAll(), 'cod_disciplina');
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<int> */
    private function parsePeriodosNums($texto) {
        $nums = [];
        foreach (preg_split('/\s*,\s*/', trim((string) $texto)) as $p) {
            if ($p !== '' && ctype_digit($p)) {
                $nums[] = (int) $p;
            }
        }
        return $nums;
    }

    private function normalizarHora($valor) {
        $valor = trim((string) $valor);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $valor, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;
            if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59 && $s >= 0 && $s <= 59) {
                return sprintf('%02d:%02d:%02d', $h, $i, $s);
            }
        }
        return null;
    }
}
