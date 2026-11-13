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
        } catch (Throwable $e) {
            // sem permissão CREATE
        }
        $this->schema_ok = true;
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
}
