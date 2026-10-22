<?php
/**
 * Autorizações de entrada/saída fora do horário.
 * Podem ser criadas pelo responsável (portal) ou pela escola (telefone, e-mail etc.).
 * Status: pendente → ocorrido | nao_ocorrido
 */

class AutorizacaoResponsavel {
    private $conn;
    private $table = 'autorizacoes_responsavel';
    private $schema_ok = false;

    public const TIPO_ENTRADA = 'entrada_fora_horario';
    public const TIPO_SAIDA = 'saida_fora_horario';

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_OCORRIDO = 'ocorrido';
    public const STATUS_NAO_OCORRIDO = 'nao_ocorrido';

    public const ORIGEM_PORTAL = 'portal';
    public const ORIGEM_ESCOLA = 'escola';

    public function __construct($db) {
        $this->conn = $db;
    }

    public static function tiposLabels() {
        return [
            self::TIPO_ENTRADA => 'Entrada fora do horário',
            self::TIPO_SAIDA => 'Saída fora do horário',
        ];
    }

    public static function statusLabels() {
        return [
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_OCORRIDO => 'Ocorrido',
            self::STATUS_NAO_OCORRIDO => 'Não ocorrido',
        ];
    }

    public static function meiosLabels() {
        return [
            'telefone' => 'Telefone',
            'email' => 'E-mail',
            'whatsapp' => 'WhatsApp',
            'presencial' => 'Presencial',
            'outro' => 'Outro',
        ];
    }

    public static function statusBadgeClass($status) {
        if ($status === self::STATUS_OCORRIDO) {
            return 'success';
        }
        if ($status === self::STATUS_NAO_OCORRIDO) {
            return 'danger';
        }
        return 'warning';
    }

    public static function ehRegistroEscola(array $item) {
        return ($item['origem'] ?? self::ORIGEM_PORTAL) === self::ORIGEM_ESCOLA;
    }

    /** Nome de quem autorizou (portal: responsável; escola: texto informado). */
    public static function nomeQuemAutorizou(array $item) {
        if (self::ehRegistroEscola($item)) {
            return trim((string) ($item['autorizado_por_nome'] ?? ''));
        }
        return trim((string) ($item['responsavel_nome'] ?? ''));
    }

    private function garantirSchema() {
        if ($this->schema_ok) {
            return;
        }
        try {
            $cols = $this->conn->query("SHOW COLUMNS FROM {$this->table}")->fetchAll(PDO::FETCH_COLUMN);
            if (!$cols) {
                return;
            }
            if (!in_array('origem', $cols, true)) {
                $this->conn->exec(
                    "ALTER TABLE {$this->table}
                     ADD COLUMN origem ENUM('portal','escola') NOT NULL DEFAULT 'portal' AFTER responsavel_id"
                );
            }
            if (!in_array('autorizado_por_nome', $cols, true)) {
                $this->conn->exec(
                    "ALTER TABLE {$this->table}
                     ADD COLUMN autorizado_por_nome VARCHAR(200) NULL AFTER justificativa"
                );
            }
            if (!in_array('meio_autorizacao', $cols, true)) {
                $this->conn->exec(
                    "ALTER TABLE {$this->table}
                     ADD COLUMN meio_autorizacao VARCHAR(50) NULL AFTER autorizado_por_nome"
                );
            }
            if (!in_array('registrado_por', $cols, true)) {
                $this->conn->exec(
                    "ALTER TABLE {$this->table}
                     ADD COLUMN registrado_por INT NULL AFTER meio_autorizacao"
                );
                try {
                    $this->conn->exec(
                        "ALTER TABLE {$this->table}
                         ADD CONSTRAINT fk_autorizacao_registrado_por
                         FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE SET NULL"
                    );
                } catch (Throwable $e) {
                    // FK pode já existir
                }
            }
            // responsavel_id passa a ser opcional (registros da escola)
            $colInfo = $this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'responsavel_id'")->fetch();
            if ($colInfo && strtoupper((string) ($colInfo['Null'] ?? '')) === 'NO') {
                $this->conn->exec("ALTER TABLE {$this->table} MODIFY responsavel_id INT NULL");
            }
        } catch (Throwable $e) {
            // tabela inexistente ou sem permissão ALTER
        }
        $this->schema_ok = true;
    }

    /** Criação pelo responsável no portal. $data/$hora = dia e horário previstos da entrada/saída. */
    public function create($responsavel_id, $aluno_id, $tipo, $data, $hora, $justificativa) {
        $this->garantirSchema();
        if (!isset(self::tiposLabels()[$tipo])) {
            return false;
        }
        $justificativa = trim((string) $justificativa);
        if ($justificativa === '' || empty($data) || empty($hora) || empty($responsavel_id)) {
            return false;
        }

        $query = "INSERT INTO " . $this->table . "
                  (responsavel_id, origem, aluno_id, tipo, data_autorizacao, hora, justificativa, status)
                  VALUES (:responsavel_id, 'portal', :aluno_id, :tipo, :data_autorizacao, :hora, :justificativa, 'pendente')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':responsavel_id', (int) $responsavel_id, PDO::PARAM_INT);
        $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':data_autorizacao', $data);
        $stmt->bindValue(':hora', $hora);
        $stmt->bindValue(':justificativa', $justificativa);
        if (!$stmt->execute()) {
            return false;
        }
        return (int) $this->conn->lastInsertId();
    }

    /**
     * Registro pela escola (aviso por telefone, e-mail etc.).
     * $data/$hora = dia e horário previstos da entrada/saída.
     * O momento do registro fica em created_at (automático).
     */
    public function createByEscola($aluno_id, $tipo, $data, $hora, $autorizado_por_nome, $meio, $observacoes, $registrado_por) {
        $this->garantirSchema();
        if (!isset(self::tiposLabels()[$tipo])) {
            return false;
        }
        $meios = self::meiosLabels();
        if (!isset($meios[$meio])) {
            return false;
        }
        $autorizado_por_nome = trim((string) $autorizado_por_nome);
        $observacoes = trim((string) $observacoes);
        if ($autorizado_por_nome === '' || empty($data) || empty($hora) || empty($aluno_id) || empty($registrado_por)) {
            return false;
        }
        if ($observacoes === '') {
            $observacoes = 'Aviso recebido pela escola.';
        }

        $query = "INSERT INTO " . $this->table . "
                  (responsavel_id, origem, aluno_id, tipo, data_autorizacao, hora, justificativa,
                   autorizado_por_nome, meio_autorizacao, registrado_por, status)
                  VALUES (NULL, 'escola', :aluno_id, :tipo, :data_autorizacao, :hora, :justificativa,
                          :autorizado_por_nome, :meio, :registrado_por, 'pendente')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':data_autorizacao', $data);
        $stmt->bindValue(':hora', $hora);
        $stmt->bindValue(':justificativa', $observacoes);
        $stmt->bindValue(':autorizado_por_nome', $autorizado_por_nome);
        $stmt->bindValue(':meio', $meio);
        $stmt->bindValue(':registrado_por', (int) $registrado_por, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return false;
        }
        return (int) $this->conn->lastInsertId();
    }

    /**
     * Envia e-mail imediato aos professores indicados ($professor_ids = IDs de users).
     * @param list<int> $professor_ids
     * @return array{enviados: int, falhas: int, destinatarios: int, mensagens: list<string>}
     */
    public function enviarEmailProfessores($autorizacao_id, array $professor_ids) {
        $stats = ['enviados' => 0, 'falhas' => 0, 'destinatarios' => 0, 'mensagens' => []];
        $auth = $this->getById($autorizacao_id);
        if (!$auth) {
            $stats['mensagens'][] = 'Autorização não encontrada.';
            return $stats;
        }

        $professor_ids = array_values(array_unique(array_filter(array_map('intval', $professor_ids))));
        if (empty($professor_ids)) {
            $stats['mensagens'][] = 'Nenhum professor selecionado.';
            return $stats;
        }

        $configuracao = new Configuracao($this->conn);
        if (!$configuracao->isEmailEnabled()) {
            $stats['mensagens'][] = 'Envio de e-mail desabilitado em Configuração de e-mail.';
            return $stats;
        }
        if (!$configuracao->permiteEnvioEmail()) {
            $stats['mensagens'][] = 'Envio bloqueado pela variável EMAIL_SEND.';
            return $stats;
        }
        if (!$configuracao->isEmailConfigured()) {
            $stats['mensagens'][] = 'SMTP incompleto (host, porta e remetente).';
            return $stats;
        }

        $userModel = new User($this->conn);
        $todos = $userModel->listProfessoresComEmail();
        $por_id = [];
        foreach ($todos as $p) {
            $por_id[(int) $p['id']] = $p;
        }
        $professores = [];
        foreach ($professor_ids as $pid) {
            if (isset($por_id[$pid])) {
                $professores[] = $por_id[$pid];
            }
        }
        $stats['destinatarios'] = count($professores);
        if (empty($professores)) {
            $stats['mensagens'][] = 'Nenhum dos professores selecionados tem e-mail válido.';
            return $stats;
        }

        $tipos = self::tiposLabels();
        $meios = self::meiosLabels();
        $nome_aluno = !empty($auth['aluno_nome_social']) ? $auth['aluno_nome_social'] : ($auth['aluno_nome'] ?? '');
        $tipo_label = $tipos[$auth['tipo'] ?? ''] ?? ($auth['tipo'] ?? '');
        $data_fmt = !empty($auth['data_autorizacao'])
            ? date('d/m/Y', strtotime($auth['data_autorizacao']))
            : '';
        $hora_fmt = !empty($auth['hora']) ? substr($auth['hora'], 0, 5) : '';
        $meio_label = $meios[$auth['meio_autorizacao'] ?? ''] ?? ($auth['meio_autorizacao'] ?? '');
        $quem = self::nomeQuemAutorizou($auth);
        $turma_label = $this->turmaLabelDoAluno((int) $auth['aluno_id']);

        $assunto = 'Autorização de ' . mb_strtolower($tipo_label) . ': ' . $nome_aluno;
        $linhas = [];
        $linhas[] = 'Foi registrada uma autorização de entrada/saída fora do horário:';
        $linhas[] = '';
        $linhas[] = 'Aluno: ' . $nome_aluno;
        if ($turma_label !== '') {
            $linhas[] = 'Turma: ' . $turma_label;
        }
        $linhas[] = 'Tipo: ' . $tipo_label;
        $linhas[] = 'Dia/horário previstos: ' . $data_fmt . ($hora_fmt !== '' ? ' às ' . $hora_fmt : '');
        $linhas[] = 'Autorizado por: ' . $quem;
        if ($meio_label !== '') {
            $linhas[] = 'Como autorizou: ' . $meio_label;
        }
        if (!empty($auth['justificativa'])) {
            $linhas[] = 'Observações: ' . trim((string) $auth['justificativa']);
        }
        if (!empty($auth['registrado_por_nome'])) {
            $linhas[] = 'Registrado na escola por: ' . $auth['registrado_por_nome'];
        }
        $linhas[] = '';
        $linhas[] = 'Se o aluno sair durante a sua aula de forma antecipada, conforme autorizado aqui, entre no EduCuidar, vá em Autorizações/Entrada e Saída, e marque o evento como ocorrido, por favor.';
        $linhas[] = '';
        $linhas[] = 'O EduCuidar é apenas um apoio à comunicação escolar.';
        $linhas[] = '';
        $linhas[] = '—';
        $linhas[] = 'EduCuidar';
        $corpo = implode("\n", $linhas);

        $mailer = new SmtpMailer($configuracao->getEmailConfig());
        foreach ($professores as $prof) {
            try {
                $mailer->send([$prof['email']], $assunto, $corpo);
                $stats['enviados']++;
            } catch (Exception $e) {
                $stats['falhas']++;
                $stats['mensagens'][] = 'ERRO → ' . $prof['email'] . ': ' . $e->getMessage();
            }
        }

        return $stats;
    }

    private function turmaLabelDoAluno($aluno_id) {
        $config = new Configuracao($this->conn);
        $ano = $config->getAnoCorrente();
        $stmt = $this->conn->prepare(
            "SELECT c.nome AS curso_nome, t.ano_curso
             FROM aluno_turmas at
             INNER JOIN turmas t ON t.id = at.turma_id
             INNER JOIN cursos c ON c.id = t.curso_id
             WHERE at.aluno_id = :aluno_id AND t.ano_civil = :ano
             ORDER BY t.id DESC
             LIMIT 1"
        );
        $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        $stmt->bindValue(':ano', $ano);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return '';
        }
        return ($row['curso_nome'] ?? '') . ' - ' . (int) $row['ano_curso'] . 'º';
    }

    public function getById($id) {
        $this->garantirSchema();
        $query = "SELECT a.*,
                  r.nome as responsavel_nome, r.email as responsavel_email,
                  al.nome as aluno_nome, al.nome_social as aluno_nome_social,
                  u.full_name as confirmado_por_nome,
                  ur.full_name as registrado_por_nome
                  FROM " . $this->table . " a
                  LEFT JOIN responsaveis r ON r.id = a.responsavel_id
                  INNER JOIN alunos al ON al.id = a.aluno_id
                  LEFT JOIN users u ON u.id = a.confirmado_por
                  LEFT JOIN users ur ON ur.id = a.registrado_por
                  WHERE a.id = :id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Autorizações do responsável no portal + as registradas pela escola
     * para alunos vinculados a ele (aviso por telefone, e-mail etc.).
     */
    public function listByResponsavel($responsavel_id, $limit = 50) {
        $this->garantirSchema();
        $query = "SELECT a.*,
                  al.nome as aluno_nome, al.nome_social as aluno_nome_social,
                  ur.full_name as registrado_por_nome
                  FROM " . $this->table . " a
                  INNER JOIN alunos al ON al.id = a.aluno_id
                  LEFT JOIN users ur ON ur.id = a.registrado_por
                  WHERE a.responsavel_id = :responsavel_id
                     OR (
                         a.origem = 'escola'
                         AND a.aluno_id IN (
                             SELECT ra.aluno_id FROM responsavel_alunos ra
                             WHERE ra.responsavel_id = :responsavel_id2
                         )
                     )
                  ORDER BY a.data_autorizacao DESC, a.hora DESC, a.id DESC
                  LIMIT " . (int) $limit;
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':responsavel_id', (int) $responsavel_id, PDO::PARAM_INT);
        $stmt->bindValue(':responsavel_id2', (int) $responsavel_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listForStaff($filtros = []) {
        $this->garantirSchema();
        $query = "SELECT a.*,
                  r.nome as responsavel_nome,
                  al.nome as aluno_nome, al.nome_social as aluno_nome_social, al.numero_matricula,
                  u.full_name as confirmado_por_nome,
                  ur.full_name as registrado_por_nome
                  FROM " . $this->table . " a
                  LEFT JOIN responsaveis r ON r.id = a.responsavel_id
                  INNER JOIN alunos al ON al.id = a.aluno_id
                  LEFT JOIN users u ON u.id = a.confirmado_por
                  LEFT JOIN users ur ON ur.id = a.registrado_por
                  WHERE 1=1";

        $params = [];
        if (!empty($filtros['status'])) {
            $query .= " AND a.status = :status";
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['tipo'])) {
            $query .= " AND a.tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['data'])) {
            $query .= " AND a.data_autorizacao = :data";
            $params[':data'] = $filtros['data'];
        }
        if (!empty($filtros['nome'])) {
            $query .= " AND (al.nome LIKE :nome OR al.nome_social LIKE :nome2
                        OR r.nome LIKE :nome3 OR a.autorizado_por_nome LIKE :nome4)";
            $like = '%' . $filtros['nome'] . '%';
            $params[':nome'] = $like;
            $params[':nome2'] = $like;
            $params[':nome3'] = $like;
            $params[':nome4'] = $like;
        }

        $query .= " ORDER BY
                    CASE a.status WHEN 'pendente' THEN 0 ELSE 1 END,
                    a.data_autorizacao DESC, a.hora DESC, a.id DESC
                    LIMIT 200";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Marca como ocorrido e cria o evento vinculado (se houver tipo configurado). */
    public function marcarOcorrido($id, $user_id) {
        $this->garantirSchema();
        $auth = $this->getById($id);
        if (!$auth || ($auth['status'] ?? '') !== self::STATUS_PENDENTE) {
            return false;
        }

        $config = new Configuracao($this->conn);
        $tipo_evento_id = $config->getAutorizacaoTipoEventoId($auth['tipo']);
        if (!$tipo_evento_id) {
            return 'sem_tipo_evento';
        }

        $parentesco = '';
        if (!empty($auth['responsavel_id'])) {
            $parentesco = $this->getParentesco($auth['responsavel_id'], $auth['aluno_id']);
        }
        $obs = $this->montarObservacoesEvento($auth, $parentesco);

        // Data/hora do evento = as pedidas na autorização (não o momento do OK)
        $data_evento = $auth['data_autorizacao'];
        $hora_evento = substr((string) ($auth['hora'] ?? ''), 0, 8);
        if (strlen($hora_evento) === 5) {
            $hora_evento .= ':00';
        }

        try {
            $this->conn->beginTransaction();

            $evento = new Evento($this->conn);
            $evento->aluno_id = $auth['aluno_id'];
            $evento->turma_id = null;
            $evento->tipo_evento_id = $tipo_evento_id;
            $evento->data_evento = $data_evento;
            $evento->hora_evento = $hora_evento;
            $evento->observacoes = $obs;
            $evento->prontuario = '';
            $evento->registrado_por = $user_id;

            if (!$evento->create()) {
                $this->conn->rollBack();
                return false;
            }

            // Reforça data/hora da autorização no registro criado
            $fixData = $this->conn->prepare(
                "UPDATE eventos SET data_evento = :data_evento, hora_evento = :hora_evento WHERE id = :id"
            );
            $fixData->bindValue(':data_evento', $data_evento);
            $fixData->bindValue(':hora_evento', $hora_evento);
            $fixData->bindValue(':id', (int) $evento->id);
            $fixData->execute();

            $query = "UPDATE " . $this->table . "
                      SET status = 'ocorrido',
                          confirmado_por = :user_id,
                          confirmado_em = NOW(),
                          evento_id = :evento_id
                      WHERE id = :id AND status = 'pendente'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindValue(':evento_id', (int) $evento->id);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
            if (function_exists('processarAlertasAluno')) {
                processarAlertasAluno($this->conn, $auth['aluno_id']);
            }
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    /** Marca como não ocorrido (sem criar evento). */
    public function marcarNaoOcorrido($id, $user_id) {
        $this->garantirSchema();
        $query = "UPDATE " . $this->table . "
                  SET status = 'nao_ocorrido',
                      confirmado_por = :user_id,
                      confirmado_em = NOW()
                  WHERE id = :id AND status = 'pendente'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    private function getParentesco($responsavel_id, $aluno_id) {
        $query = "SELECT parentesco FROM responsavel_alunos
                  WHERE responsavel_id = :responsavel_id AND aluno_id = :aluno_id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row['parentesco'] ?? '';
    }

    private function montarObservacoesEvento(array $auth, $parentesco) {
        $data_fmt = !empty($auth['data_autorizacao'])
            ? date('d/m/Y', strtotime($auth['data_autorizacao']))
            : '';
        $hora_prev = !empty($auth['hora']) ? substr($auth['hora'], 0, 5) : '';
        $linhas = [];
        $linhas[] = 'Observações: ' . trim((string) ($auth['justificativa'] ?? ''));

        if (self::ehRegistroEscola($auth)) {
            $meios = self::meiosLabels();
            $meio = $auth['meio_autorizacao'] ?? '';
            $linhas[] = 'Autorizado por: ' . trim((string) ($auth['autorizado_por_nome'] ?? ''));
            $linhas[] = 'Como autorizou: ' . ($meios[$meio] ?? $meio);
            if (!empty($auth['registrado_por_nome'])) {
                $linhas[] = 'Registrado na escola por: ' . $auth['registrado_por_nome'];
            }
        } else {
            $nome = $auth['responsavel_nome'] ?? '';
            $linhas[] = 'Autorizado por: ' . $nome
                . ($parentesco !== '' ? ' (' . $parentesco . ')' : '');
            $linhas[] = 'Como autorizou: Portal do responsável';
        }

        $linhas[] = 'Dia/horário previstos: ' . $data_fmt . ($hora_prev !== '' ? ' às ' . $hora_prev : '');
        return implode("\n", $linhas);
    }

    /**
     * Remove autorização: só se pendente e a data/hora prevista ainda não chegou.
     */
    public function remover($id, $responsavel_id) {
        $this->garantirSchema();
        $query = "DELETE FROM " . $this->table . "
                  WHERE id = :id
                    AND responsavel_id = :responsavel_id
                    AND origem = 'portal'
                    AND status = 'pendente'
                    AND TIMESTAMP(data_autorizacao, hora) > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /** Exclusão pelo administrador (qualquer status/origem). */
    public function excluirByAdmin($id) {
        $this->garantirSchema();
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /** Indica se o responsável ainda pode remover esta autorização. */
    public static function podeRemover(array $item) {
        if (($item['status'] ?? '') !== self::STATUS_PENDENTE) {
            return false;
        }
        if (($item['origem'] ?? self::ORIGEM_PORTAL) === self::ORIGEM_ESCOLA) {
            return false;
        }
        $data = $item['data_autorizacao'] ?? '';
        if ($data === '') {
            return false;
        }
        $hora = $item['hora'] ?? '00:00:00';
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }
        $ts = strtotime($data . ' ' . $hora);
        if ($ts === false) {
            return false;
        }
        return $ts > time();
    }
}
