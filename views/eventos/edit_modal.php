<?php
$aluno_id_value = $aluno_id ?? '';
$turma_corrente_id = isset($turma_corrente['id']) ? $turma_corrente['id'] : '';
?>
<!-- Modal para Editar Evento -->
<div class="modal fade" id="editEventoModal" tabindex="-1" aria-labelledby="editEventoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventoModalLabel"><i class="bi bi-pencil"></i> Editar Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_evento_id">
                    <input type="hidden" name="aluno_id" id="edit_aluno_id" value="<?php echo htmlspecialchars($aluno_id_value); ?>">
                    <input type="hidden" name="turma_id" id="edit_turma_id" value="<?php echo htmlspecialchars($turma_corrente_id); ?>">
                    
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="edit_tipo_evento_id" class="form-label">Tipo de Evento <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_tipo_evento_id" name="tipo_evento_id" required>
                                <option value="">Selecione o tipo...</option>
                                <?php foreach ($tipos_eventos as $te): ?>
                                <?php
                                $prontuario_tipo = $te['prontuario_user_type_id'] ?? '';
                                if (empty($prontuario_tipo) && !empty($te['gera_prontuario_cae'])) {
                                    $prontuario_tipo = 'assistencia_estudantil';
                                }
                                ?>
                                <option value="<?php echo $te['id']; ?>" data-prontuario-user-type-id="<?php echo htmlspecialchars($prontuario_tipo); ?>">
                                    <?php echo htmlspecialchars($te['nome']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 mb-3">
                            <label for="edit_data_evento" class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_data_evento" name="data_evento" required>
                        </div>
                        <div class="col-12 col-md-3 mb-3">
                            <label for="edit_hora_evento" class="form-label">Hora</label>
                            <input type="time" class="form-control" id="edit_hora_evento" name="hora_evento">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="edit_observacoes" name="observacoes" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Anexos</label>
                        <div id="edit_anexos_container">
                            <div class="input-group mb-2">
                                <input type="file" class="form-control" name="anexos[]">
                                <button type="button" class="btn btn-outline-danger btn-remove-anexo-input" title="Remover arquivo">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddEditAnexo">
                            <i class="bi bi-plus-circle"></i> Adicionar outro arquivo
                        </button>
                        <small class="text-muted d-block mt-1">PDF, imagens, DOC/DOCX, XLS/XLSX ou TXT (até 10MB cada).</small>
                    </div>

                    <div class="mb-3" id="edit_anexos_existentes"></div>
                    <div id="edit_anexos_removidos"></div>
                    
                    <div class="mb-3" id="edit_prontuario_cae_container" style="display: none;">
                        <label for="edit_prontuario_cae" class="form-label">
                            <i class="bi bi-file-text"></i> Prontuário (uso exclusivo)
                        </label>
                        <textarea class="form-control" id="edit_prontuario_cae" name="prontuario_cae" rows="5" 
                                  placeholder="Descrição do atendimento para o prontuário (visível apenas para o tipo de usuário definido no tipo de evento)"></textarea>
                        <small class="text-muted">Este campo é visível apenas para o tipo de usuário definido no tipo de evento.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
