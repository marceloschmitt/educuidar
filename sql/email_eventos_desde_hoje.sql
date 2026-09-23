-- Define hoje como data inicial dos e-mails (não dispara histórico atrasado)
INSERT INTO configuracoes (chave, valor, descricao) VALUES
('email_eventos_desde', CURDATE(), 'Data inicial para e-mails de eventos (não envia registros anteriores)')
ON DUPLICATE KEY UPDATE
  valor = IF(valor = '' OR valor IS NULL, CURDATE(), valor),
  descricao = VALUES(descricao);
