<?php
/**
 * Relatório imprimível das autorizações do termo (Salvar como PDF).
 */
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$termoModel = new TermoAutorizacao($db);

$pode_ver = $user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2();
if (!$user->isLoggedIn() || !$pode_ver) {
    header('Location: index.php');
    exit;
}

$termo_id = (int) ($_GET['id'] ?? 0);
$termo = $termo_id ? $termoModel->getById($termo_id) : null;
if (!$termo) {
    $_SESSION['error'] = 'Autorização não encontrada.';
    header('Location: termos_autorizacao.php');
    exit;
}

$listas = $termoModel->montarListasDecisoesParaPdf($termo_id);
$turmas_label = [];
foreach ($termo['turmas'] ?? [] as $tu) {
    $turmas_label[] = ($tu['curso_nome'] ?? '') . ' - ' . (int) ($tu['ano_curso'] ?? 0) . 'º';
}

$fmtData = static function ($valor) {
    if (empty($valor)) {
        return '—';
    }
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
};

$fmtResponsavel = static function (array $linha) {
    $nome = trim((string) ($linha['responsavel_nome'] ?? '—'));
    $parentesco = trim((string) ($linha['parentesco'] ?? ''));
    if ($parentesco !== '' && $nome !== '—') {
        return $nome . ' (' . $parentesco . ')';
    }
    return $nome !== '' ? $nome : '—';
};

$secoes = [
    [
        'titulo' => 'Discordaram',
        'itens' => $listas['discordaram'],
    ],
    [
        'titulo' => 'Concordaram',
        'itens' => $listas['concordaram'],
    ],
    [
        'titulo' => 'Não responderam',
        'itens' => $listas['nao_responderam'],
    ],
];

$page_title = 'Autorizações — ' . ($termo['titulo'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        @page {
            margin: 15mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 24px;
            line-height: 1.35;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 8px;
        }
        h2 {
            font-size: 14px;
            margin: 24px 0 8px;
            border-bottom: 1px solid #333;
            padding-bottom: 4px;
        }
        .meta {
            margin-bottom: 16px;
            color: #333;
        }
        .meta div { margin: 2px 0; }
        .toolbar {
            margin-bottom: 16px;
        }
        .toolbar button, .toolbar a {
            display: inline-block;
            margin-right: 8px;
            padding: 8px 12px;
            font-size: 13px;
            text-decoration: none;
            border: 1px solid #333;
            background: #f5f5f5;
            color: #111;
            border-radius: 4px;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f0f0f0;
            font-weight: 600;
        }
        th.num, td.num {
            width: 40px;
            text-align: right;
            white-space: nowrap;
        }
        .vazio {
            color: #666;
            font-style: italic;
            margin: 0 0 8px;
        }
        .count { font-weight: normal; color: #555; }
        @media print {
            body { margin: 0; }
            .toolbar { display: none !important; }
            h2 { page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" id="btn-imprimir">Imprimir / Salvar como PDF</button>
        <a href="termo_autorizacao_ver.php?id=<?php echo (int) $termo_id; ?>">Voltar</a>
    </div>

    <h1>Autorizações — <?php echo htmlspecialchars($termo['titulo'] ?? ''); ?></h1>
    <div class="meta">
        <div>
            <strong>Data do evento:</strong>
            <?php echo date('d/m/Y', strtotime($termo['data_evento'])); ?>
            <?php if (!empty($termo['hora'])): ?>
            às <?php echo substr($termo['hora'], 0, 5); ?>
            <?php endif; ?>
        </div>
        <div>
            <strong>Turmas:</strong>
            <?php echo htmlspecialchars(!empty($turmas_label) ? implode(', ', $turmas_label) : '—'); ?>
        </div>
        <div>
            <strong>Gerado em:</strong>
            <?php echo date('d/m/Y H:i'); ?>
        </div>
    </div>

    <?php foreach ($secoes as $secao): ?>
    <h2>
        <?php echo htmlspecialchars($secao['titulo']); ?>
        <span class="count">(<?php echo count($secao['itens']); ?>)</span>
    </h2>
    <?php if (empty($secao['itens'])): ?>
    <p class="vazio">Nenhum registro nesta lista.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th class="num">Nº</th>
                <th style="width: 32%;">Aluno</th>
                <th style="width: 40%;">Responsável</th>
                <th style="width: 22%;">Data</th>
            </tr>
        </thead>
        <tbody>
            <?php $n = 0; foreach ($secao['itens'] as $linha): $n++; ?>
            <tr>
                <td class="num"><?php echo $n; ?></td>
                <td><?php echo htmlspecialchars($linha['aluno_nome']); ?></td>
                <td><?php echo htmlspecialchars($fmtResponsavel($linha)); ?></td>
                <td><?php echo htmlspecialchars($fmtData($linha['decidido_em'] ?? null)); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php endforeach; ?>

    <script>
    (function () {
        var tituloOriginal = document.title;
        function imprimir() {
            // Título vazio: o horário já aparece abaixo do nome do evento ("Gerado em").
            // Assim o cabeçalho do navegador não repete data/hora nem "DECISÕES".
            document.title = ' ';
            window.print();
            setTimeout(function () { document.title = tituloOriginal; }, 500);
        }
        document.getElementById('btn-imprimir').addEventListener('click', imprimir);
        window.addEventListener('load', function () {
            setTimeout(imprimir, 200);
        });
    })();
    </script>
</body>
</html>
