<?php if (!empty($alertas_login_popup)): ?>
<div class="modal fade" id="modalAlertasLogin" tabindex="-1" aria-labelledby="modalAlertasLoginLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalAlertasLoginLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Alertas recentes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    <?php echo count($alertas_login_popup) === 1 ? 'Foi detectado 1 alerta' : 'Foram detectados ' . count($alertas_login_popup) . ' alertas'; ?>
                    nas últimas 24 horas.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="alertas-col-foto">Foto</th>
                                <th class="alertas-col-nome">Nome</th>
                                <th>Curso / Turma</th>
                                <th>Regra</th>
                                <th>Período</th>
                                <th>Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alertas_login_popup as $alerta): ?>
                            <?php
                            $nome_exibicao = $alerta['aluno_nome'] ?? '';
                            $turma_label = $alerta['turma_label'] ?? '—';
                            $regra_nome = $alerta['regra_nome'] ?? '';
                            $periodo_label = $alerta['periodo_label'] ?? '';
                            ?>
                            <tr>
                                <td>
                                    <?php if (!empty($alerta['foto'])): ?>
                                        <img src="<?php echo htmlspecialchars($alerta['foto']); ?>"
                                             alt="Foto de <?php echo htmlspecialchars($nome_exibicao); ?>"
                                             class="img-thumbnail"
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center"
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="alerta-aluno-nome-popup"><?php echo htmlspecialchars($nome_exibicao); ?></td>
                                <td><?php echo htmlspecialchars($turma_label); ?></td>
                                <td><?php echo htmlspecialchars($regra_nome); ?></td>
                                <td><?php echo htmlspecialchars($periodo_label); ?></td>
                                <td><span class="badge bg-danger"><?php echo (int) ($alerta['quantidade_contada'] ?? 0); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <a href="alertas.php" class="btn btn-outline-primary">
                    <i class="bi bi-list-ul"></i> Ver todos os alertas
                </a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
