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
Nova área do sistema para **detectar e exibir situações que exigem atenção** da equipe, com critérios **configuráveis** pelo administrador — sem depender de regras fixas no código.

O item se divide em **duas partes**:
1. **Configuração de alertas** — interface administrativa para definir regras.
2. **Relatório de alertas** — tela que aplica as regras ativas e lista os alunos em alerta.

> Exemplo histórico (agora virará uma regra cadastrável): aluno com **3 dias seguidos** de eventos do tipo “Ausência da aula”.

---

### Parte A — Interface de configuração de alertas

#### Acesso
- **Somente administrador** (`user_types.nivel = 'administrador'`).
- Nova entrada no menu admin: **Regras de alerta** (`alertas_regras.php`).
- Padrão de UI semelhante a `tipos_eventos.php`: listagem + modal criar/editar.

#### O que cada regra define

| Campo | Descrição |
|-------|-----------|
| Nome | Ex.: “Faltas em dias consecutivos” |
| Descrição | Texto livre para a equipe pedagógica |
| Ativo | Regra participa ou não do motor de detecção |
| Tipos de evento | Multi-select de `tipos_eventos` (apenas ativos) |
| Tipo de critério | Um dos três modos abaixo |
| Parâmetros | Valores numéricos conforme o tipo |
| Opções de calendário | Ignorar domingos / ignorar sábados (checkboxes) |

#### 1) Tipos de evento que podem gerar alerta
- Relação **many-to-many** entre regra e `tipos_eventos`.
- Somente eventos cujo `tipo_evento_id` estiver vinculado à regra entram na contagem.
- **Não** usar flag fixa `conta_como_falta` em `tipos_eventos` — a configuração fica nas regras de alerta (mais flexível).
- Uma regra pode agrupar vários tipos (ex.: todas as ausências).
- O mesmo tipo de evento pode participar de **várias regras** (ex.: regra de 3 dias seguidos e regra de 5 em 7 dias).

#### 2) Parâmetros de disparo — três modos

Cada regra escolhe **um** tipo de critério (radio na interface):

| Modo (`tipo`) | Parâmetros na UI | Significado |
|---------------|------------------|-------------|
| `dias_consecutivos` | **N** dias | Aluno tem ao menos **1** evento (de um dos tipos vinculados) em cada um de **N dias de calendário consecutivos**. Ex.: N=3 → alerta no 3º dia seguido com ocorrência. |
| `intervalo_dias` | **N** ocorrências em **D** dias | Aluno acumula **N** ou mais eventos (tipos vinculados) em uma janela móvel de **D** dias de calendário. Ex.: 5 ocorrências em 7 dias. |
| `mesmo_dia` | **N** ocorrências | Aluno tem **N** ou mais eventos (tipos vinculados) na **mesma** `data_evento`. Ex.: 2 ausências no mesmo dia. |

**Validações na interface:**
- `quantidade` ≥ 1 em todos os modos.
- `intervalo_dias` ≥ 1 e obrigatório apenas no modo `intervalo_dias`.
- Pelo menos **1** tipo de evento selecionado.
- Nome obrigatório.

**Wireframe do formulário (criar/editar):**
```
Nome: [________________________]
Descrição: [__________________]

Tipos de evento que contam:
  [x] Ausência da aula
  [x] Ausência na aula estando no campus
  [ ] Entrada atrasada (1º período)
  ...

Critério de disparo:
  ( ) Dias consecutivos     →  [ 3 ] dias seguidos
  ( ) Intervalo de dias     →  [ 5 ] ocorrências em [ 7 ] dias
  ( ) Mesmo dia             →  [ 2 ] ocorrências no mesmo dia

Calendário:
  [x] Ignorar domingos
  [ ] Ignorar sábados

[ ] Regra ativa
```

#### Listagem de regras (admin)
Colunas sugeridas:
- Nome
- Tipos de evento (badges)
- Critério resumido (ex.: “3 dias consecutivos”, “5 em 7 dias”)
- Ativo
- Ações (editar / excluir)

---

### Parte B — Motor de detecção

#### Escopo da avaliação (por aluno)
- Considerar eventos do **ano civil corrente** (`configuracoes.ano_corrente`), via turma do aluno ou `data_evento`.
- Excluir alunos com `desistente = 1`.
- Agrupar por `aluno_id`.
- Usar apenas `data_evento` (não `hora_evento`) para critérios de calendário.

#### Algoritmos (por modo)

**`dias_consecutivos`**
1. Obter datas distintas com eventos qualificados, ordenadas.
2. Aplicar filtro de calendário (pular domingos/sábados se configurado).
3. Encontrar a maior sequência consecutiva de dias com ocorrência.
4. Disparar se sequência ≥ `quantidade`.
5. Retornar período (`data_inicio`, `data_fim`) e lista de datas.

**`intervalo_dias`**
1. Para cada data com evento (ou para cada janela deslizante de `intervalo_dias` dias):
2. Contar eventos qualificados na janela `[data − D + 1, data]`.
3. Disparar se contagem ≥ `quantidade`.
4. Retornar janela que disparou e eventos envolvidos.

**`mesmo_dia`**
1. Agrupar eventos qualificados por `data_evento`.
2. Disparar se algum dia tem contagem ≥ `quantidade`.
3. Retornar a(s) data(s) e quantidade.

#### Classe sugerida
`models/AlertaDetector.php` (ou métodos em `models/Alerta.php`):
- `avaliarRegra($regra_id, $filtros)` → lista de ocorrências
- `avaliarTodasRegrasAtivas($filtros)` → agrega por aluno/regra
- Um método privado por modo: `avaliarDiasConsecutivos()`, `avaliarIntervaloDias()`, `avaliarMesmoDia()`

---

### Parte C — Relatório de alertas (`alertas.php`)

#### Acesso
- Admin, nivel0, nivel1 (a confirmar com a equipe).
- **Coordenador de curso** (item 3): vê alertas **apenas dos cursos que coordena**.
- Nível 2: sem acesso (recomendação inicial).

#### Filtros do relatório
- Curso, turma (ano corrente)
- Regra de alerta (todas ou uma específica)
- Somente alertas novos / todos (quando existir `alertas_gerados`)

#### Colunas sugeridas
- Aluno
- Curso / turma
- Regra que disparou
- Critério (texto legível)
- Período ou data(s) envolvidas
- Quantidade contada
- Link para ficha / prontuário

#### Momento da avaliação
- **Fase 1:** sob demanda ao abrir o relatório (query + processamento em PHP).
- **Fase 2 (item 4):** job agendado ou trigger ao registrar evento + gravação em `alertas_gerados`.

---

### Modelo de dados proposto

```sql
-- Regra de alerta (configurável)
CREATE TABLE alertas_regras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    tipo_criterio ENUM('dias_consecutivos', 'intervalo_dias', 'mesmo_dia') NOT NULL,
    quantidade INT NOT NULL,
    intervalo_dias INT NULL,
    ignorar_domingos TINYINT(1) DEFAULT 1,
    ignorar_sabados TINYINT(1) DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de evento vinculados à regra
CREATE TABLE alertas_regras_tipos_evento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    regra_id INT NOT NULL,
    tipo_evento_id INT NOT NULL,
    UNIQUE KEY unique_regra_tipo (regra_id, tipo_evento_id),
    FOREIGN KEY (regra_id) REFERENCES alertas_regras(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_evento_id) REFERENCES tipos_eventos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de alertas detectados (item 4 — notificação)
CREATE TABLE alertas_gerados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    regra_id INT NOT NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    quantidade_contada INT NOT NULL,
    detalhe JSON NULL,
    notificado_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (regra_id) REFERENCES alertas_regras(id) ON DELETE CASCADE,
    INDEX idx_aluno_regra (aluno_id, regra_id),
    INDEX idx_notificado (notificado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Regra de exemplo (seed opcional):**
```sql
INSERT INTO alertas_regras (nome, tipo_criterio, quantidade, ignorar_domingos, ativo)
VALUES ('Faltas em 3 dias seguidos', 'dias_consecutivos', 3, 1, 1);
-- + vínculos com tipos de evento de ausência
```

---

### Arquivos novos / impactados

| Arquivo | Função |
|---------|--------|
| `alertas_regras.php` | CRUD de regras (admin) |
| `alertas.php` | Relatório de alertas |
| `models/AlertaRegra.php` | CRUD + vínculo com tipos de evento |
| `models/AlertaDetector.php` | Motor de avaliação dos três modos |
| `models/Alerta.php` | Facade: relatório + histórico (`alertas_gerados`) |
| `includes/header_menu_items.php` | “Regras de alerta” (admin) + “Alertas” |
| `database.sql` | Tabelas acima |

---

### Decisões pendentes
- [ ] Nivel0 e nivel1 têm acesso ao relatório ou só admin + coordenadores?
- [ ] Ignorar feriados/recesso (tabela de calendário escolar futura)?
- [ ] Uma regra pode ter **mais de um** critério ativo no futuro (ex.: 3 dias seguidos **ou** 5 em 7 dias na mesma regra)? → **v1: um critério por regra**; criar regras separadas.
- [ ] Excluir sábados por padrão nas regras (alinhar com item 1)?

---

### Fases de implementação

#### Fase 2a — Configuração (prioridade)
- [x] Migration: `alertas_regras`, `alertas_regras_tipos_evento`
- [x] Model `AlertaRegra.php`
- [x] Página `alertas_regras.php` (listagem + criar/editar/excluir)
- [x] Validações de formulário
- [x] Item de menu (admin)

#### Fase 2b — Relatório
- [x] Model `AlertaDetector.php` com os 3 modos
- [x] Página `alertas.php` com filtros
- [x] Escopo por coordenador de curso (item 3)
- [ ] Testes com dados reais (ausências, múltiplos tipos, mesmo dia)

#### Fase 2c — Histórico (prepara item 4)
- [ ] Migration: `alertas_gerados`
- [ ] Gravar alerta ao detectar (evitar duplicidade)
- [ ] Indicador “novo” / “já notificado” no relatório

---

### Critérios futuros (além dos três modos)
- [ ] Combinação de tipos com operador AND (ex.: falta + saída antecipada no mesmo dia)
- [ ] Limiar por turma ou curso (regra com escopo)
- [ ] Regras sazonais (válidas só em determinado período letivo)

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
- [x] Migration / atualizar `database.sql`
- [x] Model `User` ou novo `CoordenacaoCurso.php`
- [x] Formulário criar/editar usuário
- [x] Exibição na listagem de usuários
- [ ] Usar coordenação no filtro do relatório de alertas (coordenador vê só seus cursos) — item 2

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
| 2. Relatório de alertas | Implementado | Config + relatório; falta testar com dados reais |
| 3. Coordenadores | Implementado | UI em usuários e cursos; migration em `database.sql` |
| 4. E-mail alertas | Não iniciado | Depende de 2 e 3 |
| 5. Módulo responsáveis | Planejamento | Campos a definir |

---

## Próximo passo

1. Executar migration das tabelas de alertas no banco existente (SQL comentado no final de `database.sql`).
2. Cadastrar regras em **Regras de Alerta** e testar o relatório em **Alertas**.
3. Ajustar critérios conforme feedback da equipe pedagógica.
