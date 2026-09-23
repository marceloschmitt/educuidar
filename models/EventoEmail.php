<?php
/**
 * Envio de e-mails de eventos aos responsáveis (após atraso de 2h)
 * e resumo diário aos coordenadores (após 19:30).
 */
class EventoEmail {
    private $conn;
    private $table = 'eventos_email_enviados';
    private $table_resumo = 'email_resumo_coordenador';

    /** Horas após o registro do evento antes de enviar aos responsáveis. */
    public const ATRASO_HORAS = 2;

    /** Horário (HH:MM) a partir do qual o resumo do dia pode ser enviado. */
    public const HORA_RESUMO_COORDENADOR = '19:30';

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

    public function resumoJaEnviado($user_id, $data_ref) {
        $query = "SELECT 1 FROM " . $this->table_resumo . "
                  WHERE user_id = :user_id AND data_ref = :data_ref
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':data_ref', $data_ref);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function registrarResumoCoordenador($user_id, $data_ref, $email, $total_eventos) {
        $query = "INSERT INTO " . $this->table_resumo . "
                  (user_id, data_ref, email, total_eventos)
                  VALUES (:user_id, :data_ref, :email, :total_eventos)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':data_ref', $data_ref);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':total_eventos', $total_eventos);
        return $stmt->execute();
    }

    /** Histórico de envios aos responsáveis (mais recentes primeiro). */
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

    /** Histórico de resumos diários aos coordenadores. */
    public function listResumosCoordenador($limit = 50) {
        $limit = max(1, (int) $limit);
        $query = "SELECT rc.*, u.full_name AS coordenador_nome
                  FROM " . $this->table_resumo . " rc
                  INNER JOIN users u ON u.id = rc.user_id
                  ORDER BY rc.enviado_em DESC, rc.id DESC
                  LIMIT {$limit}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Eventos do dia (notificáveis) a partir da data inicial.
     * Um registro por evento (curso resolvido pela turma do evento ou matrícula atual).
     */
    public function listEventosDoDiaParaResumo($data_ref) {
        $config = new Configuracao($this->conn);
        $desde = $config->getEmailEventosDesde();
        $ano = $config->getAnoCorrente();

        $query = "SELECT e.id, e.aluno_id, e.turma_id, e.data_evento, e.hora_evento, e.observacoes, e.created_at,
                         te.nome AS tipo_nome,
                         te.observacoes_visiveis_responsaveis,
                         COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome,
                         COALESCE(
                             t.curso_id,
                             (
                                 SELECT t2.curso_id
                                 FROM aluno_turmas at
                                 INNER JOIN turmas t2 ON t2.id = at.turma_id
                                 WHERE at.aluno_id = e.aluno_id AND t2.ano_civil = :ano
                                 ORDER BY t2.id DESC
                                 LIMIT 1
                             )
                         ) AS curso_id,
                         COALESCE(
                             c.nome,
                             (
                                 SELECT c2.nome
                                 FROM aluno_turmas at
                                 INNER JOIN turmas t2 ON t2.id = at.turma_id
                                 INNER JOIN cursos c2 ON c2.id = t2.curso_id
                                 WHERE at.aluno_id = e.aluno_id AND t2.ano_civil = :ano2
                                 ORDER BY t2.id DESC
                                 LIMIT 1
                             )
                         ) AS curso_nome
                  FROM eventos e
                  INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
                  INNER JOIN alunos a ON a.id = e.aluno_id
                  LEFT JOIN turmas t ON t.id = e.turma_id
                  LEFT JOIN cursos c ON c.id = t.curso_id
                  WHERE te.notificar_email_responsaveis = 1
                    AND DATE(e.created_at) = :data_ref
                    AND e.created_at >= :desde
                  ORDER BY curso_nome ASC,
                           COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC,
                           e.created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':data_ref', $data_ref);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':ano', $ano);
        $stmt->bindParam(':ano2', $ano);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function podeEnviarResumoAgora($agora = null) {
        $agora = $agora ?: date('H:i');
        return $agora >= self::HORA_RESUMO_COORDENADOR;
    }

    public static function montarAssuntoResumo($data_ref) {
        return 'EduCuidar: resumo de ocorrências — ' . date('d/m/Y', strtotime($data_ref));
    }

    public static function montarCorpoResumo(array $eventos, $data_ref) {
        $linhas = [];
        $linhas[] = 'Esta é uma mensagem automática do sistema EduCuidar.';
        $linhas[] = 'Não responda a este e-mail — respostas não são monitoradas.';
        $linhas[] = '';
        $linhas[] = 'Resumo das ocorrências registradas em ' . date('d/m/Y', strtotime($data_ref)) . ':';
        $linhas[] = '';

        $curso_atual = null;
        foreach ($eventos as $ev) {
            $curso = $ev['curso_nome'] ?? 'Curso não identificado';
            if ($curso !== $curso_atual) {
                if ($curso_atual !== null) {
                    $linhas[] = '';
                }
                $linhas[] = '— ' . $curso . ' —';
                $curso_atual = $curso;
            }
            $aluno = $ev['aluno_nome'] ?? 'aluno(a)';
            $tipo = $ev['tipo_nome'] ?? 'Ocorrência';
            $hora = !empty($ev['hora_evento']) ? substr($ev['hora_evento'], 0, 5) : '';
            $hora_reg = !empty($ev['created_at']) ? date('H:i', strtotime($ev['created_at'])) : '';
            $linha = '• ' . $aluno . ' — ' . $tipo;
            if ($hora !== '') {
                $linha .= ' (evento ' . $hora . ')';
            } elseif ($hora_reg !== '') {
                $linha .= ' (registro ' . $hora_reg . ')';
            }
            $linhas[] = $linha;

            if (!empty($ev['observacoes_visiveis_responsaveis'])) {
                $obs = trim((string) ($ev['observacoes'] ?? ''));
                if ($obs !== '') {
                    $linhas[] = '  Obs.: ' . $obs;
                }
            }
        }

        $linhas[] = '';
        $linhas[] = 'Total: ' . count($eventos) . ' ocorrência(s).';
        $linhas[] = '';
        $linhas[] = 'O EduCuidar é apenas um apoio à comunicação escolar.';
        $linhas[] = '';
        $linhas[] = '—';
        $linhas[] = 'EduCuidar';

        return implode("\n", $linhas);
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
     * Processa pendentes aos responsáveis. Retorna estatísticas.
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
                if ($email === '' || $this->jaEnviadoResponsavel((int) $evento['id'], $resp_id)) {
                    $stats['pulados']++;
                    continue;
                }

                try {
                    $mailer->send([$email], $assunto, $corpo);
                    $this->registrarEnvioResponsavel((int) $evento['id'], $resp_id, $email);
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

    /**
     * Um e-mail de resumo por coordenador, após 19:30, com a lista do dia.
     * @return array{enviados: int, falhas: int, pulados: int, mensagens: list<string>}
     */
    public function processarResumosCoordenadores(Configuracao $configuracao, $forcar = false) {
        $stats = ['enviados' => 0, 'falhas' => 0, 'pulados' => 0, 'mensagens' => []];

        if (!$configuracao->isEmailEnabled()) {
            $stats['mensagens'][] = 'Resumo: envio desabilitado (email_enabled).';
            return $stats;
        }
        if (!$configuracao->permiteEnvioEmail()) {
            $stats['mensagens'][] = 'Resumo: bloqueado pela variável EMAIL_SEND.';
            return $stats;
        }
        if (!$configuracao->isEmailConfigured()) {
            $stats['mensagens'][] = 'Resumo: SMTP incompleto.';
            return $stats;
        }
        if (!$forcar && !self::podeEnviarResumoAgora()) {
            $stats['mensagens'][] = 'Resumo: ainda não são '
                . self::HORA_RESUMO_COORDENADOR
                . ' (hora atual ' . date('H:i') . ').';
            return $stats;
        }

        $data_ref = date('Y-m-d');
        $eventos = $this->listEventosDoDiaParaResumo($data_ref);
        if (empty($eventos)) {
            $stats['mensagens'][] = 'Resumo: nenhuma ocorrência notificável hoje.';
            return $stats;
        }

        $por_curso = [];
        foreach ($eventos as $ev) {
            $curso_id = (int) ($ev['curso_id'] ?? 0);
            if ($curso_id <= 0) {
                continue;
            }
            if (!isset($por_curso[$curso_id])) {
                $por_curso[$curso_id] = [];
            }
            $por_curso[$curso_id][] = $ev;
        }
        if ($por_curso === []) {
            $stats['mensagens'][] = 'Resumo: ocorrências sem curso identificado.';
            return $stats;
        }

        $user = new User($this->conn);
        $mailer = new SmtpMailer($configuracao->getEmailConfig());

        // Agrupa por coordenador (pode coordenar vários cursos)
        $por_coordenador = [];
        foreach ($por_curso as $curso_id => $lista) {
            foreach ($user->getCoordenadoresPorCurso($curso_id) as $coord) {
                $uid = (int) $coord['id'];
                $email = trim((string) ($coord['email'] ?? ''));
                if ($email === '') {
                    continue;
                }
                if (!isset($por_coordenador[$uid])) {
                    $por_coordenador[$uid] = [
                        'id' => $uid,
                        'nome' => $coord['full_name'] ?? '',
                        'email' => $email,
                        'eventos' => [],
                        'ids_vistos' => [],
                    ];
                }
                foreach ($lista as $ev) {
                    $eid = (int) $ev['id'];
                    if (isset($por_coordenador[$uid]['ids_vistos'][$eid])) {
                        continue;
                    }
                    $por_coordenador[$uid]['ids_vistos'][$eid] = true;
                    $por_coordenador[$uid]['eventos'][] = $ev;
                }
            }
        }

        if ($por_coordenador === []) {
            $stats['mensagens'][] = 'Resumo: nenhum coordenador com e-mail para os cursos do dia.';
            return $stats;
        }

        foreach ($por_coordenador as $coord) {
            $uid = (int) $coord['id'];
            $email = $coord['email'];
            if ($this->resumoJaEnviado($uid, $data_ref)) {
                $stats['pulados']++;
                $stats['mensagens'][] = "Resumo já enviado hoje → {$email}";
                continue;
            }
            if (empty($coord['eventos'])) {
                $stats['pulados']++;
                continue;
            }

            // Ordena por curso/aluno para o corpo
            usort($coord['eventos'], static function ($a, $b) {
                $ca = (string) ($a['curso_nome'] ?? '');
                $cb = (string) ($b['curso_nome'] ?? '');
                if ($ca !== $cb) {
                    return strcmp($ca, $cb);
                }
                return strcmp((string) ($a['aluno_nome'] ?? ''), (string) ($b['aluno_nome'] ?? ''));
            });

            $assunto = self::montarAssuntoResumo($data_ref);
            $corpo = self::montarCorpoResumo($coord['eventos'], $data_ref);
            $total = count($coord['eventos']);

            try {
                $mailer->send([$email], $assunto, $corpo);
                $this->registrarResumoCoordenador($uid, $data_ref, $email, $total);
                $stats['enviados']++;
                $stats['mensagens'][] = "OK resumo {$data_ref} ({$total} ocorrências) → {$email}";
            } catch (Exception $e) {
                $stats['falhas']++;
                $stats['mensagens'][] = "ERRO resumo → {$email}: " . $e->getMessage();
            }
        }

        return $stats;
    }
}
