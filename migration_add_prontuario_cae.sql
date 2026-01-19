-- Migration: Adicionar campo prontuario_cae na tabela eventos
-- Execute este comando no MySQL se o banco de dados já estiver instalado

ALTER TABLE eventos
ADD COLUMN prontuario_cae TEXT NULL AFTER observacoes;
