<?php
/**
 * Layout minimalista do portal de responsáveis (mobile-first).
 * Esperado: $page_title, $responsavel_nome (opcional)
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Portal do responsável'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --resp-bg: #f3f6f4;
            --resp-accent: #1f6b4a;
        }
        body {
            background: var(--resp-bg);
            min-height: 100vh;
            font-size: 1.05rem;
        }
        .resp-header {
            background: var(--resp-accent);
            color: #fff;
            padding: 0.75rem 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .resp-header-brand {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            min-width: 0;
            flex-wrap: wrap;
        }
        .resp-header-brand .resp-nome {
            opacity: 0.85;
            font-size: 0.95rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
        }
        .resp-header-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.55rem;
        }
        .resp-header-nav a {
            color: #fff;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 0.5rem;
            padding: 0.35rem 0.65rem;
            font-size: 0.9rem;
            line-height: 1.2;
            min-height: 2.25rem;
            display: inline-flex;
            align-items: center;
        }
        .resp-header-nav a:hover,
        .resp-header-nav a:focus {
            background: rgba(255,255,255,.12);
            color: #fff;
        }
        .resp-card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,.06);
        }
        .resp-event {
            display: flex;
            gap: 0.85rem;
            padding: 1rem;
            border-bottom: 1px solid #ececec;
            align-items: flex-start;
        }
        .resp-event:last-child { border-bottom: 0; }
        .resp-event-date {
            min-width: 4.2rem;
            text-align: center;
            font-weight: 600;
            line-height: 1.2;
        }
        .resp-event-date .day { font-size: 1.4rem; display: block; }
        .resp-event-date .month { font-size: 0.8rem; text-transform: uppercase; color: #666; }
        .aluno-chip {
            display: block;
            width: 100%;
            text-align: left;
            border: 1px solid #dce5df;
            background: #fff;
            border-radius: 0.85rem;
            padding: 1rem 1.1rem;
            margin-bottom: 0.65rem;
            font-size: 1.1rem;
        }
        .aluno-chip.active, .aluno-chip:hover {
            border-color: var(--resp-accent);
            background: #eef7f2;
        }
        .btn-touch { min-height: 2.75rem; }
    </style>
</head>
<body>
<?php if (!empty($show_header)): ?>
<header class="resp-header">
    <div class="resp-header-brand">
        <strong>EduCuidar</strong>
        <?php if (!empty($responsavel_nome)): ?>
        <span class="resp-nome"><?php echo htmlspecialchars($responsavel_nome); ?></span>
        <?php endif; ?>
    </div>
    <nav class="resp-header-nav" aria-label="Menu do responsável">
        <a href="index.php">Ocorrências</a>
        <a href="autorizacoes.php">Autorizações</a>
        <a href="meusdados.php">Conta</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<?php endif; ?>
<main class="container<?php echo empty($layout_full) ? ' py-3' : ''; ?>"<?php echo empty($layout_full) ? ' style="max-width: 640px;"' : ''; ?>>
