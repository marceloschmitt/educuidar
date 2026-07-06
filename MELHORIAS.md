# Planejamento de Melhorias — EduCuidar

Documento de anotações e planejamento para as próximas evoluções do sistema.  
Atualizado em: 06/07/2026

---

## Visão geral e ordem sugerida

| # | Melhoria | Prioridade sugerida | Dependências |
|---|----------|---------------------|--------------|
| 6 | Filtro “apenas meus eventos” | Alta (rápida) | Nenhuma |
| 1 | Filtro de eventos de sábado | Alta (rápida) | Nenhuma |
| 3 | Coordenadores de curso | Alta | Nenhuma |
| 2 | Relatório de alertas | Média | Item 3 (para escopo por curso) |
| 4 | E-mail de alertas aos coordenadores | Média | Itens 2 e 3 |
| 5 | Módulo de responsáveis (pais) | Baixa (fase posterior) | Planejamento de campos |

A ordem acima permite entregar valor incremental: primeiro filtros e coordenação, depois alertas, depois notificações e, por fim, o portal dos responsáveis.

---

## 6. Filtro “apenas meus eventos”

### Objetivo
Permitir que o usuário marque nos filtros que deseja visualizar **apenas os eventos que ele mesmo registrou** (`eventos.registrado_por = user_id`).

### Situação atual no código
- **Nível 2** (`user_types.nivel = 'nivel2'`) já vê apenas eventos próprios.
- Demais usuários autorizados podem ativar o filtro manualmente.
- O filtro deve coexistir com os filtros de curso, turma, ano, nome, tipo de evento e sábado.

### Comportamento esperado
- Checkbox **“Apenas meus eventos”** nos filtros do Dashboard e da tela Eventos.
- Padrão: desmarcado para usuários que podem ver todos os eventos.
- Nível 2 permanece sempre limitado aos próprios eventos.
- Quando marcado, estatísticas e listagens usam `registrado_por = user_id`.

### Tarefas
- [x] Adicionar parâmetro `apenas_meus_eventos` nos filtros GET
- [x] Atualizar `$registrado_por` no Dashboard e Eventos
- [x] Preservar filtro ao clicar nos cards do Dashboard
- [x] Preservar filtro após edição/exclusão em Eventos
- [x] Testar com usuários de diferentes níveis

---

## 1. Filtro de eventos em sábados (Dashboard e Eventos)

### Objetivo
Permitir ao usuário escolher se eventos ocorridos em **sábados** aparecem ou não nas listagens e estatísticas do **Dashboard** (`index.php`) e da tela **Eventos** (`eventos.php`).

### Comportamento esperado
- Checkbox **“Incluir eventos de sábado”** na área de filtros do Dashboard e Eventos.
- Estado padrão: **mostrar** sábados.
- Preferência persistida na **sessão PHP** (`$_SESSION['incluir_sabados']`).
- Critério: apenas `data_evento` (`DAYOFWEEK(e.data_evento) = 7` no MySQL).
- O filtro afeta lista de eventos, estatísticas do dashboard e listagem em `eventos.php`.

### Arquivos impactados
- `config/helpers.php` — `resolveIncluirSabadosSession()`, helpers de filtro
- `index.php` — formulário de filtros e lógica de filtragem
- `eventos.php` — formulário de filtros e lógica de filtragem
- `models/Evento.php` — parâmetro `$incluir_sabados` em `getAll()` e `getEstatisticas()`

### Decisões
- [x] Padrão: **mostrar** sábados
- [x] Persistência: **sessão PHP**
- [x] Sábado considera apenas `data_evento`

### Tarefas
- [x] Parâmetro `incluir_sabados` na query (atualiza sessão)
- [x] Filtro SQL em `getAll()` e `getEstatisticas()`
- [x] Checkbox “Incluir eventos de sábado”
- [x] Botão “Filtros padrão” restaura padrão (mostrar sábados, ano corrente)
- [x] Testar com eventos em dias úteis e sábados

---

## 2. Relatório de alertas

### Objetivo
Nova área do sistema para exibir **alertas** sobre situações que exigem atenção da equipe. Os critérios serão expandidos ao longo do tempo; o primeiro critério implementado é:

> **Alerta:** aluno com **3 dias seguidos de falta**.

### Primeiro critério — 3 faltas consecutivas

#### Definição de “falta”
Usar tipos de evento configuráveis como “ausência”. Candidatos iniciais na base atual (`tipos_eventos`):
- `Ausência da aula`
- `Ausência de atendimento no NAPNE`
- `Ausência na aula estando no campus`

**Decisão pendente:** criar flag `conta_como_falta` em `tipos_eventos` ou manter lista fixa de IDs no código/configuração.

#### Definição de “dia seguido”
- Contar **dias de calendário consecutivos** em que o aluno teve ao menos um evento de falta.
- Ignorar domingos? Ignorar sábados? Ignorar recesso/feriado? → **a definir** (recomendação: ignorar apenas domingos; respeitar filtro de sábado do item 1 se aplicável ao relatório).
- Considerar apenas o **ano civil corrente** (`configuracoes.ano_corrente`).
- Alunos marcados como `desistente = 1` devem ser excluídos.

#### Saída do relatório
Colunas sugeridas:
- Nome do aluno
- Curso / turma (ano corrente)
- Datas das 3 faltas (ou período: de … até …)
- Quantidade total de dias consecutivos (se > 3, destacar)
- Link para ficha / prontuário do aluno

### Arquivos novos / impactados
- `alertas.php` (ou `relatorio_alertas.php`) — página do relatório
- `models/Alerta.php` — lógica de detecção de critérios
- `includes/header_menu_items.php` — item de menu
- `database.sql` — possível tabela `alertas_gerados` para histórico e evitar duplicidade de notificação (item 4)
- `models/TipoEvento.php` — flag `conta_como_falta` (se adotada)

### Critérios futuros (placeholder)
- [ ] A definir em conjunto com a equipe pedagógica
- [ ] Arquitetura: cada critério como método/classe separada (`CriterioFaltasConsecutivas`, etc.)

### Tarefas — fase 1
- [ ] Definir quais tipos de evento contam como falta
- [ ] Implementar algoritmo de dias consecutivos
- [ ] Criar página de relatório com filtros (curso, turma)
- [ ] Restringir acesso (admin, nivel0, nivel1? coordenadores do curso?)

---

## 3. Coordenadores de curso

### Objetivo
Usuários do sistema (`users`) podem ser marcados como **coordenadores** de um ou mais **cursos** (`cursos`). A coordenação é independente do tipo de usuário (`user_types` / nível de acesso).

### Modelo de dados proposto

```sql
CREATE TABLE user_cursos_coordenacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    curso_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_curso (user_id, curso_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);
```

### Interface
- Na tela de **Usuários** (`views/usuarios/index.php` / `UsuariosController.php`):
  - Checkbox ou multi-select “Coordenador de cursos”
  - Listagem dos cursos coordenados (badges ou lista)
- Na listagem de usuários: coluna ou indicador visual “Coordenador” + cursos
- Opcional: na tela de **Cursos** (`cursos.php`), exibir quem coordena cada curso

### Regras de negócio
- Um usuário pode coordenar **vários** cursos.
- Um curso pode ter **vários** coordenadores.
- Coordenação não substitui permissões de `user_types`; é um papel adicional.
- Métodos úteis em `User.php`:
  - `isCoordenador()`
  - `getCursosCoordenados($user_id)`
  - `getCoordenadoresPorCurso($curso_id)`
  - `setCursosCoordenados($user_id, array $curso_ids)`

### Tarefas
- [ ] Migration / atualizar `database.sql`
- [ ] Model `User` ou novo `CoordenacaoCurso.php`
- [ ] Formulário criar/editar usuário
- [ ] Exibição na listagem de usuários
- [ ] Usar coordenação no filtro do relatório de alertas (coordenador vê só seus cursos)

---

## 4. E-mail de alertas para coordenadores

### Objetivo
Quando um alerta for gerado (item 2), os **coordenadores do curso** do aluno devem receber notificação por **e-mail** (`users.email`).

### Pré-requisitos
- Infraestrutura de e-mail ainda **não existe** no projeto — precisa ser criada.
- Configuração SMTP em **Configurações** (`configuracoes.php` / `Configuracao.php`):
  - `smtp_host`, `smtp_port`, `smtp_user`, `smtp_password`, `smtp_from`, `smtp_encryption`
- Classe utilitária `Mailer.php` ou uso de biblioteca (PHPMailer recomendado).

### Fluxo proposto
1. Job agendado (cron) ou verificação ao registrar evento de falta
2. Motor de alertas detecta nova situação (ex.: 3º dia consecutivo)
3. Registra em `alertas_gerados` (evita reenvio do mesmo alerta)
4. Busca coordenadores do curso do aluno
5. Envia e-mail com resumo e link para o relatório/ficha

### Tabela sugerida — histórico de alertas

```sql
CREATE TABLE alertas_gerados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    criterio VARCHAR(50) NOT NULL,  -- ex: 'faltas_3_dias'
    data_inicio DATE,
    data_fim DATE,
    notificado_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    INDEX idx_aluno_criterio (aluno_id, criterio)
);
```

### Decisões pendentes
- [ ] Envio em tempo real vs. digest diário (ex.: 1 e-mail por manhã)
- [ ] Cron no servidor ou trigger ao salvar evento?
- [ ] Coordenador sem e-mail cadastrado: ignorar ou alertar admin?

### Tarefas
- [ ] Configuração SMTP na área administrativa
- [ ] Classe de envio de e-mail
- [ ] Template HTML do e-mail de alerta
- [ ] Integração com motor de alertas e coordenadores
- [ ] Log de envios / falhas

---

## 5. Módulo de responsáveis (pais) — portal separado

### Objetivo
Módulo dedicado para **responsáveis** (pais, tutores, etc.) com funcionalidades específicas, começando por:
- Autorização de **chegada atrasada**
- Autorização de **saída antecipada**

Login **sem LDAP** — tabela e autenticação próprias, separadas de `users`.

### Princípios
- Responsáveis **não** são usuários internos do EduCuidar.
- Autenticação local (senha em `password_hash`).
- Vínculo responsável ↔ aluno(s) — relação many-to-many se um responsável tiver vários filhos.

### Modelo de dados — rascunho inicial

```sql
CREATE TABLE responsaveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefone VARCHAR(20) NULL,
    cpf VARCHAR(14) NULL,
    password VARCHAR(255) NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE responsavel_alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    responsavel_id INT NOT NULL,
    aluno_id INT NOT NULL,
    parentesco VARCHAR(50) NULL,  -- pai, mãe, tutor, etc.
    UNIQUE KEY unique_responsavel_aluno (responsavel_id, aluno_id),
    FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
);

CREATE TABLE autorizacoes_responsavel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    responsavel_id INT NOT NULL,
    aluno_id INT NOT NULL,
    tipo ENUM('chegada_atrasada', 'saida_antecipada') NOT NULL,
    data DATE NOT NULL,
    hora TIME NULL,
    motivo TEXT NULL,
    status ENUM('pendente', 'aprovada', 'registrada', 'cancelada') DEFAULT 'pendente',
    evento_id INT NULL,  -- vínculo com evento criado no sistema
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id),
    FOREIGN KEY (aluno_id) REFERENCES alunos(id),
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE SET NULL
);
```

### Campos a planejar (próxima conversa)
- [ ] Dados cadastrais do responsável (CPF obrigatório? validação?)
- [ ] Como o responsável é vinculado ao aluno (convite por e-mail, código, cadastro pela secretaria?)
- [ ] Fluxo de aprovação: automático ou revisão pela equipe?
- [ ] Integração com tipos de evento existentes (`Entrada atrasada autorizada`, `Saída antecipada autorizada`)
- [ ] Recuperação de senha
- [ ] Layout / URL separada (ex.: `/responsaveis/` ou subdomínio)

### Arquivos previstos (fase futura)
- `responsaveis/login.php`, `responsaveis/index.php`, etc.
- `classes/Responsavel.php`
- `models/AutorizacaoResponsavel.php`
- Área admin para gerenciar vínculos responsável–aluno

### Tarefas — agora
- [ ] Validar campos necessários com a equipe
- [ ] Definir fluxo de autorização (pendente → registrado como evento)
- [ ] Esboçar wireframe do portal do responsável

---

## Notas técnicas do código atual (referência)

| Área | Onde está |
|------|-------------|
| Dashboard e filtros | `index.php` |
| Listagem de eventos | `eventos.php` |
| Model de eventos | `models/Evento.php` |
| Tipos de evento (incl. ausências) | `models/TipoEvento.php`, `tipos_eventos.php` |
| Usuários e LDAP | `classes/User.php`, `usuarios.php`, `classes/LDAPAuth.php` |
| Cursos | `models/Curso.php`, `cursos.php` |
| Configurações globais | `models/Configuracao.php`, `configuracoes.php` |
| Menu lateral | `includes/header_menu_items.php` |
| Schema do banco | `database.sql` |

**Observação:** não há envio de e-mail implementado hoje; o item 4 exige nova infraestrutura.

---

## Registro de progresso

| Item | Status | Observações |
|------|--------|-------------|
| 6. Apenas meus eventos | Testado | Filtro opcional; Nível 2 segue limitado aos próprios eventos |
| 1. Filtro sábados | Testado | Padrão: mostrar; sessão PHP |
| 2. Relatório de alertas | Não iniciado | Critério 1: 3 faltas seguidas |
| 3. Coordenadores | Não iniciado | |
| 4. E-mail alertas | Não iniciado | Depende de 2 e 3 |
| 5. Módulo responsáveis | Planejamento | Campos a definir |

---

## Próximo passo

Seguir com a **melhoria 3** (coordenadores de curso), base para o relatório de alertas (item 2) e e-mails aos coordenadores (item 4).
