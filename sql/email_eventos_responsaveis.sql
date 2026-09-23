-- E-mail automático a responsáveis sobre eventos (após 2h do registro)
-- Execute em bancos já existentes. Novas instalações usam database.sql.

ALTER TABLE configuracoes
  MODIFY valor VARCHAR(500) NOT NULL;

ALTER TABLE tipos_eventos
  ADD COLUMN notificar_email_responsaveis TINYINT(1) DEFAULT 0
  AFTER observacoes_visiveis_responsaveis;

ALTER TABLE tipos_eventos
  ADD INDEX idx_notificar_email_responsaveis (notificar_email_responsaveis);

CREATE TABLE IF NOT EXISTS eventos_email_enviados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    responsavel_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_evento_responsavel (evento_id, responsavel_id),
    INDEX idx_evento (evento_id),
    INDEX idx_responsavel (responsavel_id),
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes (chave, valor, descricao) VALUES
('email_enabled', '0', 'Enviar e-mails automáticos de eventos aos responsáveis (1=sim, 0=não)'),
('email_host', '', 'Host SMTP (mesmo do projeto MAPA)'),
('email_port', '587', 'Porta SMTP'),
('email_encryption', 'tls', 'Criptografia SMTP: tls, ssl ou none'),
('email_username', '', 'Usuário SMTP'),
('email_password', '', 'Senha SMTP'),
('email_from_address', '', 'Remetente (From) — mesmo do projeto MAPA'),
('email_from_name', 'EduCuidar', 'Nome do remetente')
ON DUPLICATE KEY UPDATE chave = chave;
