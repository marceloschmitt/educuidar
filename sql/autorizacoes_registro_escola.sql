-- Autorizações também registradas pela escola (telefone, e-mail etc.)

ALTER TABLE autorizacoes_responsavel
    MODIFY responsavel_id INT NULL,
    ADD COLUMN origem ENUM('portal', 'escola') NOT NULL DEFAULT 'portal' AFTER responsavel_id,
    ADD COLUMN autorizado_por_nome VARCHAR(200) NULL AFTER justificativa,
    ADD COLUMN meio_autorizacao VARCHAR(50) NULL AFTER autorizado_por_nome,
    ADD COLUMN registrado_por INT NULL AFTER meio_autorizacao,
    ADD INDEX idx_origem (origem),
    ADD INDEX idx_registrado_por (registrado_por);

-- FK de registrado_por (pode falhar se já existir)
-- ALTER TABLE autorizacoes_responsavel
--   ADD CONSTRAINT fk_autorizacao_registrado_por
--   FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE SET NULL;
