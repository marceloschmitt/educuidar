# Guia de Instalação - Sistema de Controle IFRS

## Requisitos do Sistema

- PHP 8.0 ou superior
- MySQL 5.7 ou superior (ou MariaDB 10.3+)
- Servidor web (Apache/Nginx)
- Extensões PHP: PDO, PDO_MySQL, LDAP (opcional, para implementação futura)
- Usuário MySQL com permissões para criar banco de dados e tabelas

## Passo a Passo da Instalação

### 1. Configurar o Banco de Dados

1. Crie o arquivo `config/config.php` com a seguinte estrutura:

```php
<?php
return [
    'database' => [
        'host' => 'localhost',        // Host do MySQL
        'db_name' => 'educuidar',  // Nome do banco de dados
        'username' => 'root',         // Usuário do MySQL
        'password' => ''              // Senha do MySQL
    ]
];
```

2. Ajuste as credenciais conforme seu ambiente

### 2. Criar o Banco de Dados

Você pode criar o banco de dados de duas formas:

#### Opção A: Via linha de comando MySQL

```bash
mysql -u root -p < database.sql
```

#### Opção B: Via phpMyAdmin ou cliente MySQL

1. Abra o phpMyAdmin ou seu cliente MySQL favorito
2. Execute o conteúdo do arquivo `database.sql`
3. O script irá:
   - Criar o banco de dados `educuidar`
   - Criar todas as tabelas necessárias: `users`, `alunos`, `cursos`, `turmas`, `aluno_turmas`, `eventos`, `tipos_eventos`, `configuracoes`
   - Inserir tipos de eventos padrão
   - Configurar o ano corrente como o ano atual

### 3. Criar o Primeiro Usuário Administrador

Após executar o script SQL, você precisará criar o primeiro usuário administrador:

1. Acesse o sistema: `http://localhost/educuidar/login.php`
2. No campo "Usuário", digite `admin` (deixe a senha em branco)
3. Você será redirecionado para definir a senha inicial do administrador
4. Defina uma senha segura e confirme
5. Após definir a senha, você estará logado como administrador

### 4. Estrutura do Banco de Dados

O sistema utiliza as seguintes tabelas principais:

#### Tabela: `users`
Armazena todos os usuários do sistema:
- `id` - ID único
- `username` - Nome de usuário (único)
- `email` - E-mail (único)
- `password` - Senha criptografada (bcrypt, pode ser NULL para LDAP)
- `full_name` - Nome completo
- `user_type` - Tipo: `administrador`, `nivel1`, `nivel2`
- `created_at` - Data de criação

#### Tabela: `alunos`
Armazena informações dos alunos (separados dos usuários):
- `id` - ID único
- `nome` - Nome completo do aluno
- `email` - E-mail do aluno
- `telefone_celular` - Telefone celular
- `created_at` - Data de criação

#### Tabela: `cursos`
Armazena os cursos disponíveis:
- `id` - ID único
- `nome` - Nome do curso
- `created_at` - Data de criação

#### Tabela: `turmas`
Armazena as turmas vinculadas aos cursos:
- `id` - ID único
- `curso_id` - Referência ao curso (FK)
- `ano_civil` - Ano civil da turma
- `ano_curso` - Ano do curso (1º, 2º ou 3º)
- `created_at` - Data de criação
- Constraint única: `(curso_id, ano_civil, ano_curso)`

#### Tabela: `aluno_turmas`
Tabela de relacionamento muitos-para-muitos entre alunos e turmas:
- `aluno_id` - Referência ao aluno (FK)
- `turma_id` - Referência à turma (FK)
- `created_at` - Data de associação

#### Tabela: `tipos_eventos`
Armazena os tipos de eventos configuráveis:
- `id` - ID único
- `nome` - Nome do tipo de evento
- `cor` - Cor (classe Bootstrap ou hexadecimal)
- `ativo` - Status ativo/inativo
- `created_at` - Data de criação

#### Tabela: `eventos`
Armazena todos os eventos registrados:
- `id` - ID único
- `aluno_id` - Referência ao aluno (FK para alunos)
- `turma_id` - Referência à turma (FK para turmas)
- `tipo_evento_id` - Referência ao tipo de evento (FK para tipos_eventos)
- `data_evento` - Data do evento
- `hora_evento` - Hora do evento (opcional)
- `observacoes` - Observações adicionais
- `registrado_por` - ID do usuário que registrou (FK para users)
- `created_at` - Data de registro

#### Tabela: `configuracoes`
Armazena configurações do sistema:
- `id` - ID único
- `chave` - Chave da configuração (ex: 'ano_corrente')
- `valor` - Valor da configuração
- `descricao` - Descrição da configuração
- `updated_at` - Data de atualização

### 5. Índices e Performance

O banco de dados já inclui índices otimizados:
- Índices em `user_type`, `username`, `email` na tabela `users`
- Índices em `nome` nas tabelas `alunos` e `cursos`
- Índices em `aluno_id`, `turma_id`, `tipo_evento_id`, `data_evento` na tabela `eventos`
- Índices em `ano_civil`, `curso_id` na tabela `turmas`
- Constraint única em `turmas` para evitar duplicatas: `(curso_id, ano_civil, ano_curso)`
- Foreign keys com CASCADE para integridade referencial

### 6. Backup do Banco de Dados

Para fazer backup:

```bash
mysqldump -u root -p educuidar > backup_$(date +%Y%m%d).sql
```

Para restaurar:

```bash
mysql -u root -p educuidar < backup_YYYYMMDD.sql
```

## Solução de Problemas

### Erro: "Connection error"
- Verifique se o MySQL está rodando
- Confirme as credenciais em `config/config.php`
- Verifique se o banco de dados foi criado

### Erro: "Access denied"
- Verifique se o usuário MySQL tem permissões adequadas
- Tente criar o banco manualmente primeiro

### Erro: "Table doesn't exist"
- Execute o script `database.sql` novamente
- Verifique se está usando o banco correto (`USE educuidar;`)

## Segurança

⚠️ **IMPORTANTE**: 
- Defina uma senha forte para o administrador no primeiro acesso
- Não exponha o arquivo `config/config.php` publicamente (ele está no .gitignore)
- Use senhas fortes para o usuário MySQL
- Configure o MySQL para aceitar apenas conexões locais em produção
- Crie usuários, cursos e turmas conforme necessário através da interface do sistema

