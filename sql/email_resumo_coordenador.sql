-- Resumo diário aos coordenadores (um e-mail por dia, após 19:30)
CREATE TABLE IF NOT EXISTS email_resumo_coordenador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    data_ref DATE NOT NULL,
    email VARCHAR(100) NOT NULL,
    total_eventos INT NOT NULL DEFAULT 0,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_data (user_id, data_ref),
    INDEX idx_data_ref (data_ref),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
