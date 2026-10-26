-- Token de redefinição de senha do portal de responsáveis.
-- Bancos novos já podem incluir as colunas em database.sql.

ALTER TABLE responsaveis
    ADD COLUMN reset_token_hash VARCHAR(64) NULL DEFAULT NULL AFTER password,
    ADD COLUMN reset_token_expires DATETIME NULL DEFAULT NULL AFTER reset_token_hash,
    ADD INDEX idx_reset_token_hash (reset_token_hash);
