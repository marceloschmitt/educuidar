<?php
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$alunoModel = new Aluno($db);
$configuracao = new Configuracao($db);

if (!$user->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pode_ver = $user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2();
if (!$pode_ver) {
    header('Location: index.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$hoje = date('Y-m-d');
$lista = $alunoModel->listMaioresIdade($ano_corrente, $hoje);

$page_title = 'Alunos maiores de idade';
require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-person-check"></i> Alunos maiores de idade</h5>
        <span class="small text-muted">
            <?php echo count($lista); ?> aluno(s) com 18 anos ou mais em <?php echo date('d/m/Y'); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($lista)): ?>
        <p class="text-muted text-center my-4">Nenhum aluno ativo com data de nascimento que complete 18 anos ou mais.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Aluno</th>
                        <th>Matrícula</th>
                        <th>Nascimento</th>
                        <th>Idade</th>
                        <th class="pe-3">Turma (<?php echo (int) $ano_corrente; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $a): ?>
                    <tr>
                        <td class="ps-3"><?php echo htmlspecialchars($a['nome']); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($a['numero_matricula'] ?: '—'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($a['data_nascimento'])); ?></td>
                        <td><span class="badge bg-primary"><?php echo (int) $a['idade']; ?> anos</span></td>
                        <td class="pe-3 small">
                            <?php if (!empty($a['curso_nome'])): ?>
                            <?php echo htmlspecialchars($a['curso_nome'] . ' - ' . (int) $a['ano_curso'] . 'º'); ?>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
