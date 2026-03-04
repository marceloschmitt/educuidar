<!-- Modal para Ver Ficha do Aluno (partial: usado em alunos.php e index.php) -->
<?php $user_ficha = isset($user) ? $user : null; ?>
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
                                        <th>Turmas:</th>
                                        <td id="ficha_turmas">-</td>
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
                        <div id="ficha_rede_atendimento" class="text-muted ficha-text">-</div>
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
                            <div id="ficha_nee" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Profissionais de Referência na Assistência Estudantil:</strong>
                            <div id="ficha_profissionais_referencia" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Outras Observações:</strong>
                            <div id="ficha_outras_observacoes" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                    </div>
                </div>

                <?php if ($user_ficha && $user_ficha->isNivel0()): ?>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="mb-3"><i class="bi bi-people"></i> Dados da Assistência Estudantil</h6>
                        <h6 class="mt-2 mb-3">Geral</h6>
                        <div class="mb-3">
                            <strong>Identidade de gênero:</strong>
                            <div id="ficha_identidade_genero" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Grupo familiar:</strong>
                            <div id="ficha_grupo_familiar" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Guarda legal do estudante:</strong>
                            <div id="ficha_guarda_legal" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Escolaridade dos pais ou responsáveis:</strong>
                            <div id="ficha_escolaridade_pais_responsaveis" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Necessidade de mudança:</strong>
                            <div id="ficha_necessidade_mudanca" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Razão para escolha do IFRS, Campus e Curso:</strong>
                            <div id="ficha_razao_escolha_ifrs" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Expectativa do estudante e da família:</strong>
                            <div id="ficha_expectativa_estudante_familia" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Conhecimento sobre curso técnico:</strong>
                            <div id="ficha_conhecimento_curso_tecnico" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Observações da assistência estudantil:</strong>
                            <div id="ficha_observacoes_assistencia_estudantil" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <hr>
                        <h6 class="mt-2 mb-3">Percurso Formativo</h6>
                        <div class="mb-3">
                            <strong>Estabelecimento onde cursou o Ensino Fundamental:</strong>
                            <div id="ficha_estabelecimento_ensino_fundamental" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Monitoria, atendimento especializado ou reprovação no Ensino Fundamental:</strong>
                            <div id="ficha_monitoria_atendimento_reprovacao_fundamental" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Deficiência ou necessidade específica:</strong>
                            <div id="ficha_deficiencia_necessidade_especifica" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Necessidade de adequação física ou de metodologia para aprendizagem:</strong>
                            <div id="ficha_necessidade_adequacao_aprendizagem" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Situações envolvendo medidas disciplinares:</strong>
                            <div id="ficha_medidas_disciplinares" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Bullying, sofrido ou praticado:</strong>
                            <div id="ficha_bullying" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Maiores dificuldades:</strong>
                            <div id="ficha_maiores_dificuldades" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Acesso à internet em casa (qual equipamento):</strong>
                            <div id="ficha_acesso_internet_casa" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Local de estudo:</strong>
                            <div id="ficha_local_estudo" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Rotina de estudo em casa:</strong>
                            <div id="ficha_rotina_estudo_casa" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Hábito de leitura:</strong>
                            <div id="ficha_habito_leitura" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Atividades extra-curriculares:</strong>
                            <div id="ficha_atividades_extracurriculares" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <hr>
                        <h6 class="mt-2 mb-3">Saúde e outras redes de atenção</h6>
                        <div class="mb-3">
                            <strong>Rede de atendimento da família:</strong>
                            <div id="ficha_rede_atendimento_familia" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Acompanhamento/tratamento especializado:</strong>
                            <div id="ficha_acompanhamento_tratamento_especializado" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Alergias:</strong>
                            <div id="ficha_alergias" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Medicação de uso contínuo:</strong>
                            <div id="ficha_medicacao_uso_continuo" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Situação marcante na vida:</strong>
                            <div id="ficha_situacao_marcante_vida" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <hr>
                        <h6 class="mt-2 mb-3">Auxílio e direitos estudantis</h6>
                        <div class="mb-3">
                            <strong>Meio de transporte:</strong>
                            <div id="ficha_meio_transporte" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                        <div class="mb-3">
                            <strong>Auxílios:</strong>
                            <div id="ficha_auxilios_direitos_estudantis" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <?php if ($user_ficha && ($user_ficha->isAdmin() || $user_ficha->isNivel0())): ?>
                <button type="button" class="btn btn-primary" id="btnEditarDaFicha">
                    <i class="bi bi-pencil"></i> Editar Aluno
                </button>
                <?php endif; ?>
                <?php if ($user_ficha && $user_ficha->isNivel0()): ?>
                <a href="#" class="btn btn-info" id="btnProntuario">
                    <i class="bi bi-file-text"></i> Ver Prontuário
                </a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
