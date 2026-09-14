-- Remove autorizações canceladas (conceito descontinuado: agora se remove o registro)
DELETE FROM autorizacoes_responsavel WHERE status = 'cancelada';

-- Restringe o ENUM aos status atuais
ALTER TABLE autorizacoes_responsavel
  MODIFY status ENUM('previsto', 'ocorrido') NOT NULL DEFAULT 'previsto';
