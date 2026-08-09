<?php
/**
 * Reprocessa alertas de todos os alunos (ou de um aluno) e sincroniza alertas_gerados.
 *
 * Remove alertas obsoletos, cria/atualiza os válidos conforme as regras ativas.
 *
 * Uso (na raiz do projeto ou em python/):
 *   php python/reprocessar_alertas.php
 *   php python/reprocessar_alertas.php --dry-run
 *   php python/reprocessar_alertas.php --aluno=123
 */

require_once __DIR__ . '/../config/init.php';

$dry_run = in_array('--dry-run', $argv, true);
$aluno_filtro = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--aluno=') === 0) {
        $aluno_filtro = (int) substr($arg, strlen('--aluno='));
    }
}

$database = new Database();
$db = $database->getConnection();
$configuracao = new Configuracao($db);
$detector = new AlertaDetector($db);
$alerta_gerado = new AlertaGerado($db);
$ano = $configuracao->getAnoCorrente();

if ($aluno_filtro) {
    $stmt = $db->prepare(
        "SELECT id, COALESCE(NULLIF(nome_social, ''), nome) AS nome
         FROM alunos
         WHERE id = :id AND COALESCE(desistente, 0) = 0"
    );
    $stmt->bindValue(':id', $aluno_filtro, PDO::PARAM_INT);
    $stmt->execute();
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($alunos)) {
        fwrite(STDERR, "Aluno id={$aluno_filtro} não encontrado ou desistente.\n");
        exit(1);
    }
} else {
    $stmt = $db->query(
        "SELECT id, COALESCE(NULLIF(nome_social, ''), nome) AS nome
         FROM alunos
         WHERE COALESCE(desistente, 0) = 0
         ORDER BY nome ASC"
    );
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$total_alunos = count($alunos);
$com_alerta = 0;
$total_alertas = 0;
$erros = 0;

echo $dry_run ? "[DRY-RUN] " : "";
echo "Reprocessando alertas — ano {$ano}, {$total_alunos} aluno(s).\n";

foreach ($alunos as $i => $aluno) {
    $aluno_id = (int) $aluno['id'];
    $n = $i + 1;
    try {
        $alertas = $detector->avaliarTodasRegrasAtivas([
            'ano_corrente' => $ano,
            'aluno_id' => $aluno_id,
        ]);

        if (!$dry_run) {
            $alerta_gerado->sincronizarAlertasAluno($aluno_id, $alertas);
        }

        $qtd = count($alertas);
        if ($qtd > 0) {
            $com_alerta++;
            $total_alertas += $qtd;
            echo "  [{$n}/{$total_alunos}] id={$aluno_id} {$aluno['nome']}: {$qtd} alerta(s)\n";
            foreach ($alertas as $al) {
                echo "      - {$al['regra_nome']} | {$al['periodo_label']} | qtd={$al['quantidade_contada']}\n";
            }
        } elseif ($aluno_filtro) {
            echo "  id={$aluno_id} {$aluno['nome']}: nenhum alerta\n";
        }
    } catch (Throwable $e) {
        $erros++;
        fwrite(STDERR, "  ERRO id={$aluno_id}: {$e->getMessage()}\n");
    }
}

if (!$dry_run && !$aluno_filtro) {
    // Remove alertas de alunos desistentes
    $db->exec(
        "DELETE ag FROM alertas_gerados ag
         INNER JOIN alunos a ON a.id = ag.aluno_id
         WHERE COALESCE(a.desistente, 0) = 1"
    );
}

echo "\nConcluído";
echo $dry_run ? " (simulação, nada gravado)" : "";
echo ".\n";
echo "Alunos com alerta: {$com_alerta}\n";
echo "Total de alertas detectados: {$total_alertas}\n";
if ($erros) {
    echo "Erros: {$erros}\n";
    exit(1);
}

exit(0);
