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
    if (document.getElementById('edit_aluno_id')) {
        document.getElementById('edit_aluno_id').value = evento.aluno_id || '';
    }
    if (document.getElementById('edit_turma_id')) {
        document.getElementById('edit_turma_id').value = evento.turma_id || '';
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
    if (document.getElementById('edit_prontuario_cae')) {
        document.getElementById('edit_prontuario_cae').value = evento.prontuario_cae || '';
    }

    var anexosContainer = document.getElementById('edit_anexos_existentes');
    if (anexosContainer) {
        anexosContainer.innerHTML = '';
        if (evento.anexos && evento.anexos.length > 0) {
            var title = document.createElement('label');
            title.className = 'form-label';
            title.textContent = 'Anexos existentes';
            anexosContainer.appendChild(title);

            var list = document.createElement('ul');
            list.className = 'list-unstyled mb-0';
            evento.anexos.forEach(function(anexo) {
                var item = document.createElement('li');
                var link = document.createElement('a');
                link.href = anexo.caminho;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = anexo.nome_original;
                item.appendChild(link);

                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.className = 'd-inline ms-2';
                form.onsubmit = function() {
                    return confirm('Remover este anexo?');
                };

                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_anexo';
                form.appendChild(actionInput);

                var anexoInput = document.createElement('input');
                anexoInput.type = 'hidden';
                anexoInput.name = 'anexo_id';
                anexoInput.value = anexo.id;
                form.appendChild(anexoInput);

                var alunoInput = document.createElement('input');
                alunoInput.type = 'hidden';
                alunoInput.name = 'aluno_id';
                alunoInput.value = evento.aluno_id || '';
                form.appendChild(alunoInput);

                var button = document.createElement('button');
                button.type = 'submit';
                button.className = 'btn btn-sm btn-outline-danger';
                button.textContent = 'Remover';
                form.appendChild(button);

                item.appendChild(form);
                list.appendChild(item);
            });
            anexosContainer.appendChild(list);
        } else {
            var empty = document.createElement('small');
            empty.className = 'text-muted';
            empty.textContent = 'Sem anexos.';
            anexosContainer.appendChild(empty);
        }
    }
    
    // Mostrar campo de prontuário se o tipo marcar essa opção
    var tipoEventoSelect = document.getElementById('edit_tipo_evento_id');
    var prontuarioContainer = document.getElementById('edit_prontuario_cae_container');
    if (tipoEventoSelect && prontuarioContainer) {
        var selectedOption = tipoEventoSelect.options[tipoEventoSelect.selectedIndex];
        var geraProntuario = selectedOption && selectedOption.dataset ? selectedOption.dataset.geraProntuario : '0';
        prontuarioContainer.style.display = (geraProntuario === '1') ? 'block' : 'none';
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
    document.getElementById('modal_pessoa_referencia').value = '';
    document.getElementById('modal_telefone_pessoa_referencia').value = '';
    document.getElementById('modal_rede_atendimento').value = '';
    document.getElementById('modal_auxilio_estudantil').value = '0';
    document.getElementById('modal_nee').value = '';
    document.getElementById('modal_indigena').value = '0';
    document.getElementById('modal_pei').value = '0';
    document.getElementById('modal_profissionais_referencia').value = '';
    document.getElementById('modal_outras_observacoes').value = '';
    if (document.getElementById('modal_identidade_genero')) {
        document.getElementById('modal_identidade_genero').value = '';
    }
    if (document.getElementById('modal_grupo_familiar')) {
        document.getElementById('modal_grupo_familiar').value = '';
    }
    if (document.getElementById('modal_guarda_legal')) {
        document.getElementById('modal_guarda_legal').value = '';
    }
    if (document.getElementById('modal_escolaridade_pais_responsaveis')) {
        document.getElementById('modal_escolaridade_pais_responsaveis').value = '';
    }
    if (document.getElementById('modal_necessidade_mudanca')) {
        document.getElementById('modal_necessidade_mudanca').value = '';
    }
    if (document.getElementById('modal_meio_transporte')) {
        document.getElementById('modal_meio_transporte').value = '';
    }
    if (document.getElementById('modal_razao_escolha_ifrs')) {
        document.getElementById('modal_razao_escolha_ifrs').value = '';
    }
    if (document.getElementById('modal_expectativa_estudante_familia')) {
        document.getElementById('modal_expectativa_estudante_familia').value = '';
    }
    if (document.getElementById('modal_conhecimento_curso_tecnico')) {
        document.getElementById('modal_conhecimento_curso_tecnico').value = '';
    }
    if (document.getElementById('modal_rede_atendimento_familia')) {
        document.getElementById('modal_rede_atendimento_familia').value = '';
    }
    document.getElementById('modal_foto').value = '';
    document.getElementById('foto_preview').innerHTML = '';
    var fotoAtual = document.getElementById('foto_atual');
    fotoAtual.innerHTML = '';
    // Mostrar imagem padrão quando criar novo aluno
    var defaultDiv = document.createElement('div');
    defaultDiv.className = 'bg-secondary text-white rounded d-flex align-items-center justify-content-center';
    defaultDiv.style.width = '150px';
    defaultDiv.style.height = '150px';
    defaultDiv.innerHTML = '<i class="bi bi-person" style="font-size: 3rem;"></i>';
    fotoAtual.appendChild(defaultDiv);
    document.getElementById('remover_foto_container').style.display = 'none';
    document.getElementById('remover_foto').checked = false;
    document.getElementById('modalTitle').textContent = 'Novo Aluno';
    document.getElementById('modalSubmitText').textContent = 'Criar';
    var returnToInput = document.getElementById('formReturnTo');
    if (returnToInput) {
        returnToInput.value = '';
    }
}

// Função para visualizar ficha do aluno
function viewFichaAluno(aluno) {
    // Preencher foto
    var fotoContainer = document.getElementById('ficha_foto');
    fotoContainer.innerHTML = '';
    if (aluno.foto) {
        var img = document.createElement('img');
        img.src = aluno.foto;
        img.className = 'img-thumbnail';
        img.style.maxWidth = '200px';
        img.style.maxHeight = '200px';
        img.style.width = '200px';
        img.style.height = '200px';
        img.style.objectFit = 'cover';
        fotoContainer.appendChild(img);
    } else {
        var defaultDiv = document.createElement('div');
        defaultDiv.className = 'bg-secondary text-white rounded d-flex align-items-center justify-content-center mx-auto';
        defaultDiv.style.width = '200px';
        defaultDiv.style.height = '200px';
        defaultDiv.innerHTML = '<i class="bi bi-person" style="font-size: 4rem;"></i>';
        fotoContainer.appendChild(defaultDiv);
    }
    
    // Preencher nome: se tem nome social, mostrar ele; senão, mostrar o nome original
    var nomeSocialEl = document.getElementById('ficha_nome_social');
    if (aluno.nome_social && aluno.nome_social.trim() !== '') {
        // Tem nome social: mostrar nome social como nome principal e esconder o elemento de nome social
        document.getElementById('ficha_nome').textContent = aluno.nome_social;
        nomeSocialEl.style.display = 'none';
    } else {
        // Não tem nome social: mostrar nome original e esconder o elemento de nome social
        document.getElementById('ficha_nome').textContent = aluno.nome || '-';
        nomeSocialEl.style.display = 'none';
    }
    
    // Preencher dados de identificação
    document.getElementById('ficha_email').textContent = aluno.email || '-';
    document.getElementById('ficha_telefone_celular').textContent = aluno.telefone_celular || '-';
    
    // Data de nascimento formatada
    var dataNasc = aluno.data_nascimento || '';
    if (dataNasc && dataNasc !== '') {
        try {
            var date = new Date(dataNasc);
            var formattedDate = date.toLocaleDateString('pt-BR');
            document.getElementById('ficha_data_nascimento').textContent = formattedDate;
        } catch (e) {
            document.getElementById('ficha_data_nascimento').textContent = dataNasc;
        }
    } else {
        document.getElementById('ficha_data_nascimento').textContent = '-';
    }
    
    document.getElementById('ficha_numero_matricula').textContent = aluno.numero_matricula || '-';
    document.getElementById('ficha_endereco').textContent = aluno.endereco || '-';
    
    // Preencher informações acadêmicas
    document.getElementById('ficha_curso').textContent = aluno.curso_nome || '-';
    
    // Preencher todas as turmas
    var turmasEl = document.getElementById('ficha_turmas');
    if (aluno.todas_turmas && aluno.todas_turmas.length > 0) {
        var turmasHtml = '<ul class="list-unstyled mb-0">';
        aluno.todas_turmas.forEach(function(turma) {
            var turmaText = turma.curso_nome + ' - ' + turma.ano_curso + 'º Ano - ' + turma.ano_civil;
            if (turma.is_ano_corrente) {
                turmaText += ' <span class="badge bg-success">Corrente</span>';
            }
            turmasHtml += '<li>' + turmaText + '</li>';
        });
        turmasHtml += '</ul>';
        turmasEl.innerHTML = turmasHtml;
    } else {
        turmasEl.textContent = '-';
    }
    
    var totalEventos = aluno.total_eventos || 0;
    document.getElementById('ficha_total_eventos').textContent = totalEventos;
    
    // Preencher pessoa de referência
    document.getElementById('ficha_pessoa_referencia').textContent = aluno.pessoa_referencia || '-';
    document.getElementById('ficha_telefone_pessoa_referencia').textContent = aluno.telefone_pessoa_referencia || '-';
    
    // Preencher rede de atendimento
    var redeAtendimentoEl = document.getElementById('ficha_rede_atendimento');
    if (aluno.rede_atendimento && aluno.rede_atendimento.trim() !== '') {
        redeAtendimentoEl.textContent = aluno.rede_atendimento;
    } else {
        redeAtendimentoEl.textContent = '-';
    }
    
    // Preencher informações adicionais
    document.getElementById('ficha_auxilio_estudantil').textContent = (aluno.auxilio_estudantil == 1 || aluno.auxilio_estudantil === '1') ? 'Sim' : 'Não';
    document.getElementById('ficha_indigena').textContent = (aluno.indigena == 1 || aluno.indigena === '1') ? 'Sim' : 'Não';
    document.getElementById('ficha_pei').textContent = (aluno.pei == 1 || aluno.pei === '1') ? 'Sim' : 'Não';
    
    var neeEl = document.getElementById('ficha_nee');
    if (aluno.nee && aluno.nee.trim() !== '') {
        neeEl.textContent = aluno.nee;
    } else {
        neeEl.textContent = '-';
    }
    
    var profissionaisEl = document.getElementById('ficha_profissionais_referencia');
    if (aluno.profissionais_referencia && aluno.profissionais_referencia.trim() !== '') {
        profissionaisEl.textContent = aluno.profissionais_referencia;
    } else {
        profissionaisEl.textContent = '-';
    }
    
    var observacoesEl = document.getElementById('ficha_outras_observacoes');
    if (aluno.outras_observacoes && aluno.outras_observacoes.trim() !== '') {
        observacoesEl.textContent = aluno.outras_observacoes;
    } else {
        observacoesEl.textContent = '-';
    }

    var setFichaTexto = function(id, value) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        if (value && value.trim() !== '') {
            el.textContent = value;
        } else {
            el.textContent = '-';
        }
    };

    setFichaTexto('ficha_identidade_genero', aluno.identidade_genero || '');
    setFichaTexto('ficha_grupo_familiar', aluno.grupo_familiar || '');
    setFichaTexto('ficha_guarda_legal', aluno.guarda_legal || '');
    setFichaTexto('ficha_escolaridade_pais_responsaveis', aluno.escolaridade_pais_responsaveis || '');
    setFichaTexto('ficha_necessidade_mudanca', aluno.necessidade_mudanca || '');
    setFichaTexto('ficha_meio_transporte', aluno.meio_transporte || '');
    setFichaTexto('ficha_razao_escolha_ifrs', aluno.razao_escolha_ifrs || '');
    setFichaTexto('ficha_expectativa_estudante_familia', aluno.expectativa_estudante_familia || '');
    setFichaTexto('ficha_conhecimento_curso_tecnico', aluno.conhecimento_curso_tecnico || '');
    setFichaTexto('ficha_rede_atendimento_familia', aluno.rede_atendimento_familia || '');
    
    // Armazenar dados do aluno para o botão de editar
    var modalFicha = document.getElementById('modalFichaAluno');
    if (modalFicha) {
        modalFicha.setAttribute('data-aluno-data', JSON.stringify(aluno));
    }
    
    // Atualizar link do prontuário CAE se existir
    var btnProntuarioCAE = document.getElementById('btnProntuarioCAE');
    if (btnProntuarioCAE && aluno.id) {
        btnProntuarioCAE.href = 'prontuario_ae.php?aluno_id=' + aluno.id;
    }
    
    // Abrir modal
    var modalElement = document.getElementById('modalFichaAluno');
    if (!modalElement) {
        return;
    }
    
    var modal = new bootstrap.Modal(modalElement);
    modal.show();
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
    document.getElementById('modal_pessoa_referencia').value = aluno.pessoa_referencia || '';
    document.getElementById('modal_telefone_pessoa_referencia').value = aluno.telefone_pessoa_referencia || '';
    document.getElementById('modal_rede_atendimento').value = aluno.rede_atendimento || '';
    document.getElementById('modal_auxilio_estudantil').value = (aluno.auxilio_estudantil == 1 || aluno.auxilio_estudantil === '1') ? '1' : '0';
    document.getElementById('modal_nee').value = aluno.nee || '';
    document.getElementById('modal_indigena').value = (aluno.indigena == 1 || aluno.indigena === '1') ? '1' : '0';
    document.getElementById('modal_pei').value = (aluno.pei == 1 || aluno.pei === '1') ? '1' : '0';
    document.getElementById('modal_profissionais_referencia').value = aluno.profissionais_referencia || '';
    document.getElementById('modal_outras_observacoes').value = aluno.outras_observacoes || '';
    if (document.getElementById('modal_identidade_genero')) {
        document.getElementById('modal_identidade_genero').value = aluno.identidade_genero || '';
    }
    if (document.getElementById('modal_grupo_familiar')) {
        document.getElementById('modal_grupo_familiar').value = aluno.grupo_familiar || '';
    }
    if (document.getElementById('modal_guarda_legal')) {
        document.getElementById('modal_guarda_legal').value = aluno.guarda_legal || '';
    }
    if (document.getElementById('modal_escolaridade_pais_responsaveis')) {
        document.getElementById('modal_escolaridade_pais_responsaveis').value = aluno.escolaridade_pais_responsaveis || '';
    }
    if (document.getElementById('modal_necessidade_mudanca')) {
        document.getElementById('modal_necessidade_mudanca').value = aluno.necessidade_mudanca || '';
    }
    if (document.getElementById('modal_meio_transporte')) {
        document.getElementById('modal_meio_transporte').value = aluno.meio_transporte || '';
    }
    if (document.getElementById('modal_razao_escolha_ifrs')) {
        document.getElementById('modal_razao_escolha_ifrs').value = aluno.razao_escolha_ifrs || '';
    }
    if (document.getElementById('modal_expectativa_estudante_familia')) {
        document.getElementById('modal_expectativa_estudante_familia').value = aluno.expectativa_estudante_familia || '';
    }
    if (document.getElementById('modal_conhecimento_curso_tecnico')) {
        document.getElementById('modal_conhecimento_curso_tecnico').value = aluno.conhecimento_curso_tecnico || '';
    }
    if (document.getElementById('modal_rede_atendimento_familia')) {
        document.getElementById('modal_rede_atendimento_familia').value = aluno.rede_atendimento_familia || '';
    }
    document.getElementById('modalTitle').textContent = 'Editar Aluno';
    document.getElementById('modalSubmitText').textContent = 'Salvar';
    var modalAluno = document.getElementById('modalAluno');
    if (modalAluno) {
        var textareas = modalAluno.querySelectorAll('textarea');
        textareas.forEach(function(textarea) {
            autoResizeTextarea(textarea);
        });
    }
    
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
    } else {
        // Mostrar imagem padrão quando não há foto (similar à lista)
        var defaultDiv = document.createElement('div');
        defaultDiv.className = 'bg-secondary text-white rounded d-flex align-items-center justify-content-center';
        defaultDiv.style.width = '150px';
        defaultDiv.style.height = '150px';
        defaultDiv.innerHTML = '<i class="bi bi-person" style="font-size: 3rem;"></i>';
        fotoAtual.innerHTML = '<small class="text-muted">Foto atual:</small><br>';
        fotoAtual.appendChild(defaultDiv);
    }
    
    // Reset file input
    document.getElementById('modal_foto').value = '';
    
    var modalElement = document.getElementById('modalAluno');
    if (!modalElement) {
        return;
    }
    
    var modal = new bootstrap.Modal(modalElement);
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
    if (!modal) return;
    
    var modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        modalBody.style.setProperty('overflow-y', 'auto', 'important');
        modalBody.style.setProperty('max-height', 'calc(90vh - 140px)', 'important');
        modalBody.style.setProperty('flex', '1 1 auto', 'important');
        modalBody.style.setProperty('min-height', '0', 'important');
        modalBody.style.setProperty('font-size', '0.75rem', 'important');
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
    labels.forEach(function(label) {
        label.style.setProperty('font-size', '0.75rem', 'important');
        label.style.setProperty('margin-bottom', '0.25rem', 'important');
        label.style.setProperty('font-weight', '500', 'important');
    });
    
    var inputs = modal.querySelectorAll('.form-control, .form-select');
    inputs.forEach(function(input) {
        input.style.setProperty('font-size', '0.75rem', 'important');
        input.style.setProperty('padding', '0.3rem 0.6rem', 'important');
        input.style.setProperty('line-height', '1.3', 'important');
        if (input.tagName !== 'TEXTAREA') {
            input.style.setProperty('height', 'calc(1.3em + 0.6rem + 2px)', 'important');
        }
    });
    
    var smalls = modal.querySelectorAll('small');
    smalls.forEach(function(small) {
        small.style.setProperty('font-size', '0.7rem', 'important');
    });
    
    var mb3s = modal.querySelectorAll('.mb-3');
    mb3s.forEach(function(mb3) {
        mb3.style.setProperty('margin-bottom', '0.5rem', 'important');
    });
    
    // Garantir que os botões do modal-footer tenham o mesmo tamanho e fonte dos outros modais
    var footerButtons = modal.querySelectorAll('.modal-footer .btn');
    footerButtons.forEach(function(button) {
        button.style.setProperty('font-size', '0.8rem', 'important');
        button.style.setProperty('padding', '0.4rem 0.8rem', 'important');
        button.style.setProperty('line-height', '1.5', 'important');
    });
}

function autoResizeTextarea(textarea) {
    if (!textarea) {
        return;
    }
    if (!textarea.dataset.initialHeight) {
        textarea.dataset.initialHeight = textarea.offsetHeight;
    }
    textarea.style.height = 'auto';
    var baseHeight = parseInt(textarea.dataset.initialHeight, 10) || textarea.offsetHeight;
    if (textarea.value && textarea.value.trim() !== '' && textarea.scrollHeight > baseHeight + 2) {
        textarea.style.height = textarea.scrollHeight + 'px';
    } else {
        textarea.style.height = baseHeight + 'px';
    }
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

    // Abrir ficha ou edição automaticamente quando vindo com ?ficha=ID ou ?edit=ID
    var urlParams = new URLSearchParams(window.location.search);
    var fichaParam = urlParams.get('ficha');
    var editParam = urlParams.get('edit');
    if (fichaParam || editParam) {
        var targetId = fichaParam || editParam;
        var alunoData = null;
        var alunoRows = document.querySelectorAll('.aluno-row[data-aluno]');
        alunoRows.forEach(function(row) {
            if (alunoData) {
                return;
            }
            var dataStr = row.getAttribute('data-aluno');
            if (!dataStr) {
                return;
            }
            try {
                var parsed = JSON.parse(dataStr);
                if (String(parsed.id) === String(targetId)) {
                    alunoData = parsed;
                }
            } catch (e) {
                // Ignorar erro de parse
            }
        });
        if (alunoData) {
            if (editParam) {
                editAluno(alunoData);
            } else {
                viewFichaAluno(alunoData);
            }
        }
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

    // Auto-ajustar altura de textareas no modal de aluno
    var modalAluno = document.getElementById('modalAluno');
    if (modalAluno) {
        modalAluno.addEventListener('shown.bs.modal', function() {
            var textareas = modalAluno.querySelectorAll('textarea');
            textareas.forEach(function(textarea) {
                autoResizeTextarea(textarea);
            });
        });

        modalAluno.addEventListener('input', function(e) {
            if (e.target && e.target.tagName === 'TEXTAREA') {
                autoResizeTextarea(e.target);
            }
        });

        modalAluno.addEventListener('hidden.bs.modal', function() {
            var params = new URLSearchParams(window.location.search);
            var returnTo = params.get('return_to');
            var editParam = params.get('edit');
            if (returnTo && editParam) {
                window.location.href = returnTo;
            }
        });
    }

    function createAnexoInput() {
        var wrapper = document.createElement('div');
        wrapper.className = 'input-group mb-2';

        var input = document.createElement('input');
        input.type = 'file';
        input.name = 'anexos[]';
        input.className = 'form-control';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-danger btn-remove-anexo-input';
        btn.title = 'Remover arquivo';
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        btn.addEventListener('click', function() {
            wrapper.remove();
        });

        wrapper.appendChild(input);
        wrapper.appendChild(btn);
        return wrapper;
    }

    function wireRemoveButtons(container) {
        var buttons = container.querySelectorAll('.btn-remove-anexo-input');
        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var group = btn.closest('.input-group');
                if (group) {
                    group.remove();
                }
            });
        });
    }

    var modalAnexosContainer = document.getElementById('modal_anexos_container');
    var btnAddModalAnexo = document.getElementById('btnAddModalAnexo');
    if (modalAnexosContainer && btnAddModalAnexo) {
        wireRemoveButtons(modalAnexosContainer);
        btnAddModalAnexo.addEventListener('click', function() {
            modalAnexosContainer.appendChild(createAnexoInput());
        });
    }

    var editAnexosContainer = document.getElementById('edit_anexos_container');
    var btnAddEditAnexo = document.getElementById('btnAddEditAnexo');
    if (editAnexosContainer && btnAddEditAnexo) {
        wireRemoveButtons(editAnexosContainer);
        btnAddEditAnexo.addEventListener('click', function() {
            editAnexosContainer.appendChild(createAnexoInput());
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
    
    // Botões de visualizar ficha
    var viewFichaButtons = document.querySelectorAll('.btn-view-ficha');
    viewFichaButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            var alunoData = this.getAttribute('data-aluno') ? JSON.parse(this.getAttribute('data-aluno')) : null;
            if (!alunoData && this.closest('.aluno-row')) {
                alunoData = JSON.parse(this.closest('.aluno-row').getAttribute('data-aluno'));
            }
            if (alunoData) {
                viewFichaAluno(alunoData);
            }
            hideContextMenu();
        });
    });
    
    // Menu contextual ao clicar na linha do aluno
    var alunoRows = document.querySelectorAll('.aluno-row');
    var contextMenu = document.getElementById('alunoContextMenu');
    var isAdmin = document.body.getAttribute('data-is-admin') === '1' || 
                  document.body.getAttribute('data-is-admin') === 'true';
    
    alunoRows.forEach(function(row) {
        row.addEventListener('click', function(e) {
            // Não mostrar menu se clicar em um link ou botão dentro da linha
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            var alunoData = JSON.parse(this.getAttribute('data-aluno'));
            
            // Posicionar menu próximo ao ponto de clique
            var rect = this.getBoundingClientRect();
            var clickX = e.clientX;
            var clickY = e.clientY;
            
            contextMenu.style.display = 'block';
            contextMenu.style.position = 'fixed';
            contextMenu.style.top = clickY + 'px';
            contextMenu.style.left = Math.min(clickX, window.innerWidth - 220) + 'px';
            contextMenu.style.zIndex = '1050';
            contextMenu.classList.add('show');
            
            // Preencher dados do aluno no menu
            var btnViewFicha = contextMenu.querySelector('.btn-view-ficha');
            btnViewFicha.setAttribute('data-aluno', JSON.stringify(alunoData));
            
            var linkVerEventos = document.getElementById('contextMenuVerEventos');
            linkVerEventos.href = 'registrar_evento.php?aluno_id=' + alunoData.id;
            
            // Configurar link do prontuário CAE se existir
            var linkProntuarioCAE = document.getElementById('contextMenuProntuarioCAE');
            if (linkProntuarioCAE) {
                linkProntuarioCAE.href = 'prontuario_ae.php?aluno_id=' + alunoData.id;
            }
            
            // Mostrar ações de admin se for admin
            var adminActions = document.getElementById('contextMenuAdminActions');
            if (isAdmin) {
                adminActions.style.display = 'block';
                var linkGerenciarTurmas = document.getElementById('contextMenuGerenciarTurmas');
                linkGerenciarTurmas.href = 'aluno_turmas.php?id=' + alunoData.id;
                document.getElementById('contextMenuDeleteId').value = alunoData.id;
            } else {
                adminActions.style.display = 'none';
            }
        });
    });
    
    // Fechar menu ao clicar fora
    function hideContextMenu() {
        if (contextMenu) {
            contextMenu.style.display = 'none';
            contextMenu.classList.remove('show');
        }
    }
    
    // Fechar menu ao clicar em links ou botões do menu
    if (contextMenu) {
        contextMenu.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                // Pequeno delay para permitir navegação
                setTimeout(hideContextMenu, 100);
            }
        });
    }
    
    document.addEventListener('click', function(e) {
        if (contextMenu && !contextMenu.contains(e.target) && !e.target.closest('.aluno-row')) {
            hideContextMenu();
        }
    });
    
    // Fechar menu ao pressionar ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideContextMenu();
        }
    });
    
    // Botão editar da ficha
    var btnEditarDaFicha = document.getElementById('btnEditarDaFicha');
    if (btnEditarDaFicha) {
        btnEditarDaFicha.addEventListener('click', function() {
            var modalFicha = document.getElementById('modalFichaAluno');
            if (modalFicha) {
                var alunoDataStr = modalFicha.getAttribute('data-aluno-data');
                if (alunoDataStr) {
                    try {
                        var alunoData = JSON.parse(alunoDataStr);
                        // Fechar modal da ficha
                        var modalInstance = bootstrap.Modal.getInstance(modalFicha);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                        // Abrir modal de edição
                        editAluno(alunoData);
                    } catch (e) {
                        console.error('Erro ao processar dados do aluno:', e);
                    }
                }
            }
        });
    }
    
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
    
    // Mostrar/ocultar campo de prontuário CAE quando tipo de evento mudar
    var modalTipoEvento = document.getElementById('modal_tipo_evento_id');
    var prontuarioContainer = document.getElementById('prontuario_cae_container');
    if (modalTipoEvento && prontuarioContainer) {
        modalTipoEvento.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var geraProntuario = selectedOption && selectedOption.dataset ? selectedOption.dataset.geraProntuario : '0';
            if (geraProntuario === '1') {
                prontuarioContainer.style.display = 'block';
            } else {
                prontuarioContainer.style.display = 'none';
                document.getElementById('modal_prontuario_cae').value = '';
            }
        });
    }
    
    // Mostrar/ocultar campo de prontuário CAE quando tipo de evento mudar no modal de edição
    var editTipoEvento = document.getElementById('edit_tipo_evento_id');
    var editProntuarioContainer = document.getElementById('edit_prontuario_cae_container');
    if (editTipoEvento && editProntuarioContainer) {
        editTipoEvento.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var geraProntuario = selectedOption && selectedOption.dataset ? selectedOption.dataset.geraProntuario : '0';
            if (geraProntuario === '1') {
                editProntuarioContainer.style.display = 'block';
            } else {
                editProntuarioContainer.style.display = 'none';
                document.getElementById('edit_prontuario_cae').value = '';
            }
        });
    }
    
    // Linhas clicáveis para mostrar observações (mantido para compatibilidade)
    var observacoesRows = document.querySelectorAll('.row-observacoes');
    observacoesRows.forEach(function(row) {
        row.addEventListener('click', function() {
            var eventoData = JSON.parse(this.getAttribute('data-evento'));
            showObservacoes(eventoData);
        });
    });
    
    // Menu contextual ao clicar na linha do evento
    var eventoRows = document.querySelectorAll('.evento-row');
    var eventoContextMenu = document.getElementById('eventoContextMenu');
    
    eventoRows.forEach(function(row) {
        row.addEventListener('click', function(e) {
            // Não mostrar menu se clicar em um link ou botão dentro da linha
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            var eventoData = JSON.parse(this.getAttribute('data-evento'));
            
            // Posicionar menu próximo ao ponto de clique
            var rect = this.getBoundingClientRect();
            var clickX = e.clientX;
            var clickY = e.clientY;
            
            eventoContextMenu.style.display = 'block';
            eventoContextMenu.style.position = 'fixed';
            eventoContextMenu.style.top = clickY + 'px';
            eventoContextMenu.style.left = Math.min(clickX, window.innerWidth - 220) + 'px';
            eventoContextMenu.style.zIndex = '1050';
            eventoContextMenu.classList.add('show');
            
            // Verificar se está em registrar_evento.php
            var currentPage = window.location.pathname.split('/').pop();
            var isRegistrarEvento = (currentPage === 'registrar_evento.php');
            
            // Se não tiver permissão para editar ou deletar e estiver em registrar_evento.php, mostrar mensagem
            if (isRegistrarEvento && !eventoData.can_edit && !eventoData.can_delete) {
                alert('Não pode ser alterado');
                hideEventoContextMenu();
                return;
            }
            
            // Preencher dados do evento no menu
            var btnVerObservacoes = document.getElementById('contextMenuVerObservacoes');
            if (btnVerObservacoes) {
                btnVerObservacoes.onclick = function() {
                    showObservacoes(eventoData);
                    hideEventoContextMenu();
                };
            }
            
            // Mostrar ações de editar/excluir se tiver permissão
            var eventoActions = document.getElementById('contextMenuEventoActions');
            if (eventoData.can_edit || eventoData.can_delete) {
                eventoActions.style.display = 'block';
                
                if (eventoData.can_edit) {
                    var btnEditar = document.getElementById('contextMenuEditarEvento');
                    btnEditar.style.display = 'block';
                    btnEditar.onclick = function() {
                        editEvento(eventoData);
                        hideEventoContextMenu();
                    };
                } else {
                    document.getElementById('contextMenuEditarEvento').style.display = 'none';
                }
                
                if (eventoData.can_delete) {
                    var linkExcluir = document.getElementById('contextMenuExcluirEvento');
                    linkExcluir.style.display = 'block';
                    // Construir URL de exclusão preservando filtros
                    var urlParams = new URLSearchParams(window.location.search);
                    var deleteUrl = currentPage + '?delete=' + eventoData.id;
                    
                    // Se estiver em registrar_evento.php, preservar aluno_id
                    if (isRegistrarEvento && urlParams.get('aluno_id')) {
                        deleteUrl += '&aluno_id=' + urlParams.get('aluno_id');
                    } else {
                        // Se estiver em eventos.php, preservar filtros
                        if (urlParams.get('filtro_curso')) {
                            deleteUrl += '&filtro_curso=' + urlParams.get('filtro_curso');
                        }
                        if (urlParams.get('filtro_turma')) {
                            deleteUrl += '&filtro_turma=' + urlParams.get('filtro_turma');
                        }
                        if (urlParams.get('filtro_nome')) {
                            deleteUrl += '&filtro_nome=' + encodeURIComponent(urlParams.get('filtro_nome'));
                        }
                    }
                    linkExcluir.href = deleteUrl;
                    linkExcluir.onclick = function(e) {
                        if (!confirm('Tem certeza que deseja excluir este evento?')) {
                            e.preventDefault();
                            hideEventoContextMenu();
                        }
                    };
                } else {
                    document.getElementById('contextMenuExcluirEvento').style.display = 'none';
                }
            } else {
                eventoActions.style.display = 'none';
            }
        });
    });
    
    // Fechar menu ao clicar fora
    function hideEventoContextMenu() {
        if (eventoContextMenu) {
            eventoContextMenu.style.display = 'none';
            eventoContextMenu.classList.remove('show');
        }
    }
    
    // Fechar menu ao clicar em links ou botões do menu
    if (eventoContextMenu) {
        eventoContextMenu.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                setTimeout(hideEventoContextMenu, 100);
            }
        });
    }
    
    document.addEventListener('click', function(e) {
        if (eventoContextMenu && !eventoContextMenu.contains(e.target) && !e.target.closest('.evento-row')) {
            hideEventoContextMenu();
        }
    });
    
    // Fechar menu ao pressionar ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideEventoContextMenu();
        }
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
        
        // Inicializar campos e aplicar estilos quando o modal for aberto
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
            // Aplicar estilos
            applyModalStyles(this);
        });
        createUserModal.addEventListener('shown.bs.modal', function() {
            applyModalStyles(this);
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
    if (modalAluno) {
        modalAluno.addEventListener('show.bs.modal', function() {
            applyModalStyles(this);
        });
        modalAluno.addEventListener('shown.bs.modal', function() {
            applyModalStyles(this);
        });
    }

    var editEventoModal = document.getElementById('editEventoModal');
    if (editEventoModal) {
        editEventoModal.addEventListener('show.bs.modal', function() {
            applyModalStyles(this);
        });
        editEventoModal.addEventListener('shown.bs.modal', function() {
            applyModalStyles(this);
        });
    }

    var modalRegistrarEvento = document.getElementById('modalRegistrarEvento');
    if (modalRegistrarEvento) {
        modalRegistrarEvento.addEventListener('show.bs.modal', function() {
            applyModalStyles(this);
        });
        modalRegistrarEvento.addEventListener('shown.bs.modal', function() {
            applyModalStyles(this);
        });
    }
    
    // Modal de ficha do aluno - aplicar estilos quando aberto
    var modalFichaAluno = document.getElementById('modalFichaAluno');
    if (modalFichaAluno) {
        modalFichaAluno.addEventListener('show.bs.modal', function() {
            applyModalStyles(this);
        });
        modalFichaAluno.addEventListener('shown.bs.modal', function() {
            applyModalStyles(this);
        });
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


