-- Observações visíveis aos responsáveis (por tipo de evento)
-- Execute em bancos que já rodaram modulo_responsaveis.sql

ALTER TABLE tipos_eventos
    ADD COLUMN observacoes_visiveis_responsaveis TINYINT(1) DEFAULT 0
    AFTER visivel_responsaveis;
