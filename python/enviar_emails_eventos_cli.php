<?php
/**
 * CLI: e-mails aos responsáveis (após 2h) e resumo aos coordenadores (após 19:30).
 * Uso: php enviar_emails_eventos_cli.php
 *      php enviar_emails_eventos_cli.php --forcar-resumo
 */
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$configuracao = new Configuracao($db);
$eventoEmail = new EventoEmail($db);

$forcar_resumo = in_array('--forcar-resumo', $argv, true);

$stats = $eventoEmail->processarPendentes($configuracao, 200);
echo 'responsaveis enviados=' . $stats['enviados']
    . ' falhas=' . $stats['falhas']
    . ' pulados=' . $stats['pulados'] . "\n";
foreach ($stats['mensagens'] as $msg) {
    echo $msg . "\n";
}

$stats_resumo = $eventoEmail->processarResumosCoordenadores($configuracao, $forcar_resumo);
echo 'resumo enviados=' . $stats_resumo['enviados']
    . ' falhas=' . $stats_resumo['falhas']
    . ' pulados=' . $stats_resumo['pulados'] . "\n";
foreach ($stats_resumo['mensagens'] as $msg) {
    echo $msg . "\n";
}

$falhas = (int) $stats['falhas'] + (int) $stats_resumo['falhas'];
exit($falhas > 0 ? 1 : 0);
