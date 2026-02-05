<?php
// Process POST requests before including header (to allow redirects)
require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$evento = new Evento($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

function saveEventAttachments($db, $evento_id, $files, &$errors) {
    if (empty($evento_id) || empty($files) || !isset($files['name']) || !is_array($files['name'])) {
        return;
    }

    $upload_dir = __DIR__ . '/uploads/eventos/' . $evento_id;
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            $errors[] = 'Não foi possível criar o diretório de anexos.';
            return;
        }
    }

    $allowed_types = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'
    ];

    $max_size = 10 * 1024 * 1024;

    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Erro ao enviar o arquivo: {$name}.";
            continue;
        }
        if ($files['size'][$i] > $max_size) {
            $errors[] = "Arquivo muito grande: {$name}.";
            continue;
        }

        $tmp_name = $files['tmp_name'][$i];
        $mime_type = mime_content_type($tmp_name) ?: ($files['type'][$i] ?? '');
        if (!in_array($mime_type, $allowed_types, true)) {
            $errors[] = "Tipo de arquivo não permitido: {$name}.";
            continue;
        }

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $safe_ext = $ext ? preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '';
        $stored_name = uniqid('anexo_', true) . ($safe_ext ? '.' . strtolower($safe_ext) : '');
        $dest_path = $upload_dir . '/' . $stored_name;

        if (!move_uploaded_file($tmp_name, $dest_path)) {
            $errors[] = "Falha ao salvar o arquivo: {$name}.";
            continue;
        }

        $relative_path = 'uploads/eventos/' . $evento_id . '/' . $stored_name;
        $stmt = $db->prepare("INSERT INTO eventos_anexos (evento_id, nome_original, caminho, mime_type, tamanho) 
                              VALUES (:evento_id, :nome_original, :caminho, :mime_type, :tamanho)");
        $stmt->bindParam(':evento_id', $evento_id);
        $stmt->bindParam(':nome_original', $name);
        $stmt->bindParam(':caminho', $relative_path);
        $stmt->bindParam(':mime_type', $mime_type);
        $stmt->bindParam(':tamanho', $files['size'][$i], PDO::PARAM_INT);
        $stmt->execute();
    }
}

function deleteEventAttachments($db, $evento_id) {
    $stmt = $db->prepare("SELECT caminho FROM eventos_anexos WHERE evento_id = :evento_id");
    $stmt->bindParam(':evento_id', $evento_id);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $path = __DIR__ . '/' . ltrim($row['caminho'], '/');
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $dir = __DIR__ . '/uploads/eventos/' . $evento_id;
    if (is_dir($dir)) {
        @rmdir($dir);
    }
}

function deleteAttachmentById($db, $anexo_id) {
    $stmt = $db->prepare("SELECT id, evento_id, caminho FROM eventos_anexos WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $anexo_id);
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $path = __DIR__ . '/' . ltrim($row['caminho'], '/');
    if (is_file($path)) {
        @unlink($path);
    }

    $delete = $db->prepare("DELETE FROM eventos_anexos WHERE id = :id");
    $delete->bindParam(':id', $anexo_id);
    $delete->execute();

    return $row['evento_id'];
}

function canModifyEvent($db, $evento_id, $user) {
    if ($user->isAdmin()) {
        return true;
    }
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        return false;
    }
    if (!$user->isNivel1() && !$user->isNivel2()) {
        return false;
    }

    $stmt = $db->prepare("SELECT registrado_por, created_at FROM eventos WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $evento_id);
    $stmt->execute();
    $event = $stmt->fetch();
    if (!$event || $event['registrado_por'] != $user_id) {
        return false;
    }

    $created_at = strtotime($event['created_at'] ?? '');
    if (!$created_at) {
        return false;
    }
    return (time() - $created_at) <= 3600;
}

function getUserTypeIdBySlug($db, $slug) {
    if (empty($slug)) {
        return null;
    }
    $stmt = $db->prepare("SELECT id FROM user_types WHERE slug = :slug LIMIT 1");
    $stmt->bindParam(':slug', $slug);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row['id'] ?? null;
}

function getAssistenciaTypeId($db) {
    return getUserTypeIdBySlug($db, 'assistencia_estudantil');
}

function canUseProntuario($db, $tipo_evento_id, $user_type_id) {
    if (empty($tipo_evento_id) || empty($user_type_id)) {
        return false;
    }
    $stmt = $db->prepare("SELECT te.prontuario_user_type_id, te.gera_prontuario_cae,
                          ut_assist.id as assist_id
                          FROM tipos_eventos te
                          LEFT JOIN user_types ut_assist ON ut_assist.slug = 'assistencia_estudantil'
                          WHERE te.id = :id LIMIT 1");
    $stmt->bindParam(':id', $tipo_evento_id);
    $stmt->execute();
    $tipo = $stmt->fetch();
    if (!$tipo) {
        return false;
    }
    $prontuario_tipo_id = $tipo['prontuario_user_type_id'] ?? null;
    if (empty($prontuario_tipo_id) && !empty($tipo['gera_prontuario_cae'])) {
        $prontuario_tipo_id = $tipo['assist_id'] ?? null;
    }
    return !empty($prontuario_tipo_id) && (string)$prontuario_tipo_id === (string)$user_type_id;
}

// Only admin, nivel1, nivel2, assistencia_estudantil and napne can view all events
if (!$user->isAdmin() && !$user->isNivel1() && !$user->isNivel2()) {
    header('Location: index.php');
    exit;
}

// Get filters
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_nome = $_GET['filtro_nome'] ?? '';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$evento = new Evento($db);
$curso = new Curso($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

// Only admin, nivel1, nivel2, assistencia_estudantil and napne can view all events
if (!$user->isAdmin() && !$user->isNivel1() && !$user->isNivel2()) {
    header('Location: index.php');
    exit;
}

// Get filters
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_turma = $_GET['filtro_turma'] ?? '';
$filtro_nome = $_GET['filtro_nome'] ?? '';
$ano_corrente = $configuracao->getAnoCorrente();
$filtro_ano = $_GET['filtro_ano'] ?? $ano_corrente;
if (!is_numeric($filtro_ano)) {
    $filtro_ano = $ano_corrente;
} else {
    $filtro_ano = (int)$filtro_ano;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $evento->id = $_POST['id'] ?? '';
    $evento->aluno_id = $_POST['aluno_id'] ?? '';
    $evento->turma_id = $_POST['turma_id'] ?? '';
    $evento->tipo_evento_id = $_POST['tipo_evento_id'] ?? '';
    $evento->data_evento = $_POST['data_evento'] ?? '';
    $evento->hora_evento = $_POST['hora_evento'] ?? '';
    $evento->observacoes = $_POST['observacoes'] ?? '';
    $evento->prontuario_cae = $_POST['prontuario_cae'] ?? '';
    $current_user_type_id = $_SESSION['user_type_id'] ?? '';
    if (empty($current_user_type_id)) {
        $current_user_type_id = getUserTypeIdBySlug($db, $_SESSION['user_type'] ?? '');
    }
    if (!canUseProntuario($db, $evento->tipo_evento_id, $current_user_type_id)) {
        $evento->prontuario_cae = '';
    }
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (empty($evento->id) || empty($evento->aluno_id) || empty($evento->tipo_evento_id) || empty($evento->data_evento)) {
        $_SESSION['error'] = 'Por favor, preencha todos os campos obrigatórios!';
    } else {
        if ($user->isAdmin()) {
            // Admin pode editar qualquer evento
            if ($evento->update()) {
                $upload_errors = [];
                if (!empty($_FILES['anexos'])) {
                    saveEventAttachments($db, $evento->id, $_FILES['anexos'], $upload_errors);
                }
                if (!empty($_POST['delete_anexos']) && is_array($_POST['delete_anexos'])) {
                    foreach ($_POST['delete_anexos'] as $anexo_id) {
                        if (canModifyEvent($db, $evento->id, $user)) {
                            deleteAttachmentById($db, $anexo_id);
                        }
                    }
                }
                if (!empty($upload_errors)) {
                    $_SESSION['error'] = implode(' ', $upload_errors);
                }
                $_SESSION['success'] = 'Evento atualizado com sucesso!';
            } else {
                $_SESSION['error'] = 'Erro ao atualizar evento.';
            }
        } elseif (($user->isNivel1() || $user->isNivel2()) && $user_id) {
            // Nivel1 e Nivel2 podem editar apenas seus próprios eventos criados há menos de 1 hora
            if ($evento->update($user_id, true)) {
                $upload_errors = [];
                if (!empty($_FILES['anexos'])) {
                    saveEventAttachments($db, $evento->id, $_FILES['anexos'], $upload_errors);
                }
                if (!empty($_POST['delete_anexos']) && is_array($_POST['delete_anexos'])) {
                    foreach ($_POST['delete_anexos'] as $anexo_id) {
                        if (canModifyEvent($db, $evento->id, $user)) {
                            deleteAttachmentById($db, $anexo_id);
                        }
                    }
                }
                if (!empty($upload_errors)) {
                    $_SESSION['error'] = implode(' ', $upload_errors);
                }
                $_SESSION['success'] = 'Evento atualizado com sucesso!';
            } else {
                $_SESSION['error'] = 'Não é possível editar este evento. Você só pode editar eventos criados por você há menos de 1 hora.';
            }
        }
    }
    
    // Preserve filters in redirect
    $params = [];
    if ($filtro_curso) $params['filtro_curso'] = $filtro_curso;
    if ($filtro_turma) $params['filtro_turma'] = $filtro_turma;
    if ($filtro_nome) $params['filtro_nome'] = $filtro_nome;
    if ($filtro_ano) $params['filtro_ano'] = $filtro_ano;
    $redirect_url = 'eventos.php?' . http_build_query($params);
    header('Location: ' . $redirect_url);
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $evento->id = $_GET['delete'];
    $user_id = $_SESSION['user_id'] ?? null;
    
    if ($user->isAdmin()) {
        // Admin pode deletar qualquer evento
        if ($evento->delete()) {
            deleteEventAttachments($db, $evento->id);
            // Preserve filters in redirect
            $params = [];
            if ($filtro_curso) $params['filtro_curso'] = $filtro_curso;
            if ($filtro_turma) $params['filtro_turma'] = $filtro_turma;
            if ($filtro_nome) $params['filtro_nome'] = $filtro_nome;
            if ($filtro_ano) $params['filtro_ano'] = $filtro_ano;
            $params['success'] = 'deleted';
            $redirect_url = 'eventos.php?' . http_build_query($params);
            header('Location: ' . $redirect_url);
            exit;
        }
    } elseif (($user->isNivel1() || $user->isNivel2()) && $user_id) {
        // Nivel1, Nivel2 e Assistência Estudantil podem deletar apenas seus próprios eventos criados há menos de 1 hora
        if ($evento->delete($user_id, true)) {
            deleteEventAttachments($db, $evento->id);
            // Preserve filters in redirect
            $params = [];
            if ($filtro_curso) $params['filtro_curso'] = $filtro_curso;
            if ($filtro_turma) $params['filtro_turma'] = $filtro_turma;
            if ($filtro_nome) $params['filtro_nome'] = $filtro_nome;
            if ($filtro_ano) $params['filtro_ano'] = $filtro_ano;
            $params['success'] = 'deleted';
            $redirect_url = 'eventos.php?' . http_build_query($params);
            header('Location: ' . $redirect_url);
            exit;
        } else {
            $_SESSION['error'] = 'Não é possível excluir este evento. Você só pode excluir eventos criados por você há menos de 1 hora.';
        }
    }
}

// Nivel2 só vê eventos que ele mesmo registrou
$user_id = $_SESSION['user_id'] ?? null;
$registrado_por = ($user->isNivel2()) ? $user_id : null;
$current_user_type_id = $_SESSION['user_type_id'] ?? '';
if (empty($current_user_type_id)) {
    $current_user_type_id = getUserTypeIdBySlug($db, $_SESSION['user_type'] ?? '');
}
$assistencia_type_id = getAssistenciaTypeId($db);
$eventos = $evento->getAll($registrado_por, $filtro_ano);
$anexos_por_evento = [];
if (!empty($eventos)) {
    $evento_ids = array_column($eventos, 'id');
    $placeholders = implode(',', array_fill(0, count($evento_ids), '?'));
    $stmt = $db->prepare("SELECT id, evento_id, nome_original, caminho 
                          FROM eventos_anexos 
                          WHERE evento_id IN ($placeholders)
                          ORDER BY id ASC");
    $stmt->execute($evento_ids);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $anexos_por_evento[$row['evento_id']][] = $row;
    }
}
$cursos = $curso->getAll();
$turmas_ano_corrente_lista = $turma->getTurmasPorAnoCorrente($filtro_ano);
$stmt_anos = $db->prepare("SELECT DISTINCT ano_civil FROM turmas ORDER BY ano_civil DESC");
$stmt_anos->execute();
$anos_disponiveis = $stmt_anos->fetchAll(PDO::FETCH_COLUMN);
if (empty($anos_disponiveis)) {
    $anos_disponiveis = [$filtro_ano];
} elseif (!in_array($filtro_ano, $anos_disponiveis, true)) {
    array_unshift($anos_disponiveis, $filtro_ano);
}
$tipo_evento_model = new TipoEvento($db);
$tipos_eventos = $tipo_evento_model->getAll(true); // Apenas ativos

// Get curso info if filtered
$curso_filtrado = null;
if ($filtro_curso) {
    $curso_filtrado = $curso->getById($filtro_curso);
}

// Get turma info if filtered
$turma_filtrada = null;
if ($filtro_turma) {
    $turma_filtrada = $turma->getById($filtro_turma);
}

// Apply filters
if ($filtro_curso) {
    $eventos = array_filter($eventos, function($evt) use ($filtro_curso) {
        return !empty($evt['curso_id']) && $evt['curso_id'] == $filtro_curso;
    });
}

if ($filtro_turma) {
    $eventos = array_filter($eventos, function($evt) use ($filtro_turma) {
        return !empty($evt['turma_id']) && $evt['turma_id'] == $filtro_turma;
    });
}

if ($filtro_nome) {
    $filtro_nome_lower = mb_strtolower($filtro_nome, 'UTF-8');
    $eventos = array_filter($eventos, function($evt) use ($filtro_nome_lower) {
        $nome_lower = mb_strtolower($evt['aluno_nome'] ?? '', 'UTF-8');
        return mb_strpos($nome_lower, $filtro_nome_lower) !== false;
    });
}

// Re-index array after filtering
$eventos = array_values($eventos);

// Get success/error messages from session
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_GET['success'])) {
    $success_msg = $_GET['success'] == 'deleted' ? 'Evento excluído com sucesso!' : '';
}

if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

$page_title = 'Eventos';
require_once 'includes/header.php';
?>

<?php if (isset($success_msg)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error_msg)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Todos os Eventos</h5>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" action="" class="row g-3 mb-4">
            <div class="col-md-3">
                <label for="filtro_curso" class="form-label">Filtrar por Curso</label>
                <select class="form-select form-select-sm" id="filtro_curso" name="filtro_curso">
                    <option value="">Todos os cursos</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($filtro_curso == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtro_turma" class="form-label">Filtrar por Turma (Ano <?php echo $filtro_ano; ?>)</label>
                <select class="form-select form-select-sm" id="filtro_turma" name="filtro_turma">
                    <option value="">Todas as turmas</option>
                    <?php 
                    $turmas_filtradas = $turmas_ano_corrente_lista;
                    if ($filtro_curso) {
                        $turmas_filtradas = array_filter($turmas_ano_corrente_lista, function($t) use ($filtro_curso) {
                            return $t['curso_id'] == $filtro_curso;
                        });
                    }
                    foreach ($turmas_filtradas as $t): 
                    ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo ($filtro_turma == $t['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['curso_nome'] ?? ''); ?> - 
                        <?php echo htmlspecialchars($t['ano_curso']); ?>º Ano
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtro_ano" class="form-label">Filtrar por Ano</label>
                <select class="form-select form-select-sm" id="filtro_ano" name="filtro_ano">
                    <?php foreach ($anos_disponiveis as $ano): ?>
                    <option value="<?php echo htmlspecialchars($ano); ?>" <?php echo ((string)$filtro_ano === (string)$ano) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ano); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtro_nome" class="form-label">Filtrar por Nome do Aluno</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="filtro_nome" name="filtro_nome" 
                           value="<?php echo htmlspecialchars($filtro_nome); ?>" 
                           placeholder="Digite o nome do aluno..."
                           onkeypress="if(event.key === 'Enter') { this.form.submit(); }">
                    <button class="btn btn-outline-secondary" type="submit" title="Buscar">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <?php if ($filtro_curso || $filtro_turma || $filtro_nome || ($filtro_ano != $ano_corrente)): ?>
                <a href="eventos.php" class="btn btn-secondary btn-sm w-100">
                    <i class="bi bi-x-circle"></i> Limpar Filtros
                </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($eventos)): ?>
        <p class="text-muted text-center">Nenhum evento encontrado<?php echo ($filtro_curso || $filtro_turma || $filtro_nome) ? ' com os filtros selecionados' : ' registrado ainda'; ?>.</p>
        <?php else: ?>
        <?php if ($curso_filtrado && !$turma_filtrada): ?>
        <div class="alert alert-primary mb-3" role="alert">
            <h5 class="alert-heading mb-0">
                <i class="bi bi-book"></i> 
                Curso: <strong><?php echo htmlspecialchars($curso_filtrado['nome'] ?? ''); ?></strong>
            </h5>
        </div>
        <?php endif; ?>
        <?php if ($turma_filtrada): ?>
        <div class="alert alert-info mb-3" role="alert">
            <h5 class="alert-heading mb-0">
                <i class="bi bi-collection"></i> 
                Turma: <strong><?php echo htmlspecialchars($turma_filtrada['curso_nome'] ?? ''); ?></strong> - 
                <?php echo htmlspecialchars($turma_filtrada['ano_curso'] ?? ''); ?>º Ano - 
                Ano <?php echo htmlspecialchars($turma_filtrada['ano_civil'] ?? ''); ?>
            </h5>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Aluno</th>
                                <th>Tipo</th>
                                <th>Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos as $evt): ?>
                            <?php
                            // Verificar permissões para editar/excluir
                            $user_id = $_SESSION['user_id'] ?? null;
                            $can_edit = false;
                            $can_delete = false;
                            
                            if ($user->isAdmin()) {
                                $can_edit = true;
                                $can_delete = true;
                            } elseif (($user->isNivel1() || $user->isNivel2()) && $user_id) {
                                if ($evt['registrado_por'] == $user_id) {
                                    $created_at = strtotime($evt['created_at'] ?? '');
                                    $now = time();
                                    $diff_seconds = $now - $created_at;
                                    $can_edit = ($diff_seconds <= 3600); // 1 hora
                                    $can_delete = ($diff_seconds <= 3600);
                                }
                            }
                            ?>
                            <tr class="evento-row" data-evento='<?php echo htmlspecialchars(json_encode([
                                'id' => $evt['id'],
                                'data' => date('d/m/Y', strtotime($evt['data_evento'])),
                                'hora' => $evt['hora_evento'] ? date('H:i', strtotime($evt['hora_evento'])) : '-',
                                'aluno' => $evt['aluno_nome'] ?? 'N/A',
                                'tipo' => $evt['tipo_evento_nome'] ?? 'N/A',
                                'registrado_por' => $evt['registrado_por_nome'] ?? '-',
                                'observacoes' => $evt['observacoes'] ?? '',
                                'prontuario_cae' => (function() use ($evt, $current_user_type_id, $assistencia_type_id) {
                                    $prontuario_tipo_id = $evt['tipo_evento_prontuario_user_type_id'] ?? '';
                                    if (empty($prontuario_tipo_id) && !empty($evt['tipo_evento_gera_prontuario'])) {
                                        $prontuario_tipo_id = $assistencia_type_id;
                                    }
                                    return (!empty($prontuario_tipo_id) && (string)$current_user_type_id === (string)$prontuario_tipo_id) ? ($evt['prontuario_cae'] ?? '') : '';
                                })(),
                                'prontuario_user_type_id' => (function() use ($evt, $assistencia_type_id) {
                                    $prontuario_tipo_id = $evt['tipo_evento_prontuario_user_type_id'] ?? '';
                                    if (empty($prontuario_tipo_id) && !empty($evt['tipo_evento_gera_prontuario'])) {
                                        $prontuario_tipo_id = $assistencia_type_id;
                                    }
                                    return $prontuario_tipo_id;
                                })(),
                                'aluno_id' => $evt['aluno_id'] ?? '',
                                'turma_id' => $evt['turma_id'] ?? '',
                                'tipo_evento_id' => $evt['tipo_evento_id'] ?? '',
                                'data_evento' => $evt['data_evento'] ?? '',
                                'hora_evento' => $evt['hora_evento'] ?? '',
                                'registrado_por_id' => $evt['registrado_por'] ?? '',
                                'created_at' => $evt['created_at'] ?? '',
                                'can_edit' => $can_edit,
                                'can_delete' => $can_delete,
                                'anexos' => $anexos_por_evento[$evt['id']] ?? [],
                                'can_view_anexos' => (function() use ($evt, $current_user_type_id, $assistencia_type_id) {
                                    $prontuario_tipo_id = $evt['tipo_evento_prontuario_user_type_id'] ?? '';
                                    if (empty($prontuario_tipo_id) && !empty($evt['tipo_evento_gera_prontuario'])) {
                                        $prontuario_tipo_id = $assistencia_type_id;
                                    }
                                    return empty($prontuario_tipo_id) || ((string)$current_user_type_id === (string)$prontuario_tipo_id);
                                })(),
                                'can_view_prontuario' => (function() use ($evt, $current_user_type_id, $assistencia_type_id) {
                                    $prontuario_tipo_id = $evt['tipo_evento_prontuario_user_type_id'] ?? '';
                                    if (empty($prontuario_tipo_id) && !empty($evt['tipo_evento_gera_prontuario'])) {
                                        $prontuario_tipo_id = $assistencia_type_id;
                                    }
                                    return (!empty($prontuario_tipo_id) && (string)$current_user_type_id === (string)$prontuario_tipo_id);
                                })()
                            ])); ?>'>
                                <td><?php echo date('d/m/Y', strtotime($evt['data_evento'])); ?></td>
                                <td><?php echo $evt['hora_evento'] ? date('H:i', strtotime($evt['hora_evento'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($evt['aluno_nome'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($evt['tipo_evento_nome'])): ?>
                                        <span class="badge bg-<?php echo htmlspecialchars($evt['tipo_evento_cor'] ?? 'secondary'); ?>">
                                            <?php echo htmlspecialchars($evt['tipo_evento_nome']); ?>
                                        </span>
                                        <?php if (!empty($anexos_por_evento[$evt['id']])): ?>
                                            <i class="bi bi-paperclip ms-2 text-muted" title="Possui anexos"></i>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($evt['registrado_por_nome'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
            </table>
            <!-- Espaço no final para permitir que o menu contextual apareça completamente -->
            <div style="height: 150px;"></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Menu contextual para ações do evento (dinâmico) -->
<div class="dropdown-menu" id="eventoContextMenu" style="position: absolute; display: none;">
    <button class="dropdown-item" type="button" id="contextMenuVerEvento">
        <i class="bi bi-info-circle text-info"></i> Ver Evento
    </button>
    <div id="contextMenuEventoActions" style="display: none;">
        <hr class="dropdown-divider">
        <button class="dropdown-item" type="button" id="contextMenuEditarEvento">
            <i class="bi bi-pencil text-primary"></i> Editar
        </button>
        <hr class="dropdown-divider">
        <a class="dropdown-item text-danger" href="#" id="contextMenuExcluirEvento">
            <i class="bi bi-trash"></i> Excluir
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/views/eventos/view_modal.php'; ?>

<?php require_once __DIR__ . '/views/eventos/edit_modal.php'; ?>

<?php require_once 'includes/footer.php'; ?>

