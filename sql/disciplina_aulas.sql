-- Grade de aulas por disciplina (expandida de turno_turma do SIGAA)

CREATE TABLE IF NOT EXISTS disciplina_aulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turma_id INT NULL,
    codigo_disciplina VARCHAR(50) NOT NULL,
    disciplina_nome VARCHAR(255) NULL,
    curso_nome VARCHAR(255) NULL,
    id_turma_sigaa INT NOT NULL DEFAULT 0,
    data_aula DATE NOT NULL,
    periodos INT NOT NULL DEFAULT 1,
    turno CHAR(1) NULL,
    periodos_nums VARCHAR(64) NULL,
    periodo_letivo VARCHAR(10) NULL,
    UNIQUE KEY uq_disciplina_aula_turma (
        turma_id, codigo_disciplina, id_turma_sigaa, data_aula
    ),
    INDEX idx_data_aula (data_aula),
    INDEX idx_codigo_data (codigo_disciplina, data_aula),
    INDEX idx_turma_id (turma_id),
    INDEX idx_periodo_letivo (periodo_letivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
