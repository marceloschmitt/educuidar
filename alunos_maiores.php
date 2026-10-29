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
$pode_editar = $user->isAdmin() || $user->isNivel0() || $user->isNivel1();
if (!$pode_ver) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'educacao_especial') {
    if (!$pode_editar) {
        $_SESSION['error'] = 'Você não tem permissão para alterar este dado.';
    } else {
        $aluno_id = (int) ($_POST['aluno_id'] ?? 0);
        $educacao_especial = !empty($_POST['educacao_especial']);
        if ($aluno_id && $alunoModel->setEducacaoEspecial($aluno_id, $educacao_especial)) {
            $_SESSION['success'] = $educacao_especial
                ? 'Aluno marcado como educação especial (continua precisando de autorização dos pais).'
                : 'Aluno desmarcado de educação especial (autoriza sozinho por ser maior de idade).';
        } else {
            $_SESSION['error'] = 'Não foi possível atualizar o aluno.';
        }
    }
    header('Location: alunos_maiores.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$hoje = date('Y-m-d');
$lista = $alunoModel->listMaioresIdade($ano_corrente, $hoje);

$page_title = 'Alunos maiores de idade';
require __DIR__ . '/includes/header.php';
?>

<?php if (!empty($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-person-check"></i> Alunos maiores de idade</h5>
        <span class="small text-muted">
            <?php echo count($lista); ?> aluno(s) com 18 anos ou mais em <?php echo date('d/m/Y'); ?>
        </span>
    </div>
    <div class="card-body border-bottom small text-muted">
        Marque <strong>Educação especial</strong> quando o aluno for maior de idade, mas continuar
        precisando de autorização dos pais nos eventos.
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
                        <th>Turma (<?php echo (int) $ano_corrente; ?>)</th>
                        <th class="pe-3">Educação especial</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $a): ?>
                    <?php $ee = !empty($a['educacao_especial']); ?>
                    <tr class="<?php echo $ee ? 'table-warning' : ''; ?>">
                        <td class="ps-3">
                            <?php echo htmlspecialchars($a['nome']); ?>
                            <?php if ($ee): ?>
                            <div><span class="badge bg-warning text-dark">Educação especial</span></div>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?php echo htmlspecialchars($a['numero_matricula'] ?: '—'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($a['data_nascimento'])); ?></td>
                        <td><span class="badge bg-primary"><?php echo (int) $a['idade']; ?> anos</span></td>
                        <td class="small">
                            <?php if (!empty($a['curso_nome'])): ?>
                            <?php echo htmlspecialchars($a['curso_nome'] . ' - ' . (int) $a['ano_curso'] . 'º'); ?>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3">
                            <?php if ($pode_editar): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="educacao_especial">
                                <input type="hidden" name="aluno_id" value="<?php echo (int) $a['id']; ?>">
                                <?php if ($ee): ?>
                                <input type="hidden" name="educacao_especial" value="0">
                                <button type="submit" class="btn btn-outline-secondary btn-sm"
                                        onclick="return confirm('Remover a marca de educação especial? O aluno passará a autorizar sozinho.');">
                                    Remover educação especial
                                </button>
                                <?php else: ?>
                                <input type="hidden" name="educacao_especial" value="1">
                                <button type="submit" class="btn btn-warning btn-sm"
                                        onclick="return confirm('Marcar como educação especial? Passará a precisar de autorização dos pais.');">
                                    Marcar educação especial
                                </button>
                                <?php endif; ?>
                            </form>
                            <?php else: ?>
                            <?php echo $ee ? 'Sim' : 'Não'; ?>
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
