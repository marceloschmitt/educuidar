<?php
/**
 * Envio de e-mails de eventos aos responsáveis (após atraso de 2h).
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
        $query = "SELECT e.id, e.aluno_id, e.data_evento, e.hora_evento, e.observacoes, e.created_at,
                         te.nome AS tipo_nome,
                         COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome
                  FROM eventos e
                  INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
                  INNER JOIN alunos a ON a.id = e.aluno_id
                  WHERE te.notificar_email_responsaveis = 1
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
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function jaEnviado($evento_id, $responsavel_id) {
        $query = "SELECT 1 FROM " . $this->table . "
                  WHERE evento_id = :evento_id AND responsavel_id = :responsavel_id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function registrarEnvio($evento_id, $responsavel_id, $email) {
        $query = "INSERT INTO " . $this->table . " (evento_id, responsavel_id, email)
                  VALUES (:evento_id, :responsavel_id, :email)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->bindParam(':responsavel_id', $responsavel_id);
        $stmt->bindParam(':email', $email);
        return $stmt->execute();
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
        $obs = trim((string) ($evento['observacoes'] ?? ''));

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
        if ($obs !== '') {
            $linhas[] = 'Observação:';
            $linhas[] = $obs;
            $linhas[] = '';
        } else {
            $linhas[] = 'Observação: (não informada)';
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
        $pendentes = $this->listPendentes($limit);

        foreach ($pendentes as $evento) {
            $destinatarios = $responsavel->getAprovadosByAlunoId((int) $evento['aluno_id']);
            if (empty($destinatarios)) {
                $stats['pulados']++;
                continue;
            }

            $assunto = self::montarAssunto($evento);
            $corpo = self::montarCorpo($evento);

            foreach ($destinatarios as $dest) {
                $resp_id = (int) $dest['id'];
                $email = trim((string) ($dest['email'] ?? ''));
                if ($email === '' || $this->jaEnviado((int) $evento['id'], $resp_id)) {
                    $stats['pulados']++;
                    continue;
                }

                try {
                    $mailer->send([$email], $assunto, $corpo);
                    $this->registrarEnvio((int) $evento['id'], $resp_id, $email);
                    $stats['enviados']++;
                    $stats['mensagens'][] = "OK evento={$evento['id']} → {$email}";
                } catch (Exception $e) {
                    $stats['falhas']++;
                    $stats['mensagens'][] = "ERRO evento={$evento['id']} → {$email}: " . $e->getMessage();
                }
            }
        }

        return $stats;
    }
}
