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
                                    <?php if (!empty($a['nome_social'])): ?>
                                        <?php echo htmlspecialchars($a['nome_social']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($a['nome']); ?>
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
                                                <button class="dropdown-item btn-view-ficha" type="button" data-aluno='<?php echo htmlspecialchars(json_encode($a)); ?>'>
                                                    <i class="bi bi-file-text text-info"></i> Ver Ficha
                                                </button>
                                            </li>
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
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
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
                                <input type="text" class="form-control" id="modal_nome" name="nome" required maxlength="200" size="50">
                            </div>
                            <div class="mb-3">
                                <label for="modal_nome_social" class="form-label">Nome Social</label>
                                <input type="text" class="form-control form-control-sm" id="modal_nome_social" name="nome_social" maxlength="200" size="50">
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
                            <div class="mb-3">
                                <label for="modal_endereco" class="form-label">Endereço</label>
                                <textarea class="form-control" id="modal_endereco" name="endereco" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="modal_data_nascimento" class="form-label">Data de Nascimento</label>
                                        <input type="date" class="form-control form-control-sm" id="modal_data_nascimento" name="data_nascimento" style="max-width: 200px;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="modal_numero_matricula" class="form-label">Número de Matrícula</label>
                                        <input type="text" class="form-control form-control-sm" id="modal_numero_matricula" name="numero_matricula" style="max-width: 200px;">
                                    </div>
                                </div>
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
                    
                    <hr>
                    <h6 class="mb-3">Informações Adicionais</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_pessoa_referencia" class="form-label">Pessoa de Referência</label>
                                <input type="text" class="form-control" id="modal_pessoa_referencia" name="pessoa_referencia">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_telefone_pessoa_referencia" class="form-label">Telefone da Pessoa de Referência</label>
                                <input type="text" class="form-control" id="modal_telefone_pessoa_referencia" name="telefone_pessoa_referencia" 
                                       placeholder="(51) 99999-9999">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_rede_atendimento" class="form-label">Rede de Atendimento</label>
                        <textarea class="form-control" id="modal_rede_atendimento" name="rede_atendimento" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="modal_auxilio_estudantil" class="form-label">Auxílio Estudantil</label>
                                <select class="form-select" id="modal_auxilio_estudantil" name="auxilio_estudantil">
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="modal_indigena" class="form-label">Indígena</label>
                                <select class="form-select" id="modal_indigena" name="indigena">
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="modal_pei" class="form-label">Plano Educacional Individual (PEI)</label>
                                <select class="form-select" id="modal_pei" name="pei">
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_nee" class="form-label">Necessidades Educacionais Especiais (NEE)</label>
                        <textarea class="form-control" id="modal_nee" name="nee" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_profissionais_referencia" class="form-label">Profissionais de Referência na Assistência Estudantil</label>
                        <textarea class="form-control" id="modal_profissionais_referencia" name="profissionais_referencia" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_outras_observacoes" class="form-label">Outras Observações</label>
                        <textarea class="form-control" id="modal_outras_observacoes" name="outras_observacoes" rows="3"></textarea>
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

<!-- Modal para Ver Ficha do Aluno -->
<div class="modal fade" id="modalFichaAluno" tabindex="-1" aria-labelledby="modalFichaAlunoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFichaAlunoLabel">
                    <i class="bi bi-file-text"></i> Ficha do Aluno
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <div id="ficha_foto" class="mb-3"></div>
                    </div>
                    <div class="col-md-9">
                        <h4 id="ficha_nome" class="mb-3"></h4>
                        <p id="ficha_nome_social" class="text-muted mb-3"></p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3"><i class="bi bi-person-badge"></i> Dados de Identificação</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">E-mail:</th>
                                        <td id="ficha_email">-</td>
                                    </tr>
                                    <tr>
                                        <th>Telefone Celular:</th>
                                        <td id="ficha_telefone_celular">-</td>
                                    </tr>
                                    <tr>
                                        <th>Data de Nascimento:</th>
                                        <td id="ficha_data_nascimento">-</td>
                                    </tr>
                                    <tr>
                                        <th>Número de Matrícula:</th>
                                        <td id="ficha_numero_matricula">-</td>
                                    </tr>
                                    <tr>
                                        <th>Endereço:</th>
                                        <td id="ficha_endereco">-</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3"><i class="bi bi-book"></i> Informações Acadêmicas</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">Curso:</th>
                                        <td id="ficha_curso">-</td>
                                    </tr>
                                    <tr>
                                        <th>Turma:</th>
                                        <td id="ficha_turma">-</td>
                                    </tr>
                                    <tr>
                                        <th>Total de Eventos:</th>
                                        <td id="ficha_total_eventos">-</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3"><i class="bi bi-people"></i> Pessoa de Referência</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="40%">Nome:</th>
                                <td id="ficha_pessoa_referencia">-</td>
                            </tr>
                            <tr>
                                <th>Telefone:</th>
                                <td id="ficha_telefone_pessoa_referencia">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3"><i class="bi bi-diagram-3"></i> Rede de Atendimento</h6>
                        <div id="ficha_rede_atendimento" class="text-muted">-</div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="mb-3"><i class="bi bi-info-circle"></i> Informações Adicionais</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Auxílio Estudantil:</strong> <span id="ficha_auxilio_estudantil">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Indígena:</strong> <span id="ficha_indigena">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>PEI:</strong> <span id="ficha_pei">-</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <strong>Necessidades Educacionais Especiais (NEE):</strong>
                            <div id="ficha_nee" class="text-muted mt-1">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Profissionais de Referência na Assistência Estudantil:</strong>
                            <div id="ficha_profissionais_referencia" class="text-muted mt-1">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Outras Observações:</strong>
                            <div id="ficha_outras_observacoes" class="text-muted mt-1">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <?php if ($user->isAdmin() || $user->isAssistenciaEstudantil()): ?>
                <button type="button" class="btn btn-primary" id="btnEditarDaFicha">
                    <i class="bi bi-pencil"></i> Editar Aluno
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
