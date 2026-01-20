<?php
$page_title = 'Prontuário CAE';
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$evento = new Evento($db);
$aluno = new Aluno($db);

// Apenas usuários da CAE podem acessar
if (!$user->isAssistenciaEstudantil()) {
    header('Location: index.php');
    exit;
}

$aluno_id = $_GET['aluno_id'] ?? '';

if (empty($aluno_id)) {
    header('Location: alunos.php');
    exit;
}

$aluno_data = $aluno->getById($aluno_id);
if (!$aluno_data) {
    header('Location: alunos.php');
    exit;
}

// Buscar todos os eventos CAE do aluno, com ou sem descrição
$query = "SELECT e.id, e.aluno_id, e.turma_id, e.tipo_evento_id, 
          e.data_evento, e.hora_evento, e.observacoes, e.prontuario_cae, e.registrado_por, e.created_at,
          te.nome as tipo_evento_nome, te.cor as tipo_evento_cor,
          u.full_name as registrado_por_nome
          FROM eventos e
          LEFT JOIN tipos_eventos te ON e.tipo_evento_id = te.id
          LEFT JOIN users u ON e.registrado_por = u.id
          WHERE e.aluno_id = :aluno_id AND te.gera_prontuario_cae = 1
          ORDER BY e.data_evento ASC, e.hora_evento ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':aluno_id', $aluno_id);
$stmt->execute();
$eventos_cae = $stmt->fetchAll();


require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-text"></i> Prontuário CAE - 
                    <?php echo htmlspecialchars(!empty($aluno_data['nome_social']) ? $aluno_data['nome_social'] : ($aluno_data['nome'] ?? '')); ?>
                </h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                    <a href="alunos.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar para Alunos
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3 text-center mb-3">
                        <?php if (!empty($aluno_data['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($aluno_data['foto']); ?>" 
                                 alt="Foto de <?php echo htmlspecialchars($aluno_data['nome'] ?? ''); ?>" 
                                 class="img-thumbnail" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 150px; height: 150px;">
                                <i class="bi bi-person" style="font-size: 4rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <h4><?php echo htmlspecialchars(!empty($aluno_data['nome_social']) ? $aluno_data['nome_social'] : ($aluno_data['nome'] ?? '')); ?></h4>
                        <?php if (!empty($aluno_data['email'])): ?>
                        <p class="mb-1"><strong>E-mail:</strong> <?php echo htmlspecialchars($aluno_data['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($aluno_data['telefone_celular'])): ?>
                        <p class="mb-1"><strong>Telefone:</strong> <?php echo htmlspecialchars($aluno_data['telefone_celular']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($aluno_data['numero_matricula'])): ?>
                        <p class="mb-0"><strong>Matrícula:</strong> <?php echo htmlspecialchars($aluno_data['numero_matricula']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <hr>
                
                <h5 class="mb-3"><i class="bi bi-journal-text"></i> Histórico de Atendimentos CAE</h5>
                
                <?php if (empty($eventos_cae)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Nenhum atendimento CAE encontrado para este aluno.
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($eventos_cae as $ev): ?>
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong>
                                    <?php echo date('d/m/Y', strtotime($ev['data_evento'])); ?>
                                    <?php if (!empty($ev['hora_evento'])): ?>
                                        às <?php echo date('H:i', strtotime($ev['hora_evento'])); ?>
                                    <?php endif; ?>
                                </strong>
                                <?php if (!empty($ev['tipo_evento_nome'])): ?>
                                    <span class="badge bg-<?php echo htmlspecialchars($ev['tipo_evento_cor']); ?> ms-2">
                                        <?php echo htmlspecialchars($ev['tipo_evento_nome']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                Registrado por: <?php echo htmlspecialchars($ev['registrado_por_nome'] ?? '-'); ?>
                            </small>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($ev['observacoes'])): ?>
                            <div class="mb-3">
                                <strong>Observações Gerais:</strong>
                                <div class="text-muted mt-1">
                                    <?php echo nl2br(htmlspecialchars($ev['observacoes'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($ev['prontuario_cae'])): ?>
                            <div class="mb-2">
                                <strong>Descrição do Prontuário:</strong>
                            </div>
                            <div class="p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($ev['prontuario_cae'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
