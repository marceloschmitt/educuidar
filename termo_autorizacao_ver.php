<?php
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$termoModel = new TermoAutorizacao($db);

$pode_ver = $user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2();
$pode_gerenciar = $user->isAdmin() || $user->isNivel0() || $user->isNivel1();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pode_gerenciar) {
        $_SESSION['error'] = 'Você não tem permissão para alterar esta autorização.';
        header('Location: termo_autorizacao_ver.php?id=' . $termo_id);
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'liberar' && $user->isAdmin()) {
        $resposta_id = (int) ($_POST['resposta_id'] ?? 0);
        if ($resposta_id && $termoModel->liberarAlteracao($resposta_id, $_SESSION['user_id'])) {
            $_SESSION['success'] = 'Alteração liberada. O responsável poderá responder novamente.';
        } else {
            $_SESSION['error'] = 'Não foi possível liberar (já liberado ou inexistente).';
        }
        header('Location: termo_autorizacao_ver.php?id=' . $termo_id);
        exit;
    }

    if ($action === 'suspender') {
        if ($termoModel->suspender($termo_id)) {
            $_SESSION['success'] = 'Autorização suspensa. Os responsáveis não podem mais responder.';
        } else {
            $_SESSION['error'] = 'Não foi possível suspender.';
        }
        header('Location: termo_autorizacao_ver.php?id=' . $termo_id);
        exit;
    }

    if ($action === 'reativar') {
        if ($termoModel->reativar($termo_id)) {
            $_SESSION['success'] = 'Autorização reativada.';
        } else {
            $_SESSION['error'] = 'Não foi possível reativar.';
        }
        header('Location: termo_autorizacao_ver.php?id=' . $termo_id);
        exit;
    }

    if ($action === 'excluir') {
        if (!$user->isAdmin()) {
            $_SESSION['error'] = 'Apenas o administrador pode excluir uma autorização.';
        } else {
            $resultado = $termoModel->excluir($termo_id);
            if ($resultado === true) {
                $_SESSION['success'] = 'Autorização excluída.';
                header('Location: termos_autorizacao.php');
                exit;
            }
            $_SESSION['error'] = 'Não foi possível excluir.';
        }
        header('Location: termo_autorizacao_ver.php?id=' . $termo_id);
        exit;
    }

    if ($action === 'enviar_email') {
        $stats = $termoModel->enviarEmailsPendentes(
            $termo_id,
            TermoAutorizacao::urlPortalTermo($termo_id)
        );
        if ($stats['enviados'] > 0 && $stats['falhas'] === 0) {
            $_SESSION['success'] = 'E-mail enviado a ' . (int) $stats['enviados'] . ' responsável(is) pendente(s).';
        } elseif ($stats['enviados'] > 0) {
            $_SESSION['success'] = 'E-mail enviado a ' . (int) $stats['enviados']
                . ' responsável(is); ' . (int) $stats['falhas'] . ' falha(s).';
            $_SESSION['error'] = implode(' ', array_slice($stats['mensagens'], -5));
        } else {
            $_SESSION['error'] = !empty($stats['mensagens'])
                ? implode(' ', $stats['mensagens'])
                : 'Nenhum e-mail foi enviado.';
        }
        header('Location: termo_autorizacao_ver.php?id=' . $termo_id);
        exit;
    }

    if ($action === 'visibilidade') {
        $visivel = !empty($_POST['visivel']);
        if ($termoModel->setVisivelPais($termo_id, $visivel)) {
            $_SESSION['success'] = $visivel
                ? 'Autorização visível para os responsáveis.'
                : 'Autorização oculta dos responsáveis.';
        } else {
            $_SESSION['error'] = 'Não foi possível alterar a visibilidade.';
        }
        header('Location: termo_autorizacao_ver.php?id=' . $termo_id);
        exit;
    }
}

$termo = $termoModel->getById($termo_id);
$alunos = $termoModel->listAlunosDoTermo($termo_id);
$respostas = $termoModel->listRespostasDoTermo($termo_id);
$historico = $termoModel->listHistorico($termo_id);
$totais = $termoModel->totaisRespostas($termo_id);
$total_respostas = $termoModel->contarRespostas($termo_id);
$labels = TermoAutorizacao::decisoesLabels();
$ativo = TermoAutorizacao::estaAtivo($termo);
$visivel_pais = TermoAutorizacao::estaVisivelPais($termo);
$destinatarios_email = $ativo ? $termoModel->listDestinatariosPendentesParaEmail($termo_id) : [];
$qtd_email_pendentes = count($destinatarios_email);

$filtro_turma = (int) ($_GET['turma_id'] ?? 0);
$filtro_decisao = trim((string) ($_GET['filtro_decisao'] ?? $_GET['decisao'] ?? ''));
$decisoes_filtro = [
    'concordou' => 'Concordaram',
    'discordou' => 'Discordaram',
    'pendente' => 'Pendentes',
    'discordancia' => 'Com discordância',
];
if ($filtro_decisao !== '' && !isset($decisoes_filtro[$filtro_decisao])) {
    $filtro_decisao = '';
}

$turmas_filtro = [];
foreach ($termo['turmas'] ?? [] as $tu) {
    $tid = (int) ($tu['id'] ?? 0);
    if ($tid > 0) {
        $turmas_filtro[$tid] = ($tu['curso_nome'] ?? '') . ' - ' . (int) ($tu['ano_curso'] ?? 0) . 'º';
    }
}
if ($filtro_turma > 0 && !isset($turmas_filtro[$filtro_turma])) {
    $filtro_turma = 0;
}

$respostas_por_aluno = [];
foreach ($respostas as $r) {
    $respostas_por_aluno[(int) $r['aluno_id']][] = $r;
}

$status_por_aluno = [];
foreach ($alunos as $aluno) {
    $aid = (int) $aluno['id'];
    $decs = array_column($respostas_por_aluno[$aid] ?? [], 'decisao');
    $tem_sim = in_array(TermoAutorizacao::DECISAO_CONCORDOU, $decs, true);
    $tem_nao = in_array(TermoAutorizacao::DECISAO_DISCORDOU, $decs, true);
    if ($tem_sim && $tem_nao) {
        $status_por_aluno[$aid] = 'discordancia';
    } elseif ($tem_sim) {
        $status_por_aluno[$aid] = 'concordou';
    } elseif ($tem_nao) {
        $status_por_aluno[$aid] = 'discordou';
    } else {
        $status_por_aluno[$aid] = 'pendente';
    }
}

$alunos_filtrados = [];
foreach ($alunos as $aluno) {
    $aid = (int) $aluno['id'];
    if ($filtro_turma > 0 && (int) ($aluno['turma_id'] ?? 0) !== $filtro_turma) {
        continue;
    }
    if ($filtro_decisao !== '' && ($status_por_aluno[$aid] ?? '') !== $filtro_decisao) {
        continue;
    }
    $alunos_filtrados[] = $aluno;
}

$totais = [
    'total_alunos' => count($alunos_filtrados),
    'concordaram' => 0,
    'discordaram' => 0,
    'pendentes' => 0,
    'alunos_com_discordancia' => 0,
];
foreach ($alunos_filtrados as $aluno) {
    $status = $status_por_aluno[(int) $aluno['id']] ?? 'pendente';
    if ($status === 'concordou') {
        $totais['concordaram']++;
    } elseif ($status === 'discordou') {
        $totais['discordaram']++;
    } elseif ($status === 'discordancia') {
        $totais['alunos_com_discordancia']++;
    } else {
        $totais['pendentes']++;
    }
}

$page_title = 'Autorização — ' . $termo['titulo'];
require __DIR__ . '/includes/header.php';
?>

<?php if (!empty($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
    <a href="termos_autorizacao.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
    <?php if ($pode_gerenciar): ?>
    <a href="termo_autorizacao_editar.php?id=<?php echo (int) $termo['id']; ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-pencil"></i> Editar
    </a>
    <?php endif; ?>
    <?php if (TermoAutorizacao::temPdf($termo)): ?>
    <a href="termo_autorizacao_pdf.php?id=<?php echo (int) $termo['id']; ?>" class="btn btn-outline-primary btn-sm" target="_blank">
        <i class="bi bi-file-pdf"></i> Ver PDF
    </a>
    <?php endif; ?>
    <a href="termo_autorizacao_respostas_pdf.php?id=<?php echo (int) $termo_id; ?>"
       class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
        <i class="bi bi-file-earmark-pdf"></i> Gerar PDF
    </a>
    <?php if ($pode_gerenciar): ?>
    <?php if ($visivel_pais): ?>
    <form method="POST" class="d-inline" onsubmit="return confirm('Ocultar esta autorização dos responsáveis? Eles não a verão no portal.');">
        <input type="hidden" name="action" value="visibilidade">
        <input type="hidden" name="visivel" value="0">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye-slash"></i> Ocultar dos pais
        </button>
    </form>
    <?php else: ?>
    <form method="POST" class="d-inline">
        <input type="hidden" name="action" value="visibilidade">
        <input type="hidden" name="visivel" value="1">
        <button type="submit" class="btn btn-outline-success btn-sm">
            <i class="bi bi-eye"></i> Tornar visível aos pais
        </button>
    </form>
    <?php endif; ?>
    <?php if ($ativo): ?>
    <form method="POST" class="d-inline" onsubmit="return confirm('Suspender esta autorização? Os responsáveis não poderão mais responder. As respostas já dadas são mantidas.');">
        <input type="hidden" name="action" value="suspender">
        <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-pause-circle"></i> Suspender</button>
    </form>
    <?php if ($qtd_email_pendentes > 0): ?>
    <?php
    $msg_email = 'Enviar e-mail para ' . (int) $qtd_email_pendentes
        . ' responsável(is) que ainda não responderam?';
    ?>
    <form method="POST" class="d-inline" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode($msg_email), ENT_QUOTES, 'UTF-8'); ?>);">
        <input type="hidden" name="action" value="enviar_email">
        <button type="submit" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-envelope"></i> Enviar e-mail aos pendentes
            <span class="badge text-bg-primary"><?php echo (int) $qtd_email_pendentes; ?></span>
        </button>
    </form>
    <?php else: ?>
    <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Não há responsáveis pendentes com e-mail">
        <i class="bi bi-envelope"></i> Enviar e-mail aos pendentes
    </button>
    <?php endif; ?>
    <?php else: ?>
    <form method="POST" class="d-inline">
        <input type="hidden" name="action" value="reativar">
        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-play-circle"></i> Reativar</button>
    </form>
    <?php endif; ?>
    <?php if ($user->isAdmin()): ?>
    <?php
    $msg_excluir = $total_respostas > 0
        ? 'Atenção: esta autorização já possui ' . (int) $total_respostas . ' resposta(s) de responsáveis. Ao excluir, essas assinaturas serão removidas permanentemente. Deseja continuar?'
        : 'Excluir esta autorização? Esta ação não pode ser desfeita.';
    ?>
    <form method="POST" class="d-inline" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode($msg_excluir), ENT_QUOTES, 'UTF-8'); ?>);">
        <input type="hidden" name="action" value="excluir">
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Excluir</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!$visivel_pais): ?>
<div class="alert alert-secondary">
    <i class="bi bi-eye-slash"></i> Esta autorização está <strong>oculta</strong> dos responsáveis no portal.
</div>
<?php endif; ?>
<?php if (!$ativo): ?>
<div class="alert alert-warning">
    <i class="bi bi-pause-circle"></i> Esta autorização está <strong>suspensa</strong>. Os responsáveis não podem responder enquanto ela não for reativada.
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-people"></i> <?php echo htmlspecialchars($termo['titulo']); ?></h5>
        <div class="d-flex gap-1 flex-wrap">
            <?php if ($ativo): ?>
            <span class="badge bg-success">Ativa</span>
            <?php else: ?>
            <span class="badge bg-secondary">Suspensa</span>
            <?php endif; ?>
            <?php if ($visivel_pais): ?>
            <span class="badge bg-info">Visível aos pais</span>
            <?php else: ?>
            <span class="badge bg-dark">Oculta dos pais</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <strong>Data</strong>
                <div><?php echo date('d/m/Y', strtotime($termo['data_evento'])); ?>
                    <?php if (!empty($termo['hora'])): ?>
                    às <?php echo substr($termo['hora'], 0, 5); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-5">
                <strong>Turmas</strong>
                <div>
                    <?php foreach ($termo['turmas'] as $tu): ?>
                    <span class="badge bg-info me-1">
                        <?php echo htmlspecialchars(($tu['curso_nome'] ?? '') . ' - ' . (int) $tu['ano_curso'] . 'º'); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-4">
                <strong>Cadastrado por</strong>
                <div><?php echo htmlspecialchars($termo['criado_por_nome'] ?? '—'); ?>
                    <span class="text-muted small">em <?php echo date('d/m/Y H:i', strtotime($termo['created_at'])); ?></span>
                </div>
            </div>
            <div class="col-12">
                <strong>Objetivo</strong>
                <div><?php echo nl2br(htmlspecialchars($termo['objetivo'])); ?></div>
            </div>
            <?php if (TermoAutorizacao::temTexto($termo)): ?>
            <div class="col-12">
                <strong>Texto da autorização</strong>
                <div class="border rounded p-3 bg-light mt-1"><?php echo nl2br(htmlspecialchars($termo['texto_autorizacao'])); ?></div>
            </div>
            <?php elseif (TermoAutorizacao::temPdf($termo)): ?>
            <div class="col-12">
                <strong>Autorização</strong>
                <div>
                    <a href="termo_autorizacao_pdf.php?id=<?php echo (int) $termo['id']; ?>" target="_blank">
                        <i class="bi bi-file-pdf"></i>
                        <?php echo htmlspecialchars($termo['pdf_nome_original'] ?: 'Abrir PDF'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-people"></i> Decisões dos responsáveis</h5>
        <span class="small text-muted">
            <?php echo count($alunos_filtrados); ?> de <?php echo count($alunos); ?> aluno(s)
        </span>
    </div>
    <div class="card-body border-bottom">
        <form method="GET" action="termo_autorizacao_ver.php" class="row g-2 align-items-end" id="form-filtro-decisoes">
            <input type="hidden" name="id" value="<?php echo (int) $termo_id; ?>">
            <div class="col-md-5">
                <label class="form-label mb-1" for="filtro_turma">Turma</label>
                <select class="form-select form-select-sm" id="filtro_turma" name="turma_id" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($turmas_filtro as $tid => $tlabel): ?>
                    <option value="<?php echo (int) $tid; ?>" <?php echo $filtro_turma === (int) $tid ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tlabel); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label mb-1" for="filtro_decisao">Decisão</label>
                <select class="form-select form-select-sm" id="filtro_decisao" name="filtro_decisao" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($decisoes_filtro as $valor => $rotulo): ?>
                    <option value="<?php echo htmlspecialchars($valor); ?>" <?php echo $filtro_decisao === $valor ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($rotulo); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <?php if ($filtro_turma || $filtro_decisao !== ''): ?>
                <a href="termo_autorizacao_ver.php?id=<?php echo (int) $termo_id; ?>" class="btn btn-outline-secondary btn-sm">
                    Limpar
                </a>
                <?php endif; ?>
            </div>
        </form>
        <div class="d-flex flex-wrap gap-3 small mt-3">
            <span><span class="badge bg-success"><?php echo (int) $totais['concordaram']; ?></span> concordaram</span>
            <span><span class="badge bg-danger"><?php echo (int) $totais['discordaram']; ?></span> discordaram</span>
            <span><span class="badge bg-warning text-dark"><?php echo (int) $totais['pendentes']; ?></span> pendentes</span>
            <?php if ((int) $totais['alunos_com_discordancia'] > 0): ?>
            <span class="text-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong><?php echo (int) $totais['alunos_com_discordancia']; ?></strong> com discordância entre responsáveis
            </span>
            <?php endif; ?>
            <span class="text-muted"><?php echo (int) $totais['total_alunos']; ?> aluno(s)</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($alunos)): ?>
        <p class="text-muted text-center my-4">Nenhum aluno nas turmas deste termo.</p>
        <?php elseif (empty($alunos_filtrados)): ?>
        <p class="text-muted text-center my-4">Nenhum aluno corresponde aos filtros selecionados.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Aluno</th>
                        <th>Turma</th>
                        <th class="pe-3">Responsáveis / decisões</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunos_filtrados as $aluno): ?>
                    <?php
                    $aid = (int) $aluno['id'];
                    $resps_aluno = $respostas_por_aluno[$aid] ?? [];
                    $tem_discordancia = $termoModel->alunoTemDiscordancia($termo_id, $aid);
                    $alguem_respondeu = !empty($resps_aluno);
                    $por_resp = [];
                    foreach ($resps_aluno as $r) {
                        $por_resp[(int) $r['responsavel_id']] = $r;
                    }
                    ?>
                    <tr class="<?php echo $tem_discordancia ? 'table-danger' : ''; ?>">
                        <td class="ps-3">
                            <?php echo htmlspecialchars($aluno['nome']); ?>
                            <?php if ($tem_discordancia): ?>
                            <div><span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill"></i> Discordância</span></div>
                            <?php endif; ?>
                            <?php if (!empty($aluno['numero_matricula'])): ?>
                            <div class="small text-muted"><?php echo htmlspecialchars($aluno['numero_matricula']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($aluno['turma_label']); ?></td>
                        <td class="pe-3">
                            <?php if (empty($aluno['responsaveis'])): ?>
                            <span class="text-muted small">Nenhum responsável aprovado vinculado.</span>
                            <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($aluno['responsaveis'] as $rv): ?>
                                <?php $r = $por_resp[(int) $rv['id']] ?? null; ?>
                                <li class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <span>
                                        <?php echo htmlspecialchars($rv['nome']); ?>
                                        <?php if (!empty($rv['parentesco'])): ?>
                                        <span class="text-muted small">(<?php echo htmlspecialchars($rv['parentesco']); ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($r): ?>
                                    <span class="badge bg-<?php echo TermoAutorizacao::decisaoBadgeClass($r['decisao']); ?>">
                                        <?php echo htmlspecialchars($labels[$r['decisao']] ?? $r['decisao']); ?>
                                    </span>
                                    <span class="small text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($r['decidido_em'])); ?>
                                    </span>
                                    <?php if ($ativo && (int) $r['pode_alterar']): ?>
                                    <span class="badge bg-info">Pode alterar</span>
                                    <?php elseif ($ativo && $user->isAdmin()): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Permitir que este responsável responda novamente?');">
                                        <input type="hidden" name="action" value="liberar">
                                        <input type="hidden" name="resposta_id" value="<?php echo (int) $r['id']; ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm py-0">
                                            Permitir nova resposta
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php elseif (!$alguem_respondeu): ?>
                                    <span class="badge bg-warning text-dark">Pendente</span>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($historico)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Histórico de alterações</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Quando</th>
                        <th>Aluno</th>
                        <th>Responsável</th>
                        <th>De → Para</th>
                        <th class="pe-3">Liberado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $h): ?>
                    <tr>
                        <td class="ps-3 text-nowrap"><?php echo date('d/m/Y H:i', strtotime($h['registrado_em'])); ?></td>
                        <td><?php echo htmlspecialchars($h['aluno_nome']); ?></td>
                        <td><?php echo htmlspecialchars($h['responsavel_nome']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo TermoAutorizacao::decisaoBadgeClass($h['decisao_anterior']); ?>">
                                <?php echo htmlspecialchars($labels[$h['decisao_anterior']] ?? $h['decisao_anterior']); ?>
                            </span>
                            →
                            <span class="badge bg-<?php echo TermoAutorizacao::decisaoBadgeClass($h['nova_decisao']); ?>">
                                <?php echo htmlspecialchars($labels[$h['nova_decisao']] ?? $h['nova_decisao']); ?>
                            </span>
                        </td>
                        <td class="pe-3">
                            <?php echo htmlspecialchars($h['liberado_por_nome'] ?? '—'); ?>
                            <?php if (!empty($h['liberado_em'])): ?>
                            <div class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($h['liberado_em'])); ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
