<?php
/**
 * CLI: reprocessa alertas para uma lista de aluno_id.
 * Uso: php processar_alertas_cli.php [--silencioso] 1 2 3
 *   --silencioso  alertas criados nesta execução já ficam como notificados (sem pop-up).
 */
require_once __DIR__ . '/../config/init.php';

$args = array_slice($argv, 1);
$silencioso = in_array('--silencioso', $args, true);
$args = array_values(array_diff($args, ['--silencioso']));

$ids = array_values(array_unique(array_filter(array_map('intval', $args))));
if (empty($ids)) {
    fwrite(STDERR, "Informe um ou mais aluno_id.\n");
    exit(1);
}

$database = new Database();
$db = $database->getConnection();

$ultimo_id_antes = 0;
if ($silencioso) {
    $ultimo_id_antes = (int) $db->query("SELECT COALESCE(MAX(id), 0) FROM alertas_gerados")->fetchColumn();
}

foreach ($ids as $aluno_id) {
    processarAlertasAluno($db, $aluno_id);
    echo "OK aluno_id={$aluno_id}\n";
}

if ($silencioso) {
    $stmt = $db->prepare("UPDATE alertas_gerados SET notificado_em = NOW()
                          WHERE id > :ultimo AND notificado_em IS NULL");
    $stmt->bindValue(':ultimo', $ultimo_id_antes, PDO::PARAM_INT);
    $stmt->execute();
    echo "Silencioso: " . $stmt->rowCount() . " alerta(s) novo(s) marcados como notificados\n";
}
