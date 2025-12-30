-- Migration script to improve database schema
-- Run this script if you already have an existing database
-- Note: If you get errors about columns/indexes already existing, you can safely ignore them

USE educuidar;

-- Add updated_at and ativo fields to cursos
-- Note: Remove the line if the column already exists
ALTER TABLE cursos 
ADD COLUMN ativo TINYINT(1) DEFAULT 1 AFTER nome;

ALTER TABLE cursos 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Check if index exists before adding (MySQL doesn't support IF NOT EXISTS for indexes in ALTER TABLE)
-- If index already exists, comment out the following line
ALTER TABLE cursos 
ADD INDEX idx_ativo (ativo);

-- Add updated_at, ativo, and improved indexes to turmas
ALTER TABLE turmas 
ADD COLUMN ativo TINYINT(1) DEFAULT 1 AFTER ano_curso;

ALTER TABLE turmas 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE turmas 
ADD INDEX idx_ativo (ativo);

ALTER TABLE turmas 
ADD INDEX idx_curso_ano (curso_id, ano_civil);

-- Add created_at to configuracoes if missing
ALTER TABLE configuracoes 
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER descricao;

-- Add updated_at to alunos
ALTER TABLE alunos 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE alunos 
ADD INDEX idx_nome_email (nome, email);

-- Add updated_at, ativo, and improved indexes to users
ALTER TABLE users 
ADD COLUMN ativo TINYINT(1) DEFAULT 1 AFTER auth_type;

ALTER TABLE users 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE users 
ADD INDEX idx_auth_type (auth_type);

ALTER TABLE users 
ADD INDEX idx_ativo (ativo);

-- Add composite index to aluno_turmas
ALTER TABLE aluno_turmas 
ADD INDEX idx_aluno_turma (aluno_id, turma_id);

-- Add updated_at and index to tipos_eventos
ALTER TABLE tipos_eventos 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE tipos_eventos 
ADD INDEX idx_nome (nome);

-- Add updated_at and composite indexes to eventos
ALTER TABLE eventos 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE eventos 
ADD INDEX idx_registrado_por (registrado_por);

ALTER TABLE eventos 
ADD INDEX idx_aluno_data (aluno_id, data_evento);

ALTER TABLE eventos 
ADD INDEX idx_turma_data (turma_id, data_evento);

ALTER TABLE eventos 
ADD INDEX idx_registrado_data (registrado_por, data_evento);

ALTER TABLE eventos 
ADD INDEX idx_data_tipo (data_evento, tipo_evento_id);

-- Update all existing records to be active
UPDATE cursos SET ativo = 1 WHERE ativo IS NULL;
UPDATE turmas SET ativo = 1 WHERE ativo IS NULL;
UPDATE users SET ativo = 1 WHERE ativo IS NULL;

