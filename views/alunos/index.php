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
                <?php if ($user->isAdmin()): ?>
                <div>
                    <a href="importar_alunos.php" class="btn btn-success btn-sm me-2">
                        <i class="bi bi-upload"></i> Importar CSV
                    </a>
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
                                <td><?php echo htmlspecialchars($a['nome']); ?></td>
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
                                            <?php if ($user->isAdmin()): ?>
                                            <li>
                                                <button class="dropdown-item btn-edit-aluno" type="button" data-aluno='<?php echo htmlspecialchars(json_encode($a)); ?>'>
                                                    <i class="bi bi-pencil text-primary"></i> Editar
                                                </button>
                                            </li>
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
            <form method="POST" action="" id="formAluno">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="formId" value="">
                    
                    <div class="mb-3">
                        <label for="modal_nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_nome" name="nome" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="modal_email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_telefone_celular" class="form-label">Telefone Celular</label>
                        <input type="text" class="form-control" id="modal_telefone_celular" name="telefone_celular" 
                               placeholder="(51) 99999-9999">
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

