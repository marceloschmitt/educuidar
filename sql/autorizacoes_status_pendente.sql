-- Status: pendente (inicial) | ocorrido | nao_ocorrido
ALTER TABLE autorizacoes_responsavel
  MODIFY status ENUM('pendente', 'previsto', 'ocorrido', 'nao_ocorrido') NOT NULL DEFAULT 'pendente';

UPDATE autorizacoes_responsavel SET status = 'pendente' WHERE status = 'previsto';

ALTER TABLE autorizacoes_responsavel
  MODIFY status ENUM('pendente', 'ocorrido', 'nao_ocorrido') NOT NULL DEFAULT 'pendente';
