<?php
/**
 * API: frequência por disciplina de um aluno no ano corrente (dados do SIGAA).
 * GET id = aluno_id
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

$aluno_id = (int) ($_GET['id'] ?? 0);
if ($aluno_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do aluno inválido']);
    exit;
}

$ano = (int) (new Configuracao($db))->getAnoCorrente();
$linhas = (new FrequenciaDisciplina($db))->getPorAluno($aluno_id, $ano);

$atualizado_em = null;
$disciplinas = [];
foreach ($linhas as $f) {
    if ($atualizado_em === null || $f['atualizado_em'] > $atualizado_em) {
        $atualizado_em = $f['atualizado_em'];
    }
    $percentual = (float) $f['percentual'];
    $disciplinas[] = [
        'nome' => FrequenciaDisciplina::nomeLegivel($f['disciplina_nome']),
        'aulas' => (int) $f['aulas'],
        'faltas' => (int) $f['faltas'],
        'percentual' => $percentual,
        'frequencia' => $f['percentual_frequencia'] !== null ? (float) $f['percentual_frequencia'] : round(100 - $percentual, 1),
    ];
}

echo json_encode([
    'ano' => $ano,
    'limite_faltas' => FrequenciaDisciplina::LIMITE_FALTAS,
    'atualizado_em' => $atualizado_em ? date('d/m/Y', strtotime($atualizado_em)) : null,
    'geral' => FrequenciaDisciplina::resumoGeral($linhas),
    'disciplinas' => $disciplinas,
], JSON_UNESCAPED_UNICODE);
