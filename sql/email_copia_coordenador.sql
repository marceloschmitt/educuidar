-- Permite registrar cópia do e-mail ao coordenador (user_id)
-- Execute se já rodou email_eventos_responsaveis.sql

ALTER TABLE eventos_email_enviados
  DROP FOREIGN KEY eventos_email_enviados_ibfk_2;

ALTER TABLE eventos_email_enviados
  MODIFY responsavel_id INT NULL,
  ADD COLUMN user_id INT NULL AFTER responsavel_id,
  ADD INDEX idx_user (user_id),
  ADD UNIQUE KEY unique_evento_user (evento_id, user_id),
  ADD FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE CASCADE,
  ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
