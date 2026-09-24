<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);
$eventoEmail = new EventoEmail($db);

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_aluno = $_GET['filtro_aluno'] ?? '';

$cursos = $curso->getAll();
$turmas_ano = $turma->getTurmasPorAnoCorrente($ano_corrente);

$alunos_opcoes = [];
if ($filtro_turma !== '') {
    $alunos_opcoes = $turma->getAlunos((int) $filtro_turma);
} elseif ($filtro_curso !== '') {
    foreach ($turmas_ano as $t) {
        if ((int) $t['curso_id'] !== (int) $filtro_curso) {
            continue;
        }
        foreach ($turma->getAlunos((int) $t['id']) as $a) {
            $alunos_opcoes[(int) $a['id']] = $a;
        }
    }
    $alunos_opcoes = array_values($alunos_opcoes);
    usort($alunos_opcoes, static function ($a, $b) {
        return strcasecmp($a['nome'] ?? '', $b['nome'] ?? '');
    });
}

$filtros = [
    'curso_id' => $filtro_curso !== '' ? (int) $filtro_curso : null,
    'turma_id' => $filtro_turma !== '' ? (int) $filtro_turma : null,
    'aluno_id' => $filtro_aluno !== '' ? (int) $filtro_aluno : null,
];

$enviados = $eventoEmail->listEnviados(300, $filtros);
$resumos = $eventoEmail->listResumosCoordenador(50);

$page_title = 'E-mails enviados';
require_once 'includes/header.php';
?>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="filtro_curso" class="form-label">Curso</label>
                <select class="form-select form-select-sm" id="filtro_curso" name="filtro_curso"
                        onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"
                        <?php echo (string) $filtro_curso === (string) $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtro_turma" class="form-label">Turma (<?php echo (int) $ano_corrente; ?>)</label>
                <select class="form-select form-select-sm" id="filtro_turma" name="filtro_turma"
                        onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($turmas_ano as $t): ?>
                        <?php if ($filtro_curso !== '' && (int) $t['curso_id'] !== (int) $filtro_curso) continue; ?>
                    <option value="<?php echo (int) $t['id']; ?>"
                        <?php echo (string) $filtro_turma === (string) $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(($t['curso_nome'] ?? '') . ' — ' . ($t['ano_curso'] ?? '') . 'º'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="filtro_aluno" class="form-label">Aluno</label>
                <select class="form-select form-select-sm" id="filtro_aluno" name="filtro_aluno"
                        onchange="this.form.submit()"
                        <?php echo empty($alunos_opcoes) && $filtro_aluno === '' ? '' : ''; ?>>
                    <option value="">Todos</option>
                    <?php
                    // Se há aluno filtrado mas lista vazia, busca nome para exibir
                    if ($filtro_aluno !== '' && empty($alunos_opcoes)) {
                        $aluno_model = new Aluno($db);
                        $a_sel = $aluno_model->getById((int) $filtro_aluno);
                        if ($a_sel) {
                            $alunos_opcoes = [$a_sel];
                        }
                    }
                    foreach ($alunos_opcoes as $a):
                        $nome_a = $a['nome'] ?? '';
                    ?>
                    <option value="<?php echo (int) $a['id']; ?>"
                        <?php echo (string) $filtro_aluno === (string) $a['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($nome_a); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filtro_curso === '' && $filtro_turma === ''): ?>
                <div class="form-text">Selecione curso ou turma para listar alunos.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-2">
                <?php if ($filtro_curso || $filtro_turma || $filtro_aluno): ?>
                <a href="emails_enviados.php" class="btn btn-outline-secondary btn-sm w-100">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-inbox"></i> E-mails aos responsáveis</h5>
        <span class="small text-muted"><?php echo count($enviados); ?> registro(s)</span>
    </div>
    <div class="card-body">
        <?php if (empty($enviados)): ?>
        <p class="text-muted mb-0">Nenhum e-mail encontrado com os filtros atuais.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Enviado em</th>
                        <th>Aluno</th>
                        <th>Curso</th>
                        <th>Evento</th>
                        <th>Destinatário</th>
                        <th>E-mail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enviados as $row): ?>
                    <?php
                    $nome_dest = $row['responsavel_nome'] ?: ($row['coordenador_nome'] ?: '—');
                    $data_ev = !empty($row['data_evento'])
                        ? date('d/m/y', strtotime($row['data_evento']))
                        : '—';
                    $hora_ev = !empty($row['hora_evento']) ? substr($row['hora_evento'], 0, 5) : '';
                    ?>
                    <tr>
                        <td class="text-nowrap small">
                            <?php echo date('d/m/Y H:i', strtotime($row['enviado_em'])); ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['aluno_nome']); ?></td>
                        <td class="small"><?php echo htmlspecialchars($row['curso_nome'] ?? '—'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['tipo_nome']); ?>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars($data_ev . ($hora_ev !== '' ? ' ' . $hora_ev : '')); ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $row['destinatario_tipo'] === 'Coordenador' ? 'secondary' : 'primary'; ?>">
                                <?php echo htmlspecialchars($row['destinatario_tipo']); ?>
                            </span>
                            <div class="small"><?php echo htmlspecialchars($nome_dest); ?></div>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($row['email']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people"></i> Resumos aos coordenadores</h5>
        <span class="small text-muted">Últimos <?php echo count($resumos); ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($resumos)): ?>
        <p class="text-muted mb-0">Nenhum resumo enviado ainda.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Enviado em</th>
                        <th>Dia</th>
                        <th>Coordenador</th>
                        <th>Ocorrências</th>
                        <th>E-mail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumos as $row): ?>
                    <tr>
                        <td class="text-nowrap small">
                            <?php echo date('d/m/Y H:i', strtotime($row['enviado_em'])); ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($row['data_ref'])); ?></td>
                        <td><?php echo htmlspecialchars($row['coordenador_nome']); ?></td>
                        <td><?php echo (int) $row['total_eventos']; ?></td>
                        <td class="small"><?php echo htmlspecialchars($row['email']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
