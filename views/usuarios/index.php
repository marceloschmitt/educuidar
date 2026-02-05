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
                <h5 class="mb-0"><i class="bi bi-people"></i> Lista de Usuários</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-person-plus"></i> Novo Usuário
                </button>
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
                                <th>Autenticação</th>
                                <th>Cadastrado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usr): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usr['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($usr['username']); ?></td>
                                <td><?php echo htmlspecialchars($usr['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $usr['user_type'] === 'administrador' ? 'danger' : 
                                            ($usr['user_type'] === 'nivel1' ? 'primary' : 
                                            ($usr['user_type'] === 'nivel2' ? 'info' : 
                                            ($usr['user_type'] === 'assistencia_estudantil' ? 'success' : 'secondary'))); 
                                    ?>">
                                        <?php 
                                        $tipo_nome = [
                                            'administrador' => 'Administrador',
                                            'nivel1' => 'Nível 1',
                                            'nivel2' => 'Nível 2',
                                            'assistencia_estudantil' => 'Assistência Estudantil'
                                        ];
                                        echo $tipo_nome[$usr['user_type']] ?? ucfirst($usr['user_type']); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo ($usr['auth_type'] ?? 'local') === 'ldap' ? 'success' : 'secondary'; 
                                    ?>">
                                        <?php 
                                        echo ($usr['auth_type'] ?? 'local') === 'ldap' ? 'LDAP' : 'Local'; 
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($usr['created_at'])); ?></td>
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
                        <label for="user_type" class="form-label">Tipo de Usuário <span class="text-danger">*</span></label>
                        <select class="form-select" id="user_type" name="user_type" required>
                            <option value="">Selecione...</option>
                            <option value="administrador">Administrador</option>
                            <option value="nivel1">Usuário Nível 1</option>
                            <option value="nivel2">Usuário Nível 2</option>
                            <option value="assistencia_estudantil">Assistência Estudantil</option>
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
                        <label for="edit_user_type" class="form-label">Tipo de Usuário <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_user_type" name="user_type" required>
                            <option value="">Selecione...</option>
                            <option value="administrador">Administrador</option>
                            <option value="nivel1">Usuário Nível 1</option>
                            <option value="nivel2">Usuário Nível 2</option>
                            <option value="assistencia_estudantil">Assistência Estudantil</option>
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

