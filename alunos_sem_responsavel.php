<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$configuracao = new Configuracao($db);
$responsavel = new Responsavel($db);
$curso = new Curso($db);
$turma = new Turma($db);

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';

$alunos = $responsavel->getAlunosSemResponsavel(
    $ano_corrente,
    $filtro_curso !== '' ? $filtro_curso : null,
    $filtro_turma !== '' ? $filtro_turma : null
);

$cursos = $curso->getAll();
$turmas_lista = $turma->getTurmasPorAnoCorrente($ano_corrente);

$page_title = 'Alunos sem responsável';
require_once 'includes/header.php';
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-person-x"></i> Alunos sem responsável (<?php echo (int) $ano_corrente; ?>)</h5>
        <a href="admin_responsaveis.php" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Alunos do ano corrente sem vínculo a responsável <strong>aprovado</strong>.
            Quem não tem CPF cadastrado não consegue ser vinculado pelo cadastro público.
        </p>
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-4">
                <label for="filtro_curso" class="form-label">Curso</label>
                <select class="form-select form-select-sm" id="filtro_curso" name="filtro_curso" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ((string)$filtro_curso === (string)$c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="filtro_turma" class="form-label">Turma</label>
                <select class="form-select form-select-sm" id="filtro_turma" name="filtro_turma" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($turmas_lista as $t): ?>
                        <?php if ($filtro_curso && (string)$t['curso_id'] !== (string)$filtro_curso) continue; ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo ((string)$filtro_turma === (string)$t['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(($t['curso_nome'] ?? '') . ' - ' . ($t['ano_curso'] ?? '') . 'º Ano'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if (empty($alunos)): ?>
        <p class="text-muted text-center mb-0">Todos os alunos do filtro possuem responsável aprovado.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Matrícula</th>
                        <th>CPF</th>
                        <th>Curso / Turma</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunos as $a): ?>
                    <?php
                    $nome = !empty($a['nome_social']) ? $a['nome_social'] : ($a['nome'] ?? '');
                    $cpf_digits = normalizeCpf($a['cpf'] ?? '');
                    $sem_cpf = strlen($cpf_digits) !== 11;
                    ?>
                    <tr class="<?php echo $sem_cpf ? 'table-warning' : ''; ?>">
                        <td><?php echo htmlspecialchars($nome); ?></td>
                        <td><?php echo htmlspecialchars($a['numero_matricula'] ?? '—'); ?></td>
                        <td>
                            <?php if ($sem_cpf): ?>
                                <span class="badge bg-warning text-dark">Sem CPF</span>
                            <?php else: ?>
                                <?php echo htmlspecialchars(formatCpf($cpf_digits)); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($a['curso_nome'] ?? ''); ?> —
                            <?php echo htmlspecialchars(($a['ano_curso'] ?? '') . 'º Ano'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0"><?php echo count($alunos); ?> aluno(s) sem responsável aprovado.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
