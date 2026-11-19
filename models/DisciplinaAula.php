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
