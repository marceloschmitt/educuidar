// JavaScript para Sistema de Controle IFRS

// Função para editar evento
function editEvento(evento) {
    // Prevenir propagação do evento se necessário
    if (event && event.stopPropagation) {
        event.stopPropagation();
    }
    
    // Preencher campos do formulário
    if (document.getElementById('edit_evento_id')) {
        document.getElementById('edit_evento_id').value = evento.id || '';
    }
    if (document.getElementById('edit_tipo_evento_id')) {
        document.getElementById('edit_tipo_evento_id').value = evento.tipo_evento_id || '';
    }
    if (document.getElementById('edit_data_evento')) {
        document.getElementById('edit_data_evento').value = evento.data_evento || '';
    }
    if (document.getElementById('edit_hora_evento')) {
        var hora = evento.hora_evento || '';
        if (hora && hora.length >= 5) {
            document.getElementById('edit_hora_evento').value = hora.substring(0, 5);
        } else {
            document.getElementById('edit_hora_evento').value = hora;
        }
    }
    if (document.getElementById('edit_observacoes')) {
        document.getElementById('edit_observacoes').value = evento.observacoes || '';
    }
    
    // Abrir modal
    var modalElement = document.getElementById('editEventoModal');
    if (modalElement) {
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        console.error('Modal editEventoModal não encontrado');
    }
}

// Função para resetar formulário de aluno
function resetForm() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('modal_nome').value = '';
    document.getElementById('modal_nome_social').value = '';
    document.getElementById('modal_email').value = '';
    document.getElementById('modal_telefone_celular').value = '';
    document.getElementById('modal_data_nascimento').value = '';
    document.getElementById('modal_numero_matricula').value = '';
    document.getElementById('modal_endereco').value = '';
    document.getElementById('modal_foto').value = '';
    document.getElementById('foto_preview').innerHTML = '';
    document.getElementById('foto_atual').innerHTML = '';
    document.getElementById('remover_foto_container').style.display = 'none';
    document.getElementById('remover_foto').checked = false;
    document.getElementById('modalTitle').textContent = 'Novo Aluno';
    document.getElementById('modalSubmitText').textContent = 'Criar';
}

// Função para editar aluno
function editAluno(aluno) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = aluno.id || '';
    document.getElementById('modal_nome').value = aluno.nome || '';
    document.getElementById('modal_nome_social').value = aluno.nome_social || '';
    document.getElementById('modal_email').value = aluno.email || '';
    document.getElementById('modal_telefone_celular').value = aluno.telefone_celular || '';
    document.getElementById('modal_data_nascimento').value = aluno.data_nascimento || '';
    document.getElementById('modal_numero_matricula').value = aluno.numero_matricula || '';
    document.getElementById('modal_endereco').value = aluno.endereco || '';
    document.getElementById('modalTitle').textContent = 'Editar Aluno';
    document.getElementById('modalSubmitText').textContent = 'Salvar';
    
    // Handle photo preview
    var fotoPreview = document.getElementById('foto_preview');
    var fotoAtual = document.getElementById('foto_atual');
    var removerFotoContainer = document.getElementById('remover_foto_container');
    var removerFoto = document.getElementById('remover_foto');
    
    fotoPreview.innerHTML = '';
    fotoAtual.innerHTML = '';
    removerFotoContainer.style.display = 'none';
    removerFoto.checked = false;
    
    if (aluno.foto) {
        var img = document.createElement('img');
        img.src = aluno.foto;
        img.className = 'img-thumbnail';
        img.style.maxWidth = '150px';
        img.style.maxHeight = '150px';
        fotoAtual.innerHTML = '<small class="text-muted">Foto atual:</small><br>';
        fotoAtual.appendChild(img);
        removerFotoContainer.style.display = 'block';
    }
    
    // Reset file input
    document.getElementById('modal_foto').value = '';
    
    console.log('DEBUG: editAluno - antes de abrir modal');
    console.log('DEBUG: applyModalStyles existe?', typeof applyModalStyles);
    
    var modalElement = document.getElementById('modalAluno');
    console.log('DEBUG: modalElement encontrado?', modalElement);
    
    if (!modalElement) {
        console.error('DEBUG: modalElement não encontrado!');
        return;
    }
    
    var modal = new bootstrap.Modal(modalElement);
    
    // Aplicar estilos antes de abrir o modal
    console.log('DEBUG: Tentando aplicar estilos antes de abrir');
    if (typeof applyModalStyles === 'function') {
        console.log('DEBUG: Chamando applyModalStyles');
        applyModalStyles(modalElement);
    } else {
        console.error('DEBUG: applyModalStyles não é uma função!');
    }
    
    // Aplicar estilos novamente após o modal ser totalmente mostrado
    modalElement.addEventListener('shown.bs.modal', function() {
        console.log('DEBUG: shown.bs.modal disparado');
        if (typeof applyModalStyles === 'function') {
            console.log('DEBUG: Chamando applyModalStyles no shown.bs.modal');
            applyModalStyles(this);
        }
    }, { once: true }); // Usar { once: true } para executar apenas uma vez
    
    console.log('DEBUG: Abrindo modal');
    modal.show();
}

// Initialize password toggles function
function initPasswordToggles() {
    // Find all password fields and add toggle functionality
    document.querySelectorAll('input[type="password"]').forEach(function(passwordInput) {
        // Skip if already has toggle
        if (passwordInput.parentElement && passwordInput.parentElement.classList.contains('password-wrapper')) {
            return;
        }
        
        // Create wrapper
        var wrapper = document.createElement('div');
        wrapper.className = 'password-wrapper';
        
        // Create toggle button
        var toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'password-toggle';
        toggleBtn.innerHTML = '<i class="bi bi-eye"></i>';
        toggleBtn.setAttribute('aria-label', 'Mostrar senha');
        
        // Wrap the input
        passwordInput.parentNode.insertBefore(wrapper, passwordInput);
        wrapper.appendChild(passwordInput);
        wrapper.appendChild(toggleBtn);
        
        // Add click event
        toggleBtn.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
                toggleBtn.setAttribute('aria-label', 'Ocultar senha');
            } else {
                passwordInput.type = 'password';
                toggleBtn.innerHTML = '<i class="bi bi-eye"></i>';
                toggleBtn.setAttribute('aria-label', 'Mostrar senha');
            }
        });
    });
}

// Função para aplicar estilos de scroll e fonte em modais (deve estar no escopo global)
function applyModalStyles(modal) {
    if (!modal) {
        console.log('DEBUG: applyModalStyles - modal é null');
        return;
    }
    
    console.log('DEBUG: applyModalStyles chamada para modal:', modal.id || 'sem id');
    
    var modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        console.log('DEBUG: Aplicando estilos ao modalBody');
        modalBody.style.setProperty('overflow-y', 'auto', 'important');
        modalBody.style.setProperty('max-height', 'calc(90vh - 140px)', 'important');
        modalBody.style.setProperty('flex', '1 1 auto', 'important');
        modalBody.style.setProperty('min-height', '0', 'important');
        modalBody.style.setProperty('font-size', '0.75rem', 'important');
        console.log('DEBUG: fontSize aplicado:', modalBody.style.fontSize);
    } else {
        console.log('DEBUG: modalBody não encontrado');
    }
    
    var modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.style.setProperty('display', 'flex', 'important');
        modalContent.style.setProperty('flex-direction', 'column', 'important');
        modalContent.style.setProperty('max-height', '90vh', 'important');
        modalContent.style.setProperty('overflow', 'hidden', 'important');
    }
    
    var form = modal.querySelector('form');
    if (form) {
        form.style.setProperty('display', 'flex', 'important');
        form.style.setProperty('flex-direction', 'column', 'important');
        form.style.setProperty('flex', '1 1 auto', 'important');
        form.style.setProperty('min-height', '0', 'important');
        form.style.setProperty('overflow', 'hidden', 'important');
    }
    
    // Aplicar fontes menores com !important
    var labels = modal.querySelectorAll('.form-label');
    console.log('DEBUG: Labels encontrados:', labels.length);
    labels.forEach(function(label) {
        label.style.setProperty('font-size', '0.75rem', 'important');
        label.style.setProperty('margin-bottom', '0.25rem', 'important');
        label.style.setProperty('font-weight', '500', 'important');
    });
    
    var inputs = modal.querySelectorAll('.form-control, .form-select');
    console.log('DEBUG: Inputs encontrados:', inputs.length);
    inputs.forEach(function(input) {
        input.style.setProperty('font-size', '0.75rem', 'important');
        input.style.setProperty('padding', '0.3rem 0.6rem', 'important');
        input.style.setProperty('line-height', '1.3', 'important');
        input.style.setProperty('height', 'calc(1.3em + 0.6rem + 2px)', 'important');
    });
    
    var smalls = modal.querySelectorAll('small');
    smalls.forEach(function(small) {
        small.style.setProperty('font-size', '0.7rem', 'important');
    });
    
    var mb3s = modal.querySelectorAll('.mb-3');
    mb3s.forEach(function(mb3) {
        mb3.style.setProperty('margin-bottom', '0.5rem', 'important');
    });
    
    console.log('DEBUG: applyModalStyles concluída');
}

// Event listeners quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Initialize password toggles
    initPasswordToggles();
    // Filtro de curso - limpar turma quando curso mudar
    var filtroCurso = document.getElementById('filtro_curso');
    if (filtroCurso) {
        filtroCurso.addEventListener('change', function() {
            var filtroTurma = document.getElementById('filtro_turma');
            if (filtroTurma) {
                filtroTurma.value = '';
            }
            this.form.submit();
        });
    }
    
    // Filtro de turma - submeter quando mudar
    var filtroTurma = document.getElementById('filtro_turma');
    if (filtroTurma) {
        filtroTurma.addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    // Filtro de nome - submeter ao pressionar Enter
    var filtroNome = document.getElementById('filtro_nome');
    if (filtroNome) {
        filtroNome.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
    
    // Prevenir propagação de eventos em botões dropdown
    var dropdownButtons = document.querySelectorAll('.dropdown-toggle');
    dropdownButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Botão de imprimir
    var btnImprimir = document.getElementById('btnImprimir');
    if (btnImprimir) {
        btnImprimir.addEventListener('click', function() {
            window.print();
        });
    }
    
    // Preview de foto ao selecionar arquivo
    var fotoInput = document.getElementById('modal_foto');
    if (fotoInput) {
        fotoInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            var fotoPreview = document.getElementById('foto_preview');
            var fotoAtual = document.getElementById('foto_atual');
            
            if (file) {
                // Hide current photo when new file is selected
                fotoAtual.innerHTML = '';
                document.getElementById('remover_foto_container').style.display = 'none';
                document.getElementById('remover_foto').checked = false;
                
                // Validate file type
                var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Tipo de arquivo não permitido. Use apenas imagens (JPG, PNG, GIF).');
                    e.target.value = '';
                    fotoPreview.innerHTML = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Arquivo muito grande. Tamanho máximo: 5MB.');
                    e.target.value = '';
                    fotoPreview.innerHTML = '';
                    return;
                }
                
                // Show preview
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-thumbnail';
                    img.style.maxWidth = '150px';
                    img.style.maxHeight = '150px';
                    fotoPreview.innerHTML = '<small class="text-muted">Nova foto:</small><br>';
                    fotoPreview.appendChild(img);
                };
                reader.readAsDataURL(file);
            } else {
                fotoPreview.innerHTML = '';
            }
        });
    }
    
    // Botão novo aluno - resetar formulário
    var btnNovoAluno = document.getElementById('btnNovoAluno');
    if (btnNovoAluno) {
        btnNovoAluno.addEventListener('click', function() {
            resetForm();
        });
    }
    
    // Botões de editar evento
    var editEventoButtons = document.querySelectorAll('.btn-edit-evento');
    editEventoButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            var eventoData = JSON.parse(this.getAttribute('data-evento'));
            editEvento(eventoData);
        });
    });
    
    // Botões de editar aluno
    var editAlunoButtons = document.querySelectorAll('.btn-edit-aluno');
    editAlunoButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            var alunoData = JSON.parse(this.getAttribute('data-aluno'));
            editAluno(alunoData);
        });
    });
    
    // Botões de excluir evento - confirmação
    var deleteEventoButtons = document.querySelectorAll('.btn-delete-evento');
    deleteEventoButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja excluir este evento?')) {
                e.preventDefault();
            }
        });
    });
    
    // Se houver aluno_edit (vindo de data attribute), abrir modal automaticamente
    var alunoEditData = document.body.getAttribute('data-aluno-edit');
    if (alunoEditData) {
        try {
            var alunoEdit = JSON.parse(alunoEditData);
            editAluno(alunoEdit);
        } catch (e) {
            console.error('Erro ao processar aluno_edit:', e);
        }
    }
    
    // Botões de editar turma
    var editTurmaButtons = document.querySelectorAll('.btn-edit-turma');
    editTurmaButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            var turmaData = JSON.parse(this.getAttribute('data-turma'));
            editarTurma(turmaData);
        });
    });
    
    // Botões de editar curso
    var editCursoButtons = document.querySelectorAll('.btn-edit-curso');
    editCursoButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            var cursoData = JSON.parse(this.getAttribute('data-curso'));
            editCurso(cursoData);
        });
    });
    
    // Botões de editar usuário
    var editUserButtons = document.querySelectorAll('.btn-edit-user');
    editUserButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            var userData = JSON.parse(this.getAttribute('data-user'));
            editUser(userData);
        });
    });
    
    // Linhas clicáveis para mostrar observações
    var observacoesRows = document.querySelectorAll('.row-observacoes');
    observacoesRows.forEach(function(row) {
        row.addEventListener('click', function() {
            var eventoData = JSON.parse(this.getAttribute('data-evento'));
            showObservacoes(eventoData);
        });
    });
    
    // Selecionar todos os alunos
    var selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            selectAllAlunos(this.checked);
        });
    }
    
    var selectAllButtons = document.querySelectorAll('.btn-select-all, .btn-deselect-all');
    selectAllButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var select = this.classList.contains('btn-select-all');
            selectAllAlunos(select);
        });
    });
    
    // Toggle campos de senha para usuários (formulário de criação)
    var userTypeSelect = document.getElementById('user_type');
    var authTypeSelect = document.getElementById('auth_type');
    if (userTypeSelect && authTypeSelect) {
        userTypeSelect.addEventListener('change', toggleFields);
        authTypeSelect.addEventListener('change', toggleFields);
    }
    
    // Resetar formulário de criação quando o modal for fechado
    var createUserModal = document.getElementById('createUserModal');
    if (createUserModal) {
        createUserModal.addEventListener('hidden.bs.modal', function() {
            var form = document.getElementById('createUserForm');
            if (form) {
                form.reset();
                // Resetar campos de senha
                var passwordInput = document.getElementById('password');
                var passwordHelp = document.getElementById('password_help');
                var passwordRequired = document.getElementById('password_required');
                if (passwordInput) {
                    passwordInput.value = '';
                    passwordInput.disabled = false;
                }
                if (passwordHelp) {
                    passwordHelp.textContent = '';
                    passwordHelp.className = 'text-muted';
                }
                if (passwordRequired) passwordRequired.style.display = 'inline';
            }
        });
        
        // Inicializar campos quando o modal for aberto
        createUserModal.addEventListener('show.bs.modal', function() {
            // Limpar todos os campos antes de abrir o modal
            var form = document.getElementById('createUserForm');
            if (form) {
                form.reset();
                // Garantir que os campos estejam vazios
                var usernameInput = document.getElementById('username');
                var emailInput = document.getElementById('email');
                var passwordInput = document.getElementById('password');
                var fullNameInput = document.getElementById('full_name');
                if (usernameInput) usernameInput.value = '';
                if (emailInput) emailInput.value = '';
                if (passwordInput) passwordInput.value = '';
                if (fullNameInput) fullNameInput.value = '';
            }
            toggleFields();
        });
    }
    
    var editUserTypeSelect = document.getElementById('edit_user_type');
    var editAuthTypeSelect = document.getElementById('edit_auth_type');
    if (editUserTypeSelect && editAuthTypeSelect) {
        editUserTypeSelect.addEventListener('change', toggleEditPasswordField);
        editAuthTypeSelect.addEventListener('change', toggleEditPasswordField);
    }
    
    // Re-initialize password toggles when modals are shown (for dynamic content)
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function() {
            initPasswordToggles();
        });
    });
    
    // Modais de usuário - aplicar estilos quando abertos
    var createUserModal = document.getElementById('createUserModal');
    if (createUserModal) {
        createUserModal.addEventListener('show.bs.modal', function() {
            applyModalStyles(this);
        });
        createUserModal.addEventListener('shown.bs.modal', function() {
            applyModalStyles(this);
        });
    }
    
    var editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function() {
            applyModalStyles(this);
        });
        editUserModal.addEventListener('shown.bs.modal', function() {
            applyModalStyles(this);
        });
    }
    
    // Modal de aluno - aplicar estilos quando aberto
    var modalAluno = document.getElementById('modalAluno');
    console.log('DEBUG: DOMContentLoaded - modalAluno encontrado?', modalAluno);
    if (modalAluno) {
        console.log('DEBUG: Adicionando event listeners ao modalAluno');
        modalAluno.addEventListener('show.bs.modal', function() {
            console.log('DEBUG: show.bs.modal disparado para modalAluno');
            applyModalStyles(this);
        });
        modalAluno.addEventListener('shown.bs.modal', function() {
            console.log('DEBUG: shown.bs.modal disparado para modalAluno');
            applyModalStyles(this);
        });
    } else {
        console.error('DEBUG: modalAluno NÃO encontrado no DOMContentLoaded!');
    }
    
    // Formulários de confirmação
    var confirmForms = document.querySelectorAll('.form-confirm, .form-delete-turma, .form-delete-curso');
    confirmForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var confirmMsg = this.getAttribute('data-confirm');
            if (confirmMsg && !confirm(confirmMsg)) {
                e.preventDefault();
            }
        });
    });
    
    // Atualizar selectAll quando checkboxes individuais mudarem
    var alunoCheckboxes = document.querySelectorAll('.aluno-checkbox');
    var selectAll = document.getElementById('selectAll');
    if (selectAll && alunoCheckboxes.length > 0) {
        alunoCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                var allChecked = Array.from(alunoCheckboxes).every(function(cb) { return cb.checked; });
                var someChecked = Array.from(alunoCheckboxes).some(function(cb) { return cb.checked; });
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            });
        });
    }
    
    // Clean up modal backdrops on page load
    var backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(function(backdrop) {
        backdrop.remove();
    });
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Close modal and remove backdrop when editUserForm is submitted
    var editForm = document.getElementById('editUserForm');
    var editModal = document.getElementById('editUserModal');
    
    if (editForm && editModal) {
        editForm.addEventListener('submit', function(e) {
            // Close modal immediately
            var modalInstance = bootstrap.Modal.getInstance(editModal);
            if (modalInstance) {
                modalInstance.hide();
            }
            
            // Force remove backdrop immediately
            var backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(function(backdrop) {
                backdrop.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
    }
    
    // Also listen for hidden event to clean up
    if (editModal) {
        editModal.addEventListener('hidden.bs.modal', function() {
            var backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(function(backdrop) {
                backdrop.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
    }
});

// Função para editar turma
function editarTurma(turma) {
    document.getElementById('edit_turma_id').value = turma.id;
    document.getElementById('edit_curso_id').value = turma.curso_id;
    document.getElementById('edit_ano_civil').value = turma.ano_civil;
    document.getElementById('edit_ano_curso').value = turma.ano_curso;
    
    var modal = new bootstrap.Modal(document.getElementById('editTurmaModal'));
    modal.show();
}

// Função para editar curso
function editCurso(curso) {
    document.getElementById('edit_curso_id').value = curso.id || '';
    document.getElementById('edit_nome').value = curso.nome || '';
    
    var modal = new bootstrap.Modal(document.getElementById('editCursoModal'));
    modal.show();
}

// Função para editar usuário
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username || '';
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_full_name').value = user.full_name || '';
    document.getElementById('edit_user_type').value = user.user_type || '';
    document.getElementById('edit_auth_type').value = user.auth_type || 'local';
    document.getElementById('edit_new_password').value = '';
    toggleEditPasswordField();
    
    var modalElement = document.getElementById('editUserModal');
    var modal = new bootstrap.Modal(modalElement);
    
    // Aplicar estilos antes de abrir o modal
    if (modalElement && typeof applyModalStyles === 'function') {
        applyModalStyles(modalElement);
    }
    
    // Aplicar estilos novamente após o modal ser totalmente mostrado
    modalElement.addEventListener('shown.bs.modal', function() {
        if (typeof applyModalStyles === 'function') {
            applyModalStyles(this);
        }
    }, { once: true }); // Usar { once: true } para executar apenas uma vez
    
    modal.show();
}

// Função para mostrar observações
function showObservacoes(evento) {
    document.getElementById('obs_data').textContent = evento.data || '-';
    document.getElementById('obs_hora').textContent = evento.hora || '-';
    document.getElementById('obs_aluno').textContent = evento.aluno || '-';
    document.getElementById('obs_tipo').textContent = evento.tipo || '-';
    document.getElementById('obs_registrado_por').textContent = evento.registrado_por || '-';
    
    var observacoes = evento.observacoes || '';
    var obsTexto = document.getElementById('obs_texto');
    if (observacoes.trim() === '') {
        obsTexto.innerHTML = '<em class="text-muted">Nenhuma observação registrada.</em>';
    } else {
        obsTexto.textContent = observacoes;
    }
    
    var modal = new bootstrap.Modal(document.getElementById('observacoesModal'));
    modal.show();
}

// Função para selecionar todos os alunos
function selectAllAlunos(select) {
    var checkboxes = document.querySelectorAll('.aluno-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = select;
    });
    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = select;
    }
}

// Função para toggle campos de senha
function toggleFields() {
    var authType = document.getElementById('auth_type');
    if (!authType) return;
    
    var authTypeValue = authType.value;
    var passwordInput = document.getElementById('password');
    var passwordHelp = document.getElementById('password_help');
    var passwordRequired = document.getElementById('password_required');
    
    if (!passwordInput || !passwordHelp) return;
    
    if (authTypeValue === 'ldap') {
        passwordInput.required = false;
        passwordInput.disabled = true;
        passwordInput.value = '';
        passwordHelp.textContent = 'Usuários com autenticação LDAP não precisam de senha local. A autenticação será feita no servidor LDAP.';
        passwordHelp.className = 'text-muted';
        if (passwordRequired) {
            passwordRequired.style.display = 'none';
        }
    } else if (authTypeValue === 'local') {
        passwordInput.required = true;
        passwordInput.disabled = false;
        passwordHelp.textContent = 'Senha obrigatória para autenticação local.';
        passwordHelp.className = 'text-muted';
        if (passwordRequired) {
            passwordRequired.style.display = 'inline';
        }
    } else {
        passwordInput.required = false;
        passwordInput.disabled = true;
        passwordHelp.textContent = 'Selecione o tipo de autenticação primeiro.';
        passwordHelp.className = 'text-muted';
        if (passwordRequired) {
            passwordRequired.style.display = 'none';
        }
    }
}

// Função para toggle campos de senha no formulário de edição
function toggleEditPasswordField() {
    var authType = document.getElementById('edit_auth_type');
    if (!authType) return;
    
    var authTypeValue = authType.value;
    var passwordInput = document.getElementById('edit_new_password');
    var passwordHelp = document.getElementById('edit_password_help');
    var passwordField = document.getElementById('edit_password_field');
    
    if (!passwordInput || !passwordHelp) return;
    
    if (authTypeValue === 'ldap') {
        passwordInput.required = false;
        passwordInput.disabled = true;
        passwordInput.value = '';
        passwordHelp.textContent = 'Usuários com autenticação LDAP não usam senha local. A senha deve ser alterada no servidor LDAP.';
        passwordHelp.className = 'text-muted';
        if (passwordField) {
            passwordField.style.display = 'block';
        }
    } else if (authTypeValue === 'local') {
        passwordInput.required = false;
        passwordInput.disabled = false;
        passwordHelp.textContent = 'Deixe em branco para manter a senha atual. Preencha apenas se desejar alterar a senha.';
        passwordHelp.className = 'text-muted';
        if (passwordField) {
            passwordField.style.display = 'block';
        }
    } else {
        passwordInput.required = false;
        passwordInput.disabled = true;
        passwordHelp.textContent = '';
        if (passwordField) {
            passwordField.style.display = 'block';
        }
    }
}


