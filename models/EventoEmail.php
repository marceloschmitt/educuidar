<?php
/**
 * Envio de e-mails de eventos aos responsáveis (após atraso de 2h),
 * com cópia aos coordenadores do curso.
 */
class EventoEmail {
    private $conn;
    private $table = 'eventos_email_enviados';

    /** Horas após o registro do evento antes de enviar. */
    public const ATRASO_HORAS = 2;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Eventos elegíveis: tipo notifica e-mail, registrados há >= 2h,
     * ainda com destinatários aprovados sem registro de envio.
     */
    public function listPendentes($limit = 100) {
        $limit = max(1, (int) $limit);
        $config = new Configuracao($this->conn);
        $desde = $config->getEmailEventosDesde();

        $query = "SELECT e.id, e.aluno_id, e.turma_id, e.data_evento, e.hora_evento, e.observacoes, e.created_at,
                         te.nome AS tipo_nome,
                         te.observacoes_visiveis_responsaveis,
                         COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome
                  FROM eventos e
                  INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
                  INNER JOIN alunos a ON a.id = e.aluno_id
                  WHERE te.notificar_email_responsaveis = 1
                    AND e.created_at >= :desde
                    AND e.created_at <= DATE_SUB(NOW(), INTERVAL " . (int) self::ATRASO_HORAS . " HOUR)
                    AND EXISTS (
                        SELECT 1
                        FROM responsavel_alunos ra
                        INNER JOIN responsaveis r ON r.id = ra.responsavel_id
                        WHERE ra.aluno_id = e.aluno_id
                          AND r.status = 'aprovado'
                          AND r.ativo = 1
                          AND r.email <> ''
                          AND NOT EXISTS (
                              SELECT 1 FROM " . $this->table . " ee
                              WHERE ee.evento_id = e.id AND ee.responsavel_id = r.id
                          )
                    )
                  ORDER BY e.created_at ASC
                  LIMIT {$limit}";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':desde', $desde);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function jaEnviadoResponsavel($evento_id, $responsavel_id) {
        $query = "SELECT 1 FROM " . $this->table . "
                  WHERE evento_id = :evento_id AND responsavel_id = :responsavel_id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function jaEnviadoCoordenador($evento_id, $user_id) {
        $query = "SELECT 1 FROM " . $this->table . "
                  WHERE evento_id = :evento_id AND user_id = :user_id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function registrarEnvioResponsavel($evento_id, $responsavel_id, $email) {
        $query = "INSERT INTO " . $this->table . " (evento_id, responsavel_id, user_id, email)
                  VALUES (:evento_id, :responsavel_id, NULL, :email)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->bindParam(':email', $email);
        return $stmt->execute();
    }

    public function registrarEnvioCoordenador($evento_id, $user_id, $email) {
        $query = "INSERT INTO " . $this->table . " (evento_id, responsavel_id, user_id, email)
                  VALUES (:evento_id, NULL, :user_id, :email)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email', $email);
        return $stmt->execute();
    }

    /** Histórico de envios (mais recentes primeiro). */
    public function listEnviados($limit = 100) {
        $limit = max(1, (int) $limit);
        $query = "SELECT ee.id, ee.evento_id, ee.email, ee.enviado_em,
                         ee.responsavel_id, ee.user_id,
                         COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome,
                         te.nome AS tipo_nome,
                         e.data_evento, e.hora_evento,
                         r.nome AS responsavel_nome,
                         u.full_name AS coordenador_nome,
                         CASE
                             WHEN ee.responsavel_id IS NOT NULL THEN 'Responsável'
                             WHEN ee.user_id IS NOT NULL THEN 'Coordenador'
                             ELSE '—'
                         END AS destinatario_tipo
                  FROM " . $this->table . " ee
                  INNER JOIN eventos e ON e.id = ee.evento_id
                  INNER JOIN alunos a ON a.id = e.aluno_id
                  INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
                  LEFT JOIN responsaveis r ON r.id = ee.responsavel_id
                  LEFT JOIN users u ON u.id = ee.user_id
                  ORDER BY ee.enviado_em DESC, ee.id DESC
                  LIMIT {$limit}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Resolve curso_id a partir da turma do evento ou da matrícula no ano corrente. */
    public function getCursoIdDoEvento($aluno_id, $turma_id = null) {
        if (!empty($turma_id)) {
            $stmt = $this->conn->prepare("SELECT curso_id FROM turmas WHERE id = :id LIMIT 1");
            $stmt->bindParam(':id', $turma_id);
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row && !empty($row['curso_id'])) {
                return (int) $row['curso_id'];
            }
        }

        $config = new Configuracao($this->conn);
        $ano = $config->getAnoCorrente();
        $query = "SELECT t.curso_id
                  FROM aluno_turmas at
                  INNER JOIN turmas t ON t.id = at.turma_id
                  WHERE at.aluno_id = :aluno_id AND t.ano_civil = :ano
                  ORDER BY t.id DESC
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':ano', $ano);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row && !empty($row['curso_id']) ? (int) $row['curso_id'] : null;
    }

    public static function montarAssunto(array $evento) {
        $tipo = $evento['tipo_nome'] ?? 'Ocorrência';
        $aluno = $evento['aluno_nome'] ?? 'aluno(a)';
        return 'EduCuidar: ' . $tipo . ' — ' . $aluno;
    }

    public static function montarCorpo(array $evento) {
        $tipo = $evento['tipo_nome'] ?? 'Ocorrência';
        $aluno = $evento['aluno_nome'] ?? 'aluno(a)';
        $data = !empty($evento['data_evento'])
            ? date('d/m/Y', strtotime($evento['data_evento']))
            : '—';
        $hora = !empty($evento['hora_evento'])
            ? substr($evento['hora_evento'], 0, 5)
            : '';
        $incluir_obs = !empty($evento['observacoes_visiveis_responsaveis']);
        $obs = $incluir_obs ? trim((string) ($evento['observacoes'] ?? '')) : '';

        $linhas = [];
        $linhas[] = 'Esta é uma mensagem automática do sistema EduCuidar.';
        $linhas[] = 'Não responda a este e-mail — respostas não são monitoradas.';
        $linhas[] = '';
        $linhas[] = 'Informamos o registro da seguinte ocorrência:';
        $linhas[] = '';
        $linhas[] = 'Aluno(a): ' . $aluno;
        $linhas[] = 'Tipo: ' . $tipo;
        $linhas[] = 'Data: ' . $data . ($hora !== '' ? ' às ' . $hora : '');
        $linhas[] = '';
        if ($incluir_obs) {
            if ($obs !== '') {
                $linhas[] = 'Observação:';
                $linhas[] = $obs;
            } else {
                $linhas[] = 'Observação: (não informada)';
            }
            $linhas[] = '';
        }
        $linhas[] = 'O EduCuidar é apenas um apoio à comunicação escolar.';
        $linhas[] = 'Confirme o fato diretamente com o(a) adolescente.';
        $linhas[] = '';
        $linhas[] = '—';
        $linhas[] = 'EduCuidar';

        return implode("\n", $linhas);
    }

    /**
     * Processa pendentes e envia. Retorna estatísticas.
     * @return array{enviados: int, falhas: int, pulados: int, mensagens: list<string>}
     */
    public function processarPendentes(Configuracao $configuracao, $limit = 100) {
        $stats = ['enviados' => 0, 'falhas' => 0, 'pulados' => 0, 'mensagens' => []];

        if (!$configuracao->isEmailEnabled()) {
            $stats['mensagens'][] = 'Envio desabilitado (email_enabled).';
            return $stats;
        }
        if (!$configuracao->permiteEnvioEmail()) {
            $stats['mensagens'][] = 'Envio bloqueado pela variável de ambiente EMAIL_SEND.';
            return $stats;
        }
        if (!$configuracao->isEmailConfigured()) {
            $stats['mensagens'][] = 'SMTP incompleto (host, porta e remetente).';
            return $stats;
        }

        $emailConfig = $configuracao->getEmailConfig();
        $mailer = new SmtpMailer($emailConfig);
        $responsavel = new Responsavel($this->conn);
        $user = new User($this->conn);
        $pendentes = $this->listPendentes($limit);

        foreach ($pendentes as $evento) {
            $destinatarios = $responsavel->getAprovadosByAlunoId((int) $evento['aluno_id']);
            if (empty($destinatarios)) {
                $stats['pulados']++;
                continue;
            }

            $assunto = self::montarAssunto($evento);
            $corpo = self::montarCorpo($evento);

            $curso_id = $this->getCursoIdDoEvento(
                (int) $evento['aluno_id'],
                !empty($evento['turma_id']) ? (int) $evento['turma_id'] : null
            );
            $coordenadores = $curso_id ? $user->getCoordenadoresPorCurso($curso_id) : [];
            $cc = [];
            foreach ($coordenadores as $coord) {
                $email_coord = trim((string) ($coord['email'] ?? ''));
                if ($email_coord === '') {
                    continue;
                }
                if ($this->jaEnviadoCoordenador((int) $evento['id'], (int) $coord['id'])) {
                    continue;
                }
                $cc[] = $email_coord;
            }
            $cc = array_values(array_unique($cc));
            $cc_enviado_neste_evento = false;

            foreach ($destinatarios as $dest) {
                $resp_id = (int) $dest['id'];
                $email = trim((string) ($dest['email'] ?? ''));
                if ($email === '' || $this->jaEnviadoResponsavel((int) $evento['id'], $resp_id)) {
                    $stats['pulados']++;
                    continue;
                }

                // Cópia ao coordenador só no primeiro envio bem-sucedido deste evento
                $cc_desta_msg = (!$cc_enviado_neste_evento && $cc !== []) ? $cc : [];

                try {
                    $mailer->send([$email], $assunto, $corpo, $cc_desta_msg);
                    $this->registrarEnvioResponsavel((int) $evento['id'], $resp_id, $email);
                    $stats['enviados']++;
                    $stats['mensagens'][] = "OK evento={$evento['id']} → {$email}"
                        . ($cc_desta_msg !== [] ? ' (Cc coordenação)' : '');

                    if ($cc_desta_msg !== []) {
                        foreach ($coordenadores as $coord) {
                            $email_coord = trim((string) ($coord['email'] ?? ''));
                            if ($email_coord === '' || !in_array($email_coord, $cc_desta_msg, true)) {
                                continue;
                            }
                            if ($this->jaEnviadoCoordenador((int) $evento['id'], (int) $coord['id'])) {
                                continue;
                            }
                            $this->registrarEnvioCoordenador((int) $evento['id'], (int) $coord['id'], $email_coord);
                        }
                        $cc_enviado_neste_evento = true;
                    }
                } catch (Exception $e) {
                    $stats['falhas']++;
                    $stats['mensagens'][] = "ERRO evento={$evento['id']} → {$email}: " . $e->getMessage();
                }
            }
        }

        return $stats;
    }
}
