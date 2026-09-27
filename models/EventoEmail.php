<?php
/**
 * Resumos diários por e-mail (após 19:30) dos eventos ocorridos no dia
 * (data_evento = hoje): um por responsável e um por coordenador de curso.
 */
class EventoEmail {
    private $conn;
    private $table_resumo_coord = 'email_resumo_coordenador';
    private $table_resumo_resp = 'email_resumo_responsavel';

    /** Horário (HH:MM) a partir do qual os resumos do dia podem ser enviados. */
    public const HORA_RESUMO = '19:30';

    public function __construct($db) {
        $this->conn = $db;
    }

    public static function podeEnviarResumoAgora($agora = null) {
        $agora = $agora ?: date('H:i');
        return $agora >= self::HORA_RESUMO;
    }

    /**
     * Eventos do dia que entram nos resumos: tipo com e-mail habilitado
     * e fora de carga silenciosa do SIGAA.
     */
    public function listEventosDoDia($data_ref) {
        $config = new Configuracao($this->conn);
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
                    AND e.sem_notificacao = 0
                    AND e.data_evento = :data_ref
                  ORDER BY curso_nome ASC,
                           COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC,
                           e.hora_evento IS NULL, e.hora_evento ASC,
                           e.created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':data_ref', $data_ref);
        $stmt->bindParam(':ano', $ano);
        $stmt->bindParam(':ano2', $ano);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Responsáveis aprovados, ativos e com e-mail, com os alunos vinculados. */
    private function listResponsaveisDestinatarios() {
        $query = "SELECT r.id, r.nome, r.email, ra.aluno_id
                  FROM responsaveis r
                  INNER JOIN responsavel_alunos ra ON ra.responsavel_id = r.id
                  WHERE r.status = 'aprovado'
                    AND r.ativo = 1
                    AND r.email <> ''";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $por_resp = [];
        foreach ($stmt->fetchAll() as $row) {
            $rid = (int) $row['id'];
            if (!isset($por_resp[$rid])) {
                $por_resp[$rid] = [
                    'id' => $rid,
                    'nome' => $row['nome'],
                    'email' => trim((string) $row['email']),
                    'aluno_ids' => [],
                ];
            }
            $por_resp[$rid]['aluno_ids'][(int) $row['aluno_id']] = true;
        }
        return $por_resp;
    }

    // ------------------------------------------------------------------
    // Registro dos envios
    // ------------------------------------------------------------------

    public function resumoCoordenadorJaEnviado($user_id, $data_ref) {
        $stmt = $this->conn->prepare("SELECT 1 FROM " . $this->table_resumo_coord . "
                                      WHERE user_id = :user_id AND data_ref = :data_ref LIMIT 1");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':data_ref', $data_ref);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function registrarResumoCoordenador($user_id, $data_ref, $email, $total_eventos) {
        $stmt = $this->conn->prepare("INSERT INTO " . $this->table_resumo_coord . "
                                      (user_id, data_ref, email, total_eventos)
                                      VALUES (:user_id, :data_ref, :email, :total_eventos)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':data_ref', $data_ref);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':total_eventos', $total_eventos);
        return $stmt->execute();
    }

    public function resumoResponsavelJaEnviado($responsavel_id, $data_ref) {
        $stmt = $this->conn->prepare("SELECT 1 FROM " . $this->table_resumo_resp . "
                                      WHERE responsavel_id = :rid AND data_ref = :data_ref LIMIT 1");
        $stmt->bindParam(':rid', $responsavel_id);
        $stmt->bindParam(':data_ref', $data_ref);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function registrarResumoResponsavel($responsavel_id, $data_ref, $email, $total_eventos) {
        $stmt = $this->conn->prepare("INSERT INTO " . $this->table_resumo_resp . "
                                      (responsavel_id, data_ref, email, total_eventos)
                                      VALUES (:rid, :data_ref, :email, :total_eventos)");
        $stmt->bindParam(':rid', $responsavel_id);
        $stmt->bindParam(':data_ref', $data_ref);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':total_eventos', $total_eventos);
        return $stmt->execute();
    }

    /** Histórico de resumos diários aos coordenadores. */
    public function listResumosCoordenador($limit = 50) {
        $limit = max(1, (int) $limit);
        $query = "SELECT rc.*, u.full_name AS coordenador_nome
                  FROM " . $this->table_resumo_coord . " rc
                  INNER JOIN users u ON u.id = rc.user_id
                  ORDER BY rc.enviado_em DESC, rc.id DESC
                  LIMIT {$limit}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Histórico de resumos diários aos responsáveis (mais recentes primeiro).
     * Filtros pelos alunos atualmente vinculados ao responsável.
     * @param array{curso_id?: int|null, turma_id?: int|null, aluno_id?: int|null} $filtros
     */
    public function listResumosResponsavel($limit = 300, array $filtros = []) {
        $limit = max(1, (int) $limit);
        $config = new Configuracao($this->conn);
        $ano = $config->getAnoCorrente();

        $query = "SELECT rr.*, r.nome AS responsavel_nome,
                         (SELECT GROUP_CONCAT(COALESCE(NULLIF(a.nome_social, ''), a.nome) ORDER BY a.nome SEPARATOR ', ')
                          FROM responsavel_alunos ra
                          INNER JOIN alunos a ON a.id = ra.aluno_id
                          WHERE ra.responsavel_id = rr.responsavel_id) AS alunos_nomes
                  FROM " . $this->table_resumo_resp . " rr
                  INNER JOIN responsaveis r ON r.id = rr.responsavel_id
                  WHERE 1=1";
        $params = [];

        if (!empty($filtros['aluno_id'])) {
            $query .= " AND EXISTS (
                SELECT 1 FROM responsavel_alunos rax
                WHERE rax.responsavel_id = rr.responsavel_id AND rax.aluno_id = :aluno_id
            )";
            $params[':aluno_id'] = (int) $filtros['aluno_id'];
        }
        if (!empty($filtros['turma_id'])) {
            $query .= " AND EXISTS (
                SELECT 1 FROM responsavel_alunos rax
                INNER JOIN aluno_turmas atx ON atx.aluno_id = rax.aluno_id
                WHERE rax.responsavel_id = rr.responsavel_id AND atx.turma_id = :turma_id
            )";
            $params[':turma_id'] = (int) $filtros['turma_id'];
        }
        if (!empty($filtros['curso_id'])) {
            $query .= " AND EXISTS (
                SELECT 1 FROM responsavel_alunos rax
                INNER JOIN aluno_turmas atx ON atx.aluno_id = rax.aluno_id
                INNER JOIN turmas tx ON tx.id = atx.turma_id
                WHERE rax.responsavel_id = rr.responsavel_id
                  AND tx.curso_id = :curso_id
                  AND tx.ano_civil = :ano
            )";
            $params[':curso_id'] = (int) $filtros['curso_id'];
            $params[':ano'] = $ano;
        }

        $query .= " ORDER BY rr.enviado_em DESC, rr.id DESC LIMIT {$limit}";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Montagem das mensagens
    // ------------------------------------------------------------------

    public static function montarAssuntoResumo($data_ref) {
        return 'EduCuidar: resumo de ocorrências — ' . date('d/m/Y', strtotime($data_ref));
    }

    private static function formatarLinhaEvento(array $ev, $letra) {
        $linha = '  ' . $letra . ') ' . ($ev['tipo_nome'] ?? 'Ocorrência');
        if (!empty($ev['hora_evento'])) {
            $linha .= ' (' . substr($ev['hora_evento'], 0, 5) . ')';
        }
        if (!empty($ev['observacoes_visiveis_responsaveis'])) {
            $obs = trim(preg_replace('/^\[AUTO\]\s*/', '', (string) ($ev['observacoes'] ?? '')));
            if ($obs !== '') {
                // Uma linha: evita quebras no meio do resumo
                $linha .= ' — ' . preg_replace('/\s+/', ' ', $obs);
            }
        }
        return $linha;
    }

    /** @param array<string, array{nome: string, eventos: array}> $alunos */
    private static function linhasAlunos(array $alunos) {
        $linhas = [];
        foreach ($alunos as $bloco) {
            $linhas[] = '';
            $linhas[] = '- ' . $bloco['nome'];
            $letra = 'a';
            foreach ($bloco['eventos'] as $ev) {
                $linhas[] = self::formatarLinhaEvento($ev, $letra);
                $letra = $letra === 'z' ? 'a' : chr(ord($letra) + 1);
            }
        }
        return $linhas;
    }

    private static function agruparPorAluno(array $eventos) {
        $alunos = [];
        foreach ($eventos as $ev) {
            $chave = (string) ((int) ($ev['aluno_id'] ?? 0));
            if (!isset($alunos[$chave])) {
                $alunos[$chave] = ['nome' => $ev['aluno_nome'] ?? 'aluno(a)', 'eventos' => []];
            }
            $alunos[$chave]['eventos'][] = $ev;
        }
        return $alunos;
    }

    public static function montarCorpoResumoResponsavel(array $eventos, $data_ref) {
        $linhas = [];
        $linhas[] = 'Esta é uma mensagem automática do sistema EduCuidar.';
        $linhas[] = 'Não responda a este e-mail — respostas não são monitoradas.';
        $linhas[] = '';
        $linhas[] = 'Ocorrências do dia ' . date('d/m/Y', strtotime($data_ref)) . ':';
        $linhas = array_merge($linhas, self::linhasAlunos(self::agruparPorAluno($eventos)));
        $linhas[] = '';
        $linhas[] = 'O EduCuidar é apenas um apoio à comunicação escolar.';
        $linhas[] = 'Confirme os fatos diretamente com o(a) adolescente.';
        $linhas[] = '';
        $linhas[] = '—';
        $linhas[] = 'EduCuidar';
        return implode("\n", $linhas);
    }

    public static function montarCorpoResumo(array $eventos, $data_ref) {
        $linhas = [];
        $linhas[] = 'Esta é uma mensagem automática do sistema EduCuidar.';
        $linhas[] = 'Não responda a este e-mail — respostas não são monitoradas.';
        $linhas[] = '';
        $linhas[] = 'Resumo das ocorrências de ' . date('d/m/Y', strtotime($data_ref)) . ':';
        $linhas[] = '';

        $por_curso = [];
        foreach ($eventos as $ev) {
            $por_curso[$ev['curso_nome'] ?? 'Curso não identificado'][] = $ev;
        }

        $primeiro_curso = true;
        foreach ($por_curso as $curso => $lista) {
            if (!$primeiro_curso) {
                $linhas[] = '';
            }
            $primeiro_curso = false;
            $linhas[] = '— ' . $curso . ' —';
            $linhas = array_merge($linhas, self::linhasAlunos(self::agruparPorAluno($lista)));
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

    // ------------------------------------------------------------------
    // Processamento
    // ------------------------------------------------------------------

    /** @return string|null motivo para não enviar, ou null se pode enviar */
    private function motivoBloqueio(Configuracao $configuracao, $forcar) {
        if (!$configuracao->isEmailEnabled()) {
            return 'envio desabilitado (email_enabled).';
        }
        if (!$configuracao->permiteEnvioEmail()) {
            return 'bloqueado pela variável EMAIL_SEND.';
        }
        if (!$configuracao->isEmailConfigured()) {
            return 'SMTP incompleto (host, porta e remetente).';
        }
        if (!$forcar && !self::podeEnviarResumoAgora()) {
            return 'ainda não são ' . self::HORA_RESUMO . ' (hora atual ' . date('H:i') . ').';
        }
        return null;
    }

    /**
     * Um e-mail por responsável, após 19:30, com os eventos do dia dos seus alunos.
     * Responsável sem evento no dia não recebe nada.
     * @return array{enviados: int, falhas: int, pulados: int, mensagens: list<string>}
     */
    public function processarResumosResponsaveis(Configuracao $configuracao, $forcar = false) {
        $stats = ['enviados' => 0, 'falhas' => 0, 'pulados' => 0, 'mensagens' => []];

        $bloqueio = $this->motivoBloqueio($configuracao, $forcar);
        if ($bloqueio !== null) {
            $stats['mensagens'][] = 'Responsáveis: ' . $bloqueio;
            return $stats;
        }

        $data_ref = date('Y-m-d');
        $eventos = $this->listEventosDoDia($data_ref);
        if (empty($eventos)) {
            $stats['mensagens'][] = 'Responsáveis: nenhuma ocorrência notificável hoje.';
            return $stats;
        }

        $por_aluno = [];
        foreach ($eventos as $ev) {
            $por_aluno[(int) $ev['aluno_id']][] = $ev;
        }

        $mailer = new SmtpMailer($configuracao->getEmailConfig());
        $assunto = self::montarAssuntoResumo($data_ref);

        foreach ($this->listResponsaveisDestinatarios() as $resp) {
            $eventos_resp = [];
            foreach (array_keys($resp['aluno_ids']) as $aluno_id) {
                foreach ($por_aluno[$aluno_id] ?? [] as $ev) {
                    $eventos_resp[] = $ev;
                }
            }
            if (empty($eventos_resp)) {
                continue;
            }
            if ($this->resumoResponsavelJaEnviado($resp['id'], $data_ref)) {
                $stats['pulados']++;
                continue;
            }

            usort($eventos_resp, static function ($a, $b) {
                return strcmp((string) $a['aluno_nome'], (string) $b['aluno_nome']);
            });

            $total = count($eventos_resp);
            try {
                $mailer->send([$resp['email']], $assunto, self::montarCorpoResumoResponsavel($eventos_resp, $data_ref));
                $this->registrarResumoResponsavel($resp['id'], $data_ref, $resp['email'], $total);
                $stats['enviados']++;
                $stats['mensagens'][] = "OK resumo responsável {$data_ref} ({$total} ocorrências) → {$resp['email']}";
            } catch (Exception $e) {
                $stats['falhas']++;
                $stats['mensagens'][] = "ERRO resumo responsável → {$resp['email']}: " . $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Um e-mail por coordenador, após 19:30, com os eventos do dia dos seus cursos.
     * @return array{enviados: int, falhas: int, pulados: int, mensagens: list<string>}
     */
    public function processarResumosCoordenadores(Configuracao $configuracao, $forcar = false) {
        $stats = ['enviados' => 0, 'falhas' => 0, 'pulados' => 0, 'mensagens' => []];

        $bloqueio = $this->motivoBloqueio($configuracao, $forcar);
        if ($bloqueio !== null) {
            $stats['mensagens'][] = 'Coordenadores: ' . $bloqueio;
            return $stats;
        }

        $data_ref = date('Y-m-d');
        $eventos = $this->listEventosDoDia($data_ref);
        if (empty($eventos)) {
            $stats['mensagens'][] = 'Coordenadores: nenhuma ocorrência notificável hoje.';
            return $stats;
        }

        $por_curso = [];
        foreach ($eventos as $ev) {
            $curso_id = (int) ($ev['curso_id'] ?? 0);
            if ($curso_id > 0) {
                $por_curso[$curso_id][] = $ev;
            }
        }
        if ($por_curso === []) {
            $stats['mensagens'][] = 'Coordenadores: ocorrências sem curso identificado.';
            return $stats;
        }

        $user = new User($this->conn);
        $mailer = new SmtpMailer($configuracao->getEmailConfig());

        // Um coordenador pode coordenar vários cursos
        $por_coordenador = [];
        foreach ($por_curso as $curso_id => $lista) {
            foreach ($user->getCoordenadoresPorCurso($curso_id) as $coord) {
                $uid = (int) $coord['id'];
                $email = trim((string) ($coord['email'] ?? ''));
                if ($email === '') {
                    continue;
                }
                if (!isset($por_coordenador[$uid])) {
                    $por_coordenador[$uid] = ['id' => $uid, 'email' => $email, 'eventos' => []];
                }
                foreach ($lista as $ev) {
                    $por_coordenador[$uid]['eventos'][(int) $ev['id']] = $ev;
                }
            }
        }

        if ($por_coordenador === []) {
            $stats['mensagens'][] = 'Coordenadores: nenhum coordenador com e-mail para os cursos do dia.';
            return $stats;
        }

        $assunto = self::montarAssuntoResumo($data_ref);
        foreach ($por_coordenador as $coord) {
            $uid = $coord['id'];
            $email = $coord['email'];
            if ($this->resumoCoordenadorJaEnviado($uid, $data_ref)) {
                $stats['pulados']++;
                $stats['mensagens'][] = "Resumo já enviado hoje → {$email}";
                continue;
            }

            $lista = array_values($coord['eventos']);
            usort($lista, static function ($a, $b) {
                $cmp = strcmp((string) ($a['curso_nome'] ?? ''), (string) ($b['curso_nome'] ?? ''));
                return $cmp !== 0 ? $cmp : strcmp((string) $a['aluno_nome'], (string) $b['aluno_nome']);
            });

            $total = count($lista);
            try {
                $mailer->send([$email], $assunto, self::montarCorpoResumo($lista, $data_ref));
                $this->registrarResumoCoordenador($uid, $data_ref, $email, $total);
                $stats['enviados']++;
                $stats['mensagens'][] = "OK resumo coordenador {$data_ref} ({$total} ocorrências) → {$email}";
            } catch (Exception $e) {
                $stats['falhas']++;
                $stats['mensagens'][] = "ERRO resumo coordenador → {$email}: " . $e->getMessage();
            }
        }

        return $stats;
    }
}
