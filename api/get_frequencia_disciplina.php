<?php
/**
 * API: frequência de cada aluno da turma em uma disciplina (dados do SIGAA),
 * do maior para o menor percentual de faltas.
 * GET turma_id, cod_disciplina
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json; charset=utf-8');

$db = (new Database())->getConnection();
$user = new User($db);
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}
$user->ensureUserTypeSession();
if (!($user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2())) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão']);
    exit;
}

$turma_id = (int) ($_GET['turma_id'] ?? 0);
$cod_disciplina = trim((string) ($_GET['cod_disciplina'] ?? ''));
if ($turma_id <= 0 || $cod_disciplina === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Turma ou disciplina inválida']);
    exit;
}

$ano = (int) (new Configuracao($db))->getAnoCorrente();
$alunos = (new DashboardEstatisticas($db, ['ano' => $ano]))->faltasAlunosDisciplina($turma_id, $cod_disciplina);

echo json_encode(array_map(function ($row) {
    return [
        'id' => (int) $row['id'],
        'nome' => $row['nome'],
        'aulas' => (int) $row['aulas'],
        'faltas' => (int) $row['faltas'],
        'percentual' => (float) $row['percentual'],
        'ultima_aula' => $row['ultima_aula'] ? date('d/m/Y', strtotime($row['ultima_aula'])) : null,
    ];
}, $alunos), JSON_UNESCAPED_UNICODE);
