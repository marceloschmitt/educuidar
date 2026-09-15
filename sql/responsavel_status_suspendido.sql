-- Adiciona status "suspendido" (bloqueia acesso sem apagar autorizações/vínculos)
ALTER TABLE responsaveis
  MODIFY status ENUM('pendente', 'aprovado', 'rejeitado', 'suspendido') NOT NULL DEFAULT 'pendente';
