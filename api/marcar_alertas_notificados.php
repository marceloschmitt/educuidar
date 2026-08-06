<?php
/**
 * Marca alertas do popup de login como notificados (visualizados).
 * POST JSON: { "ids": [1, 2, 3] }
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json; charset=utf-8');

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if (!$user->isLoggedIn() || !usuarioPodeVerPopupAlertas($user)) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$ids = $payload['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}

$alerta_gerado = new AlertaGerado($db);
$visiveis = $alerta_gerado->getNaoNotificados(getCursosCoordenadosPermitidos($user));
$ids_permitidos = array_map(function ($row) {
    return (int) ($row['id'] ?? 0);
}, $visiveis);
$ids = array_values(array_intersect(array_map('intval', $ids), $ids_permitidos));
$marcados = $alerta_gerado->marcarNotificados($ids);

echo json_encode(['ok' => true, 'marcados' => $marcados]);
