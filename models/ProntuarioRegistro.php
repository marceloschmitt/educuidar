<?php
/**
 * Registros feitos direto no prontuário de um tipo de usuário, sem criar evento.
 * Só aparecem no prontuário (prontuario.php); os anexos ficam fora do acesso direto pela web
 * e são entregues por prontuario_anexo.php, que confere o tipo de usuário.
 */
class ProntuarioRegistro {
    const DIRETORIO_ANEXOS = 'uploads/prontuario';
    const TAMANHO_MAXIMO_ANEXO = 10485760;
    const TIPOS_ANEXO = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'
    ];

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /** Um tipo de usuário tem prontuário quando algum tipo de evento aponta para ele. */
    public function tipoUsuarioTemProntuario($user_type_id) {
        if (empty($user_type_id)) {
            return false;
        }
        $stmt = $this->conn->prepare("SELECT 1 FROM tipos_eventos WHERE prontuario_user_type_id = :id LIMIT 1");
        $stmt->bindValue(':id', $user_type_id);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function garantirTabelas() {
        $sql = @file_get_contents(__DIR__ . '/../sql/prontuario_registros.sql');
        if ($sql === false) {
            return;
        }
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $comando) {
            $this->conn->exec($comando);
        }
    }

    /** @return int|false id do registro criado */
    public function criar($aluno_id, $user_type_id, $data_registro, $hora_registro, $descricao, $registrado_por) {
        $this->garantirTabelas();
        $stmt = $this->conn->prepare("INSERT INTO prontuario_registros
                (aluno_id, user_type_id, data_registro, hora_registro, descricao, registrado_por)
                VALUES (:aluno_id, :user_type_id, :data_registro, :hora_registro, :descricao, :registrado_por)");
        $stmt->bindValue(':aluno_id', $aluno_id);
        $stmt->bindValue(':user_type_id', $user_type_id);
        $stmt->bindValue(':data_registro', $data_registro);
        $stmt->bindValue(':hora_registro', $hora_registro !== '' ? $hora_registro : null);
        $stmt->bindValue(':descricao', $descricao);
        $stmt->bindValue(':registrado_por', $registrado_por);
        if (!$stmt->execute()) {
            return false;
        }
        return (int) $this->conn->lastInsertId();
    }

    public function getPorAluno($aluno_id, $user_type_id, $ano) {
        try {
            $stmt = $this->conn->prepare("SELECT r.id, r.data_registro, r.hora_registro, r.descricao, r.created_at,
                        u.full_name AS registrado_por_nome
                    FROM prontuario_registros r
                    LEFT JOIN users u ON u.id = r.registrado_por
                    WHERE r.aluno_id = :aluno_id
                      AND r.user_type_id = :user_type_id
                      AND YEAR(r.data_registro) = :ano
                    ORDER BY r.data_registro ASC, r.hora_registro ASC, r.id ASC");
            $stmt->bindValue(':aluno_id', $aluno_id);
            $stmt->bindValue(':user_type_id', $user_type_id);
            $stmt->bindValue(':ano', (int) $ano, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /** @return array registro_id => lista de anexos */
    public function getAnexosPorRegistros(array $registro_ids) {
        if (empty($registro_ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($registro_ids), '?'));
        $stmt = $this->conn->prepare("SELECT id, registro_id, nome_original
                FROM prontuario_registros_anexos
                WHERE registro_id IN ($placeholders)
                ORDER BY id ASC");
        $stmt->execute(array_values($registro_ids));
        $por_registro = [];
        foreach ($stmt->fetchAll() as $anexo) {
            $por_registro[$anexo['registro_id']][] = $anexo;
        }
        return $por_registro;
    }

    /** Anexo com o tipo de usuário dono do prontuário, para conferir o acesso. */
    public function getAnexo($anexo_id) {
        try {
            $stmt = $this->conn->prepare("SELECT a.id, a.nome_original, a.caminho, a.mime_type, r.user_type_id
                    FROM prontuario_registros_anexos a
                    INNER JOIN prontuario_registros r ON r.id = a.registro_id
                    WHERE a.id = :id LIMIT 1");
            $stmt->bindValue(':id', $anexo_id);
            $stmt->execute();
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function salvarAnexos($registro_id, $files, &$erros) {
        if (empty($registro_id) || empty($files['name']) || !is_array($files['name'])) {
            return;
        }

        $raiz = __DIR__ . '/../' . self::DIRETORIO_ANEXOS;
        $diretorio = $raiz . '/' . (int) $registro_id;
        if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
            $erros[] = 'Não foi possível criar o diretório de anexos.';
            return;
        }
        if (!is_file($raiz . '/.htaccess')) {
            file_put_contents($raiz . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }

        $stmt = $this->conn->prepare("INSERT INTO prontuario_registros_anexos
                (registro_id, nome_original, caminho, mime_type, tamanho)
                VALUES (:registro_id, :nome_original, :caminho, :mime_type, :tamanho)");

        foreach ($files['name'] as $i => $nome) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $erros[] = "Erro ao enviar o arquivo: {$nome}.";
                continue;
            }
            if ($files['size'][$i] > self::TAMANHO_MAXIMO_ANEXO) {
                $erros[] = "Arquivo muito grande: {$nome}.";
                continue;
            }
            $mime_type = mime_content_type($files['tmp_name'][$i]) ?: ($files['type'][$i] ?? '');
            if (!in_array($mime_type, self::TIPOS_ANEXO, true)) {
                $erros[] = "Tipo de arquivo não permitido: {$nome}.";
                continue;
            }

            $extensao = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($nome, PATHINFO_EXTENSION)));
            $arquivo = uniqid('anexo_', true) . ($extensao !== '' ? '.' . $extensao : '');
            if (!move_uploaded_file($files['tmp_name'][$i], $diretorio . '/' . $arquivo)) {
                $erros[] = "Falha ao salvar o arquivo: {$nome}.";
                continue;
            }

            $stmt->execute([
                ':registro_id' => $registro_id,
                ':nome_original' => $nome,
                ':caminho' => self::DIRETORIO_ANEXOS . '/' . (int) $registro_id . '/' . $arquivo,
                ':mime_type' => $mime_type,
                ':tamanho' => (int) $files['size'][$i],
            ]);
        }
    }

    public static function contarPorTipoUsuario($db, $user_type_id) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM prontuario_registros WHERE user_type_id = :id");
            $stmt->bindValue(':id', $user_type_id);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}
