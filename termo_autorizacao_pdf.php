<?php
/**
 * Entrega o PDF do termo: escola (admin/n0/n1) ou responsável com aluno nas turmas.
 */
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$resp = new Responsavel($db);
$termoModel = new TermoAutorizacao($db);

$termo_id = (int) ($_GET['id'] ?? 0);
$termo = $termo_id ? $termoModel->getById($termo_id) : null;
$caminho = $termo ? $termoModel->caminhoAbsolutoPdf($termo) : null;

$autorizado = false;
if ($user->isLoggedIn() && ($user->isAdmin() || $user->isNivel0() || $user->isNivel1())) {
    $autorizado = true;
} elseif ($resp->isLoggedIn() && $termo) {
    $autorizado = $termoModel->responsavelPodeVerTermo($termo_id, $resp->getLoggedId());
}

if (!$termo || !$caminho || !$autorizado) {
    http_response_code(404);
    echo 'Arquivo não encontrado.';
    exit;
}

$nome = str_replace(['"', "\r", "\n"], '', $termo['pdf_nome_original']);
header('Content-Type: ' . ($termo['pdf_mime'] ?: 'application/pdf'));
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: inline; filename="' . $nome . '"; filename*=UTF-8\'\'' . rawurlencode($termo['pdf_nome_original']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($caminho);
