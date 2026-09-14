-- Módulo de responsáveis (pai, mãe, tutor ou outro)
-- Execute em bancos já existentes. Novas instalações usam database.sql.

CREATE TABLE IF NOT EXISTS responsaveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    cpf VARCHAR(11) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    ativo TINYINT(1) DEFAULT 0,
    status ENUM('pendente', 'aprovado', 'rejeitado') NOT NULL DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cpf (cpf),
    UNIQUE KEY unique_email (email),
    INDEX idx_status (status),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responsavel_alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    responsavel_id INT NOT NULL,
    aluno_id INT NOT NULL,
    parentesco VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_responsavel_aluno (responsavel_id, aluno_id),
    INDEX idx_responsavel (responsavel_id),
    INDEX idx_aluno (aluno_id),
    FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tipos_eventos
    ADD COLUMN visivel_responsaveis TINYINT(1) DEFAULT 0 AFTER ativo;

ALTER TABLE tipos_eventos
    ADD INDEX idx_visivel_responsaveis (visivel_responsaveis);

INSERT INTO configuracoes (chave, valor, descricao)
VALUES ('cadastro_responsaveis_habilitado', '0', 'Permite cadastro público de responsáveis (1=aberto, 0=fechado)')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
