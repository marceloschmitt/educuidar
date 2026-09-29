<!-- Modal: frequência por disciplina do aluno (dados do SIGAA) -->
<div class="modal fade" id="modalFrequenciaAluno" tabindex="-1" aria-labelledby="modalFrequenciaAlunoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFrequenciaAlunoLabel">
                    <i class="bi bi-calendar-check"></i> Frequência — <span id="freqAlunoNome"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-3 py-2 small text-muted border-bottom" id="freqAlunoInfo"></div>
                <div id="freqAlunoConteudo"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var requisicaoAtual = 0;

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto == null ? '' : String(texto);
        return div.innerHTML;
    }

    function decimal(valor) {
        return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
    }

    function plural(n, singular, pluralTexto) {
        return n.toLocaleString('pt-BR') + ' ' + (n === 1 ? singular : pluralTexto);
    }

    function montar(dados) {
        var limite = dados.limite_faltas;
        var html = '';
        if (dados.geral) {
            var critica = dados.geral.frequencia < 100 - limite;
            html += '<div class="freq-linha freq-geral">'
                + '<div class="d-flex justify-content-between align-items-baseline gap-2">'
                + '<span class="fw-bold">Frequência geral</span>'
                + '<span class="fs-5 fw-bold' + (critica ? ' text-danger' : '') + '">' + decimal(dados.geral.frequencia) + '</span></div>'
                + '<div class="small text-muted">' + plural(dados.geral.faltas, 'falta', 'faltas') + ' em '
                + plural(dados.geral.aulas, 'período', 'períodos') + ', somando todas as disciplinas</div></div>';
        }
        dados.disciplinas.forEach(function (d) {
            var critico = d.percentual > limite;
            var cor = critico ? 'var(--bs-danger)' : 'var(--bs-success)';
            html += '<div class="freq-linha">'
                + '<div class="d-flex justify-content-between align-items-baseline gap-2">'
                + '<span class="fw-semibold">' + escapar(d.nome) + '</span>'
                + '<span class="fw-bold text-nowrap' + (critico ? ' text-danger' : '') + '">' + decimal(d.percentual) + ' faltas</span></div>'
                + '<div class="progress my-1" style="height: 6px;"><div class="progress-bar" style="width: '
                + Math.min(100, d.percentual) + '%; background-color: ' + cor + ';"></div></div>'
                + '<div class="small text-muted">' + plural(d.faltas, 'falta', 'faltas') + ' em '
                + plural(d.aulas, 'período', 'períodos') + ' · frequência ' + decimal(d.frequencia) + '</div></div>';
        });
        return html;
    }

    window.viewFrequenciaAluno = function (aluno) {
        var modalEl = document.getElementById('modalFrequenciaAluno');
        if (!modalEl || !aluno || typeof bootstrap === 'undefined') return;
        var info = document.getElementById('freqAlunoInfo');
        var conteudo = document.getElementById('freqAlunoConteudo');
        var requisicao = ++requisicaoAtual;

        document.getElementById('freqAlunoNome').textContent = aluno.nome_social || aluno.nome || '';
        info.textContent = '';
        conteudo.innerHTML = '<p class="text-muted text-center my-4">Carregando...</p>';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        fetch('api/get_frequencia_aluno.php?id=' + encodeURIComponent(aluno.id))
            .then(function (res) { return res.json(); })
            .then(function (dados) {
                if (requisicao !== requisicaoAtual) return;
                if (dados.error) {
                    conteudo.innerHTML = '<p class="text-danger text-center my-4">' + escapar(dados.error) + '</p>';
                    return;
                }
                if (!dados.disciplinas.length) {
                    conteudo.innerHTML = '<p class="text-muted text-center my-4">Nenhuma frequência do SIGAA para este aluno em '
                        + dados.ano + '.</p>';
                    return;
                }
                info.textContent = 'Percentual de faltas em cada disciplina em ' + dados.ano + ', segundo o SIGAA'
                    + (dados.atualizado_em ? ' (atualizado em ' + dados.atualizado_em + ')' : '')
                    + '. Em vermelho, acima de ' + dados.limite_faltas + '% de faltas (frequência abaixo de '
                    + (100 - dados.limite_faltas) + '%).';
                conteudo.innerHTML = montar(dados);
            })
            .catch(function () {
                if (requisicao !== requisicaoAtual) return;
                conteudo.innerHTML = '<p class="text-danger text-center my-4">Erro ao carregar a frequência.</p>';
            });
    };
})();
</script>
