var reservas = [];
var itens = [];
var meusEmprestimos = [];
var itemSelecionado = 0;
var situacaoSelecionada = '';
var inicio = '';
var fim = '';
var hoje = new Date();
var ano = hoje.getFullYear();
var mes = hoje.getMonth();
var nomesMeses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
var timerMensagens;

document.getElementById('bookingForm').addEventListener('submit', enviarSolicitacao);
document.getElementById('mesAnterior').addEventListener('click', function () {
    mudarMes(-1);
});
document.getElementById('proximoMes').addEventListener('click', function () {
    mudarMes(1);
});
document.getElementById('limparFormulario').addEventListener('click', limparFormulario);

carregarDados(true);

function escreverData(anoRecebido, mesRecebido, diaRecebido) {
    var m = mesRecebido < 10 ? '0' + mesRecebido : mesRecebido;
    var d = diaRecebido < 10 ? '0' + diaRecebido : diaRecebido;
    return anoRecebido + '-' + m + '-' + d;
}

function escaparHtml(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatarDataBR(dataISO) {
    if (!dataISO) {
        return '';
    }

    var partes = dataISO.split('-');
    if (partes.length !== 3) {
        return dataISO;
    }

    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function carregarDados(restaurarEstadoSalvo) {
    fetch('/api/usuario/locacao.php', {
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
                throw new Error(resultado.dados.erro || 'Não foi possível carregar a página.');
            }

            aplicarDados(resultado.dados, restaurarEstadoSalvo);
        })
        .catch(function (erro) {
            mostrarMensagem('', erro.message);
        });
}

function aplicarDados(dados, restaurarEstadoSalvo) {
    var itemMantido = itemSelecionado;

    document.getElementById('nomeUsuario').textContent = dados.usuario.nome;
    itens = dados.itens || [];
    reservas = dados.reservas || [];
    meusEmprestimos = dados.meusEmprestimos || [];

    renderizarItens();
    renderizarHistorico();

    if (restaurarEstadoSalvo) {
        restaurarFormulario();
    } else {
        itemSelecionado = itemMantido;
        atualizarSelecaoVisual();
        mostrarPeriodo();
        desenharCalendario();
    }

    mostrarMensagem(dados.mensagem || '', dados.erro || '');
}

function renderizarItens() {
    var tabela = document.getElementById('itensTabela');

    tabela.innerHTML = itens.map(function (item) {
        var selecionavel = item.situacao_item === 'disponivel';
        return '<tr id="linha-' + Number(item.id_item) + '" data-id="' + Number(item.id_item) +
            '" data-descricao="' + escaparHtml(item.descricao) +
            '" data-taxa="' + escaparHtml(item.taxa_uso) +
            '" data-situacao="' + escaparHtml(item.situacao_item) +
            '" class="' + (selecionavel ? 'item-row--selectable' : '') + '">' +
            '<td><span class="item-id">#' + escaparHtml(item.id_item) + '</span></td>' +
            '<td><span class="item-description">' + escaparHtml(item.descricao) + '</span></td>' +
            '<td><span class="availability ' + (selecionavel ? 'availability--available' : 'availability--unavailable') + '">' +
            escaparHtml(item.situacao_item) + '</span></td>' +
            '<td><span class="money">' + escaparHtml(item.taxa_uso) + '</span></td>' +
            '</tr>';
    }).join('');

    tabela.querySelectorAll('tr[data-id]').forEach(function (linha) {
        if (linha.dataset.situacao === 'disponivel') {
            linha.addEventListener('click', function () {
                selecionarItem(linha);
            });
        }
    });

    atualizarSelecaoVisual();
}

function renderizarHistorico() {
    var historico = document.getElementById('historicoReservas');

    if (meusEmprestimos.length === 0) {
        historico.innerHTML =
            '<div class="empty-state">' +
            '<strong>Nenhuma reserva ainda</strong>' +
            '<span>Suas solicitações aparecerão aqui.</span>' +
            '</div>';
        return;
    }

    historico.innerHTML = '<div class="history-list">' + meusEmprestimos.map(function (emp) {
        var classeStatus = emp.status === 'confirmado'
            ? 'status--confirmed'
            : emp.status === 'pendente' ? 'status--pending' : 'status--other';
        var acao = emp.status === 'pendente'
            ? '<form class="reservation-actions" method="post" data-id-emprestimo="' + Number(emp.id_emprestimo) + '">' +
              '<input type="hidden" name="id_emprestimo" value="' + Number(emp.id_emprestimo) + '">' +
              '<button class="btn btn-danger" type="submit" name="excluir_emprestimo" value="1">Excluir reserva</button>' +
              '</form>'
            : '';

        return '<article class="reservation-card">' +
            '<div class="reservation-head">' +
            '<strong class="reservation-name">' + escaparHtml(emp.descricao) + '</strong>' +
            '<span class="status ' + classeStatus + '">' + escaparHtml(primeiraMaiuscula(emp.status)) + '</span>' +
            '</div>' +
            '<dl class="reservation-dates">' +
            '<div><dt>Retirada</dt><dd>' + formatarData(emp.data_hora_retirada) + '</dd></div>' +
            '<div><dt>Devolução</dt><dd>' + formatarData(emp.data_hora_devolucao) + '</dd></div>' +
            '</dl>' +
            '<p class="reservation-requested">Solicitado em ' + formatarDataHora(emp.data_hora_solicitacao) + '</p>' +
            acao +
            '</article>';
    }).join('') + '</div>';

    historico.querySelectorAll('.reservation-actions').forEach(function (form) {
        form.addEventListener('submit', excluirReserva);
    });
}

function formatarData(valor) {
    var data = String(valor || '').split(' ')[0].split('-');
    return data.length === 3 ? data[2] + '/' + data[1] + '/' + data[0] : '';
}

function formatarDataHora(valor) {
    var texto = String(valor || '');
    return formatarData(texto) + (texto.length >= 16 ? ' ' + texto.substring(11, 16) : '');
}

function primeiraMaiuscula(valor) {
    var texto = String(valor || '');
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function diaOcupado(data) {
    if (itemSelecionado === 0) {
        return true;
    }

    for (var i = 0; i < reservas.length; i++) {
        if (Number(reservas[i].id_item) === itemSelecionado &&
            data >= reservas[i].inicio && data <= reservas[i].fim) {
            return true;
        }
    }

    return false;
}

function mostrarPeriodo() {
    if (itemSelecionado === 0) {
        document.getElementById('periodoTexto').textContent = 'Nenhum item selecionado.';
    } else if (inicio === '') {
        document.getElementById('periodoTexto').textContent = 'Escolha o primeiro dia e depois o último.';
    } else if (fim === '') {
        document.getElementById('periodoTexto').textContent = 'Início: ' + formatarDataBR(inicio) + ' | Agora escolha o último dia.';
    } else {
        document.getElementById('periodoTexto').textContent = 'Período: ' + formatarDataBR(inicio) + ' até ' + formatarDataBR(fim);
    }

    document.getElementById('data_inicio').value = inicio;
    document.getElementById('data_fim').value = fim;
    document.getElementById('botaoSolicitar').disabled =
        situacaoSelecionada !== 'disponivel' || inicio === '' || fim === '';
}

function desenharCalendario() {
    var calendario = document.getElementById('calendario');
    calendario.innerHTML = '';
    document.getElementById('mesAno').textContent = nomesMeses[mes] + ' de ' + ano;

    var semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    for (var s = 0; s < 7; s++) {
        var nomeDia = document.createElement('span');
        nomeDia.className = 'calendar-weekday';
        nomeDia.textContent = semana[s];
        calendario.appendChild(nomeDia);
    }

    var primeiroDia = new Date(ano, mes, 1).getDay();
    var quantidadeDias = new Date(ano, mes + 1, 0).getDate();

    for (var v = 0; v < primeiroDia; v++) {
        calendario.appendChild(document.createElement('span'));
    }

    for (var dia = 1; dia <= quantidadeDias; dia++) {
        var data = escreverData(ano, mes + 1, dia);
        var botao = document.createElement('button');
        botao.className = 'calendar-day';
        botao.type = 'button';
        botao.textContent = dia;
        botao.value = data;

        if (itemSelecionado === 0 ||
            data < escreverData(hoje.getFullYear(), hoje.getMonth() + 1, hoje.getDate()) ||
            diaOcupado(data) ||
            situacaoSelecionada !== 'disponivel') {
            botao.disabled = true;
            botao.classList.add('is-disabled');
        } else if (inicio !== '' &&
            ((fim === '' && data === inicio) || (fim !== '' && data >= inicio && data <= fim))) {
            botao.classList.add('is-selected');
        }

        botao.addEventListener('click', function () {
            escolherDia(this.value);
        });
        calendario.appendChild(botao);
    }
}

function escolherDia(data) {
    if (itemSelecionado === 0) {
        return;
    }

    if (inicio === '' || fim !== '' || data < inicio) {
        inicio = data;
        fim = '';
    } else {
        fim = data;

        for (var i = 0; i < reservas.length; i++) {
            if (Number(reservas[i].id_item) === itemSelecionado &&
                reservas[i].inicio <= fim && reservas[i].fim >= inicio) {
                alert('O período escolhido inclui dias já reservados. Escolha outras datas.');
                fim = '';
                break;
            }
        }
    }

    mostrarPeriodo();
    desenharCalendario();
}

function mudarMes(passo) {
    mes += passo;

    if (mes > 11) {
        mes = 0;
        ano++;
    } else if (mes < 0) {
        mes = 11;
        ano--;
    }

    desenharCalendario();
}

function selecionarItem(linha) {
    if (linha.dataset.situacao !== 'disponivel') {
        return;
    }

    var idClicado = Number(linha.dataset.id);
    if (itemSelecionado === idClicado) {
        limparFormulario();
        return;
    }

    itemSelecionado = idClicado;
    situacaoSelecionada = linha.dataset.situacao;
    inicio = '';
    fim = '';
    atualizarSelecaoVisual();
    mostrarPeriodo();
    desenharCalendario();
}

function atualizarSelecaoVisual() {
    var linhas = document.querySelectorAll('tr[data-id]');
    var linhaSelecionada = null;

    linhas.forEach(function (linha) {
        var selecionada = Number(linha.dataset.id) === itemSelecionado;
        linha.classList.toggle('is-selected', selecionada);
        if (selecionada) {
            linhaSelecionada = linha;
        }
    });

    document.getElementById('id_item').value = itemSelecionado || '';
    document.getElementById('instrucaoItem').style.display = linhaSelecionada ? 'none' : 'block';
    situacaoSelecionada = linhaSelecionada ? linhaSelecionada.dataset.situacao : '';
}

function limparFormulario() {
    itemSelecionado = 0;
    situacaoSelecionada = '';
    inicio = '';
    fim = '';
    sessionStorage.removeItem('pi_id_item');
    sessionStorage.removeItem('pi_data_inicio');
    sessionStorage.removeItem('pi_data_fim');
    atualizarSelecaoVisual();
    mostrarPeriodo();
    desenharCalendario();
}

function restaurarFormulario() {
    var salvoItem = sessionStorage.getItem('pi_id_item');
    var salvoInicio = sessionStorage.getItem('pi_data_inicio');
    var salvoFim = sessionStorage.getItem('pi_data_fim');

    if (salvoItem && Number(salvoItem) > 0) {
        var linha = document.querySelector('tr[data-id="' + Number(salvoItem) + '"]');
        if (linha && linha.dataset.situacao === 'disponivel') {
            itemSelecionado = 0;
            selecionarItem(linha);
            inicio = salvoInicio || '';
            fim = salvoFim || '';
            mostrarPeriodo();
            desenharCalendario();
            return;
        }
    }

    desenharCalendario();
}

function salvarEstadoFormulario() {
    sessionStorage.setItem('pi_id_item', itemSelecionado);
    sessionStorage.setItem('pi_data_inicio', inicio);
    sessionStorage.setItem('pi_data_fim', fim);
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

function enviarSolicitacao(event) {
    event.preventDefault();
    salvarEstadoFormulario();

    var formulario = event.currentTarget;
    var dadosFormulario = new FormData(formulario);
    dadosFormulario.set('solicitar', '1');

    fetch(formulario.action, {
        method: 'POST',
        body: dadosFormulario,
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
                throw new Error(resultado.dados.erro || 'Não foi possível enviar a solicitação.');
            }

            inicio = '';
            fim = '';
            aplicarDados(resultado.dados, false);
        })
        .catch(function (erro) {
            mostrarMensagem('', erro.message);
        });
}

function excluirReserva(event) {
    event.preventDefault();

    if (!confirm('Tem certeza que deseja excluir esta reserva?')) {
        return;
    }

    var formulario = event.currentTarget;
    var dadosFormulario = new FormData(formulario);
    dadosFormulario.set('excluir_emprestimo', '1');

    fetch('/api/usuario/locacao.php', {
        method: 'POST',
        body: dadosFormulario,
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
                throw new Error(resultado.dados.erro || 'Não foi possível excluir a reserva.');
            }
            aplicarDados(resultado.dados, false);
        })
        .catch(function (erro) {
            mostrarMensagem('', erro.message);
        });
}

setInterval(function () {
    salvarEstadoFormulario();
    location.reload();
}, 30000);
