<?php
/**
 * Docentes por disciplina/turma SIGAA (importados com a grade).
 */

class DisciplinaDocente {
    private $conn;
    private $table = 'disciplina_docentes';
    private $schema_ok = false;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function garantirSchema() {
        if ($this->schema_ok) {
            return;
        }
        try {
            $sql = @file_get_contents(__DIR__ . '/../sql/disciplina_docentes.sql');
            if ($sql !== false) {
                // Remove FK para instalação sem users ainda / ambientes restritos
                $sql = preg_replace('/,\s*FOREIGN KEY\s*\([^)]+\)\s*REFERENCES[^,)]+/i', '', $sql);
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

    /**
     * Docentes de um curso + turma SIGAA.
     * @return list<array>
     */
    public function porCursoTurma($curso_nome, $id_turma_sigaa) {
        $this->garantirSchema();
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE curso_nome = :curso AND id_turma_sigaa = :id_turma
             ORDER BY codigo_disciplina ASC, docente_nome ASC"
        );
        $stmt->bindValue(':curso', (string) $curso_nome);
        $stmt->bindValue(':id_turma', (int) $id_turma_sigaa, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Docentes de todas as turmas SIGAA de um curso.
     * @return list<array>
     */
    public function porCurso($curso_nome) {
        $this->garantirSchema();
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE curso_nome = :curso
             ORDER BY codigo_disciplina ASC, docente_nome ASC"
        );
        $stmt->bindValue(':curso', (string) $curso_nome);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Docentes importados para uma turma EduCuidar.
     * @return list<array>
     */
    public function porTurmaEducuidar($turma_id) {
        $this->garantirSchema();
        $this->garantirColunaTurmaId();
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE turma_id = :turma_id
             ORDER BY codigo_disciplina ASC, docente_nome ASC"
        );
        $stmt->bindValue(':turma_id', (int) $turma_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    private function garantirColunaTurmaId() {
        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tabela
                   AND COLUMN_NAME = 'turma_id'"
            );
            $stmt->execute([':tabela' => $this->table]);
            if ((int) $stmt->fetchColumn() === 0) {
                $this->conn->exec("ALTER TABLE {$this->table} ADD COLUMN turma_id INT NULL");
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    /**
     * Docentes das turmas SIGAA informadas.
     * @param list<array{codigo_disciplina:string,id_turma_sigaa:int}> $turmas
     * @return list<array>
     */
    public function porTurmas(array $turmas) {
        $this->garantirSchema();
        if (empty($turmas)) {
            return [];
        }

        $conds = [];
        $params = [];
        foreach ($turmas as $i => $t) {
            $conds[] = "(codigo_disciplina = ? AND id_turma_sigaa = ?)";
            $params[] = (string) ($t['codigo_disciplina'] ?? '');
            $params[] = (int) ($t['id_turma_sigaa'] ?? 0);
        }
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' OR ', $conds);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function resumo() {
        $this->garantirSchema();
        try {
            $stmt = $this->conn->query(
                "SELECT COUNT(*) AS total,
                        COUNT(DISTINCT codigo_disciplina) AS disciplinas,
                        SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) AS vinculados
                 FROM {$this->table}"
            );
            $row = $stmt ? $stmt->fetch() : null;
        } catch (Throwable $e) {
            return null;
        }
        if (!$row || (int) ($row['total'] ?? 0) === 0) {
            return null;
        }
        return [
            'total' => (int) $row['total'],
            'disciplinas' => (int) $row['disciplinas'],
            'vinculados' => (int) $row['vinculados'],
        ];
    }
}
