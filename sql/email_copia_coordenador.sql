-- Ajuste seguro para cópia ao coordenador (idempotente).
-- Pode rodar mesmo se user_id já existir.

-- Torna responsavel_id opcional (coordenador não é responsável)
SET @sql := (
  SELECT IF(
    (SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'eventos_email_enviados'
       AND COLUMN_NAME = 'responsavel_id') = 'NO',
    'ALTER TABLE eventos_email_enviados MODIFY responsavel_id INT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Adiciona user_id se faltar
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'eventos_email_enviados'
       AND COLUMN_NAME = 'user_id') = 0,
    'ALTER TABLE eventos_email_enviados ADD COLUMN user_id INT NULL AFTER responsavel_id',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índice em user_id
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'eventos_email_enviados'
       AND INDEX_NAME = 'idx_user') = 0,
    'ALTER TABLE eventos_email_enviados ADD INDEX idx_user (user_id)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Unique (evento_id, user_id)
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'eventos_email_enviados'
       AND INDEX_NAME = 'unique_evento_user') = 0,
    'ALTER TABLE eventos_email_enviados ADD UNIQUE KEY unique_evento_user (evento_id, user_id)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK user_id → users (se ainda não existir)
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'eventos_email_enviados'
       AND CONSTRAINT_TYPE = 'FOREIGN KEY'
       AND CONSTRAINT_NAME = 'fk_eventos_email_user') = 0,
    'ALTER TABLE eventos_email_enviados ADD CONSTRAINT fk_eventos_email_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
