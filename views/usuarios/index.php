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

<?php if (!empty($resultado_professores)): ?>
<?php $falhou_professores = (int) $resultado_professores['codigo'] !== 0; ?>
<div class="card mb-3 border-<?php echo $falhou_professores ? 'danger' : ($resultado_professores['simulacao'] ? 'info' : 'success'); ?>">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-mortarboard"></i>
            <?php if ($falhou_professores): ?>
                Erro ao inserir professores do SIGAA
            <?php elseif ($resultado_professores['simulacao']): ?>
                Simulação: professores do SIGAA (nada foi gravado)
            <?php else: ?>
                Professores do SIGAA inseridos
            <?php endif; ?>
        </h6>
        <button type="button" class="btn-close" aria-label="Fechar" onclick="this.closest('.card').remove()"></button>
    </div>
    <div class="card-body">
        <?php if ($resultado_professores['saida'] !== ''): ?>
        <pre class="mb-0 small" style="max-height: 420px; overflow: auto;"><?php echo htmlspecialchars($resultado_professores['saida']); ?></pre>
        <?php endif; ?>
        <?php if ($resultado_professores['erros'] !== ''): ?>
        <div class="alert alert-<?php echo $falhou_professores ? 'danger' : 'warning'; ?> mb-0 mt-2">
            <pre class="mb-0 small" style="white-space: pre-wrap;"><?php echo htmlspecialchars($resultado_professores['erros']); ?></pre>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people"></i> Lista de Usuários</h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inserirProfessoresModal">
                        <i class="bi bi-cloud-download"></i> Inserir professores do SIGAA
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="bi bi-person-plus"></i> Novo Usuário
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Usuário</th>
                                <th>E-mail</th>
                                <th>Tipo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usr): ?>
                            <tr>
                                <td>
                                    <div><?php echo htmlspecialchars($usr['full_name']); ?></div>
                                    <?php if (!empty($usr['cursos_coordenados'])): ?>
                                    <div class="mt-1">
                                        <?php foreach ($usr['cursos_coordenados'] as $cc): ?>
                                            <span class="badge bg-warning text-dark me-1 mb-1" title="Coordenador">
                                                <?php echo htmlspecialchars($cc['nome']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($usr['username']); ?></td>
                                <td><?php echo htmlspecialchars($usr['email']); ?></td>
                                <td>
                                    <?php
                                    $user_type_nome = $usr['user_type_nome'] ?? '';
                                    ?>
                                    <span class="badge bg-<?php 
                                        $nivel = $usr['user_level'] ?? '';
                                        echo $nivel === 'administrador' ? 'danger' :
                                            ($nivel === 'nivel0' ? 'primary' :
                                            ($nivel === 'nivel1' ? 'info' :
                                            ($nivel === 'nivel2' ? 'secondary' : 'secondary')));
                                    ?>">
                                        <?php echo htmlspecialchars($user_type_nome); ?>
                                    </span>
                                </td>
                                <td class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm btn-edit-user" 
                                            data-user='<?php echo htmlspecialchars(json_encode($usr)); ?>'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$usr['id']): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Usuário corrente">
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" action="" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$usr['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Inserir Professores do SIGAA -->
<?php
$tipo_professor_id = '';
foreach ($user_types as $ut) {
    if (mb_strtolower(trim($ut['nome'])) === 'professor') {
        $tipo_professor_id = (string) $ut['id'];
        break;
    }
}
?>
<div class="modal fade" id="inserirProfessoresModal" tabindex="-1" aria-labelledby="inserirProfessoresModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inserirProfessoresModalLabel"><i class="bi bi-cloud-download"></i> Inserir professores do SIGAA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="inserirProfessoresForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="inserir_professores">
                    <p class="small text-muted">
                        Busca no SIGAA os professores das disciplinas dos cursos integrados e cadastra,
                        com autenticação LDAP, os que ainda não existem. Usuários já cadastrados
                        (mesmo usuário, e-mail ou nome) não são alterados.
                    </p>
                    <div class="mb-3">
                        <label for="tipo_usuario_id" class="form-label">Tipo de usuário dos novos professores <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo_usuario_id" name="tipo_usuario_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($user_types as $ut): ?>
                            <option value="<?php echo (int) $ut['id']; ?>" <?php echo (string) $ut['id'] === $tipo_professor_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ut['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <small class="text-muted">Use "Simular" para ver quem seria cadastrado antes de gravar.</small>
                    <div class="d-none mt-3" id="inserirProfessoresAguarde">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Consultando o SIGAA, isso pode levar alguns segundos...
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-outline-primary" name="simular" value="1">
                        <i class="bi bi-eye"></i> Simular
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Inserir professores
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('inserirProfessoresForm').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        return;
    }
    var botao = e.submitter;
    if (botao && botao.name) {
        var campo = document.createElement('input');
        campo.type = 'hidden';
        campo.name = botao.name;
        campo.value = botao.value;
        this.appendChild(campo);
    }
    this.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    document.getElementById('inserirProfessoresAguarde').classList.remove('d-none');
});
</script>

<!-- Modal para Criar Usuário -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel"><i class="bi bi-person-plus"></i> Novo Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="createUserForm" autocomplete="off">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuário <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required autocomplete="off" value="">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required autocomplete="off" value="">
                    </div>
                    
                    <div class="mb-3">
                        <label for="user_type_id" class="form-label">Tipo de Usuário <span class="text-danger">*</span></label>
                        <select class="form-select" id="user_type_id" name="user_type_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($user_types as $ut): ?>
                            <option value="<?php echo htmlspecialchars($ut['id']); ?>"><?php echo htmlspecialchars($ut['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="auth_type" class="form-label">Tipo de Autenticação <span class="text-danger">*</span></label>
                        <select class="form-select" id="auth_type" name="auth_type" required>
                            <option value="">Selecione...</option>
                            <option value="local">Senha Local</option>
                            <option value="ldap">LDAP</option>
                        </select>
                        <small class="text-muted">Escolha se o usuário usará senha local ou autenticação LDAP</small>
                    </div>
                    
                    <div class="mb-3" id="password_field">
                        <label for="password" class="form-label">Senha <span id="password_required" class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" value="">
                        <small class="text-muted" id="password_help"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required autocomplete="off" value="">
                    </div>

                    <?php if (!empty($cursos)): ?>
                    <div class="mb-3">
                        <label class="form-label">Coordenador de cursos</label>
                        <div class="border rounded p-2" style="max-height: 160px; overflow-y: auto;">
                            <?php foreach ($cursos as $c): ?>
                            <div class="form-check">
                                <input class="form-check-input create-curso-coordenacao-cb" type="checkbox"
                                       name="cursos_coordenacao[]" value="<?php echo (int)$c['id']; ?>"
                                       id="create_curso_coord_<?php echo (int)$c['id']; ?>">
                                <label class="form-check-label" for="create_curso_coord_<?php echo (int)$c['id']; ?>">
                                    <?php echo htmlspecialchars($c['nome']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Opcional. Marque os cursos que este usuário coordena.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Criar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Usuário -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel"><i class="bi bi-pencil"></i> Editar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Usuário <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">E-mail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_user_type_id" class="form-label">Tipo de Usuário <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_user_type_id" name="user_type_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($user_types as $ut): ?>
                            <option value="<?php echo htmlspecialchars($ut['id']); ?>"><?php echo htmlspecialchars($ut['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_auth_type" class="form-label">Tipo de Autenticação <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_auth_type" name="auth_type" required>
                            <option value="">Selecione...</option>
                            <option value="local">Senha Local</option>
                            <option value="ldap">LDAP</option>
                        </select>
                        <small class="text-muted">Escolha se o usuário usará senha local ou autenticação LDAP</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                    </div>
                    
                    <div class="mb-3" id="edit_password_field">
                        <label for="edit_new_password" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="edit_new_password" name="new_password">
                        <small class="text-muted" id="edit_password_help">Deixe em branco para manter a senha atual.</small>
                    </div>

                    <?php if (!empty($cursos)): ?>
                    <div class="mb-3">
                        <label class="form-label">Coordenador de cursos</label>
                        <div class="border rounded p-2" style="max-height: 160px; overflow-y: auto;">
                            <?php foreach ($cursos as $c): ?>
                            <div class="form-check">
                                <input class="form-check-input edit-curso-coordenacao-cb" type="checkbox"
                                       name="cursos_coordenacao[]" value="<?php echo (int)$c['id']; ?>"
                                       id="edit_curso_coord_<?php echo (int)$c['id']; ?>">
                                <label class="form-check-label" for="edit_curso_coord_<?php echo (int)$c['id']; ?>">
                                    <?php echo htmlspecialchars($c['nome']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Opcional. Marque os cursos que este usuário coordena.</small>
                    </div>
                    <?php endif; ?>
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

