-- Docentes por disciplina/turma SIGAA (importação admin da grade)

CREATE TABLE IF NOT EXISTS disciplina_docentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_disciplina VARCHAR(50) NOT NULL,
    disciplina_nome VARCHAR(255) NULL,
    curso_nome VARCHAR(255) NULL,
    id_turma_sigaa INT NOT NULL DEFAULT 0,
    docente_nome VARCHAR(255) NOT NULL,
    docente_email VARCHAR(255) NULL,
    docente_cpf VARCHAR(14) NULL,
    user_id INT NULL,
    periodo_letivo VARCHAR(10) NULL,
    UNIQUE KEY uq_disciplina_docente (
        codigo_disciplina, curso_nome, id_turma_sigaa, docente_nome, periodo_letivo
    ),
    INDEX idx_codigo_turma (codigo_disciplina, id_turma_sigaa),
    INDEX idx_user_id (user_id),
    INDEX idx_periodo_letivo (periodo_letivo),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
