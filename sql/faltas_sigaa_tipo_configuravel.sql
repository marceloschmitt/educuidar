-- Faltas SIGAA: tipo de evento configurável e carga silenciosa (idempotente).
-- Depois de rodar, escolha o tipo em Configurações > API SIGAA.

-- Eventos inseridos numa carga inicial não geram e-mail (responsáveis/coordenador)
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'eventos'
       AND COLUMN_NAME = 'sem_notificacao') = 0,
    'ALTER TABLE eventos ADD COLUMN sem_notificacao TINYINT(1) NOT NULL DEFAULT 0 AFTER registrado_por',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO configuracoes (chave, valor, descricao) VALUES
('api_sigaa_tipo_evento_falta_id', '', 'tipos_eventos.id usado nas faltas lidas do SIGAA'),
('api_sigaa_tipo_evento_falta_carga_ok', '', 'Tipo de falta SIGAA cuja carga inicial (silenciosa) já foi feita')
ON DUPLICATE KEY UPDATE chave = chave;
