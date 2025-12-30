-- Database schema for IFRS Control System

CREATE DATABASE IF NOT EXISTS educuidar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE educuidar;

-- Cursos table
CREATE TABLE IF NOT EXISTS cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Turmas table
CREATE TABLE IF NOT EXISTS turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    ano_civil INT NOT NULL,
    ano_curso TINYINT NOT NULL CHECK (ano_curso IN (1, 2, 3)),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_turma (curso_id, ano_civil, ano_curso),
    INDEX idx_curso (curso_id),
    INDEX idx_ano_civil (ano_civil),
    INDEX idx_ano_curso (ano_curso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações do sistema
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) UNIQUE NOT NULL,
    valor VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir ano corrente padrão (ano atual)
INSERT INTO configuracoes (chave, valor, descricao) VALUES
('ano_corrente', YEAR(CURDATE()), 'Ano civil corrente para controle de eventos');

-- Alunos table
CREATE TABLE IF NOT EXISTS alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    email VARCHAR(100) NULL,
    telefone_celular VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nome (nome),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table (only for system users: admin, nivel1, nivel2)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NULL,
    full_name VARCHAR(200) NOT NULL,
    user_type ENUM('administrador', 'nivel1', 'nivel2') NOT NULL,
    auth_type ENUM('local', 'ldap') NOT NULL DEFAULT 'local',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aluno-Turma relationship table (many-to-many)
CREATE TABLE IF NOT EXISTS aluno_turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    turma_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_aluno_turma (aluno_id, turma_id),
    INDEX idx_aluno (aluno_id),
    INDEX idx_turma (turma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de Eventos table
CREATE TABLE IF NOT EXISTS tipos_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cor VARCHAR(20) DEFAULT 'secondary',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events table
CREATE TABLE IF NOT EXISTS eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    turma_id INT NULL,
    tipo_evento_id INT NOT NULL,
    data_evento DATE NOT NULL,
    hora_evento TIME NULL,
    observacoes TEXT NULL,
    registrado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL,
    FOREIGN KEY (tipo_evento_id) REFERENCES tipos_eventos(id) ON DELETE RESTRICT,
    FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_aluno (aluno_id),
    INDEX idx_turma (turma_id),
    INDEX idx_tipo (tipo_evento_id),
    INDEX idx_data (data_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default event types
INSERT INTO tipos_eventos (nome, cor) VALUES
('Chegada Atrasada', 'primary'),
('Saída Antecipada', 'warning'),
('Falta', 'danger'),
('Atendimento', 'success');


