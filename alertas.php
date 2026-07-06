<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);
$alerta_regra = new AlertaRegra($db);
$detector = new AlertaDetector($db);

if (!$user->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$pode_ver = $user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isCoordenador($user_id);
if (!$pode_ver || $user->isNivel2()) {
    header('Location: index.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_regra = $_GET['filtro_regra'] ?? '';

$cursos = $curso->getAll();
$turmas_ano_corrente = $turma->getTurmasPorAnoCorrente($ano_corrente);
$regras = $alerta_regra->getAll(true);
$regras_map = [];
foreach ($regras as $regra_item) {
    $regras_map[(int) $regra_item['id']] = $regra_item;
}

$cursos_coordenados = [];
$cursos_permitidos = getCursosCoordenadosPermitidos($user, $user_id);
if ($cursos_permitidos !== null) {
    $cursos_coordenados = $user->getCursosCoordenados($user_id);
    if ($filtro_curso && !in_array((int) $filtro_curso, $cursos_permitidos, true)) {
        $filtro_curso = '';
    }
    $cursos = array_values(array_filter($cursos, function ($c) use ($cursos_permitidos) {
        return in_array((int) $c['id'], $cursos_permitidos, true);
    }));
}

$filtros_detector = [
    'ano_corrente' => $ano_corrente,
    'curso_id' => $filtro_curso ?: null,
    'turma_id' => $filtro_turma ?: null,
    'cursos_permitidos' => $cursos_permitidos,
];

$alertas = $detector->avaliarTodasRegrasAtivas($filtros_detector);

if ($filtro_regra) {
    $alertas = array_values(array_filter($alertas, function ($a) use ($filtro_regra) {
        return (int) $a['regra_id'] === (int) $filtro_regra;
    }));
}

$page_title = 'Alertas';
require_once 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Relatório de Alertas</h5>
                <?php if ($user->isAdmin()): ?>
                <a href="alertas_regras.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-gear"></i> Regras de alerta
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($cursos_coordenados)): ?>
                <div class="alert alert-info py-2 mb-3">
                    <i class="bi bi-funnel"></i>
                    Exibindo alertas dos cursos que você coordena:
                    <?php foreach ($cursos_coordenados as $cc): ?>
                        <span class="badge bg-warning text-dark ms-1"><?php echo htmlspecialchars($cc['nome']); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <form method="GET" action="" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="filtro_curso" class="form-label">Curso</label>
                        <select class="form-select form-select-sm" id="filtro_curso" name="filtro_curso">
                            <option value="">Todos</option>
                            <?php foreach ($cursos as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo (string) $filtro_curso === (string) $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filtro_turma" class="form-label">Turma (<?php echo (int) $ano_corrente; ?>)</label>
                        <select class="form-select form-select-sm" id="filtro_turma" name="filtro_turma">
                            <option value="">Todas</option>
                            <?php foreach ($turmas_ano_corrente as $t): ?>
                                <?php if ($filtro_curso && (int) $t['curso_id'] !== (int) $filtro_curso) continue; ?>
                            <option value="<?php echo (int) $t['id']; ?>" <?php echo (string) $filtro_turma === (string) $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($t['curso_nome'] ?? '') . ' - ' . $t['ano_curso'] . 'º Ano'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filtro_regra" class="form-label">Regra</label>
                        <select class="form-select form-select-sm" id="filtro_regra" name="filtro_regra">
                            <option value="">Todas</option>
                            <?php foreach ($regras as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo (string) $filtro_regra === (string) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <?php if ($filtro_curso || $filtro_turma || $filtro_regra): ?>
                        <a href="alertas.php" class="btn btn-secondary btn-sm">
                            <i class="bi bi-x-circle"></i> Limpar
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($regras)): ?>
                <p class="text-muted text-center mb-0">
                    Nenhuma regra de alerta ativa.
                    <?php if ($user->isAdmin()): ?>
                    <a href="alertas_regras.php">Cadastrar regras</a>
                    <?php endif; ?>
                </p>
                <?php elseif (empty($alertas)): ?>
                <p class="text-muted text-center mb-0">Nenhum alerta encontrado com os filtros atuais.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Curso / Turma</th>
                                <th>Regra</th>
                                <th>Critério</th>
                                <th>Período / datas</th>
                                <th>Qtd.</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alertas as $alerta): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($alerta['aluno_nome']); ?></td>
                                <td><?php echo htmlspecialchars($alerta['turma_label'] ?? '—'); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($alerta['regra_nome']); ?></div>
                                    <?php
                                    $regra_atual = $regras_map[(int) $alerta['regra_id']] ?? null;
                                    $tipos_evento_regra = $regra_atual['tipos_evento_nomes'] ?? [];
                                    ?>
                                    <?php if (!empty($tipos_evento_regra)): ?>
                                    <div class="mt-1">
                                        <?php foreach ($tipos_evento_regra as $tipo_evento): ?>
                                        <span class="badge bg-<?php echo htmlspecialchars($tipo_evento['cor'] ?? 'secondary'); ?> me-1 mb-1">
                                            <?php echo htmlspecialchars($tipo_evento['nome'] ?? ''); ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($alerta['criterio_resumo']); ?></td>
                                <td><?php echo htmlspecialchars($alerta['periodo_label'] ?? ''); ?></td>
                                <td><span class="badge bg-danger"><?php echo (int) $alerta['quantidade_contada']; ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-ver-ficha-alerta"
                                            data-aluno-id="<?php echo (int) $alerta['aluno_id']; ?>">
                                        <i class="bi bi-person-lines-fill"></i> Ficha
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/views/alunos/ficha_modal.php'; ?>

<script>
document.querySelectorAll('.btn-ver-ficha-alerta').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var alunoId = this.getAttribute('data-aluno-id');
        if (!alunoId) return;
        fetch('api/get_aluno_ficha.php?id=' + encodeURIComponent(alunoId))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                if (typeof viewFichaAluno === 'function') {
                    viewFichaAluno(data);
                } else {
                    alert('Não foi possível abrir a ficha do aluno.');
                }
            })
            .catch(function() {
                alert('Erro ao carregar a ficha do aluno.');
            });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
