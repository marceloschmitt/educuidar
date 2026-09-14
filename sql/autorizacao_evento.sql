-- Vínculo autorização → evento criado ao marcar como ocorrido

ALTER TABLE autorizacoes_responsavel
    ADD COLUMN evento_id INT NULL AFTER confirmado_em;

ALTER TABLE autorizacoes_responsavel
    ADD INDEX idx_evento (evento_id);

ALTER TABLE autorizacoes_responsavel
    ADD CONSTRAINT fk_autorizacao_evento
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE SET NULL;

INSERT INTO configuracoes (chave, valor, descricao) VALUES
('autorizacao_entrada_tipo_evento_id', '', 'Tipo de evento ao marcar entrada fora do horário como ocorrida'),
('autorizacao_saida_tipo_evento_id', '', 'Tipo de evento ao marcar saída fora do horário como ocorrida')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
