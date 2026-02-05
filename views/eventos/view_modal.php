<!-- Modal para Ver Evento -->
<div class="modal fade" id="eventoModal" tabindex="-1" aria-labelledby="eventoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventoModalLabel"><i class="bi bi-info-circle"></i> Detalhes do Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Data:</strong> <span id="obs_data"></span><br>
                    <strong>Hora:</strong> <span id="obs_hora"></span><br>
                    <strong>Aluno:</strong> <span id="obs_aluno"></span><br>
                    <strong>Tipo:</strong> <span id="obs_tipo"></span><br>
                    <strong>Registrado por:</strong> <span id="obs_registrado_por"></span>
                </div>
                <hr>
                <div>
                    <strong>Observações:</strong>
                    <p id="obs_texto" class="mt-2"></p>
                </div>
                <div id="obs_prontuario_section" style="display: none;">
                    <hr>
                    <div>
                        <strong>Prontuário:</strong>
                        <p id="obs_prontuario_texto" class="mt-2"></p>
                    </div>
                </div>
                <div id="obs_anexos_section" style="display: none;">
                    <hr>
                    <div>
                        <strong>Anexos:</strong>
                        <ul id="obs_anexos_list" class="mt-2 list-unstyled mb-0"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
