-- Autorizações de entrada/saída fora do horário (portal de responsáveis)

CREATE TABLE IF NOT EXISTS autorizacoes_responsavel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    responsavel_id INT NOT NULL,
    aluno_id INT NOT NULL,
    tipo ENUM('entrada_fora_horario', 'saida_fora_horario') NOT NULL,
    data_autorizacao DATE NOT NULL,
    hora TIME NOT NULL,
    justificativa TEXT NOT NULL,
    status ENUM('pendente', 'ocorrido', 'nao_ocorrido') NOT NULL DEFAULT 'pendente',
    confirmado_por INT NULL,
    confirmado_em TIMESTAMP NULL,
    evento_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aluno (aluno_id),
    INDEX idx_responsavel (responsavel_id),
    INDEX idx_data (data_autorizacao),
    INDEX idx_status (status),
    INDEX idx_tipo (tipo),
    INDEX idx_evento (evento_id),
    FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmado_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Se a tabela já existia com pendente/confirmada:
-- ALTER TABLE autorizacoes_responsavel
--   MODIFY status ENUM('pendente','confirmada','cancelada','previsto','ocorrido') NOT NULL DEFAULT 'previsto';
-- UPDATE autorizacoes_responsavel SET status = 'previsto' WHERE status = 'pendente';
-- UPDATE autorizacoes_responsavel SET status = 'ocorrido' WHERE status = 'confirmada';
-- ALTER TABLE autorizacoes_responsavel
--   MODIFY status ENUM('previsto','ocorrido','cancelada') NOT NULL DEFAULT 'previsto';
