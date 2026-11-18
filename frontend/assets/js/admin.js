var timerRefresh;
var timerMensagens;

carregarSolicitacoes();
iniciarTemporizador();

function escaparHtml(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function carregarSolicitacoes() {
    fetch('/api/admin/retira.php', {
        credentials: 'same-origin'
    })
        .then(function (resposta) {
            return resposta.json().then(function (dados) {
                return { resposta: resposta, dados: dados };
            });
        })
        .then(function (resultado) {
            if (resultado.resposta.status === 401 || resultado.dados.redirect) {
                window.location.href = resultado.dados.redirect || '/auth/login.html';
                return;
            }
            if (resultado.resposta.status === 403) {
                window.location.href = resultado.dados.redirect || '/usuario/locacao.html';
                return;
            }
            if (!resultado.resposta.ok) {
                throw new Error(resultado.dados.erro || 'Não foi possível carregar as solicitações.');
            }

            document.getElementById('nomeUsuario').textContent = resultado.dados.usuario.nome;
            renderizarSolicitacoes(resultado.dados.emprestimosPendentes || []);
            mostrarMensagem(resultado.dados.mensagem || '', resultado.dados.erro || '');
        })
        .catch(function (erro) {
            mostrarMensagem('', erro.message);
        });
}

function renderizarSolicitacoes(emprestimos) {
    var conteudo = document.getElementById('solicitacoesConteudo');

    if (emprestimos.length === 0) {
        conteudo.innerHTML =
            '<div class="empty-state">' +
            '<strong>Tudo em dia</strong>' +
            '<span>Nenhuma solicitação de empréstimo pendente para retirada no momento.</span>' +
            '</div>';
        return;
    }

    conteudo.innerHTML =
        '<div class="table-wrap"><table class="data-table admin-table">' +
        '<thead><tr>' +
        '<th># ID</th><th>Morador</th><th>Item solicitado</th><th>Data/hora</th><th>Observações</th><th>Ação</th>' +
        '</tr></thead><tbody>' +
        emprestimos.map(function (emp) {
            return '<tr>' +
                '<td><span class="request-id">#' + escaparHtml(emp.id_emprestimo) + '</span></td>' +
                '<td><span class="request-person">' + escaparHtml(emp.nome_morador) + '</span></td>' +
                '<td>' + escaparHtml(emp.nome_item) + '</td>' +
                '<td><span class="request-date">' + formatarDataHora(emp.data_hora_solicitacao) + '</span></td>' +
                '<td><span class="request-note">' + escaparHtml(emp.observacoes || '-') + '</span></td>' +
                '<td><form class="admin-action-form" method="post" data-id-emprestimo="' + Number(emp.id_emprestimo) + '">' +
                '<input type="hidden" name="id_emprestimo" value="' + Number(emp.id_emprestimo) + '">' +
                '<button class="btn" type="submit">Confirmar retirada</button>' +
                '</form></td>' +
                '</tr>';
        }).join('') +
        '</tbody></table></div>';

    conteudo.querySelectorAll('.admin-action-form').forEach(function (form) {
        form.addEventListener('submit', confirmarRetirada);
    });
}

function formatarDataHora(valor) {
    var texto = String(valor || '');
    var data = texto.split(' ')[0].split('-');
    if (data.length !== 3) {
        return '';
    }
    return data[2] + '/' + data[1] + '/' + data[0] +
        (texto.length >= 16 ? ' ' + texto.substring(11, 16) : '');
}

function confirmarRetirada(event) {
    event.preventDefault();
    pararTemporizador();

    if (!confirm('Confirma a entrega do item?')) {
        iniciarTemporizador();
        return;
    }

    var formulario = event.currentTarget;
    fetch('/api/admin/retira.php', {
        method: 'POST',
        body: new FormData(formulario),
        credentials: 'same-origin'
    })
        .then(function (resposta) {
            return resposta.json().then(function (dados) {
                return { resposta: resposta, dados: dados };
            });
        })
        .then(function (resultado) {
            if (resultado.resposta.status === 401 || resultado.dados.redirect) {
                window.location.href = resultado.dados.redirect || '/auth/login.html';
                return;
            }
            if (!resultado.resposta.ok) {
                throw new Error(resultado.dados.erro || 'Não foi possível processar a retirada.');
            }
            document.getElementById('nomeUsuario').textContent = resultado.dados.usuario.nome;
            renderizarSolicitacoes(resultado.dados.emprestimosPendentes || []);
            mostrarMensagem(resultado.dados.mensagem || '', resultado.dados.erro || '');
            iniciarTemporizador();
        })
        .catch(function (erro) {
            mostrarMensagem('', erro.message);
            iniciarTemporizador();
        });
}

function mostrarMensagem(mensagem, erro) {
    var sucesso = document.getElementById('mensagemSucesso');
    var falha = document.getElementById('mensagemErro');

    sucesso.textContent = mensagem || '';
    falha.textContent = erro || '';
    sucesso.hidden = !mensagem;
    falha.hidden = !erro;

    clearTimeout(timerMensagens);
    if (mensagem || erro) {
        timerMensagens = setTimeout(function () {
            sucesso.hidden = true;
            falha.hidden = true;
        }, 5000);
    }
}

function iniciarTemporizador() {
    clearInterval(timerRefresh);
    timerRefresh = setInterval(function () {
        location.reload();
    }, 30000);
}

function pararTemporizador() {
    clearInterval(timerRefresh);
}
