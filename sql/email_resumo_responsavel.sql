-- Resumo diário aos responsáveis (um e-mail por dia, após 19:30).
-- Substitui o envio por evento (eventos_email_enviados deixa de ser usada; o histórico antigo fica lá).
CREATE TABLE IF NOT EXISTS email_resumo_responsavel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    responsavel_id INT NOT NULL,
    data_ref DATE NOT NULL,
    email VARCHAR(100) NOT NULL,
    total_eventos INT NOT NULL DEFAULT 0,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_responsavel_data (responsavel_id, data_ref),
    INDEX idx_data_ref (data_ref),
    FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
