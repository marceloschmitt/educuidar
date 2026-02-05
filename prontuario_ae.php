<?php
require_once 'config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$evento = new Evento($db);
$aluno = new Aluno($db);
$turma = new Turma($db);
$configuracao = new Configuracao($db);

// Apenas usuários logados podem acessar
if (!$user->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user_type = $user->getUserType();
$user_type_labels = [
    'administrador' => 'Administrador',
    'nivel1' => 'Professor',
    'nivel2' => 'Nível 2',
    'assistencia_estudantil' => 'Assistência Estudantil'
];
$prontuario_titulo = $user_type_labels[$user_type] ?? 'Usuário';
$page_title = 'Prontuário - ' . $prontuario_titulo;

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

// Buscar todos os eventos do prontuário do tipo de usuário corrente
$query = "SELECT e.id, e.aluno_id, e.turma_id, e.tipo_evento_id, 
          e.data_evento, e.hora_evento, e.observacoes, e.prontuario_cae, e.registrado_por, e.created_at,
          te.nome as tipo_evento_nome, te.cor as tipo_evento_cor,
          u.full_name as registrado_por_nome
          FROM eventos e
          LEFT JOIN tipos_eventos te ON e.tipo_evento_id = te.id
          LEFT JOIN users u ON e.registrado_por = u.id
          WHERE e.aluno_id = :aluno_id
            AND (
                te.prontuario_user_type = :user_type
                OR (te.prontuario_user_type IS NULL AND te.gera_prontuario_cae = 1 AND :user_type = 'assistencia_estudantil')
            )
          ORDER BY e.data_evento ASC, e.hora_evento ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':aluno_id', $aluno_id);
$stmt->bindParam(':user_type', $user_type);
$stmt->execute();
$eventos_cae = $stmt->fetchAll();
$anexos_por_evento = [];
if (!empty($eventos_cae)) {
    $evento_ids = array_column($eventos_cae, 'id');
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

$ano_corrente = $configuracao->getAnoCorrente();
$turmas_aluno = $aluno->getTurmasAluno($aluno_id);
$aluno_ficha = $aluno_data;
$aluno_ficha['todas_turmas'] = [];
foreach ($turmas_aluno as $ta) {
    $turma_completa = $turma->getById($ta['id']);
    if ($turma_completa) {
        $aluno_ficha['todas_turmas'][] = [
            'id' => $ta['id'],
            'curso_nome' => $turma_completa['curso_nome'] ?? '',
            'curso_id' => $turma_completa['curso_id'] ?? '',
            'ano_curso' => $ta['ano_curso'],
            'ano_civil' => $ta['ano_civil'],
            'is_ano_corrente' => ($ta['ano_civil'] == $ano_corrente)
        ];
    }
}
if (!empty($turmas_aluno)) {
    $turma_base = $turmas_aluno[0];
    $turma_base_completa = $turma->getById($turma_base['id']);
    if ($turma_base_completa) {
        $aluno_ficha['curso_nome'] = $turma_base_completa['curso_nome'] ?? '';
        $aluno_ficha['curso_id'] = $turma_base_completa['curso_id'] ?? '';
        $aluno_ficha['ano_curso'] = $turma_base['ano_curso'];
        $aluno_ficha['ano_civil'] = $turma_base['ano_civil'];
        $aluno_ficha['is_ano_corrente'] = ($turma_base['ano_civil'] == $ano_corrente);
    }
}
$aluno_ficha['total_eventos'] = $evento->countByAluno($aluno_id);
$aluno_ficha_json = htmlspecialchars(json_encode($aluno_ficha));


require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-3 printable-area">
            <div class="card-header d-flex justify-content-between align-items-center no-print">
                <h5 class="mb-0">
                    <i class="bi bi-file-text"></i> Prontuário <?php echo htmlspecialchars($prontuario_titulo); ?> - 
                    <?php echo htmlspecialchars(!empty($aluno_data['nome_social']) ? $aluno_data['nome_social'] : ($aluno_data['nome'] ?? '')); ?>
                </h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary btn-view-ficha" data-aluno='<?php echo $aluno_ficha_json; ?>'>
                        <i class="bi bi-file-text"></i> Ver Ficha
                    </button>
                    <?php if (!$user->isNivel2()): ?>
                    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                    <?php endif; ?>
                    <a href="registrar_evento.php?aluno_id=<?php echo htmlspecialchars($aluno_id); ?>" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar para Aluno
                    </a>
                </div>
            </div>
            <div class="card-header printable-header" style="display: none;">
                <div class="row">
                    <div class="col-md-2 text-center mb-2">
                        <?php if (!empty($aluno_data['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($aluno_data['foto']); ?>" 
                                 alt="Foto de <?php echo htmlspecialchars($aluno_data['nome'] ?? ''); ?>" 
                                 class="img-thumbnail" 
                                 style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 100px; height: 100px;">
                                <i class="bi bi-person" style="font-size: 2.5rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <h4 class="mb-2"><?php echo htmlspecialchars(!empty($aluno_data['nome_social']) ? $aluno_data['nome_social'] : ($aluno_data['nome'] ?? '')); ?></h4>
                        <?php if (!empty($aluno_data['email'])): ?>
                        <p class="mb-1"><strong>E-mail:</strong> <?php echo htmlspecialchars($aluno_data['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($aluno_data['telefone_celular'])): ?>
                        <p class="mb-1"><strong>Telefone:</strong> <?php echo htmlspecialchars($aluno_data['telefone_celular']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($aluno_data['numero_matricula'])): ?>
                        <p class="mb-1"><strong>Matrícula:</strong> <?php echo htmlspecialchars($aluno_data['numero_matricula']); ?></p>
                        <?php endif; ?>
                        <p class="mb-0"><strong>Data de impressão:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4 no-print">
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
                
            <h5 class="mb-3"><i class="bi bi-journal-text"></i> Histórico de Atendimentos - <?php echo htmlspecialchars($prontuario_titulo); ?></h5>
                
                <?php if (empty($eventos_cae)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Nenhum atendimento encontrado para este aluno.
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($eventos_cae as $ev): ?>
                    <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center printable-event-header">
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
                            <?php if (!empty($anexos_por_evento[$ev['id']])): ?>
                            <div class="mt-3">
                                <strong>Anexos:</strong>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($anexos_por_evento[$ev['id']] as $anexo): ?>
                                    <li>
                                        <a href="<?php echo htmlspecialchars($anexo['caminho']); ?>" target="_blank" rel="noopener">
                                            <?php echo htmlspecialchars($anexo['nome_original']); ?>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
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

                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="mb-3"><i class="bi bi-people"></i> Dados da Assistência Estudantil</h6>
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
                            <strong>Meio de transporte:</strong>
                            <div id="ficha_meio_transporte" class="text-muted mt-1 ficha-text">-</div>
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
                            <strong>Rede de atendimento da família:</strong>
                            <div id="ficha_rede_atendimento_familia" class="text-muted mt-1 ficha-text">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <?php if ($user->isAssistenciaEstudantil()): ?>
                <a href="alunos.php?edit=<?php echo htmlspecialchars($aluno_id); ?>&return_to=<?php echo urlencode('prontuario_ae.php?aluno_id=' . $aluno_id); ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar Aluno
                </a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
