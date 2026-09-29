<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if (!$user->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user_type_id = $user->getUserTypeId();
if (empty($user_type_id)) {
    $stmt = $db->prepare("SELECT user_type_id FROM user_user_types WHERE user_id = :user_id LIMIT 1");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user_type_id = $stmt->fetchColumn() ?: null;
}

$registro = new ProntuarioRegistro($db);
$anexo = $registro->getAnexo($_GET['id'] ?? 0);
$caminho = $anexo ? __DIR__ . '/' . ltrim($anexo['caminho'], '/') : '';

if (!$anexo || empty($user_type_id) || (string) $anexo['user_type_id'] !== (string) $user_type_id || !is_file($caminho)) {
    http_response_code(404);
    echo 'Anexo não encontrado.';
    exit;
}

$nome = str_replace(['"', "\r", "\n"], '', $anexo['nome_original']);
header('Content-Type: ' . ($anexo['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: inline; filename="' . $nome . '"; filename*=UTF-8\'\'' . rawurlencode($anexo['nome_original']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($caminho);
