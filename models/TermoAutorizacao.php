<?php
/**
 * Autorizações de passeios e eventos escolares (outro tipo além de entrada/saída).
 * A escola cadastra a atividade (PDF + turmas); os responsáveis respondem concordou/discordou.
 * Decisão irrevogável, salvo liberação do admin (com histórico).
 * Pode ser suspensa (ainda com respostas) ou excluída (só sem respostas).
 */
class TermoAutorizacao {
    public const DIRETORIO_PDF = 'uploads/termos';
    public const TAMANHO_MAXIMO_PDF = 10485760; // 10 MB
    public const MIME_PDF = 'application/pdf';

    public const DECISAO_CONCORDOU = 'concordou';
    public const DECISAO_DISCORDOU = 'discordou';

    public const STATUS_ATIVO = 'ativo';
    public const STATUS_SUSPENSO = 'suspenso';

    private $conn;
    private $tabelas_ok = false;

    public function __construct($db) {
        $this->conn = $db;
    }

    public static function decisoesLabels() {
        return [
            self::DECISAO_CONCORDOU => 'Concordou',
            self::DECISAO_DISCORDOU => 'Discordou',
        ];
    }

    public static function statusLabels() {
        return [
            self::STATUS_ATIVO => 'Ativa',
            self::STATUS_SUSPENSO => 'Suspensa',
        ];
    }

    public static function decisaoBadgeClass($decisao) {
        if ($decisao === self::DECISAO_CONCORDOU) {
            return 'success';
        }
        if ($decisao === self::DECISAO_DISCORDOU) {
            return 'danger';
        }
        return 'secondary';
    }

    public static function estaAtivo(array $termo) {
        $status = $termo['status'] ?? self::STATUS_ATIVO;
        return $status === self::STATUS_ATIVO;
    }

    private function garantirTabelas() {
        if ($this->tabelas_ok) {
            return;
        }
        $sql = @file_get_contents(__DIR__ . '/../sql/termos_autorizacao.sql');
        if ($sql === false) {
            return;
        }
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $comando) {
            $this->conn->exec($comando);
        }
        // Migrações em instalações já existentes
        try {
            $cols = $this->conn->query("SHOW COLUMNS FROM termo_autorizacao")->fetchAll(PDO::FETCH_COLUMN);
            if ($cols && !in_array('status', $cols, true)) {
                $this->conn->exec(
                    "ALTER TABLE termo_autorizacao
                     ADD COLUMN status ENUM('ativo','suspenso') NOT NULL DEFAULT 'ativo' AFTER pdf_tamanho,
                     ADD INDEX idx_status (status)"
                );
            }
            if ($cols && !in_array('texto_autorizacao', $cols, true)) {
                $this->conn->exec(
                    "ALTER TABLE termo_autorizacao
                     ADD COLUMN texto_autorizacao TEXT NULL AFTER hora"
                );
            }
            // PDF passou a ser opcional (pode haver só texto)
            if ($cols) {
                $this->conn->exec("ALTER TABLE termo_autorizacao MODIFY pdf_caminho VARCHAR(255) NULL");
                $this->conn->exec("ALTER TABLE termo_autorizacao MODIFY pdf_nome_original VARCHAR(255) NULL");
            }
        } catch (Throwable $e) {
            // tabela ainda inexistente ou sem permissão ALTER
        }
        $this->tabelas_ok = true;
    }

    public static function temPdf(array $termo) {
        return !empty($termo['pdf_caminho']);
    }

    public static function temTexto(array $termo) {
        return trim((string) ($termo['texto_autorizacao'] ?? '')) !== '';
    }

    private function garantirDiretorioPdf($termo_id = null) {
        $raiz = __DIR__ . '/../' . self::DIRETORIO_PDF;
        if (!is_dir($raiz)) {
            mkdir($raiz, 0755, true);
        }
        if (!is_file($raiz . '/.htaccess')) {
            file_put_contents(
                $raiz . '/.htaccess',
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
            );
        }
        if ($termo_id === null) {
            return $raiz;
        }
        $dir = $raiz . '/' . (int) $termo_id;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Cria a autorização. Informe PDF ($arquivo = $_FILES['pdf']) ou texto digitado ($texto_autorizacao).
     * @return int|false id do termo
     */
    public function criar($titulo, $objetivo, $data_evento, $hora, array $turma_ids, $criado_por, $arquivo = null, $texto_autorizacao = null) {
        $this->garantirTabelas();

        $titulo = trim((string) $titulo);
        $objetivo = trim((string) $objetivo);
        $texto_autorizacao = trim((string) ($texto_autorizacao ?? ''));
        $hora = $hora !== null && $hora !== '' ? $hora : null;
        $turma_ids = array_values(array_unique(array_filter(array_map('intval', $turma_ids))));

        $tem_pdf = is_array($arquivo) && ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $tem_texto = $texto_autorizacao !== '';

        if ($titulo === '' || $objetivo === '' || empty($data_evento) || empty($turma_ids)) {
            return false;
        }
        if (!$tem_pdf && !$tem_texto) {
            return false;
        }

        $pdf_nome = null;
        $pdf_mime = null;
        $pdf_tamanho = null;
        if ($tem_pdf) {
            if (($arquivo['size'] ?? 0) > self::TAMANHO_MAXIMO_PDF || ($arquivo['size'] ?? 0) <= 0) {
                return false;
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($arquivo['tmp_name']) ?: '';
            $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            if ($mime !== self::MIME_PDF && $ext !== 'pdf') {
                return false;
            }
            $pdf_nome = basename((string) $arquivo['name']);
            $pdf_mime = self::MIME_PDF;
            $pdf_tamanho = (int) $arquivo['size'];
        }

        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO termo_autorizacao
                 (titulo, objetivo, data_evento, hora, texto_autorizacao,
                  pdf_caminho, pdf_nome_original, pdf_mime, pdf_tamanho, criado_por)
                 VALUES (:titulo, :objetivo, :data_evento, :hora, :texto,
                         NULL, :pdf_nome, :pdf_mime, :pdf_tamanho, :criado_por)"
            );
            $stmt->bindValue(':titulo', $titulo);
            $stmt->bindValue(':objetivo', $objetivo);
            $stmt->bindValue(':data_evento', $data_evento);
            $stmt->bindValue(':hora', $hora);
            $stmt->bindValue(':texto', $tem_texto ? $texto_autorizacao : null, $tem_texto ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':pdf_nome', $pdf_nome);
            $stmt->bindValue(':pdf_mime', $pdf_mime);
            $stmt->bindValue(':pdf_tamanho', $pdf_tamanho, $pdf_tamanho !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':criado_por', $criado_por ? (int) $criado_por : null, $criado_por ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->execute();
            $termo_id = (int) $this->conn->lastInsertId();

            if ($tem_pdf) {
                $dir = $this->garantirDiretorioPdf($termo_id);
                $stored = uniqid('termo_', true) . '.pdf';
                $destino = $dir . '/' . $stored;
                if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
                    throw new RuntimeException('Falha ao salvar o PDF.');
                }
                $relativo = self::DIRETORIO_PDF . '/' . $termo_id . '/' . $stored;
                $upd = $this->conn->prepare("UPDATE termo_autorizacao SET pdf_caminho = :caminho WHERE id = :id");
                $upd->bindValue(':caminho', $relativo);
                $upd->bindValue(':id', $termo_id, PDO::PARAM_INT);
                $upd->execute();
            }

            $insTurma = $this->conn->prepare(
                "INSERT INTO termo_autorizacao_turma (termo_id, turma_id) VALUES (:termo_id, :turma_id)"
            );
            foreach ($turma_ids as $turma_id) {
                $insTurma->bindValue(':termo_id', $termo_id, PDO::PARAM_INT);
                $insTurma->bindValue(':turma_id', $turma_id, PDO::PARAM_INT);
                $insTurma->execute();
            }

            $this->conn->commit();
            return $termo_id;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    public function contarRespostas($termo_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM termo_autorizacao_resposta WHERE termo_id = :termo_id"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function suspender($termo_id) {
        return $this->setStatus($termo_id, self::STATUS_SUSPENSO);
    }

    public function reativar($termo_id) {
        return $this->setStatus($termo_id, self::STATUS_ATIVO);
    }

    private function setStatus($termo_id, $status) {
        $this->garantirTabelas();
        if (!isset(self::statusLabels()[$status])) {
            return false;
        }
        $stmt = $this->conn->prepare(
            "UPDATE termo_autorizacao SET status = :status WHERE id = :id"
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0 || $this->getById($termo_id) !== null;
    }

    /**
     * Exclui só se ninguém respondeu. Remove o PDF do disco.
     * @return true|string true ou código de erro
     */
    public function excluir($termo_id) {
        $this->garantirTabelas();
        $termo = $this->getById($termo_id);
        if (!$termo) {
            return 'nao_encontrado';
        }
        if ($this->contarRespostas($termo_id) > 0) {
            return 'tem_respostas';
        }

        $caminho = $this->caminhoAbsolutoPdf($termo);
        $dir = $caminho ? dirname($caminho) : null;

        $stmt = $this->conn->prepare("DELETE FROM termo_autorizacao WHERE id = :id");
        $stmt->bindValue(':id', (int) $termo_id, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return 'erro';
        }

        if ($caminho && is_file($caminho)) {
            @unlink($caminho);
        }
        if ($dir && is_dir($dir)) {
            @rmdir($dir);
        }
        return true;
    }

    public function getById($id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT t.*, u.full_name AS criado_por_nome
             FROM termo_autorizacao t
             LEFT JOIN users u ON u.id = t.criado_por
             WHERE t.id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        $termo = $stmt->fetch();
        if (!$termo) {
            return null;
        }
        $termo['turmas'] = $this->listTurmasDoTermo((int) $termo['id']);
        return $termo;
    }

    public function listTurmasDoTermo($termo_id) {
        $stmt = $this->conn->prepare(
            "SELECT tu.id, tu.ano_curso, tu.ano_civil, c.nome AS curso_nome
             FROM termo_autorizacao_turma tt
             INNER JOIN turmas tu ON tu.id = tt.turma_id
             INNER JOIN cursos c ON c.id = tu.curso_id
             WHERE tt.termo_id = :termo_id
             ORDER BY c.nome ASC, tu.ano_curso ASC"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Lista termos com totais de resposta (ano civil opcional, por data_evento). */
    public function listParaEscola($ano_civil = null) {
        $this->garantirTabelas();
        $params = [];
        $where = '';
        if ($ano_civil !== null && $ano_civil !== '') {
            $where = ' WHERE YEAR(t.data_evento) = :ano ';
            $params[':ano'] = (int) $ano_civil;
        }

        $query = "SELECT t.*, u.full_name AS criado_por_nome,
                  (SELECT COUNT(*) FROM termo_autorizacao_turma tt WHERE tt.termo_id = t.id) AS total_turmas
                  FROM termo_autorizacao t
                  LEFT JOIN users u ON u.id = t.criado_por
                  $where
                  ORDER BY t.data_evento DESC, t.id DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $lista = $stmt->fetchAll();

        foreach ($lista as &$termo) {
            $totais = $this->totaisRespostas((int) $termo['id']);
            $termo = array_merge($termo, $totais);
            $termo['turmas'] = $this->listTurmasDoTermo((int) $termo['id']);
            $termo['turmas_label'] = implode(', ', array_map(static function ($tu) {
                return ($tu['curso_nome'] ?? '') . ' - ' . (int) $tu['ano_curso'] . 'º';
            }, $termo['turmas']));
        }
        unset($termo);
        return $lista;
    }

    /**
     * Totais: alunos nas turmas, com vínculo de responsável aprovado;
     * concordaram / discordaram / pendentes (alunos sem nenhuma resposta);
     * alunos_com_discordancia.
     */
    public function totaisRespostas($termo_id) {
        $alunos = $this->listAlunosDoTermo($termo_id);
        $respostas = $this->listRespostasDoTermo($termo_id);

        $por_aluno = [];
        foreach ($respostas as $r) {
            $aid = (int) $r['aluno_id'];
            $por_aluno[$aid][] = $r['decisao'];
        }

        $concordaram = 0;
        $discordaram = 0;
        $pendentes = 0;
        $discordancia = 0;

        foreach ($alunos as $aluno) {
            $aid = (int) $aluno['id'];
            $decs = $por_aluno[$aid] ?? [];
            $tem_sim = in_array(self::DECISAO_CONCORDOU, $decs, true);
            $tem_nao = in_array(self::DECISAO_DISCORDOU, $decs, true);
            if ($tem_sim && $tem_nao) {
                $discordancia++;
            }
            if (empty($decs)) {
                $pendentes++;
            } elseif ($tem_sim && !$tem_nao) {
                $concordaram++;
            } elseif ($tem_nao && !$tem_sim) {
                $discordaram++;
            }
            // discordância: conta nos dois lados via flags acima; aluno "discordância" não entra só em concordaram/discordaram puros
        }

        return [
            'total_alunos' => count($alunos),
            'concordaram' => $concordaram,
            'discordaram' => $discordaram,
            'pendentes' => $pendentes,
            'alunos_com_discordancia' => $discordancia,
        ];
    }

    public function alunoTemDiscordancia($termo_id, $aluno_id) {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(DISTINCT decisao) AS qtd
             FROM termo_autorizacao_resposta
             WHERE termo_id = :termo_id AND aluno_id = :aluno_id"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn() >= 2;
    }

    /** Alunos (não desistentes) das turmas do termo, com responsáveis aprovados. */
    public function listAlunosDoTermo($termo_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT a.id,
                    COALESCE(NULLIF(a.nome_social, ''), a.nome) AS nome,
                    a.numero_matricula,
                    tu.id AS turma_id,
                    tu.ano_curso,
                    c.nome AS curso_nome
             FROM termo_autorizacao_turma tt
             INNER JOIN turmas tu ON tu.id = tt.turma_id
             INNER JOIN cursos c ON c.id = tu.curso_id
             INNER JOIN aluno_turmas at ON at.turma_id = tu.id
             INNER JOIN alunos a ON a.id = at.aluno_id
             WHERE tt.termo_id = :termo_id
               AND COALESCE(a.desistente, 0) = 0
             ORDER BY c.nome ASC, tu.ano_curso ASC, nome ASC"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->execute();
        $alunos = $stmt->fetchAll();

        $respStmt = $this->conn->prepare(
            "SELECT r.id, r.nome, r.email, ra.parentesco, ra.aluno_id
             FROM responsavel_alunos ra
             INNER JOIN responsaveis r ON r.id = ra.responsavel_id
             WHERE ra.aluno_id = :aluno_id
               AND r.status = 'aprovado'
               AND r.ativo = 1
             ORDER BY r.nome ASC"
        );

        foreach ($alunos as &$aluno) {
            $respStmt->bindValue(':aluno_id', (int) $aluno['id'], PDO::PARAM_INT);
            $respStmt->execute();
            $aluno['responsaveis'] = $respStmt->fetchAll();
            $aluno['turma_label'] = ($aluno['curso_nome'] ?? '') . ' - ' . (int) $aluno['ano_curso'] . 'º Ano';
        }
        unset($aluno);
        return $alunos;
    }

    public function listRespostasDoTermo($termo_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT resp.*,
                    r.nome AS responsavel_nome,
                    r.email AS responsavel_email,
                    ra.parentesco,
                    COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome,
                    u.full_name AS liberado_por_nome
             FROM termo_autorizacao_resposta resp
             INNER JOIN responsaveis r ON r.id = resp.responsavel_id
             INNER JOIN alunos a ON a.id = resp.aluno_id
             LEFT JOIN responsavel_alunos ra
               ON ra.responsavel_id = resp.responsavel_id AND ra.aluno_id = resp.aluno_id
             LEFT JOIN users u ON u.id = resp.liberado_por
             WHERE resp.termo_id = :termo_id
             ORDER BY aluno_nome ASC, r.nome ASC"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getResposta($termo_id, $responsavel_id, $aluno_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT * FROM termo_autorizacao_resposta
             WHERE termo_id = :termo_id
               AND responsavel_id = :responsavel_id
               AND aluno_id = :aluno_id
             LIMIT 1"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->bindValue(':responsavel_id', (int) $responsavel_id, PDO::PARAM_INT);
        $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Existe ao menos uma resposta de qualquer responsável para o aluno neste termo? */
    public function alunoTemAlgumaResposta($termo_id, $aluno_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM termo_autorizacao_resposta
             WHERE termo_id = :termo_id AND aluno_id = :aluno_id
             LIMIT 1"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    /** O aluno está em alguma turma do termo? */
    public function alunoPertenceAoTermo($termo_id, $aluno_id) {
        $stmt = $this->conn->prepare(
            "SELECT 1
             FROM termo_autorizacao_turma tt
             INNER JOIN aluno_turmas at ON at.turma_id = tt.turma_id
             INNER JOIN alunos a ON a.id = at.aluno_id
             WHERE tt.termo_id = :termo_id
               AND at.aluno_id = :aluno_id
               AND COALESCE(a.desistente, 0) = 0
             LIMIT 1"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Grava ou altera (se liberado) a decisão do responsável.
     * @return true|string true ok, ou código de erro
     */
    public function registrarDecisao($termo_id, $responsavel_id, $aluno_id, $decisao) {
        $this->garantirTabelas();
        if (!isset(self::decisoesLabels()[$decisao])) {
            return 'decisao_invalida';
        }
        $termo = $this->getById($termo_id);
        if (!$termo) {
            return 'nao_encontrado';
        }
        if (!self::estaAtivo($termo)) {
            return 'suspenso';
        }
        if (!$this->alunoPertenceAoTermo($termo_id, $aluno_id)) {
            return 'aluno_fora';
        }

        $atual = $this->getResposta($termo_id, $responsavel_id, $aluno_id);
        $agora = date('Y-m-d H:i:s');

        if ($atual && !(int) $atual['pode_alterar']) {
            return 'ja_respondeu';
        }

        if ($atual && (int) $atual['pode_alterar']) {
            $this->conn->beginTransaction();
            try {
                $hist = $this->conn->prepare(
                    "INSERT INTO termo_autorizacao_historico
                     (termo_id, responsavel_id, aluno_id, decisao_anterior, decidido_em_anterior,
                      liberado_por, liberado_em, nova_decisao, novo_decidido_em)
                     VALUES (:termo_id, :responsavel_id, :aluno_id, :dec_ant, :em_ant,
                             :liberado_por, :liberado_em, :nova, :novo_em)"
                );
                $hist->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
                $hist->bindValue(':responsavel_id', (int) $responsavel_id, PDO::PARAM_INT);
                $hist->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
                $hist->bindValue(':dec_ant', $atual['decisao']);
                $hist->bindValue(':em_ant', $atual['decidido_em']);
                $hist->bindValue(':liberado_por', $atual['liberado_por'], $atual['liberado_por'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $hist->bindValue(':liberado_em', $atual['liberado_em']);
                $hist->bindValue(':nova', $decisao);
                $hist->bindValue(':novo_em', $agora);
                $hist->execute();

                $upd = $this->conn->prepare(
                    "UPDATE termo_autorizacao_resposta
                     SET decisao = :decisao,
                         decidido_em = :decidido_em,
                         pode_alterar = 0,
                         liberado_por = NULL,
                         liberado_em = NULL
                     WHERE id = :id"
                );
                $upd->bindValue(':decisao', $decisao);
                $upd->bindValue(':decidido_em', $agora);
                $upd->bindValue(':id', (int) $atual['id'], PDO::PARAM_INT);
                $upd->execute();
                $this->conn->commit();
                return true;
            } catch (Throwable $e) {
                $this->conn->rollBack();
                return 'erro';
            }
        }

        $ins = $this->conn->prepare(
            "INSERT INTO termo_autorizacao_resposta
             (termo_id, responsavel_id, aluno_id, decisao, decidido_em)
             VALUES (:termo_id, :responsavel_id, :aluno_id, :decisao, :decidido_em)"
        );
        $ins->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $ins->bindValue(':responsavel_id', (int) $responsavel_id, PDO::PARAM_INT);
        $ins->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        $ins->bindValue(':decisao', $decisao);
        $ins->bindValue(':decidido_em', $agora);
        if (!$ins->execute()) {
            return 'erro';
        }
        return true;
    }

    /** Admin libera nova resposta do responsável. */
    public function liberarAlteracao($resposta_id, $admin_user_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "UPDATE termo_autorizacao_resposta
             SET pode_alterar = 1,
                 liberado_por = :liberado_por,
                 liberado_em = NOW()
             WHERE id = :id
               AND pode_alterar = 0"
        );
        $stmt->bindValue(':liberado_por', (int) $admin_user_id, PDO::PARAM_INT);
        $stmt->bindValue(':id', (int) $resposta_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function listHistorico($termo_id, $aluno_id = null) {
        $this->garantirTabelas();
        $query = "SELECT h.*,
                  r.nome AS responsavel_nome,
                  COALESCE(NULLIF(a.nome_social, ''), a.nome) AS aluno_nome,
                  u.full_name AS liberado_por_nome
                  FROM termo_autorizacao_historico h
                  INNER JOIN responsaveis r ON r.id = h.responsavel_id
                  INNER JOIN alunos a ON a.id = h.aluno_id
                  LEFT JOIN users u ON u.id = h.liberado_por
                  WHERE h.termo_id = :termo_id";
        if ($aluno_id !== null) {
            $query .= " AND h.aluno_id = :aluno_id";
        }
        $query .= " ORDER BY h.registrado_em DESC, h.id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        if ($aluno_id !== null) {
            $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Destinatários futuros do e-mail: responsáveis aprovados/ativos com e-mail,
     * vinculados a alunos das turmas do termo (sem duplicar e-mail).
     */
    public function listDestinatariosParaEmail($termo_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT r.id, r.nome, r.email,
                    GROUP_CONCAT(DISTINCT COALESCE(NULLIF(a.nome_social, ''), a.nome)
                                 ORDER BY a.nome SEPARATOR ', ') AS alunos
             FROM termo_autorizacao_turma tt
             INNER JOIN aluno_turmas at ON at.turma_id = tt.turma_id
             INNER JOIN alunos a ON a.id = at.aluno_id
             INNER JOIN responsavel_alunos ra ON ra.aluno_id = a.id
             INNER JOIN responsaveis r ON r.id = ra.responsavel_id
             WHERE tt.termo_id = :termo_id
               AND COALESCE(a.desistente, 0) = 0
               AND r.status = 'aprovado'
               AND r.ativo = 1
               AND r.email <> ''
             GROUP BY r.id, r.nome, r.email
             ORDER BY r.nome ASC"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Termos que afetam algum aluno vinculado ao responsável. */
    public function listParaResponsavel($responsavel_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT t.*
             FROM termo_autorizacao t
             INNER JOIN termo_autorizacao_turma tt ON tt.termo_id = t.id
             INNER JOIN aluno_turmas at ON at.turma_id = tt.turma_id
             INNER JOIN responsavel_alunos ra ON ra.aluno_id = at.aluno_id
             INNER JOIN alunos a ON a.id = at.aluno_id
             WHERE ra.responsavel_id = :responsavel_id
               AND COALESCE(a.desistente, 0) = 0
             ORDER BY t.data_evento DESC, t.id DESC"
        );
        $stmt->bindValue(':responsavel_id', (int) $responsavel_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Alunos do responsável que estão em turmas do termo. */
    public function listAlunosDoResponsavelNoTermo($termo_id, $responsavel_id) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT a.id,
                    COALESCE(NULLIF(a.nome_social, ''), a.nome) AS nome,
                    a.numero_matricula,
                    tu.ano_curso,
                    c.nome AS curso_nome
             FROM termo_autorizacao_turma tt
             INNER JOIN turmas tu ON tu.id = tt.turma_id
             INNER JOIN cursos c ON c.id = tu.curso_id
             INNER JOIN aluno_turmas at ON at.turma_id = tu.id
             INNER JOIN alunos a ON a.id = at.aluno_id
             INNER JOIN responsavel_alunos ra ON ra.aluno_id = a.id
             WHERE tt.termo_id = :termo_id
               AND ra.responsavel_id = :responsavel_id
               AND COALESCE(a.desistente, 0) = 0
             ORDER BY nome ASC"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->bindValue(':responsavel_id', (int) $responsavel_id, PDO::PARAM_INT);
        $stmt->execute();
        $alunos = $stmt->fetchAll();
        foreach ($alunos as &$aluno) {
            $aluno['resposta'] = $this->getResposta($termo_id, $responsavel_id, (int) $aluno['id']);
            $aluno['aluno_tem_resposta'] = $this->alunoTemAlgumaResposta($termo_id, (int) $aluno['id']);
            $aluno['turma_label'] = ($aluno['curso_nome'] ?? '') . ' - ' . (int) $aluno['ano_curso'] . 'º Ano';
        }
        unset($aluno);
        return $alunos;
    }

    /** Caminho absoluto do PDF, se existir. */
    public function caminhoAbsolutoPdf(array $termo) {
        if (empty($termo['pdf_caminho'])) {
            return null;
        }
        $caminho = __DIR__ . '/../' . ltrim($termo['pdf_caminho'], '/');
        return is_file($caminho) ? $caminho : null;
    }

    /** Responsável pode ver o PDF se tiver aluno nas turmas do termo. */
    public function responsavelPodeVerTermo($termo_id, $responsavel_id) {
        $stmt = $this->conn->prepare(
            "SELECT 1
             FROM termo_autorizacao_turma tt
             INNER JOIN aluno_turmas at ON at.turma_id = tt.turma_id
             INNER JOIN responsavel_alunos ra ON ra.aluno_id = at.aluno_id
             INNER JOIN alunos a ON a.id = at.aluno_id
             WHERE tt.termo_id = :termo_id
               AND ra.responsavel_id = :responsavel_id
               AND COALESCE(a.desistente, 0) = 0
             LIMIT 1"
        );
        $stmt->bindValue(':termo_id', (int) $termo_id, PDO::PARAM_INT);
        $stmt->bindValue(':responsavel_id', (int) $responsavel_id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
