<?php
/**
 * CLI: resumos diários (após 19:30) dos eventos do dia aos responsáveis e coordenadores.
 * Uso: php enviar_emails_eventos_cli.php
 *      php enviar_emails_eventos_cli.php --forcar-resumo   (ignora o horário)
 */
require_once __DIR__ . '/../config/init.php';

$database = new Database();
$db = $database->getConnection();
$configuracao = new Configuracao($db);
$eventoEmail = new EventoEmail($db);

$forcar = in_array('--forcar-resumo', $argv, true);

$falhas = 0;
$execucoes = [
    'responsaveis' => $eventoEmail->processarResumosResponsaveis($configuracao, $forcar),
    'coordenadores' => $eventoEmail->processarResumosCoordenadores($configuracao, $forcar),
];
foreach ($execucoes as $nome => $stats) {
    echo "resumo {$nome}: enviados=" . $stats['enviados']
        . ' falhas=' . $stats['falhas']
        . ' pulados=' . $stats['pulados'] . "\n";
    foreach ($stats['mensagens'] as $msg) {
        echo $msg . "\n";
    }
    $falhas += (int) $stats['falhas'];
}

exit($falhas > 0 ? 1 : 0);
