-- Participações (autorizações de passeios e eventos escolares)

CREATE TABLE IF NOT EXISTS termo_autorizacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    objetivo TEXT NOT NULL,
    data_evento DATE NOT NULL,
    hora TIME NULL,
    texto_autorizacao TEXT NULL,
    pdf_caminho VARCHAR(255) NULL,
    pdf_nome_original VARCHAR(255) NULL,
    pdf_mime VARCHAR(100) NULL,
    pdf_tamanho INT NULL,
    status ENUM('ativo', 'suspenso') NOT NULL DEFAULT 'ativo',
    visivel_pais TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_data_evento (data_evento),
    INDEX idx_status (status),
    FOREIGN KEY (criado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS termo_autorizacao_turma (
    termo_id INT NOT NULL,
    turma_id INT NOT NULL,
    PRIMARY KEY (termo_id, turma_id),
    INDEX idx_turma (turma_id),
    FOREIGN KEY (termo_id) REFERENCES termo_autorizacao(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS termo_autorizacao_resposta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    termo_id INT NOT NULL,
    responsavel_id INT NOT NULL,
    aluno_id INT NOT NULL,
    decisao ENUM('concordou', 'discordou') NOT NULL,
    decidido_em DATETIME NOT NULL,
    pode_alterar TINYINT(1) NOT NULL DEFAULT 0,
    liberado_por INT NULL,
    liberado_em DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_termo_responsavel_aluno (termo_id, responsavel_id, aluno_id),
    INDEX idx_termo_aluno (termo_id, aluno_id),
    INDEX idx_responsavel (responsavel_id),
    FOREIGN KEY (termo_id) REFERENCES termo_autorizacao(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (liberado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS termo_autorizacao_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    termo_id INT NOT NULL,
    responsavel_id INT NOT NULL,
    aluno_id INT NOT NULL,
    decisao_anterior ENUM('concordou', 'discordou') NOT NULL,
    decidido_em_anterior DATETIME NOT NULL,
    liberado_por INT NULL,
    liberado_em DATETIME NULL,
    nova_decisao ENUM('concordou', 'discordou') NOT NULL,
    novo_decidido_em DATETIME NOT NULL,
    registrado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_termo_aluno (termo_id, aluno_id),
    INDEX idx_responsavel (responsavel_id),
    FOREIGN KEY (termo_id) REFERENCES termo_autorizacao(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (liberado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
