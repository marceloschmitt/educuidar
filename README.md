# Sistema de Controle de Alunos - IFRS

Sistema desenvolvido em PHP 8 com interface Bootstrap para controle de aspectos relacionados a alunos do ensino médio em cursos do IFRS - Campus Porto Alegre.

## Características

### Tipos de Usuários
- **Administrador**: Acesso total ao sistema, pode gerenciar usuários, cursos, turmas, alunos, tipos de eventos e todos os eventos (autenticação por senha local)
- **Usuário Nível 1**: Pode registrar e visualizar eventos dos alunos, visualizar lista de alunos (autenticação por senha local, futuramente LDAP)
- **Usuário Nível 2**: Pode registrar e visualizar apenas eventos que ele mesmo registrou, visualizar lista de alunos (autenticação por senha local, futuramente LDAP)

**Nota**: Alunos não são usuários do sistema. Eles são gerenciados como uma entidade separada na tabela `alunos`.

### Sistema de Cursos e Turmas
- **Cursos**: Entidades que agrupam turmas
- **Turmas**: Identificadas pelo curso, ano civil e ano do curso (1º, 2º ou 3º ano)
- **Ano Corrente**: Configuração definida pelo administrador que determina quais turmas aparecem no controle de eventos
- **Alunos**: Podem pertencer a múltiplas turmas simultaneamente
- **Eventos**: Vinculados ao aluno e à turma do ano corrente

### Tipos de Eventos
Os tipos de eventos são configuráveis pelo administrador:
- Cada tipo possui nome, cor (Bootstrap ou hexadecimal) e status ativo/inativo
- Tipos padrão podem incluir: Chegada Atrasada, Saída Antecipada, Faltas, Atendimento, etc.
- O administrador pode criar, editar e desativar tipos de eventos

## Requisitos

- PHP 8.0 ou superior
- MySQL 5.7 ou superior (ou MariaDB 10.3+)
- Servidor web (Apache/Nginx)
- Extensões PHP: PDO, PDO_MySQL, LDAP (opcional, para implementação futura)
- Servidor LDAP configurado (opcional, para autenticação futura de nivel1 e nivel2)

## Instalação

1. Clone ou baixe o projeto para o diretório do servidor web

2. Configure o banco de dados:
   - Crie o arquivo `config/config.php` com a seguinte estrutura:
     ```php
     <?php
     return [
         'database' => [
             'host' => 'localhost',
             'db_name' => 'educuidar',
             'username' => 'seu_usuario',
             'password' => 'sua_senha'
         ]
     ];
     ```
   - Execute o script SQL: `database.sql` para criar todas as tabelas e dados iniciais
   - O script cria automaticamente:
     * Tabelas: `users`, `alunos`, `cursos`, `turmas`, `aluno_turmas`, `eventos`, `tipos_eventos`, `configuracoes`
     * Tipos de eventos padrão
     * Ano corrente: ano atual

3. Criar o primeiro usuário administrador:
   - Acesse: `http://localhost/educuidar/login.php`
   - Digite `admin` no campo usuário (deixe a senha em branco)
   - Você será redirecionado para definir a senha inicial do administrador
   - Defina uma senha segura e confirme
   - Após definir a senha, você estará logado como administrador

4. Configure o sistema:
   - Crie cursos através do menu "Cursos"
   - Crie turmas através do menu "Turmas"
   - Crie usuários adicionais através do menu "Usuários" (se necessário)
   - Configure o LDAP (opcional) através do menu "Configuração LDAP"

### Autenticação

- **Administradores**: Usam senha armazenada no banco de dados
- **Nivel1 e Nivel2**: Atualmente usam senha armazenada no banco de dados. Futuramente usarão autenticação LDAP
  - O username deve corresponder ao uid no LDAP
  - A senha será validada diretamente no servidor LDAP

## Estrutura do Projeto

```
educuidar/
├── config/
│   ├── config.php            # Configuração do sistema (criar manualmente)
│   ├── database.php          # Classe de conexão com banco de dados
│   ├── init.php              # Inicialização da aplicação
│   └── ldap.php              # Classe de autenticação LDAP
├── classes/
│   └── User.php              # Classe de usuário do sistema
├── models/
│   ├── Aluno.php             # Model de alunos
│   ├── Curso.php             # Model de cursos
│   ├── Evento.php             # Model de eventos
│   ├── Turma.php              # Model de turmas
│   ├── TipoEvento.php         # Model de tipos de eventos
│   └── Configuracao.php       # Model de configurações
├── controllers/
│   ├── Controller.php         # Classe base para controllers
│   ├── AlunosController.php  # Controller de alunos
│   ├── TurmasController.php  # Controller de turmas
│   ├── EventosController.php # Controller de eventos
│   ├── CursosController.php  # Controller de cursos
│   ├── UsuariosController.php # Controller de usuários
│   └── TiposEventosController.php # Controller de tipos de eventos
├── views/
│   ├── alunos/
│   │   └── index.php         # View de listagem de alunos
│   ├── turmas/
│   │   └── index.php         # View de listagem de turmas
│   ├── eventos/
│   │   └── index.php         # View de listagem de eventos
│   ├── cursos/
│   │   └── index.php         # View de listagem de cursos
│   ├── usuarios/
│   │   └── index.php         # View de listagem de usuários
│   ├── tipos_eventos/
│   │   └── index.php         # View de listagem de tipos de eventos
│   └── partials/
│       ├── header.php        # Cabeçalho reutilizável
│       └── footer.php        # Rodapé reutilizável
├── includes/
│   ├── header.php             # Cabeçalho e navegação
│   ├── header_menu_items.php  # Itens do menu (reutilizável)
│   └── footer.php             # Rodapé
├── index.php                  # Dashboard
├── login.php                  # Página de login
├── logout.php                 # Logout
├── alterar_senha.php          # Alteração de senha
├── js/
│   └── app.js                 # JavaScript centralizado do sistema
├── eventos.php                 # Entrada para eventos (usa EventosController)
├── registrar_evento.php       # Eventos de aluno (visualização e registro)
├── alunos.php                  # Entrada para alunos (usa AlunosController)
├── aluno_turmas.php           # Gerenciar turmas de um aluno
├── turmas.php                 # Entrada para turmas (usa TurmasController)
├── gerenciar_turmas_alunos.php # Gerenciar alunos em turmas
├── cursos.php                  # Entrada para cursos (usa CursosController)
├── tipos_eventos.php           # Entrada para tipos de eventos (usa TiposEventosController)
├── usuarios.php                # Entrada para usuários (usa UsuariosController)
├── configuracoes.php          # Configurações do sistema (ano corrente)
├── importar_alunos.php        # Importação de alunos via CSV
├── database.sql               # Script de criação do banco
├── styles.css                 # Estilos CSS do sistema
└── README.md                  # Este arquivo
```

## Funcionalidades

### Dashboard
- Estatísticas de eventos por tipo (filtradas pelo ano corrente)
- Filtros por curso e turma
- Lista de eventos recentes com informações completas
- Cards clicáveis para filtrar eventos por tipo
- Visualização diferenciada por tipo de usuário
- Coluna "Registrado por" mostra quem registrou o evento

### Gerenciamento de Alunos
- Lista de alunos com informações de curso e turma
- Filtros por curso, turma do ano corrente e nome
- Contagem de eventos por aluno (com destaque visual)
- Criação e edição de alunos via modal
- Importação em massa via arquivo CSV
- Gerenciamento de turmas por aluno (múltiplas turmas)
- Visualização e criação de eventos do aluno

### Registro e Visualização de Eventos
- Seleção de aluno a partir de lista filtrada
- Visualização de todos os eventos do aluno na turma do ano corrente
- Registro de novos eventos via modal
- Edição de eventos (com restrições de tempo para nivel1/nivel2)
- Exclusão de eventos (com restrições de tempo para nivel1/nivel2)
- Impressão da lista de eventos formatada
- Filtros avançados por curso, turma e nome do aluno

### Gerenciamento de Turmas
- Criação de turmas vinculadas a cursos
- Edição de turmas
- Listagem com destaque para turmas do ano corrente
- Gerenciamento de alunos em turmas:
  * Adicionar alunos a uma turma
  * Remover alunos de uma turma
  * Copiar todos os alunos de uma turma para outra
- Visualização de contagem de alunos por turma

### Gerenciamento de Cursos
- Criação e edição de cursos
- Listagem de cursos com contagem de turmas
- Visualização de turmas por curso

### Gerenciamento de Tipos de Eventos
- Criação de tipos de eventos personalizados
- Definição de cor (classes Bootstrap ou hexadecimal)
- Ativação/desativação de tipos
- Edição de tipos existentes

### Gerenciamento de Usuários
- Criação de usuários (administrador, nivel1, nivel2)
- Edição de dados e senha de qualquer usuário (apenas administrador)
- Listagem de todos os usuários do sistema
- Alteração de senha própria

### Configurações
- Definição do ano corrente (determina quais turmas aparecem no controle)
- Configuração centralizada do sistema

### Importação de Alunos
- Importação em massa via arquivo CSV
- Formato esperado: nome, sobrenome, email (4ª coluna ignorada)
- Primeira linha (cabeçalho) é ignorada automaticamente
- Associação automática de alunos importados à turma selecionada
- Validação de emails e tratamento de duplicatas

## Permissões e Restrições

### Administrador
- Acesso total a todas as funcionalidades
- Pode criar, editar e excluir qualquer registro
- Pode gerenciar configurações do sistema

### Usuário Nível 1
- Pode visualizar lista de alunos
- Pode registrar eventos para qualquer aluno
- Pode visualizar todos os eventos
- Pode editar/excluir apenas eventos próprios criados há menos de 1 hora

### Usuário Nível 2
- Pode visualizar lista de alunos
- Pode registrar eventos para qualquer aluno
- Pode visualizar apenas eventos que ele mesmo registrou
- Pode editar/excluir apenas eventos próprios criados há menos de 1 hora

## Interface

### Design Responsivo
- Interface adaptada para desktop e mobile
- Menu lateral responsivo (Offcanvas no mobile)
- Modais otimizados para telas pequenas
- Tabelas responsivas com scroll horizontal quando necessário

### Recursos de UX
- Modais para criação/edição (evita navegação desnecessária)
- Dropdowns de ações para melhor organização
- Filtros em tempo real
- Mensagens de sucesso/erro claras
- Confirmações para ações destrutivas
- Badges coloridos para identificação visual
- Destaque visual para turmas do ano corrente

## Segurança

- Senhas são armazenadas usando `password_hash()` do PHP (bcrypt)
- Sessões PHP para autenticação
- Proteção contra SQL Injection usando PDO prepared statements
- Validação de permissões em todas as páginas
- Escape de dados na saída (XSS protection)
- Output buffering para prevenir erros de headers
- Validação de entrada em todos os formulários

## Desenvolvimento

Este sistema foi desenvolvido seguindo boas práticas de PHP moderno:
- Orientação a objetos
- Arquitetura MVC (Model-View-Controller)
- Separação de responsabilidades:
  - **Models**: Lógica de acesso aos dados
  - **Controllers**: Lógica de negócio e controle de fluxo
  - **Views**: Apresentação (HTML/PHP)
- Separação de JavaScript em arquivo dedicado (`js/app.js`)
- Código limpo e comentado
- Interface responsiva com Bootstrap 5
- Uso de modais para melhor UX
- Tratamento de erros robusto
- Event listeners configurados via JavaScript puro (sem atributos inline)

### Arquitetura MVC

O projeto segue o padrão MVC (Model-View-Controller):

- **Models** (`models/`): Classes que representam entidades do banco de dados e contêm métodos para CRUD
- **Controllers** (`controllers/`): Classes que processam requisições, validam dados, chamam models e renderizam views
- **Views** (`views/`): Arquivos PHP que contêm apenas HTML e apresentação de dados

**Exemplo de fluxo:**
1. Usuário acessa `alunos.php`
2. `alunos.php` instancia `AlunosController` e chama `index()`
3. `AlunosController` valida permissões, busca dados via `Aluno` model, processa filtros
4. Controller renderiza a view `views/alunos/index.php` com os dados
5. View exibe o HTML final ao usuário

## Licença

Este projeto é de uso interno do IFRS - Campus Porto Alegre.
