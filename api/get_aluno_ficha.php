<?php
/**
 * API: retorna dados do aluno no formato esperado por viewFichaAluno (ficha do aluno).
 * GET id = aluno_id
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json; charset=utf-8');

$user = new User((new Database())->getConnection());
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$aluno_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($aluno_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do aluno inválido']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$aluno_model = new Aluno($db);
$turma = new Turma($db);
$evento = new Evento($db);
$configuracao = new Configuracao($db);

$aluno = $aluno_model->getById($aluno_id);
if (!$aluno) {
    http_response_code(404);
    echo json_encode(['error' => 'Aluno não encontrado']);
    exit;
}

$ano_corrente = $configuracao->getAnoCorrente();
$user_id = $_SESSION['user_id'] ?? null;
$registrado_por = ($user->isNivel2()) ? $user_id : null;

$turmas_aluno = $aluno_model->getTurmasAluno($aluno_id);
$aluno['todas_turmas'] = [];
foreach ($turmas_aluno as $ta) {
    $turma_completa = $turma->getById($ta['id']);
    if ($turma_completa) {
        $aluno['todas_turmas'][] = [
            'id' => $ta['id'],
            'curso_nome' => $turma_completa['curso_nome'] ?? '',
            'curso_id' => $turma_completa['curso_id'] ?? '',
            'ano_curso' => $ta['ano_curso'],
            'ano_civil' => $ta['ano_civil'],
            'is_ano_corrente' => ($ta['ano_civil'] == $ano_corrente)
        ];
    }
}

$turma_ano_corrente_aluno = null;
foreach ($turmas_aluno as $ta) {
    if ($ta['ano_civil'] == $ano_corrente) {
        $turma_ano_corrente_aluno = $ta;
        break;
    }
}
if (!$turma_ano_corrente_aluno && !empty($turmas_aluno)) {
    $turma_ano_corrente_aluno = $turmas_aluno[0];
}

if ($turma_ano_corrente_aluno) {
    $turma_completa = $turma->getById($turma_ano_corrente_aluno['id']);
    if ($turma_completa) {
        $aluno['curso_nome'] = $turma_completa['curso_nome'] ?? '';
        $aluno['curso_id'] = $turma_completa['curso_id'] ?? '';
    }
}

$aluno['total_eventos'] = $evento->countByAluno($aluno_id, $registrado_por, $ano_corrente);

echo json_encode($aluno);
