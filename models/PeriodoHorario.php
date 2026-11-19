<?php
/**
 * Mapa único do campus: período SIGAA (M/T/N + número) → horário de relógio.
 */

class PeriodoHorario {
    private $conn;
    private $table = 'periodos_horario';
    private $schema_ok = false;

    public const TURNOS = [
        'M' => 'Manhã',
        'T' => 'Tarde',
        'N' => 'Noite',
    ];

    public function __construct($db) {
        $this->conn = $db;
    }

    public function garantirSchema() {
        if ($this->schema_ok) {
            return;
        }
        try {
            $sql = @file_get_contents(__DIR__ . '/../sql/periodos_horario.sql');
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

    /** @return list<array> */
    public function getAll() {
        $this->garantirSchema();
        try {
            $stmt = $this->conn->query(
                "SELECT * FROM {$this->table}
                 ORDER BY FIELD(turno, 'M', 'T', 'N'), periodo ASC"
            );
            return $stmt ? $stmt->fetchAll() : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Substitui o mapa inteiro.
     * @param list<array{turno:string,periodo:int,hora_inicio:string,hora_fim:string}> $linhas
     */
    public function salvarTodos(array $linhas) {
        $this->garantirSchema();
        $this->conn->beginTransaction();
        try {
            $this->conn->exec("DELETE FROM {$this->table}");
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table} (turno, periodo, hora_inicio, hora_fim)
                 VALUES (:turno, :periodo, :hora_inicio, :hora_fim)"
            );
            foreach ($linhas as $linha) {
                $turno = strtoupper(substr(trim((string) ($linha['turno'] ?? '')), 0, 1));
                $periodo = (int) ($linha['periodo'] ?? 0);
                $inicio = $this->normalizarHora($linha['hora_inicio'] ?? '');
                $fim = $this->normalizarHora($linha['hora_fim'] ?? '');
                if (!isset(self::TURNOS[$turno]) || $periodo < 1 || $inicio === null || $fim === null) {
                    continue;
                }
                if ($fim <= $inicio) {
                    continue;
                }
                $stmt->bindValue(':turno', $turno);
                $stmt->bindValue(':periodo', $periodo, PDO::PARAM_INT);
                $stmt->bindValue(':hora_inicio', $inicio);
                $stmt->bindValue(':hora_fim', $fim);
                $stmt->execute();
            }
            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * Períodos do turno cuja faixa contém a hora (HH:MM ou HH:MM:SS).
     * @return list<array>
     */
    public function periodosQueContemHora($turno, $hora) {
        $this->garantirSchema();
        $turno = strtoupper(substr(trim((string) $turno), 0, 1));
        $hora = $this->normalizarHora($hora);
        if (!isset(self::TURNOS[$turno]) || $hora === null) {
            return [];
        }
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE turno = :turno
               AND hora_inicio <= :hora_ini
               AND hora_fim > :hora_fim
             ORDER BY periodo ASC"
        );
        $stmt->bindValue(':turno', $turno);
        $stmt->bindValue(':hora_ini', $hora);
        $stmt->bindValue(':hora_fim', $hora);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function temMapa() {
        $this->garantirSchema();
        try {
            $n = (int) $this->conn->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
            return $n > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return string|null HH:MM:SS */
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
