-- Registros feitos direto no prontuário, sem criar evento.
-- Pertencem ao prontuário de um tipo de usuário (o mesmo vínculo de tipos_eventos.prontuario_user_type_id)
-- e não aparecem em eventos, dashboard, alertas, portal dos responsáveis nem e-mails.
CREATE TABLE IF NOT EXISTS prontuario_registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    user_type_id INT NOT NULL,
    data_registro DATE NOT NULL,
    hora_registro TIME NULL,
    descricao TEXT NOT NULL,
    registrado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aluno_tipo_data (aluno_id, user_type_id, data_registro),
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_type_id) REFERENCES user_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prontuario_registros_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registro_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    tamanho INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registro_id) REFERENCES prontuario_registros(id) ON DELETE CASCADE,
    INDEX idx_registro (registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
