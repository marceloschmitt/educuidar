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

<script>
window.ALUNOS_FILTROS = {
    curso: <?php echo json_encode($filtro_curso ?? ''); ?>,
    turma: <?php echo json_encode($filtro_turma ?? ''); ?>,
    nome: <?php echo json_encode($filtro_nome ?? ''); ?>
};
</script>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people"></i> <?php echo $is_desistentes_page ? 'Alunos desistentes' : 'Lista de Alunos'; ?></h5>
                <?php if (($user->isAdmin() || $user->isNivel0()) && !$is_desistentes_page): ?>
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
                    <?php if (!empty($is_desistentes_page)): ?>
                    <input type="hidden" name="desistentes" value="1">
                    <?php endif; ?>
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
                        <a href="alunos.php<?php echo !empty($is_desistentes_page) ? '?desistentes=1' : ''; ?>" class="btn btn-secondary btn-sm w-100">
                            <i class="bi bi-x-circle"></i> Limpar Filtros
                        </a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (empty($alunos)): ?>
                <p class="text-muted text-center">Nenhum aluno encontrado<?php echo ($filtro_curso || $filtro_turma || $filtro_nome) ? ' com os filtros selecionados' : ' cadastrado ainda'; ?>.</p>
                <?php else: ?>
                <div class="mb-3">
                    <p class="text-muted mb-0">
                        <strong><?php echo count($alunos); ?></strong> 
                        <?php echo count($alunos) == 1 ? 'aluno encontrado' : 'alunos encontrados'; ?>
                        <?php if ($filtro_curso || $filtro_turma || $filtro_nome): ?>
                            <span class="text-muted">(com os filtros selecionados)</span>
                        <?php endif; ?>
                    </p>
                </div>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunos as $a): ?>
                            <tr class="aluno-row" data-aluno='<?php echo htmlspecialchars(json_encode($a)); ?>'>
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
    </div>
</div>

<!-- Menu contextual para ações do aluno (dinâmico) -->
<div class="dropdown-menu" id="alunoContextMenu" style="position: absolute; display: none;">
    <button class="dropdown-item btn-view-ficha" type="button">
        <i class="bi bi-file-text text-info"></i> Ver Ficha
    </button>
    <a class="dropdown-item" href="#" id="contextMenuVerEventos">
        <i class="bi bi-eye text-success"></i> Ver/Criar Eventos
    </a>
    <a class="dropdown-item" href="#" id="contextMenuProntuario">
        <i class="bi bi-file-text text-info"></i> Ver Prontuário
    </a>
    <div id="contextMenuAdminActions" style="display: none;">
        <a class="dropdown-item" href="#" id="contextMenuGerenciarTurmas">
            <i class="bi bi-collection text-info"></i> Gerenciar Turmas
        </a>
        <hr class="dropdown-divider">
        <form method="POST" action="" class="form-confirm" data-confirm="Tem certeza que deseja excluir este aluno?">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="contextMenuDeleteId">
            <button type="submit" class="dropdown-item text-danger">
                <i class="bi bi-trash"></i> Excluir
            </button>
        </form>
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
                    <input type="hidden" name="return_to" id="formReturnTo" value="<?php echo htmlspecialchars($return_to ?? ''); ?>">
                    <?php if (!empty($is_desistentes_page)): ?>
                    <input type="hidden" name="desistentes" id="formDesistentes" value="1">
                    <?php endif; ?>
                    
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
                                        <input type="text" class="form-control" id="modal_email" name="email" placeholder="E-mail ou texto livre">
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
                                       placeholder="(51) 99999-9999" maxlength="100">
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
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modal_desistente" name="desistente" value="1">
                            <label class="form-check-label" for="modal_desistente">Desistente</label>
                        </div>
                        <small class="text-muted">Alunos desistentes aparecem apenas na tela específica de desistentes.</small>
                    </div>

                    <?php if ($user->isNivel0()): ?>
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-people"></i> Dados da Assistência Estudantil</h6>
                    <h6 class="mt-2 mb-3">Geral</h6>
                    <div class="mb-3">
                        <label for="modal_identidade_genero" class="form-label">Identidade de gênero</label>
                        <textarea class="form-control" id="modal_identidade_genero" name="identidade_genero" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_grupo_familiar" class="form-label">Grupo familiar</label>
                        <textarea class="form-control" id="modal_grupo_familiar" name="grupo_familiar" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_guarda_legal" class="form-label">Guarda legal do estudante</label>
                        <textarea class="form-control" id="modal_guarda_legal" name="guarda_legal" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_escolaridade_pais_responsaveis" class="form-label">Escolaridade dos pais ou responsáveis</label>
                        <textarea class="form-control" id="modal_escolaridade_pais_responsaveis" name="escolaridade_pais_responsaveis" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_necessidade_mudanca" class="form-label">Necessidade de mudança</label>
                        <textarea class="form-control" id="modal_necessidade_mudanca" name="necessidade_mudanca" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_razao_escolha_ifrs" class="form-label">Razão para escolha do IFRS, Campus e Curso</label>
                        <textarea class="form-control" id="modal_razao_escolha_ifrs" name="razao_escolha_ifrs" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_expectativa_estudante_familia" class="form-label">Expectativa do estudante e da família</label>
                        <textarea class="form-control" id="modal_expectativa_estudante_familia" name="expectativa_estudante_familia" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_conhecimento_curso_tecnico" class="form-label">Conhecimento sobre curso técnico</label>
                        <textarea class="form-control" id="modal_conhecimento_curso_tecnico" name="conhecimento_curso_tecnico" rows="2"></textarea>
                    </div>
                    <hr>
                    <h6 class="mt-2 mb-3">Percurso Formativo</h6>
                    <div class="mb-3">
                        <label for="modal_estabelecimento_ensino_fundamental" class="form-label">Estabelecimento onde cursou o Ensino Fundamental</label>
                        <textarea class="form-control" id="modal_estabelecimento_ensino_fundamental" name="estabelecimento_ensino_fundamental" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_monitoria_atendimento_reprovacao_fundamental" class="form-label">Monitoria, atendimento especializado ou reprovação no Ensino Fundamental</label>
                        <textarea class="form-control" id="modal_monitoria_atendimento_reprovacao_fundamental" name="monitoria_atendimento_reprovacao_fundamental" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_deficiencia_necessidade_especifica" class="form-label">Deficiência ou necessidade específica</label>
                        <textarea class="form-control" id="modal_deficiencia_necessidade_especifica" name="deficiencia_necessidade_especifica" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_necessidade_adequacao_aprendizagem" class="form-label">Necessidade de adequação física ou de metodologia para aprendizagem</label>
                        <textarea class="form-control" id="modal_necessidade_adequacao_aprendizagem" name="necessidade_adequacao_aprendizagem" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_medidas_disciplinares" class="form-label">Situações envolvendo medidas disciplinares</label>
                        <textarea class="form-control" id="modal_medidas_disciplinares" name="medidas_disciplinares" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_bullying" class="form-label">Bullying, sofrido ou praticado</label>
                        <textarea class="form-control" id="modal_bullying" name="bullying" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_maiores_dificuldades" class="form-label">Maiores dificuldades</label>
                        <textarea class="form-control" id="modal_maiores_dificuldades" name="maiores_dificuldades" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_acesso_internet_casa" class="form-label">Acesso à internet em casa (qual equipamento)</label>
                        <textarea class="form-control" id="modal_acesso_internet_casa" name="acesso_internet_casa" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_local_estudo" class="form-label">Local de estudo</label>
                        <textarea class="form-control" id="modal_local_estudo" name="local_estudo" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_rotina_estudo_casa" class="form-label">Rotina de estudo em casa</label>
                        <textarea class="form-control" id="modal_rotina_estudo_casa" name="rotina_estudo_casa" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_habito_leitura" class="form-label">Hábito de leitura</label>
                        <textarea class="form-control" id="modal_habito_leitura" name="habito_leitura" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_atividades_extracurriculares" class="form-label">Atividades extra-curriculares</label>
                        <textarea class="form-control" id="modal_atividades_extracurriculares" name="atividades_extracurriculares" rows="2"></textarea>
                    </div>
                    <hr>
                    <h6 class="mt-2 mb-3">Saúde e outras redes de atenção</h6>
                    <div class="mb-3">
                        <label for="modal_rede_atendimento_familia" class="form-label">Rede de atendimento da família</label>
                        <textarea class="form-control" id="modal_rede_atendimento_familia" name="rede_atendimento_familia" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_acompanhamento_tratamento_especializado" class="form-label">Acompanhamento/tratamento especializado</label>
                        <textarea class="form-control" id="modal_acompanhamento_tratamento_especializado" name="acompanhamento_tratamento_especializado" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_alergias" class="form-label">Alergias</label>
                        <textarea class="form-control" id="modal_alergias" name="alergias" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_medicacao_uso_continuo" class="form-label">Medicação de uso contínuo</label>
                        <textarea class="form-control" id="modal_medicacao_uso_continuo" name="medicacao_uso_continuo" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_situacao_marcante_vida" class="form-label">Situação marcante na vida</label>
                        <textarea class="form-control" id="modal_situacao_marcante_vida" name="situacao_marcante_vida" rows="2"></textarea>
                    </div>
                    <hr>
                    <h6 class="mt-2 mb-3">Auxídio e direitos estudantis</h6>
                    <div class="mb-3">
                        <label for="modal_meio_transporte" class="form-label">Meio de transporte</label>
                        <textarea class="form-control" id="modal_meio_transporte" name="meio_transporte" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="modal_auxilios_direitos_estudantis" class="form-label">Auxílios</label>
                        <textarea class="form-control" id="modal_auxilios_direitos_estudantis" name="auxilios_direitos_estudantis" rows="2"></textarea>
                    </div>
                    <?php endif; ?>
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
<?php require_once __DIR__ . '/ficha_modal.php'; ?>
