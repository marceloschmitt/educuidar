<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$periodo_horario = new PeriodoHorario($db);

if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

$periodo_horario->garantirSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'salvar') {
    $turnos = $_POST['turno'] ?? [];
    $periodos = $_POST['periodo'] ?? [];
    $inicios = $_POST['hora_inicio'] ?? [];
    $fins = $_POST['hora_fim'] ?? [];
    $linhas = [];
    $n = max(count($turnos), count($periodos), count($inicios), count($fins));
    for ($i = 0; $i < $n; $i++) {
        $linhas[] = [
            'turno' => $turnos[$i] ?? '',
            'periodo' => $periodos[$i] ?? 0,
            'hora_inicio' => $inicios[$i] ?? '',
            'hora_fim' => $fins[$i] ?? '',
        ];
    }
    if ($periodo_horario->salvarTodos($linhas)) {
        header('Location: horarios_periodos.php?success=1');
        exit;
    }
    $_SESSION['error'] = 'Não foi possível salvar os horários.';
    header('Location: horarios_periodos.php');
    exit;
}

$page_title = 'Horários dos períodos';
require_once 'includes/header.php';

$success = isset($_GET['success']) ? 'Horários salvos com sucesso.' : '';
$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$linhas = $periodo_horario->getAll();
if (empty($linhas)) {
    // Linhas vazias para facilitar o primeiro preenchimento
    foreach (['M', 'T', 'N'] as $t) {
        for ($p = 1; $p <= 6; $p++) {
            $linhas[] = [
                'turno' => $t,
                'periodo' => $p,
                'hora_inicio' => '',
                'hora_fim' => '',
            ];
        }
    }
}

function hora_input($valor) {
    $valor = trim((string) $valor);
    if ($valor === '') {
        return '';
    }
    if (preg_match('/^(\d{2}:\d{2})/', $valor, $m)) {
        return $m[1];
    }
    return $valor;
}
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-clock"></i> Horários dos períodos (campus)</h5>
        <a href="api_sigaa_config.php" class="btn btn-outline-secondary btn-sm">
            Voltar à API SIGAA
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Mapa único do campus: cada número de período do SIGAA (ex.: dígito <code>3</code> em <code>2N1234</code>)
            corresponde a um horário de relógio no turno Manhã (M), Tarde (T) ou Noite (N).
            Usado para avisar os docentes da aula quando há autorização de saída antecipada.
            Linhas sem horário válido são ignoradas ao salvar.
        </p>

        <form method="POST" action="" id="formHorariosPeriodos">
            <input type="hidden" name="action" value="salvar">
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="tabelaHorarios">
                    <thead>
                        <tr>
                            <th>Turno</th>
                            <th>Período</th>
                            <th>Início</th>
                            <th>Fim</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $i => $linha): ?>
                        <tr>
                            <td>
                                <select class="form-select form-select-sm" name="turno[]" required>
                                    <?php foreach (PeriodoHorario::TURNOS as $cod => $nome): ?>
                                    <option value="<?php echo $cod; ?>"
                                        <?php echo ($linha['turno'] ?? '') === $cod ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nome . " ($cod)"); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="max-width: 6rem;">
                                <input type="number" class="form-control form-control-sm" name="periodo[]"
                                       min="1" max="20" required
                                       value="<?php echo (int) ($linha['periodo'] ?? 1); ?>">
                            </td>
                            <td style="max-width: 8rem;">
                                <input type="time" class="form-control form-control-sm" name="hora_inicio[]"
                                       value="<?php echo htmlspecialchars(hora_input($linha['hora_inicio'] ?? '')); ?>">
                            </td>
                            <td style="max-width: 8rem;">
                                <input type="time" class="form-control form-control-sm" name="hora_fim[]"
                                       value="<?php echo htmlspecialchars(hora_input($linha['hora_fim'] ?? '')); ?>">
                            </td>
                            <td>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-remover-linha" title="Remover">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" id="btnAddLinha">
                    <i class="bi bi-plus"></i> Adicionar linha
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Salvar horários
                </button>
            </div>
        </form>
    </div>
</div>

<template id="tplLinhaHorario">
    <tr>
        <td>
            <select class="form-select form-select-sm" name="turno[]" required>
                <option value="M">Manhã (M)</option>
                <option value="T">Tarde (T)</option>
                <option value="N" selected>Noite (N)</option>
            </select>
        </td>
        <td style="max-width: 6rem;">
            <input type="number" class="form-control form-control-sm" name="periodo[]" min="1" max="20" value="1" required>
        </td>
        <td style="max-width: 8rem;">
            <input type="time" class="form-control form-control-sm" name="hora_inicio[]">
        </td>
        <td style="max-width: 8rem;">
            <input type="time" class="form-control form-control-sm" name="hora_fim[]">
        </td>
        <td>
            <button type="button" class="btn btn-outline-danger btn-sm btn-remover-linha" title="Remover">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
(function() {
    var tbody = document.querySelector('#tabelaHorarios tbody');
    var tpl = document.getElementById('tplLinhaHorario');
    document.getElementById('btnAddLinha').addEventListener('click', function() {
        tbody.appendChild(tpl.content.cloneNode(true));
    });
    tbody.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-remover-linha');
        if (!btn) return;
        var tr = btn.closest('tr');
        if (tbody.querySelectorAll('tr').length > 1) {
            tr.remove();
        }
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
