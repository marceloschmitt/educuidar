<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$tipo_evento = new TipoEvento($db);
$stmt_user_types = $db->prepare("SELECT id, nome FROM user_types ORDER BY nome ASC");
$stmt_user_types->execute();
$user_types = $stmt_user_types->fetchAll();
$user_types_by_id = [];
foreach ($user_types as $ut) {
    $user_types_by_id[$ut['id']] = $ut;
}

// Only admin can manage tipos de eventos
if (!$user->isLoggedIn() || !$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

// Process POST requests before including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create') {
            $tipo_evento->nome = $_POST['nome'] ?? '';
            $tipo_evento->cor = $_POST['cor'] ?? 'secondary';
            $tipo_evento->prontuario_user_type_id = $_POST['prontuario_user_type_id'] ?? null;
            $tipo_evento->gera_prontuario = !empty($tipo_evento->prontuario_user_type_id) ? 1 : 0;
            $tipo_evento->ativo = isset($_POST['ativo']) ? 1 : 0;
            $tipo_evento->visivel_responsaveis = isset($_POST['visivel_responsaveis']) ? 1 : 0;
            $tipo_evento->observacoes_visiveis_responsaveis = isset($_POST['observacoes_visiveis_responsaveis']) ? 1 : 0;
            $tipo_evento->notificar_email_responsaveis = isset($_POST['notificar_email_responsaveis']) ? 1 : 0;
            
            if (empty($tipo_evento->nome)) {
                $_SESSION['error'] = 'Por favor, preencha o nome do tipo de evento!';
            } else {
                if ($tipo_evento->create()) {
                    header('Location: tipos_eventos.php?success=created');
                    exit;
                } else {
                    $_SESSION['error'] = 'Erro ao criar tipo de evento.';
                }
            }
        } elseif ($_POST['action'] == 'update' && isset($_POST['id'])) {
            $tipo_evento->id = $_POST['id'];
            $tipo_evento->nome = $_POST['nome'] ?? '';
            $tipo_evento->cor = $_POST['cor'] ?? 'secondary';
            $tipo_evento->prontuario_user_type_id = $_POST['prontuario_user_type_id'] ?? null;
            $tipo_evento->gera_prontuario = !empty($tipo_evento->prontuario_user_type_id) ? 1 : 0;
            $tipo_evento->ativo = isset($_POST['ativo']) ? 1 : 0;
            $tipo_evento->visivel_responsaveis = isset($_POST['visivel_responsaveis']) ? 1 : 0;
            $tipo_evento->observacoes_visiveis_responsaveis = isset($_POST['observacoes_visiveis_responsaveis']) ? 1 : 0;
            $tipo_evento->notificar_email_responsaveis = isset($_POST['notificar_email_responsaveis']) ? 1 : 0;
            
            if (empty($tipo_evento->nome)) {
                $_SESSION['error'] = 'Por favor, preencha o nome do tipo de evento!';
            } else {
                if ($tipo_evento->update()) {
                    header('Location: tipos_eventos.php?success=updated');
                    exit;
                } else {
                    $_SESSION['error'] = 'Erro ao atualizar tipo de evento.';
                }
            }
        } elseif ($_POST['action'] == 'delete' && isset($_POST['id'])) {
            $tipo_evento->id = (int) $_POST['id'];
            $total_eventos = $tipo_evento->getTotalEventos();
            $confirmados = (int) ($_POST['confirmar_eventos'] ?? -1);

            if ($total_eventos === 0) {
                if ($tipo_evento->delete()) {
                    header('Location: tipos_eventos.php?success=deleted');
                    exit;
                }
                $_SESSION['error'] = 'Erro ao excluir tipo de evento.';
            } elseif ($confirmados !== $total_eventos) {
                $_SESSION['error'] = "A quantidade de eventos deste tipo mudou (agora {$total_eventos}). Confira e confirme a exclusão novamente.";
            } else {
                $configuracao = new Configuracao($db);
                $era_tipo_sigaa = $configuracao->getApiSigaaTipoEventoFaltaId() === (int) $tipo_evento->id;
                $res = $tipo_evento->deleteComEventos();

                if (!$res['ok']) {
                    $_SESSION['error'] = 'Erro ao excluir o tipo e seus eventos. Nada foi apagado.';
                } else {
                    foreach ($res['anexos'] as $caminho) {
                        $path = __DIR__ . '/' . ltrim((string) $caminho, '/');
                        if (is_file($path)) {
                            @unlink($path);
                        }
                        $dir = dirname($path);
                        if (is_dir($dir) && count(scandir($dir)) === 2) {
                            @rmdir($dir);
                        }
                    }
                    foreach ($res['aluno_ids'] as $aluno_id) {
                        processarAlertasAluno($db, $aluno_id);
                    }
                    if ($era_tipo_sigaa) {
                        $configuracao->setApiSigaaTipoEventoFaltaId(null);
                    }

                    $msg = "Tipo de evento excluído junto com {$res['eventos']} evento(s).";
                    if ($era_tipo_sigaa) {
                        $msg .= ' Ele era o tipo das faltas do SIGAA: escolha outro em Configurações > API SIGAA, senão a coleta não importa faltas.';
                    }
                    $_SESSION['success_detail'] = $msg;
                    header('Location: tipos_eventos.php?success=deleted');
                    exit;
                }
            }
        }
    }
}

$page_title = 'Tipos de Eventos';
require_once 'includes/header.php';

$success = '';
$error = '';

// Handle success messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'created') {
        $success = 'Tipo de evento criado com sucesso!';
    } elseif ($_GET['success'] == 'updated') {
        $success = 'Tipo de evento atualizado com sucesso!';
    } elseif ($_GET['success'] == 'deleted') {
        $success = $_SESSION['success_detail'] ?? 'Tipo de evento excluído com sucesso!';
        unset($_SESSION['success_detail']);
    }
}

// Handle error messages from session
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get tipo for editing if requested
$tipo_edit = null;
if (isset($_GET['edit'])) {
    $tipo_edit = $tipo_evento->getById($_GET['edit']);
    if (!$tipo_edit) {
        $error = 'Tipo de evento não encontrado!';
    }
}

$tipos = $tipo_evento->getAll();
$tipo_sigaa_id = (new Configuracao($db))->getApiSigaaTipoEventoFaltaId();
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> <?php echo $tipo_edit ? 'Editar' : 'Novo'; ?> Tipo de Evento</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $tipo_edit ? 'update' : 'create'; ?>">
                    <?php if ($tipo_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $tipo_edit['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($tipo_edit['nome'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cor" class="form-label">Cor do Badge</label>
                        <select class="form-select" id="cor" name="cor">
                            <option value="primary" <?php echo ($tipo_edit['cor'] ?? '') == 'primary' ? 'selected' : ''; ?>>Azul (Primary)</option>
                            <option value="success" <?php echo ($tipo_edit['cor'] ?? '') == 'success' ? 'selected' : ''; ?>>Verde (Success)</option>
                            <option value="warning" <?php echo ($tipo_edit['cor'] ?? '') == 'warning' ? 'selected' : ''; ?>>Amarelo (Warning)</option>
                            <option value="danger" <?php echo ($tipo_edit['cor'] ?? '') == 'danger' ? 'selected' : ''; ?>>Vermelho (Danger)</option>
                            <option value="info" <?php echo ($tipo_edit['cor'] ?? '') == 'info' ? 'selected' : ''; ?>>Ciano (Info)</option>
                            <option value="secondary" <?php echo ($tipo_edit['cor'] ?? '') == 'secondary' ? 'selected' : ''; ?>>Cinza (Secondary)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" 
                                   <?php echo (!isset($tipo_edit) || $tipo_edit['ativo']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">
                                Visível para usuários
                            </label>
                            <small class="text-muted d-block">
                                Se desmarcado, o tipo não aparece na seleção ao registrar eventos
                                (continua disponível para alertas e registro automático).
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="visivel_responsaveis" name="visivel_responsaveis" value="1"
                                   <?php echo (!empty($tipo_edit['visivel_responsaveis'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="visivel_responsaveis">
                                Visível para responsáveis
                            </label>
                            <small class="text-muted d-block">
                                Se marcado, o responsável vê data, hora e este tipo no portal.
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="observacoes_visiveis_responsaveis" name="observacoes_visiveis_responsaveis" value="1"
                                   <?php echo (!empty($tipo_edit['observacoes_visiveis_responsaveis'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="observacoes_visiveis_responsaveis">
                                Observações visíveis para responsáveis
                            </label>
                            <small class="text-muted d-block">
                                Ex.: disciplina da falta. Só tem efeito se o tipo também estiver visível para responsáveis.
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notificar_email_responsaveis" name="notificar_email_responsaveis" value="1"
                                   <?php echo (!empty($tipo_edit['notificar_email_responsaveis'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notificar_email_responsaveis">
                                Incluir no resumo diário por e-mail
                            </label>
                            <small class="text-muted d-block">
                                Eventos ocorridos no dia entram no resumo diário aos responsáveis aprovados
                                do aluno e aos coordenadores do curso (horários em Configuração de e-mail).
                            </small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <?php
                        $prontuario_user_type_id = $tipo_edit['prontuario_user_type_id'] ?? '';
                        if (empty($prontuario_user_type_id) && !empty($tipo_edit['gera_prontuario'])) {
                            $prontuario_user_type_id = '';
                        }
                        ?>
                        <label for="prontuario_user_type_id" class="form-label">Prontuário exclusivo de</label>
                        <select class="form-select" id="prontuario_user_type_id" name="prontuario_user_type_id">
                            <option value="">Não gera prontuário</option>
                            <?php foreach ($user_types as $ut): ?>
                            <option value="<?php echo $ut['id']; ?>" <?php echo ((string)$prontuario_user_type_id === (string)$ut['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ut['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block">Define quem pode visualizar o prontuário deste tipo de evento.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> <?php echo $tipo_edit ? 'Atualizar' : 'Criar'; ?> Tipo
                    </button>
                    <?php if ($tipo_edit): ?>
                    <a href="tipos_eventos.php" class="btn btn-secondary w-100 mt-2">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tags"></i> Lista de Tipos de Eventos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($tipos)): ?>
                <p class="text-muted text-center">Nenhum tipo de evento cadastrado ainda.</p>
                <?php else: ?>
                <p class="text-muted small mb-2">
                    Visibilidade: <strong>usuários / responsáveis / observações / e-mail</strong>
                    — <strong>S</strong> = sim, <strong>N</strong> = não.
                </p>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width: 38%;">Nome</th>
                                <th>Prontuário</th>
                                <th class="text-nowrap" title="Usuários / Responsáveis / Observações / E-mail">Visibilidade</th>
                                <th style="width: 5.5rem;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos as $t): ?>
                            <?php
                            $vis_u = !empty($t['ativo']) ? 'S' : 'N';
                            $vis_r = !empty($t['visivel_responsaveis']) ? 'S' : 'N';
                            $vis_o = !empty($t['observacoes_visiveis_responsaveis']) ? 'S' : 'N';
                            $vis_e = !empty($t['notificar_email_responsaveis']) ? 'S' : 'N';
                            $vis_legenda = $vis_u . '/' . $vis_r . '/' . $vis_o . '/' . $vis_e;
                            $vis_title = 'Ususuários: ' . ($vis_u === 'S' ? 'sim' : 'não')
                                . ' · Responsáveis: ' . ($vis_r === 'S' ? 'sim' : 'não')
                                . ' · Observações: ' . ($vis_o === 'S' ? 'sim' : 'não')
                                . ' · E-mail: ' . ($vis_e === 'S' ? 'sim' : 'não');
                            ?>
                            <tr <?php echo (!$t['ativo']) ? 'class="table-secondary"' : ''; ?>>
                                <td>
                                    <span class="badge bg-<?php echo htmlspecialchars($t['cor']); ?> text-wrap text-start"
                                          style="white-space: normal; max-width: 100%; display: inline-block; line-height: 1.3;">
                                        <?php echo htmlspecialchars($t['nome']); ?>
                                    </span>
                                    <?php if ((int) $t['id'] === $tipo_sigaa_id): ?>
                                    <span class="badge bg-dark" title="Tipo usado nas faltas lidas do SIGAA (Configurações > API SIGAA)">SIGAA</span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['total_eventos'])): ?>
                                    <small class="text-muted d-block"><?php echo (int) $t['total_eventos']; ?> evento(s)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $prontuario_tipo_nome = $t['prontuario_user_type_nome'] ?? '';
                                    ?>
                                    <?php if (!empty($prontuario_tipo_nome)): ?>
                                        <span class="badge bg-info text-wrap text-start"
                                              style="white-space: normal;"><?php echo htmlspecialchars($prontuario_tipo_nome); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-monospace" title="<?php echo htmlspecialchars($vis_title); ?>">
                                        <?php echo htmlspecialchars($vis_legenda); ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <a href="tipos_eventos.php?edit=<?php echo $t['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php
                                    $total_ev = (int) ($t['total_eventos'] ?? 0);
                                    if ($total_ev > 0) {
                                        $msg_confirm = "ATENÇÃO: o tipo \"{$t['nome']}\" possui {$total_ev} evento(s) registrado(s).\n\n"
                                            . "Excluir o tipo APAGA DEFINITIVAMENTE todos esses eventos, com anexos e histórico de e-mails enviados. "
                                            . "Autorizações ligadas a eles perdem o vínculo e os alertas dos alunos são recalculados.";
                                        if ((int) $t['id'] === $tipo_sigaa_id) {
                                            $msg_confirm .= "\n\nEste é o tipo das faltas do SIGAA: a coleta deixará de importar faltas até você escolher outro tipo.";
                                        }
                                        $msg_confirm .= "\n\nEsta ação não pode ser desfeita. Continuar?";
                                    } else {
                                        $msg_confirm = 'Tem certeza que deseja excluir este tipo de evento?';
                                    }
                                    ?>
                                    <form method="POST" action="" style="display: inline;"
                                          class="form-confirm" data-confirm="<?php echo htmlspecialchars($msg_confirm); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                                        <input type="hidden" name="confirmar_eventos" value="<?php echo $total_ev; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                title="<?php echo $total_ev > 0 ? 'Excluir tipo e seus ' . $total_ev . ' evento(s)' : 'Excluir tipo'; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

