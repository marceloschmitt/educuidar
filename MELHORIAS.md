# Planejamento de Melhorias — EduCuidar

Documento de anotações e planejamento para as próximas evoluções do sistema.  
Atualizado em: 06/07/2026

---

## Visão geral

| # | Melhoria | Prioridade | Dependências |
|---|----------|------------|--------------|
| 1 | Filtro de eventos de sábado | Alta (rápida) | Nenhuma |
| 2 | Relatório de alertas | Média | Item 3 (escopo por curso) |
| 3 | Coordenadores de curso | Alta | Nenhuma |
| 4 | E-mail de alertas aos coordenadores | Média | Itens 2 e 3 |
| 5 | Módulo de responsáveis (pais) | Baixa (fase posterior) | Planejamento de campos |
| 6 | Professores veem só seus eventos | Alta (rápida) | Nenhuma |

### Ordem sugerida de implementação

Não precisa seguir a numeração acima — esta é a sequência recomendada para entregar valor incremental:

1. **Item 6** — Professores veem só seus eventos (rápido, independente)
2. **Item 1** — Filtro de sábados (rápido, independente)
3. **Item 3** — Coordenadores de curso (base para alertas)
4. **Item 2** — Relatório de alertas
5. **Item 4** — E-mail de alertas aos coordenadores
6. **Item 5** — Módulo de responsáveis (fase posterior)

---

## 1. Filtro de eventos em sábados (Dashboard e Eventos)

### Objetivo
Permitir ao usuário escolher se eventos ocorridos em **sábados** aparecem ou não nas listagens e estatísticas do **Dashboard** (`index.php`) e da tela **Eventos** (`eventos.php`).

### Comportamento esperado
- Botão ou toggle visível na área de filtros (ao lado dos filtros de curso/turma/tipo).
- Estado padrão: **ocultar sábados** (a confirmar com o usuário).
- A preferência deve persistir entre sessões (cookie ou `localStorage` no navegador; ou configuração por usuário no banco — ver decisão abaixo).
- O filtro deve afetar:
  - Lista de eventos recentes no dashboard
  - Estatísticas do dashboard (`Evento::getEstatisticas`)
  - Lista completa em `eventos.php`

### Arquivos impactados
- `index.php` — formulário de filtros e lógica de filtragem
- `eventos.php` — formulário de filtros e lógica de filtragem
- `models/Evento.php` — opcional: centralizar filtro em SQL (`DAYOFWEEK(e.data_evento) != 7` no MySQL; sábado = 7)
- `js/app.js` — opcional: persistir preferência no cliente

### Decisões pendentes
- [ ] Padrão: mostrar ou ocultar sábados?
- [ ] Persistência: só na sessão atual, `localStorage`, ou preferência salva por usuário em `configuracoes` / nova tabela `user_preferences`?
- [ ] Sábado considera apenas `data_evento` ou também feriados/recesso (fora do escopo inicial)?

### Tarefas
- [ ] Adicionar parâmetro `incluir_sabados` (ou `ocultar_sabados`) nos filtros GET
- [ ] Aplicar filtro na query ou no pós-processamento PHP (preferir SQL para performance)
- [ ] UI: botão toggle ou checkbox com rótulo claro (“Incluir eventos de sábado”)
- [ ] Garantir que “Limpar filtros” respeite o padrão definido
- [ ] Testar com eventos em dias úteis e sábados

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

## 6. Professores veem apenas eventos criados por eles

### Objetivo
Usuários do tipo **Professor** devem visualizar **somente os eventos que eles mesmos registraram** (`eventos.registrado_por`), em todas as telas relevantes — da mesma forma que já ocorre hoje com usuários **Nível 2**.

### Situação atual no código
- **Nível 2** (`user_types.nivel = 'nivel2'`): já filtra por `registrado_por = user_id` em dashboard, listagem de eventos, ficha do aluno e contagem de eventos.
- **Professor** (`user_types.nome = 'Professor'`, `nivel = 'nivel0'`): hoje vê **todos** os eventos, como admin e demais níveis.
- **Assistência Estudantil** e **NAPNE** (também `nivel0`, nomes diferentes): continuam vendo todos os eventos — a restrição é **apenas para Professor**, não para todo `nivel0`.

Padrão repetido no código hoje:
```php
$registrado_por = ($user->isNivel2()) ? $user_id : null;
```

### Comportamento esperado
| Tela / recurso | Professor | Assistência / NAPNE | Nível 2 | Admin / Nível 1 |
|----------------|-----------|----------------------|---------|-----------------|
| Dashboard — lista e estatísticas | Só os seus | Todos | Só os seus | Todos |
| `eventos.php` — listagem | Só os seus | Todos | Só os seus | Todos |
| Ficha do aluno — histórico e total | Só os seus | Todos | Só os seus | Todos |
| `registrar_evento.php` — eventos do aluno | Só os seus | Todos | Só os seus | Todos |
| Editar/excluir evento | Só os seus (já parcialmente implementado) | Só os seus* | Só os seus | Conforme regra atual |

\* Edição/exclusão por criador já vale para nivel0/nivel1/nivel2 em `eventos.php` e `registrar_evento.php`.

### Implementação proposta

#### 1. Métodos em `classes/User.php`
```php
public function isProfessor() {
    return $this->getUserType() === 'Professor';
}

/** Usuário vê apenas eventos que ele mesmo registrou */
public function seesOnlyOwnEvents() {
    return $this->isNivel2() || $this->isProfessor();
}
```

#### 2. Centralizar o filtro
Substituir todas as ocorrências de:
```php
$registrado_por = ($user->isNivel2()) ? $user_id : null;
```
por:
```php
$registrado_por = $user->seesOnlyOwnEvents() ? $user_id : null;
```

#### 3. Arquivos a alterar
- `classes/User.php` — novos métodos
- `index.php` — dashboard
- `eventos.php` — listagem
- `registrar_evento.php` — histórico na ficha do aluno
- `controllers/AlunosController.php` — contagem na lista de alunos
- `api/get_aluno_ficha.php` — total de eventos na ficha (modal)
- `prontuario.php` — revisar se `countByAluno` e listagem devem respeitar o filtro para professor

#### 4. UI opcional
- Mensagem informativa no dashboard/eventos para professores: *“Exibindo apenas eventos registrados por você.”*
- Avaliar se a coluna “Registrado por” ainda faz sentido quando o professor só vê os próprios registros (pode manter para consistência).

### Decisões pendentes
- [ ] Confirmar que **apenas** o tipo “Professor” é restrito (e não todo `nivel0`).
- [ ] Professor deve ver na lista de alunos o `total_eventos` só dos seus registros ou o total geral do aluno? → **recomendação: só os seus**, alinhado ao restante.
- [ ] Eventos de grupo (`evento_grupo.php`): professor que participou do registro em grupo já fica como `registrado_por` — sem mudança necessária.

### Tarefas
- [ ] Adicionar `isProfessor()` e `seesOnlyOwnEvents()` em `User.php`
- [ ] Atualizar todos os pontos que definem `$registrado_por`
- [ ] Revisar `prontuario.php` (hoje não aplica filtro por criador)
- [ ] Testar login como Professor, Nível 2, Assistência Estudantil e Admin

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

| Item | Melhoria | Status | Observações |
|------|----------|--------|-------------|
| 1 | Filtro sábados | Não iniciado | |
| 2 | Relatório de alertas | Não iniciado | Critério 1: 3 faltas seguidas |
| 3 | Coordenadores | Não iniciado | |
| 4 | E-mail alertas | Não iniciado | Depende de 2 e 3 |
| 5 | Módulo responsáveis | Planejamento | Campos a definir |
| 6 | Professores — só seus eventos | Não iniciado | Nível 2 já tem comportamento similar |

---

## Próximo passo

Seguir a **ordem sugerida de implementação** no topo do documento: começar pelo item **6** ou **1** (ambos rápidos e independentes), depois **3** → **2** → **4** → **5**.
