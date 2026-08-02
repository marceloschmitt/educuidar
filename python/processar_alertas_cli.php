<?php
/**
 * CLI: reprocessa alertas para uma lista de aluno_id.
 * Uso: php processar_alertas_cli.php 1 2 3
 */
require_once __DIR__ . '/../config/init.php';

$ids = array_values(array_unique(array_filter(array_map('intval', array_slice($argv, 1)))));
if (empty($ids)) {
    fwrite(STDERR, "Informe um ou mais aluno_id.\n");
    exit(1);
}

$database = new Database();
$db = $database->getConnection();

foreach ($ids as $aluno_id) {
    processarAlertasAluno($db, $aluno_id);
    echo "OK aluno_id={$aluno_id}\n";
}
