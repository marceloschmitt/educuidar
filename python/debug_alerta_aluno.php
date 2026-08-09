<?php
/**
 * Debug passo a passo da detecção de alertas para um aluno.
 *
 * Uso:
 *   php python/debug_alerta_aluno.php Anthony
 *   php python/debug_alerta_aluno.php "Anthony Dias"
 *   php python/debug_alerta_aluno.php --id=123
 *   php python/debug_alerta_aluno.php Anthony --aplicar
 */

require_once __DIR__ . '/../config/init.php';

$aplicar = in_array('--aplicar', $argv, true);
$aluno_id = null;
$busca_nome = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--aplicar' || $arg === '--dry-run') {
        continue;
    }
    if (strpos($arg, '--id=') === 0) {
        $aluno_id = (int) substr($arg, 5);
        continue;
    }
    if ($busca_nome === null && $arg !== '' && $arg[0] !== '-') {
        $busca_nome = $arg;
    }
}

if ($aluno_id === null && ($busca_nome === null || trim($busca_nome) === '')) {
    fwrite(STDERR, "Uso: php python/debug_alerta_aluno.php Anthony\n");
    fwrite(STDERR, "     php python/debug_alerta_aluno.php --id=123\n");
    exit(1);
}

$db = (new Database())->getConnection();
$config = new Configuracao($db);
$ano = (int) $config->getAnoCorrente();
$detector = new AlertaDetector($db);
$alerta_gerado = new AlertaGerado($db);
$alerta_regra = new AlertaRegra($db);

function dbg($msg) {
    echo $msg . "\n";
}

dbg('=== DEBUG ALERTA ALUNO ===');
dbg('ano_corrente = ' . $ano);
dbg('data/hora servidor = ' . date('Y-m-d H:i:s'));
dbg('');

// --- Localizar aluno ---
if ($aluno_id) {
    $st = $db->prepare(
        "SELECT id, nome, nome_social, desistente, cpf, numero_matricula
         FROM alunos WHERE id = ?"
    );
    $st->execute([$aluno_id]);
    $alunos = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
    $like = '%' . $busca_nome . '%';
    $st = $db->prepare(
        "SELECT id, nome, nome_social, desistente, cpf, numero_matricula
         FROM alunos
         WHERE nome LIKE ? OR nome_social LIKE ?
         ORDER BY nome
         LIMIT 20"
    );
    $st->execute([$like, $like]);
    $alunos = $st->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($alunos)) {
    dbg('ERRO: nenhum aluno encontrado.');
    exit(1);
}

if (count($alunos) > 1 && !$aluno_id) {
    dbg('Vários alunos encontrados — use --id=N:');
    foreach ($alunos as $a) {
        $ns = $a['nome_social'] ? " (social: {$a['nome_social']})" : '';
        dbg("  id={$a['id']} {$a['nome']}{$ns} desistente={$a['desistente']}");
    }
    exit(1);
}

$aluno = $alunos[0];
$aluno_id = (int) $aluno['id'];
dbg("--- Aluno ---");
dbg('id = ' . $aluno_id);
dbg('nome = ' . $aluno['nome']);
dbg('nome_social = ' . ($aluno['nome_social'] ?: '(vazio)'));
dbg('desistente = ' . (int) $aluno['desistente']);
dbg('cpf = ' . ($aluno['cpf'] ?: '(vazio)'));
dbg('matricula = ' . ($aluno['numero_matricula'] ?: '(vazio)'));
dbg('');

if ((int) $aluno['desistente'] === 1) {
    dbg('AVISO: aluno desistente — o detector IGNORA desistentes.');
}

// --- Turmas ---
dbg('--- Turmas do aluno ---');
$st = $db->prepare(
    "SELECT t.id, t.ano_civil, t.ano_curso, c.id AS curso_id, c.nome AS curso_nome
     FROM aluno_turmas at
     INNER JOIN turmas t ON t.id = at.turma_id
     INNER JOIN cursos c ON c.id = t.curso_id
     WHERE at.aluno_id = ?
     ORDER BY t.ano_civil DESC, t.ano_curso ASC"
);
$st->execute([$aluno_id]);
$turmas = $st->fetchAll(PDO::FETCH_ASSOC);
if (empty($turmas)) {
    dbg('Nenhuma turma em aluno_turmas.');
} else {
    foreach ($turmas as $t) {
        $marca = ((int) $t['ano_civil'] === $ano) ? ' ← ano corrente' : '';
        dbg("  turma_id={$t['id']} {$t['curso_nome']} {$t['ano_curso']}º / ano_civil={$t['ano_civil']}{$marca}");
    }
}
dbg('');

// --- Eventos do ano ---
dbg("--- Eventos do aluno em {$ano} (todos os tipos) ---");
$st = $db->prepare(
    "SELECT e.id, e.data_evento, e.turma_id, e.tipo_evento_id, te.nome AS tipo_nome, te.ativo AS tipo_visivel,
            e.observacoes, c.nome AS curso_evento
     FROM eventos e
     INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
     LEFT JOIN turmas t ON t.id = e.turma_id
     LEFT JOIN cursos c ON c.id = t.curso_id
     WHERE e.aluno_id = ? AND YEAR(e.data_evento) = ?
     ORDER BY e.data_evento ASC, e.id ASC"
);
$st->execute([$aluno_id, $ano]);
$eventos = $st->fetchAll(PDO::FETCH_ASSOC);
dbg('total eventos no ano = ' . count($eventos));
foreach ($eventos as $e) {
    $obs = trim((string) ($e['observacoes'] ?? ''));
    if (strlen($obs) > 60) {
        $obs = substr($obs, 0, 57) . '...';
    }
    dbg(sprintf(
        '  evt#%s %s | tipo_id=%s "%s" visivel=%s | turma_evt=%s curso_evt=%s | %s',
        $e['id'],
        $e['data_evento'],
        $e['tipo_evento_id'],
        $e['tipo_nome'],
        $e['tipo_visivel'],
        $e['turma_id'] ?: 'NULL',
        $e['curso_evento'] ?: '-',
        $obs !== '' ? $obs : '-'
    ));
}
dbg('');

// Datas agosto falta automática
dbg('--- Faltas automáticas em agosto (datas distintas) ---');
$st = $db->prepare(
    "SELECT DISTINCT e.data_evento
     FROM eventos e
     INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
     WHERE e.aluno_id = ?
       AND te.nome = 'Falta (registro automático)'
       AND e.data_evento >= ? AND e.data_evento < ?
     ORDER BY e.data_evento"
);
$ini_ago = sprintf('%04d-08-01', $ano);
$fim_ago = sprintf('%04d-09-01', $ano);
$st->execute([$aluno_id, $ini_ago, $fim_ago]);
$datas_ago = $st->fetchAll(PDO::FETCH_COLUMN);
dbg('datas agosto: ' . (empty($datas_ago) ? '(nenhuma)' : implode(', ', $datas_ago)));
dbg('qtd dias distintos em agosto = ' . count($datas_ago));
dbg('');

// --- Regras ---
$regras = $alerta_regra->getAll(true);
dbg('--- Regras ativas: ' . count($regras) . ' ---');
foreach ($regras as $r) {
    dbg(sprintf(
        '  regra#%s "%s" | criterio=%s q=%s intervalo=%s sab=%s dom=%s',
        $r['id'],
        $r['nome'],
        $r['tipo_criterio'],
        $r['quantidade'],
        $r['intervalo_dias'] ?? 'null',
        $r['ignorar_sabados'] ?? '?',
        $r['ignorar_domingos'] ?? '?'
    ));
    $nomes = $r['tipos_evento_nomes'] ?? [];
    if (empty($nomes)) {
        dbg('    tipos: (NENHUM — regra ignorada pelo detector)');
    } else {
        foreach ($nomes as $tn) {
            dbg('    tipo vinculado: ' . $tn['nome']);
        }
        dbg('    tipo_ids: [' . implode(', ', $r['tipos_evento'] ?? []) . ']');
    }
}
dbg('');

// --- Avaliação detalhada por regra (espelha o detector) ---
dbg('--- Avaliação do detector (por regra) ---');
foreach ($regras as $r) {
    dbg('');
    dbg('>> Regra #' . $r['id'] . ' — ' . $r['nome']);

    $tipo_ids = array_map('intval', $r['tipos_evento'] ?? []);
    if (empty($tipo_ids)) {
        dbg('   BLOQUEIO: regra sem tipos de evento vinculados.');
        continue;
    }

    $placeholders = implode(',', array_fill(0, count($tipo_ids), '?'));
    $sql = "SELECT e.id, e.data_evento, e.tipo_evento_id, te.nome AS tipo_nome, e.turma_id
            FROM eventos e
            INNER JOIN tipos_eventos te ON te.id = e.tipo_evento_id
            INNER JOIN alunos a ON a.id = e.aluno_id
            WHERE e.aluno_id = ?
              AND COALESCE(a.desistente, 0) = 0
              AND e.tipo_evento_id IN ($placeholders)
              AND YEAR(e.data_evento) = ?
            ORDER BY e.data_evento ASC, e.id ASC";
    $params = array_merge([$aluno_id], $tipo_ids, [$ano]);
    $st = $db->prepare($sql);
    $st->execute($params);
    $evs = $st->fetchAll(PDO::FETCH_ASSOC);

    dbg('   eventos que passam no filtro SQL do detector: ' . count($evs));
    if (empty($evs)) {
        dbg('   BLOQUEIO: nenhum evento do aluno casa com os tipos da regra + YEAR(data)=' . $ano);
        // Mostrar se existem eventos dos tipos mas fora do filtro
        $st2 = $db->prepare(
            "SELECT e.data_evento, e.tipo_evento_id, te.nome, YEAR(e.data_evento) AS ano_evt
             FROM eventos e
             JOIN tipos_eventos te ON te.id = e.tipo_evento_id
             WHERE e.aluno_id = ? AND e.tipo_evento_id IN ($placeholders)
             ORDER BY e.data_evento"
        );
        $st2->execute(array_merge([$aluno_id], $tipo_ids));
        $outros = $st2->fetchAll(PDO::FETCH_ASSOC);
        dbg('   eventos desses tipos em qualquer ano: ' . count($outros));
        foreach (array_slice($outros, 0, 30) as $o) {
            dbg("     {$o['data_evento']} (ano={$o['ano_evt']}) tipo={$o['nome']}");
        }
        continue;
    }

    foreach ($evs as $e) {
        dbg("     usa: {$e['data_evento']} evt#{$e['id']} {$e['tipo_nome']} turma={$e['turma_id']}");
    }

    $datas = [];
    foreach ($evs as $e) {
        $datas[$e['data_evento']] = true;
    }
    $datas_ord = array_keys($datas);
    sort($datas_ord);
    dbg('   datas distintas: ' . implode(', ', $datas_ord));

    if ($r['tipo_criterio'] === 'dias_consecutivos') {
        $minimo = (int) $r['quantidade'];
        dbg("   criterio dias_consecutivos: precisa >= {$minimo} dias");

        $melhor_inicio = null;
        $melhor_fim = null;
        $melhor_tamanho = 0;
        $inicio_seq = $datas_ord[0];
        $anterior = $datas_ord[0];
        $tamanho = 1;

        for ($i = 1; $i < count($datas_ord); $i++) {
            $atual = $datas_ord[$i];
            $consec = debugSaoDiasConsecutivos($anterior, $atual, $r);
            dbg("     {$anterior} -> {$atual}: " . ($consec ? 'CONSECUTIVO' : 'QUEBRA') . " (seq atual={$tamanho}" . ($consec ? '→' . ($tamanho + 1) : ', reinicia') . ')');
            if ($consec) {
                $tamanho++;
            } else {
                if ($tamanho > $melhor_tamanho) {
                    $melhor_tamanho = $tamanho;
                    $melhor_inicio = $inicio_seq;
                    $melhor_fim = $anterior;
                }
                $inicio_seq = $atual;
                $tamanho = 1;
            }
            $anterior = $atual;
        }
        if ($tamanho > $melhor_tamanho) {
            $melhor_tamanho = $tamanho;
            $melhor_inicio = $inicio_seq;
            $melhor_fim = $anterior;
        }

        dbg("   melhor sequência: {$melhor_inicio} .. {$melhor_fim} (tamanho={$melhor_tamanho})");
        if ($melhor_tamanho >= $minimo) {
            dbg('   RESULTADO: GERARIA ALERTA');
        } else {
            dbg("   RESULTADO: NÃO gera (tamanho {$melhor_tamanho} < mínimo {$minimo})");
        }
    } else {
        dbg('   criterio=' . $r['tipo_criterio'] . ' — usando motor oficial...');
        $match = $detector->avaliarRegra($r, [
            'ano_corrente' => $ano,
            'aluno_id' => $aluno_id,
        ]);
        if (empty($match)) {
            dbg('   RESULTADO: NÃO gera');
        } else {
            foreach ($match as $m) {
                dbg('   RESULTADO: GERARIA — ' . ($m['periodo_label'] ?? '') . ' qtd=' . ($m['quantidade_contada'] ?? ''));
            }
        }
    }
}

dbg('');
dbg('--- Chamada oficial avaliarTodasRegrasAtivas ---');
$detectados = $detector->avaliarTodasRegrasAtivas([
    'ano_corrente' => $ano,
    'aluno_id' => $aluno_id,
]);
dbg('alertas detectados agora: ' . count($detectados));
foreach ($detectados as $d) {
    dbg(sprintf(
        '  • %s | %s | q=%s | curso=%s | turma=%s',
        $d['regra_nome'],
        $d['periodo_label'],
        $d['quantidade_contada'],
        $d['curso_nome'] ?: '-',
        $d['turma_label'] ?: '-'
    ));
}

dbg('');
dbg('--- Já gravado em alertas_gerados ---');
$st = $db->prepare(
    "SELECT ag.*, ar.nome AS regra_nome
     FROM alertas_gerados ag
     JOIN alertas_regras ar ON ar.id = ag.regra_id
     WHERE ag.aluno_id = ?
     ORDER BY ag.data_fim DESC"
);
$st->execute([$aluno_id]);
$gravados = $st->fetchAll(PDO::FETCH_ASSOC);
dbg('registros: ' . count($gravados));
foreach ($gravados as $g) {
    dbg(sprintf(
        '  id=%s regra=%s %s..%s q=%s notificado_em=%s created_at=%s',
        $g['id'],
        $g['regra_nome'],
        $g['data_inicio'],
        $g['data_fim'],
        $g['quantidade_contada'],
        $g['notificado_em'] ?: 'NULL',
        $g['created_at']
    ));
}

if ($aplicar) {
    dbg('');
    dbg('--- --aplicar: sincronizando alertas_gerados ---');
    $alerta_gerado->sincronizarAlertasAluno($aluno_id, $detectados);
    dbg('OK sincronizado.');
} else {
    dbg('');
    dbg('Dica: para gravar o que o detector achou agora:');
    dbg("  php python/debug_alerta_aluno.php --id={$aluno_id} --aplicar");
}

dbg('');
dbg('=== FIM DEBUG ===');

/**
 * Cópia da lógica de AlertaDetector::saoDiasConsecutivos para log.
 */
function debugSaoDiasConsecutivos($data_anterior, $data_atual, array $regra) {
    $cursor = strtotime($data_anterior . ' +1 day');
    $fim = strtotime($data_atual);
    $ignorar_domingos = !empty($regra['ignorar_domingos']);
    $ignorar_sabados = !empty($regra['ignorar_sabados']);

    while ($cursor < $fim) {
        $dow = (int) date('N', $cursor);
        $ignorado = ($ignorar_domingos && $dow === 7) || ($ignorar_sabados && $dow === 6);
        if (!$ignorado) {
            return false;
        }
        $cursor = strtotime(date('Y-m-d', $cursor) . ' +1 day');
    }

    return true;
}
