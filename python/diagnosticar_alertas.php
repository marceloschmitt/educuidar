<?php
/**
 * Diagnóstico: por que alertas aparecem em um curso e não em outro.
 * Uso: php python/diagnosticar_alertas.php
 */

require_once __DIR__ . '/../config/init.php';

$db = (new Database())->getConnection();
$ano = (new Configuracao($db))->getAnoCorrente();
$detector = new AlertaDetector($db);

echo "ano_corrente={$ano}\n\n";

echo "=== Alertas gravados por curso (detalhe) ===\n";
foreach ($db->query(
    "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ag.detalhe, '$.curso_nome')), '(sem curso)') AS curso,
            COUNT(*) AS n
     FROM alertas_gerados ag
     GROUP BY curso
     ORDER BY n DESC"
) as $r) {
    echo "{$r['curso']}: {$r['n']}\n";
}

echo "\n=== Regras ativas ===\n";
$regras = (new AlertaRegra($db))->getAll(true);
foreach ($regras as $r) {
    $tipos = implode(', ', array_map('strval', $r['tipos_evento'] ?? []));
    echo "#{$r['id']} {$r['nome']} | {$r['tipo_criterio']} q={$r['quantidade']}"
        . " sab={$r['ignorar_sabados']} dom={$r['ignorar_domingos']} tipos_ids=[{$tipos}]\n";
    foreach ($r['tipos_evento'] ?? [] as $tid) {
        $st = $db->prepare('SELECT id, nome, ativo FROM tipos_eventos WHERE id = ?');
        $st->execute([(int) $tid]);
        $te = $st->fetch(PDO::FETCH_ASSOC);
        if ($te) {
            echo "    tipo {$te['id']}: {$te['nome']} visivel={$te['ativo']}\n";
        }
    }
}

echo "\n=== Faltas automáticas por curso da TURMA DO EVENTO ===\n";
$st = $db->prepare(
    "SELECT COALESCE(c.nome, '(sem curso no evento)') AS curso,
            COUNT(DISTINCT e.aluno_id) AS alunos, COUNT(*) AS eventos
     FROM eventos e
     INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
       AND te.nome = 'Falta (registro automático)'
     LEFT JOIN turmas t ON t.id = e.turma_id
     LEFT JOIN cursos c ON c.id = t.curso_id
     WHERE YEAR(e.data_evento) = ?
     GROUP BY curso
     ORDER BY alunos DESC"
);
$st->execute([$ano]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "{$r['curso']}: alunos={$r['alunos']} eventos={$r['eventos']}\n";
}

echo "\n=== Faltas automáticas por curso da TURMA DO ALUNO (ano corrente) ===\n";
$st = $db->prepare(
    "SELECT COALESCE(c.nome, '(aluno sem turma no ano)') AS curso,
            COUNT(DISTINCT e.aluno_id) AS alunos, COUNT(*) AS eventos
     FROM eventos e
     INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
       AND te.nome = 'Falta (registro automático)'
     INNER JOIN alunos a ON a.id = e.aluno_id AND COALESCE(a.desistente, 0) = 0
     LEFT JOIN aluno_turmas at ON at.aluno_id = a.id
     LEFT JOIN turmas t ON t.id = at.turma_id AND t.ano_civil = ?
     LEFT JOIN cursos c ON c.id = t.curso_id
     WHERE YEAR(e.data_evento) = ?
     GROUP BY curso
     ORDER BY alunos DESC"
);
$st->execute([$ano, $ano]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "{$r['curso']}: alunos={$r['alunos']} eventos={$r['eventos']}\n";
}

echo "\n=== Alunos com 3+ dias distintos de falta automática ===\n";
$st = $db->prepare(
    "SELECT a.id,
            COALESCE(NULLIF(a.nome_social, ''), a.nome) AS nome,
            COALESCE(MAX(c.nome), '(sem turma)') AS curso,
            COUNT(DISTINCT e.data_evento) AS dias
     FROM eventos e
     INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
       AND te.nome = 'Falta (registro automático)'
     INNER JOIN alunos a ON a.id = e.aluno_id AND COALESCE(a.desistente, 0) = 0
     LEFT JOIN aluno_turmas at ON at.aluno_id = a.id
     LEFT JOIN turmas t ON t.id = at.turma_id AND t.ano_civil = ?
     LEFT JOIN cursos c ON c.id = t.curso_id
     WHERE YEAR(e.data_evento) = ?
     GROUP BY a.id, nome
     HAVING dias >= 3
     ORDER BY curso, dias DESC, nome"
);
$st->execute([$ano, $ano]);
$candidatos = $st->fetchAll(PDO::FETCH_ASSOC);

$com_alerta = $db->query('SELECT aluno_id FROM alertas_gerados')->fetchAll(PDO::FETCH_COLUMN);
$set = array_flip(array_map('intval', $com_alerta));

$ok = [];
$falta = [];
foreach ($candidatos as $r) {
    $curso = $r['curso'];
    if (isset($set[(int) $r['id']])) {
        $ok[$curso] = ($ok[$curso] ?? 0) + 1;
    } else {
        $falta[$curso] = ($falta[$curso] ?? 0) + 1;
        if (($falta[$curso] ?? 0) <= 8) {
            echo "SEM ALERTA na tabela: curso={$curso} dias={$r['dias']} id={$r['id']} {$r['nome']}\n";

            // Rodar detector ao vivo neste aluno
            $detectados = $detector->avaliarTodasRegrasAtivas([
                'ano_corrente' => (int) $ano,
                'aluno_id' => (int) $r['id'],
            ]);
            echo '  detector agora: ' . count($detectados) . " alerta(s)\n";
            foreach ($detectados as $d) {
                echo "    -> {$d['regra_nome']} | {$d['periodo_label']} | q={$d['quantidade_contada']} | curso_det={$d['curso_nome']}\n";
            }

            // Datas distintas
            $st2 = $db->prepare(
                "SELECT DISTINCT e.data_evento
                 FROM eventos e
                 INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
                   AND te.nome = 'Falta (registro automático)'
                 WHERE e.aluno_id = ?
                   AND YEAR(e.data_evento) = ?
                 ORDER BY e.data_evento"
            );
            $st2->execute([(int) $r['id'], $ano]);
            $datas = $st2->fetchAll(PDO::FETCH_COLUMN);
            echo '  datas: ' . implode(', ', $datas) . "\n";
        }
    }
}

echo "\nResumo candidatos 3+ dias COM alerta gravado:\n";
foreach ($ok as $c => $n) {
    echo "  {$c}: {$n}\n";
}
echo "Resumo candidatos 3+ dias SEM alerta gravado:\n";
foreach ($falta as $c => $n) {
    echo "  {$c}: {$n}\n";
}

echo "\nConcluído.\n";
