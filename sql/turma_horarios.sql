-- Grade semanal por turma EduCuidar (gerada no botão Produzir grade).
-- Um aluno representante por turma define disciplina + professor em cada período.

CREATE TABLE IF NOT EXISTS turma_horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turma_id INT NOT NULL,
    dia_semana TINYINT NOT NULL COMMENT '0=seg … 4=sex',
    turno CHAR(1) NOT NULL COMMENT 'M/T/N',
    periodo TINYINT UNSIGNED NOT NULL,
    codigo_disciplina VARCHAR(50) NOT NULL,
    disciplina_nome VARCHAR(255) NULL,
    docente_nome VARCHAR(255) NULL,
    docente_email VARCHAR(255) NULL,
    user_id INT NULL,
    periodo_letivo VARCHAR(10) NULL,
    UNIQUE KEY uq_turma_horario (
        turma_id, dia_semana, turno, periodo, codigo_disciplina
    ),
    INDEX idx_turma (turma_id),
    INDEX idx_periodo_letivo (periodo_letivo),
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
