-- Database schema for IFRS Control System
-- Improved version with better indexes, constraints, and audit fields

CREATE DATABASE IF NOT EXISTS educuidar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE educuidar;

-- Cursos table
CREATE TABLE IF NOT EXISTS cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Turmas table
CREATE TABLE IF NOT EXISTS turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    ano_civil INT NOT NULL CHECK (ano_civil >= 2000 AND ano_civil <= 2100),
    ano_curso TINYINT NOT NULL CHECK (ano_curso IN (1, 2, 3)),
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_turma (curso_id, ano_civil, ano_curso),
    INDEX idx_curso (curso_id),
    INDEX idx_ano_civil (ano_civil),
    INDEX idx_ano_curso (ano_curso),
    INDEX idx_ativo (ativo),
    INDEX idx_curso_ano (curso_id, ano_civil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações do sistema
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) UNIQUE NOT NULL,
    valor VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações iniciais
INSERT INTO configuracoes (chave, valor, descricao) VALUES
('ano_corrente', YEAR(CURDATE()), 'Ano civil corrente para controle de eventos'),
('sistema_instalado', '0', 'Indica se o sistema foi instalado e configurado (0 = não instalado, 1 = instalado)');

-- Alunos table
CREATE TABLE IF NOT EXISTS alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    nome_social VARCHAR(200) NULL,
    email VARCHAR(100) NULL,
    telefone_celular VARCHAR(20) NULL,
    data_nascimento DATE NULL,
    numero_matricula VARCHAR(50) NULL,
    endereco TEXT NULL,
    foto VARCHAR(255) NULL,
    pessoa_referencia VARCHAR(200) NULL,
    telefone_pessoa_referencia VARCHAR(100) NULL,
    rede_atendimento TEXT NULL,
    auxilio_estudantil TINYINT(1) DEFAULT 0,
    nee TEXT NULL,
    indigena TINYINT(1) DEFAULT 0,
    pei TINYINT(1) DEFAULT 0,
    profissionais_referencia TEXT NULL,
    outras_observacoes TEXT NULL,
    identidade_genero TEXT NULL,
    grupo_familiar TEXT NULL,
    guarda_legal TEXT NULL,
    escolaridade_pais_responsaveis TEXT NULL,
    necessidade_mudanca TEXT NULL,
    meio_transporte TEXT NULL,
    razao_escolha_ifrs TEXT NULL,
    expectativa_estudante_familia TEXT NULL,
    conhecimento_curso_tecnico TEXT NULL,
    rede_atendimento_familia TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nome (nome),
    INDEX idx_email (email),
    INDEX idx_nome_email (nome, email),
    INDEX idx_numero_matricula (numero_matricula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NULL,
    full_name VARCHAR(200) NOT NULL,
    auth_type ENUM('local', 'ldap') NOT NULL DEFAULT 'local',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_auth_type (auth_type),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Types table
CREATE TABLE IF NOT EXISTS user_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    nivel ENUM('administrador', 'nivel0', 'nivel1', 'nivel2') NOT NULL DEFAULT 'nivel0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_nome (nome),
    INDEX idx_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User x User Types relationship table
CREATE TABLE IF NOT EXISTS user_user_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_type (user_id),
    INDEX idx_user (user_id),
    INDEX idx_user_type (user_type_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_type_id) REFERENCES user_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_types (nome, nivel) VALUES
('Administrador', 'administrador'),
('Professor', 'nivel0'),
('Nível 1', 'nivel1'),
('Nível 2', 'nivel2'),
('Assistência Estudantil', 'nivel0'),
('NAPNE', 'nivel0')
ON DUPLICATE KEY UPDATE nivel = VALUES(nivel);

-- Create initial admin user (password will be set on first login)
INSERT INTO users (username, email, password, full_name, auth_type) VALUES
('admin', 'admin@educuidar.local', NULL, 'Administrador', 'local');

INSERT INTO user_user_types (user_id, user_type_id)
SELECT u.id, ut.id
FROM users u
INNER JOIN user_types ut ON ut.nome = 'Administrador'
WHERE u.username = 'admin'
ON DUPLICATE KEY UPDATE user_type_id = VALUES(user_type_id);

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
    INDEX idx_turma (turma_id),
    INDEX idx_aluno_turma (aluno_id, turma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de Eventos table
CREATE TABLE IF NOT EXISTS tipos_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cor VARCHAR(20) DEFAULT 'secondary',
    gera_prontuario TINYINT(1) DEFAULT 0,
    prontuario_user_type_id INT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo),
    INDEX idx_nome (nome),
    INDEX idx_prontuario_user_type (prontuario_user_type_id),
    FOREIGN KEY (prontuario_user_type_id) REFERENCES user_types(id) ON DELETE SET NULL
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
    prontuario TEXT NULL,
    registrado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL,
    FOREIGN KEY (tipo_evento_id) REFERENCES tipos_eventos(id) ON DELETE RESTRICT,
    FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_aluno (aluno_id),
    INDEX idx_turma (turma_id),
    INDEX idx_tipo (tipo_evento_id),
    INDEX idx_data (data_evento),
    INDEX idx_registrado_por (registrado_por),
    -- Composite indexes for common queries
    INDEX idx_aluno_data (aluno_id, data_evento),
    INDEX idx_turma_data (turma_id, data_evento),
    INDEX idx_registrado_data (registrado_por, data_evento),
    INDEX idx_data_tipo (data_evento, tipo_evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anexos de Eventos
CREATE TABLE IF NOT EXISTS eventos_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    tamanho INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    INDEX idx_evento (evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default event types
INSERT INTO tipos_eventos (nome, cor, ativo) VALUES
('Entrada atrasada (1º período)', 'danger', 1),
('Ausência da aula', 'danger', 1),
('Atendimento (outro)', 'warning', 1),
('Ausência de atendimento no NAPNE', 'danger', 1),
('Atendimento na CAE', 'warning', 1),
('Entrada atrasada autorizada', 'info', 1),
('Saída antecipada autorizada', 'info', 1),
('Saída antecipada', 'danger', 1),
('Entrada atrasada (após recreio)', 'danger', 1),
('Atendimento no NAPNE', 'primary', 1),
('Ausência na aula estando no campus', 'danger', 1);
