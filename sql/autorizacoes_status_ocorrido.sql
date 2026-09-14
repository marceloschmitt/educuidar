-- Migra status pendente/confirmada → previsto/ocorrido
-- Execute se a tabela já foi criada com os valores antigos.

ALTER TABLE autorizacoes_responsavel
  MODIFY status ENUM('pendente', 'confirmada', 'cancelada', 'previsto', 'ocorrido') NOT NULL DEFAULT 'previsto';

UPDATE autorizacoes_responsavel SET status = 'previsto' WHERE status = 'pendente';
UPDATE autorizacoes_responsavel SET status = 'ocorrido' WHERE status = 'confirmada';

ALTER TABLE autorizacoes_responsavel
  MODIFY status ENUM('previsto', 'ocorrido', 'cancelada') NOT NULL DEFAULT 'previsto';
