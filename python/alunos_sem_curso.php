<?php
/**
 * Lista alunos sem curso/turma (situação que não deveria ocorrer).
 *
 * Uso:
 *   php python/alunos_sem_curso.php
 *   php python/alunos_sem_curso.php --ano=2026
 *   php python/alunos_sem_curso.php --incluir-desistentes
 */

require_once __DIR__ . '/../config/init.php';

$incluir_desistentes = in_array('--incluir-desistentes', $argv, true);
$ano = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--ano=') === 0) {
        $ano = (int) substr($arg, 6);
    }
}

$db = (new Database())->getConnection();
$config = new Configuracao($db);
if ($ano === null || $ano < 2000) {
    $ano = (int) $config->getAnoCorrente();
}

echo "=== Alunos sem curso/turma ===\n";
echo "ano_corrente usado = {$ano}\n";
echo 'desistentes = ' . ($incluir_desistentes ? 'incluídos' : 'excluídos') . "\n\n";

$filtro_desistente = $incluir_desistentes ? '' : 'AND COALESCE(a.desistente, 0) = 0';

// 1) Sem nenhuma turma em aluno_turmas
$sql_nenhuma = "SELECT a.id, a.nome, a.nome_social, a.cpf, a.numero_matricula, a.desistente
                FROM alunos a
                WHERE NOT EXISTS (
                    SELECT 1 FROM aluno_turmas at WHERE at.aluno_id = a.id
                )
                {$filtro_desistente}
                ORDER BY a.nome";
$sem_nenhuma = $db->query($sql_nenhuma)->fetchAll(PDO::FETCH_ASSOC);

echo "--- 1) Sem NENHUMA turma cadastrada: " . count($sem_nenhuma) . " ---\n";
foreach ($sem_nenhuma as $a) {
    $social = $a['nome_social'] ? " (social: {$a['nome_social']})" : '';
    $des = (int) $a['desistente'] ? ' [DESISTENTE]' : '';
    echo "  id={$a['id']} {$a['nome']}{$social}{$des} cpf=" . ($a['cpf'] ?: '-')
        . " mat=" . ($a['numero_matricula'] ?: '-') . "\n";
}
echo "\n";

// 2) Tem turma em outro ano, mas não no ano corrente
$sql_sem_ano = "SELECT a.id, a.nome, a.nome_social, a.cpf, a.numero_matricula, a.desistente,
                       GROUP_CONCAT(DISTINCT CONCAT(c.nome, ' ', t.ano_curso, 'º/', t.ano_civil)
                                    ORDER BY t.ano_civil DESC SEPARATOR '; ') AS outras_turmas
                FROM alunos a
                WHERE EXISTS (SELECT 1 FROM aluno_turmas at WHERE at.aluno_id = a.id)
                  AND NOT EXISTS (
                      SELECT 1
                      FROM aluno_turmas at2
                      INNER JOIN turmas t2 ON t2.id = at2.turma_id
                      WHERE at2.aluno_id = a.id AND t2.ano_civil = :ano
                  )
                  {$filtro_desistente}
                GROUP BY a.id, a.nome, a.nome_social, a.cpf, a.numero_matricula, a.desistente
                ORDER BY a.nome";
$st = $db->prepare($sql_sem_ano);
$st->execute([':ano' => $ano]);
$sem_ano = $st->fetchAll(PDO::FETCH_ASSOC);

echo "--- 2) Sem turma no ano {$ano} (mas tem turma em outro ano): " . count($sem_ano) . " ---\n";
foreach ($sem_ano as $a) {
    $social = $a['nome_social'] ? " (social: {$a['nome_social']})" : '';
    $des = (int) $a['desistente'] ? ' [DESISTENTE]' : '';
    echo "  id={$a['id']} {$a['nome']}{$social}{$des}\n";
    echo "      outras: {$a['outras_turmas']}\n";
}
echo "\n";

// 3) Tem turma no ano, mas curso NULL (dado inconsistente)
$sql_curso_null = "SELECT a.id, a.nome, t.id AS turma_id, t.ano_civil, t.ano_curso, t.curso_id
                   FROM alunos a
                   INNER JOIN aluno_turmas at ON at.aluno_id = a.id
                   INNER JOIN turmas t ON t.id = at.turma_id AND t.ano_civil = :ano
                   LEFT JOIN cursos c ON c.id = t.curso_id
                   WHERE c.id IS NULL
                   {$filtro_desistente}
                   ORDER BY a.nome";
$st = $db->prepare($sql_curso_null);
$st->execute([':ano' => $ano]);
$curso_null = $st->fetchAll(PDO::FETCH_ASSOC);

echo "--- 3) Turma no ano {$ano} com curso inexistente/NULL: " . count($curso_null) . " ---\n";
foreach ($curso_null as $a) {
    echo "  id={$a['id']} {$a['nome']} turma_id={$a['turma_id']} curso_id="
        . ($a['curso_id'] === null ? 'NULL' : $a['curso_id']) . "\n";
}
echo "\n";

$total = count($sem_nenhuma) + count($sem_ano) + count($curso_null);
echo "Total de problemas: {$total}\n";
echo "=== FIM ===\n";

exit($total > 0 ? 2 : 0);
