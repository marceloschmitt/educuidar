<?php
/**
 * CLI: envia e-mails de eventos aos responsáveis (após 2h do registro).
 * Uso: php enviar_emails_eventos_cli.php
 */
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$configuracao = new Configuracao($db);
$eventoEmail = new EventoEmail($db);

$stats = $eventoEmail->processarPendentes($configuracao, 200);

echo 'enviados=' . $stats['enviados']
    . ' falhas=' . $stats['falhas']
    . ' pulados=' . $stats['pulados'] . "\n";

foreach ($stats['mensagens'] as $msg) {
    echo $msg . "\n";
}

exit($stats['falhas'] > 0 ? 1 : 0);
