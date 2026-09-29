-- Frequência de cada aluno por disciplina, como o SIGAA informa (aulas = períodos de aula).
-- Atualizada a cada coleta; alimenta o gráfico de percentual de faltas por disciplina.
CREATE TABLE IF NOT EXISTS frequencia_disciplina (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    ano INT NOT NULL,
    cod_disciplina VARCHAR(50) NOT NULL,
    disciplina_nome VARCHAR(255) NOT NULL,
    carga_horaria INT NULL,
    aulas INT NOT NULL DEFAULT 0,
    faltas INT NOT NULL DEFAULT 0,
    presencas INT NOT NULL DEFAULT 0,
    percentual_frequencia DECIMAL(5,2) NULL,
    ultima_aula DATE NULL,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_aluno_ano_disciplina (aluno_id, ano, cod_disciplina),
    INDEX idx_ano_disciplina (ano, cod_disciplina),
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
