-- Tipo de evento com que o dashboard abre (idempotente).
-- Até esta versão o dashboard abria no tipo das faltas do SIGAA; o valor inicial copia esse tipo
-- para não mudar o dashboard de quem já usa. Depois, escolha em Configurações.
INSERT INTO configuracoes (chave, valor, descricao)
SELECT 'dashboard_tipo_evento_padrao', COALESCE(MAX(valor), ''), 'tipos_eventos.id com que o dashboard abre (vazio = todos os tipos)'
FROM configuracoes
WHERE chave = 'api_sigaa_tipo_evento_falta_id'
ON DUPLICATE KEY UPDATE chave = chave;
