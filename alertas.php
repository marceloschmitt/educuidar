<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);
$alerta_regra = new AlertaRegra($db);
$alerta_gerado = new AlertaGerado($db);
$aluno_model = new Aluno($db);

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

$alertas = $alerta_gerado->getAll([
    'curso_id' => $filtro_curso ?: null,
    'turma_id' => $filtro_turma ?: null,
    'regra_id' => $filtro_regra ?: null,
    'cursos_permitidos' => $cursos_permitidos,
]);

$alunos_map = [];
foreach ($alertas as $alerta_item) {
    $aluno_id = (int) ($alerta_item['aluno_id'] ?? 0);
    if ($aluno_id > 0 && !isset($alunos_map[$aluno_id])) {
        $aluno = $aluno_model->getById($aluno_id);
        if ($aluno) {
            $alunos_map[$aluno_id] = $aluno;
        }
    }
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
                                <th class="alertas-col-foto">Foto</th>
                                <th class="alertas-col-nome">Nome</th>
                                <th>Curso / Turma</th>
                                <th>Regra</th>
                                <th>Período / datas</th>
                                <th>Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alertas as $alerta): ?>
                            <?php
                            $aluno_id = (int) ($alerta['aluno_id'] ?? 0);
                            $aluno_data = $alunos_map[$aluno_id] ?? [
                                'id' => $aluno_id,
                                'nome' => $alerta['aluno_nome'] ?? '',
                            ];
                            $nome_exibicao = !empty($aluno_data['nome_social'])
                                ? $aluno_data['nome_social']
                                : ($alerta['aluno_nome'] ?? $aluno_data['nome'] ?? '');
                            $aluno_json = htmlspecialchars(json_encode($aluno_data));
                            ?>
                            <tr>
                                <td class="alerta-aluno-foto" data-aluno='<?php echo $aluno_json; ?>'>
                                    <?php if (!empty($aluno_data['foto'])): ?>
                                        <img src="<?php echo htmlspecialchars($aluno_data['foto']); ?>"
                                             alt="Foto de <?php echo htmlspecialchars($nome_exibicao); ?>"
                                             class="img-thumbnail"
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center"
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="alerta-aluno-nome" data-aluno='<?php echo $aluno_json; ?>'>
                                    <?php echo htmlspecialchars($nome_exibicao); ?>
                                </td>
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
                                <td><?php echo htmlspecialchars($alerta['periodo_label'] ?? ''); ?></td>
                                <td><span class="badge bg-danger"><?php echo (int) $alerta['quantidade_contada']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="height: 150px;"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="dropdown-menu" id="alunoContextMenu" style="position: absolute; display: none;">
    <button class="dropdown-item btn-view-ficha" type="button">
        <i class="bi bi-file-text text-info"></i> Ver Ficha
    </button>
    <a class="dropdown-item" href="#" id="contextMenuVerEventos">
        <i class="bi bi-eye text-success"></i> Ver/Criar Eventos
    </a>
    <a class="dropdown-item" href="#" id="contextMenuProntuario">
        <i class="bi bi-file-text text-info"></i> Ver Prontuário
    </a>
    <div id="contextMenuAdminActions" style="display: none;">
        <a class="dropdown-item" href="#" id="contextMenuGerenciarTurmas">
            <i class="bi bi-collection text-info"></i> Gerenciar Turmas
        </a>
        <hr class="dropdown-divider">
        <form method="POST" action="" class="form-confirm" data-confirm="Tem certeza que deseja excluir este aluno?">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="contextMenuDeleteId">
            <button type="submit" class="dropdown-item text-danger">
                <i class="bi bi-trash"></i> Excluir
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/views/alunos/ficha_modal.php'; ?>

<?php require_once 'includes/footer.php'; ?>
