-- Mapa único do campus: número do período SIGAA → horário de relógio

CREATE TABLE IF NOT EXISTS periodos_horario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turno CHAR(1) NOT NULL COMMENT 'M=manhã, T=tarde, N=noite',
    periodo TINYINT UNSIGNED NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    UNIQUE KEY uq_turno_periodo (turno, periodo),
    INDEX idx_turno (turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
