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
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people"></i> Lista de Alunos</h5>
                <?php if ($user->isAdmin() || $user->isAssistenciaEstudantil()): ?>
                <div>
                    <?php if ($user->isAdmin()): ?>
                    <a href="importar_alunos.php" class="btn btn-success btn-sm me-2">
                        <i class="bi bi-upload"></i> Importar CSV
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAluno" id="btnNovoAluno">
                        <i class="bi bi-plus-circle"></i> Novo Aluno
                    </button>
                </div>
                <?php endif; ?>
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
                        <label for="filtro_turma" class="form-label">Filtrar por Turma (Ano <?php echo $ano_corrente; ?>)</label>
                        <select class="form-select form-select-sm" id="filtro_turma" name="filtro_turma">
                            <option value="">Todas as turmas</option>
                            <?php foreach ($turmas_filtradas as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($filtro_turma == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['curso_nome'] ?? ''); ?> - 
                                <?php echo htmlspecialchars($t['ano_curso']); ?>º Ano
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filtro_nome" class="form-label">Filtrar por Nome</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="filtro_nome" name="filtro_nome" 
                                   value="<?php echo htmlspecialchars($filtro_nome); ?>" 
                                   placeholder="Digite o nome do aluno...">
                            <button class="btn btn-outline-secondary" type="submit" title="Buscar">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <?php if ($filtro_curso || $filtro_turma || $filtro_nome): ?>
                        <a href="alunos.php" class="btn btn-secondary btn-sm w-100">
                            <i class="bi bi-x-circle"></i> Limpar Filtros
                        </a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (empty($alunos)): ?>
                <p class="text-muted text-center">Nenhum aluno encontrado<?php echo ($filtro_curso || $filtro_turma || $filtro_nome) ? ' com os filtros selecionados' : ' cadastrado ainda'; ?>.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>Curso</th>
                                <th>Turma</th>
                                <th>Eventos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunos as $a): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($a['foto'])): ?>
                                        <img src="<?php echo htmlspecialchars($a['foto']); ?>" 
                                             alt="Foto de <?php echo htmlspecialchars($a['nome']); ?>" 
                                             class="img-thumbnail" 
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($a['nome']); ?>
                                    <?php if (!empty($a['nome_social'])): ?>
                                        <br><small class="text-muted">(<?php echo htmlspecialchars($a['nome_social']); ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($a['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($a['telefone_celular'] ?? '-'); ?></td>
                                <td>
                                    <?php if (!empty($a['curso_nome'])): ?>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($a['curso_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['turma_info'])): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($a['ano_curso']); ?>º Ano - 
                                            <?php echo htmlspecialchars($a['ano_civil']); ?>
                                            <?php if (!empty($a['is_ano_corrente'])): ?>
                                                <span class="badge bg-success ms-1">Corrente</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $total_eventos = $a['total_eventos'] ?? 0;
                                    if ($total_eventos == 0): 
                                    ?>
                                        <span class="text-muted">-</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <?php echo htmlspecialchars($total_eventos); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $a['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $a['id']; ?>">
                                            <li>
                                                <a class="dropdown-item" href="registrar_evento.php?aluno_id=<?php echo $a['id']; ?>">
                                                    <i class="bi bi-eye text-success"></i> Ver/Criar Eventos
                                                </a>
                                            </li>
                                            <?php if ($user->isAdmin() || $user->isAssistenciaEstudantil()): ?>
                                            <li>
                                                <button class="dropdown-item btn-edit-aluno" type="button" data-aluno='<?php echo htmlspecialchars(json_encode($a)); ?>'>
                                                    <i class="bi bi-pencil text-primary"></i> Editar
                                                </button>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($user->isAdmin()): ?>
                                            <li>
                                                <a class="dropdown-item" href="aluno_turmas.php?id=<?php echo $a['id']; ?>">
                                                    <i class="bi bi-collection text-info"></i> Gerenciar Turmas
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="" class="form-confirm" data-confirm="Tem certeza que deseja excluir este aluno?">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash"></i> Excluir
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
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

<!-- Modal para Criar/Editar Aluno -->
<div class="modal fade" id="modalAluno" tabindex="-1" aria-labelledby="modalAlunoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAlunoLabel">
                    <i class="bi bi-plus-circle"></i> <span id="modalTitle">Novo Aluno</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="formAluno" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="formId" value="">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="modal_nome" class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_nome" name="nome" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="modal_foto" class="form-label">Foto</label>
                                <input type="file" class="form-control" id="modal_foto" name="foto" accept="image/jpeg,image/jpg,image/png,image/gif">
                                <small class="text-muted">Máximo 5MB (JPG, PNG, GIF)</small>
                                <div id="foto_preview" class="mt-2"></div>
                                <div id="foto_atual" class="mt-2"></div>
                                <div id="remover_foto_container" class="mt-2" style="display: none;">
                                    <input type="checkbox" id="remover_foto" name="remover_foto" value="1">
                                    <label for="remover_foto" class="form-label">Remover foto atual</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_nome_social" class="form-label">Nome Social</label>
                        <input type="text" class="form-control" id="modal_nome_social" name="nome_social">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="modal_email" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_telefone_celular" class="form-label">Telefone Celular</label>
                                <input type="text" class="form-control" id="modal_telefone_celular" name="telefone_celular" 
                                       placeholder="(51) 99999-9999">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_data_nascimento" class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" id="modal_data_nascimento" name="data_nascimento">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_numero_matricula" class="form-label">Número de Matrícula</label>
                                <input type="text" class="form-control" id="modal_numero_matricula" name="numero_matricula">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_endereco" class="form-label">Endereço</label>
                        <textarea class="form-control" id="modal_endereco" name="endereco" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <span id="modalSubmitText">Criar</span> Aluno
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Garantir que os estilos sejam aplicados ao modal de aluno (mesmo esquema dos modais de usuário)
function applyModalStylesAluno(modal) {
    if (!modal) return;
    
    var modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        modalBody.style.setProperty('overflow-y', 'auto', 'important');
        modalBody.style.setProperty('max-height', 'calc(90vh - 140px)', 'important');
        modalBody.style.setProperty('flex', '1 1 auto', 'important');
        modalBody.style.setProperty('min-height', '0', 'important');
        modalBody.style.setProperty('font-size', '0.75rem', 'important');
    }
    
    var modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.style.setProperty('display', 'flex', 'important');
        modalContent.style.setProperty('flex-direction', 'column', 'important');
        modalContent.style.setProperty('max-height', '90vh', 'important');
        modalContent.style.setProperty('overflow', 'hidden', 'important');
    }
    
    var form = modal.querySelector('form');
    if (form) {
        form.style.setProperty('display', 'flex', 'important');
        form.style.setProperty('flex-direction', 'column', 'important');
        form.style.setProperty('flex', '1 1 auto', 'important');
        form.style.setProperty('min-height', '0', 'important');
        form.style.setProperty('overflow', 'hidden', 'important');
    }
    
    // Aplicar fontes menores
    var labels = modal.querySelectorAll('.form-label');
    labels.forEach(function(label) {
        label.style.setProperty('font-size', '0.75rem', 'important');
        label.style.setProperty('margin-bottom', '0.25rem', 'important');
        label.style.setProperty('font-weight', '500', 'important');
    });
    
    var inputs = modal.querySelectorAll('.form-control, .form-select');
    inputs.forEach(function(input) {
        input.style.setProperty('font-size', '0.75rem', 'important');
        input.style.setProperty('padding', '0.3rem 0.6rem', 'important');
        input.style.setProperty('line-height', '1.3', 'important');
        input.style.setProperty('height', 'calc(1.3em + 0.6rem + 2px)', 'important');
    });
    
    var smalls = modal.querySelectorAll('small');
    smalls.forEach(function(small) {
        small.style.setProperty('font-size', '0.7rem', 'important');
    });
    
    var mb3s = modal.querySelectorAll('.mb-3');
    mb3s.forEach(function(mb3) {
        mb3.style.setProperty('margin-bottom', '0.5rem', 'important');
    });
}

// Aplicar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    var modalAluno = document.getElementById('modalAluno');
    
    if (modalAluno) {
        modalAluno.addEventListener('show.bs.modal', function() {
            applyModalStylesAluno(this);
        });
        modalAluno.addEventListener('shown.bs.modal', function() {
            applyModalStylesAluno(this);
        });
    }
    
    // Também interceptar a função editAluno se existir
    if (typeof editAluno === 'function') {
        var originalEditAluno = editAluno;
        window.editAluno = function(aluno) {
            originalEditAluno(aluno);
            // Aplicar estilos após um pequeno delay para garantir que o modal foi aberto
            setTimeout(function() {
                var modalElement = document.getElementById('modalAluno');
                if (modalElement) {
                    applyModalStylesAluno(modalElement);
                }
            }, 100);
        };
    }
});
</script>

