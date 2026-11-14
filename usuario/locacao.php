<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['nome'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once __DIR__ . '/../config/conexao.php';
$db = conectarBanco();
$mensagem = '';
$erro = '';

$idSelecionado = isset($_POST['id_item']) ? (int) $_POST['id_item'] : 0;
$dataInicio = isset($_POST['data_inicio']) && is_string($_POST['data_inicio']) ? $_POST['data_inicio'] : '';
$dataFim = isset($_POST['data_fim']) && is_string($_POST['data_fim']) ? $_POST['data_fim'] : '';
$idUsuarioLogado = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir_emprestimo'])) {
    $idEmprestimoExcluir = isset($_POST['id_emprestimo']) ? (int) $_POST['id_emprestimo'] : 0;

    if ($idUsuarioLogado == 0) {
        $erro = 'Usuário não identificado na sessão.';
    } elseif ($idEmprestimoExcluir <= 0) {
        $erro = 'Reserva inválida para exclusão.';
    } else {
        $checaReserva = $db->prepare("SELECT status FROM Emprestimo WHERE id_emprestimo = ? AND id_morador = ?");
        $checaReserva->execute([$idEmprestimoExcluir, $idUsuarioLogado]);
        $statusReserva = $checaReserva->fetchColumn();

        if ($statusReserva === false) {
            $erro = 'Reserva não encontrada ou sem permissão para exclusão.';
        } elseif ($statusReserva != 'pendente') {
            $erro = 'Apenas reservas pendentes podem ser excluídas.';
        } else {
            $deletaReserva = $db->prepare("DELETE FROM Emprestimo WHERE id_emprestimo = ? AND id_morador = ?");
            $deletaReserva->execute([$idEmprestimoExcluir, $idUsuarioLogado]);
            $mensagem = 'Reserva excluída com sucesso!';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitar'])) {
    if ($idUsuarioLogado == 0) {
        $erro = 'O login precisa salvar id_usuario na sessão para solicitar o aluguel.';
    } elseif ($idSelecionado <= 0) {
        $erro = 'Selecione um item da lista.';
    } elseif ($dataInicio == '' || $dataFim == '') {
        $erro = 'Escolha as duas datas no calendário.';
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim) ||
        strtotime($dataInicio) === false ||
        strtotime($dataFim) === false ||
        date('Y-m-d', strtotime($dataInicio)) != $dataInicio ||
        date('Y-m-d', strtotime($dataFim)) != $dataFim
    ) {
        $erro = 'As datas informadas são inválidas.';
    } elseif ($dataInicio < date('Y-m-d')) {
        $erro = 'Não é possível escolher datas passadas.';
    } elseif ($dataFim < $dataInicio) {
        $erro = 'A data final não pode ser anterior à inicial.';
    } else {
        $consultaItem = $db->prepare('SELECT situacao_item FROM item WHERE id_item = ?');
        $consultaItem->execute([$idSelecionado]);
        $situacao = $consultaItem->fetchColumn();

        if ($situacao === false) {
            $erro = 'Item não encontrado.';
        } elseif ($situacao != 'disponivel') {
            $erro = 'Este item não está disponível para aluguel.';
        } else {
            $consultaDatas = $db->prepare("SELECT COUNT(*) FROM Emprestimo WHERE id_item = ? AND status IN ('pendente', 'confirmado') AND DATE(data_hora_retirada) <= ? AND DATE(data_hora_devolucao) >= ?");
            $consultaDatas->execute([$idSelecionado, $dataFim, $dataInicio]);
            $quantidade = $consultaDatas->fetchColumn();

            if ($quantidade == 0) {
                $gravar = $db->prepare("INSERT INTO Emprestimo (id_morador, id_item, data_hora_solicitacao, status, data_hora_retirada, data_hora_devolucao) VALUES (?, ?, NOW(), 'pendente', ?, ?)");
                $gravar->execute([
                    $idUsuarioLogado,
                    $idSelecionado,
                    $dataInicio . ' 08:00:00',
                    $dataFim . ' 20:00:00'
                ]);

                $mensagem = 'Solicitação enviada! Dirija-se a administração na data agendada para retirada do item.';
                $dataInicio = '';
                $dataFim = '';
            }
        }
    }
}

$resultado = $db->query('SELECT * FROM item');
$itens = $resultado->fetchAll(PDO::FETCH_ASSOC);

$resultadoReservas = $db->query("SELECT id_item, DATE(data_hora_retirada) AS inicio, DATE(data_hora_devolucao) AS fim FROM Emprestimo WHERE status IN ('pendente', 'confirmado')");
$reservas = $resultadoReservas->fetchAll(PDO::FETCH_ASSOC);

$consultaMeusEmprestimos = $db->prepare("
    SELECT e.*, i.descricao, i.taxa_uso
    FROM Emprestimo e
    JOIN item i ON e.id_item = i.id_item
    WHERE e.id_morador = ?
    ORDER BY e.data_hora_solicitacao DESC
");

$consultaMeusEmprestimos->execute([$idUsuarioLogado]);
$meusEmprestimos = $consultaMeusEmprestimos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo e reservas | Condomínio Compartilha</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <header class="app-header">
            <a class="app-brand" href="locacao.php" aria-label="Condomínio Compartilha">
                <span class="brand-mark" aria-hidden="true">C+</span>
                <span class="brand-copy">
                    <span class="brand-name">Condomínio Compartilha</span>
                    <span class="brand-tagline">Uso inteligente dos espaços e itens</span>
                </span>
            </a>

            <div class="user-menu">
                <span class="user-welcome">
                    <small>Área do morador</small>
                    <strong><?php echo htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </span>
                <a class="logout-link" href="../auth/logout.php">Sair</a>
            </div>
        </header>

        <main class="page-main">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Catálogo compartilhado</p>
                    <h1>Encontre, reserve e acompanhe</h1>
                    <p>Escolha um item disponível, consulte o calendário e envie sua solicitação de aluguel.</p>
                </div>
            </div>

            <?php if ($mensagem != ''): ?>
                <div class="flash flash-success mensagem-temporaria" role="status"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($erro != ''): ?>
                <div class="flash flash-error mensagem-temporaria" role="alert"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="workspace-grid">
                <section class="panel catalog-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Itens disponíveis</h2>
                            <p>Selecione um item para consultar as datas.</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Descrição</th>
                                        <th>Situação</th>
                                        <th>Taxa de uso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $item): ?>
                                        <tr
                                            id="linha-<?php echo (int) $item['id_item']; ?>"
                                            data-id="<?php echo (int) $item['id_item']; ?>"
                                            data-descricao="<?php echo htmlspecialchars((string) $item['descricao'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-taxa="<?php echo htmlspecialchars((string) $item['taxa_uso'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-situacao="<?php echo htmlspecialchars((string) $item['situacao_item'], ENT_QUOTES, 'UTF-8'); ?>"
                                            class="<?php echo $item['situacao_item'] == 'disponivel' ? 'item-row--selectable' : ''; ?>"
                                            <?php if ($item['situacao_item'] == 'disponivel'): ?>
                                                onclick="selecionarItem(this)"
                                            <?php endif; ?>
                                        >
                                            <td><span class="item-id">#<?php echo htmlspecialchars((string) $item['id_item']); ?></span></td>
                                            <td><span class="item-description"><?php echo htmlspecialchars((string) $item['descricao']); ?></span></td>
                                            <td>
                                                <span class="availability <?php echo $item['situacao_item'] == 'disponivel' ? 'availability--available' : 'availability--unavailable'; ?>">
                                                    <?php echo htmlspecialchars((string) $item['situacao_item']); ?>
                                                </span>
                                            </td>
                                            <td><span class="money"><?php echo htmlspecialchars((string) $item['taxa_uso']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="panel booking-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Nova reserva</h2>
                            <p>Defina um período livre no calendário.</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        <form class="booking-form" method="post" action="locacao.php" onsubmit="salvarEstadoFormulario()">
                            <input type="hidden" id="id_item" name="id_item">

                            <div id="areaCalendario" class="calendar-area">
                                <p id="instrucaoItem" class="booking-instruction">Selecione um item disponível na lista para começar.</p>

                                <div class="calendar-toolbar">
                                    <button class="calendar-nav" type="button" onclick="mudarMes(-1)" aria-label="Mês anterior">&lt;</button>
                                    <strong id="mesAno"></strong>
                                    <button class="calendar-nav" type="button" onclick="mudarMes(1)" aria-label="Próximo mês">&gt;</button>
                                </div>

                                <div id="calendario"></div>

                                <p class="calendar-legend">
                                    <span class="legend-unavailable">Indisponível</span>
                                    <span class="legend-selected">Período selecionado</span>
                                </p>
                                <p class="period-message" id="periodoTexto">Nenhum item selecionado.</p>

                                <input type="hidden" id="data_inicio" name="data_inicio">
                                <input type="hidden" id="data_fim" name="data_fim">

                                <div class="action-row">
                                    <button class="btn btn-primary" type="submit" name="solicitar" value="1" id="botaoSolicitar" disabled>Solicitar aluguel</button>
                                    <button class="btn btn-secondary" type="button" onclick="limparFormulario()">Limpar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="panel history-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Minhas reservas</h2>
                            <p>Acompanhe suas solicitações recentes.</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($meusEmprestimos)): ?>
                            <div class="empty-state">
                                <strong>Nenhuma reserva ainda</strong>
                                <span>Suas solicitações aparecerão aqui.</span>
                            </div>
                        <?php else: ?>
                            <div class="history-list">
                                <?php foreach ($meusEmprestimos as $emp): ?>
                                    <article class="reservation-card">
                                        <div class="reservation-head">
                                            <strong class="reservation-name"><?php echo htmlspecialchars($emp['descricao']); ?></strong>
                                            <span class="status <?php
                                                if ($emp['status'] == 'confirmado') echo 'status--confirmed';
                                                elseif ($emp['status'] == 'pendente') echo 'status--pending';
                                                else echo 'status--other';
                                            ?>">
                                                <?php echo ucfirst(htmlspecialchars($emp['status'])); ?>
                                            </span>
                                        </div>

                                        <dl class="reservation-dates">
                                            <div>
                                                <dt>Retirada</dt>
                                                <dd><?php echo date('d/m/Y', strtotime($emp['data_hora_retirada'])); ?></dd>
                                            </div>
                                            <div>
                                                <dt>Devolução</dt>
                                                <dd><?php echo date('d/m/Y', strtotime($emp['data_hora_devolucao'])); ?></dd>
                                            </div>
                                        </dl>

                                        <p class="reservation-requested">
                                            Solicitado em <?php echo date('d/m/Y H:i', strtotime($emp['data_hora_solicitacao'])); ?>
                                        </p>

                                        <!-- O botão de excluir reserva aparece APENAS se o status for estritamente 'pendente' -->
                                        <?php if ($emp['status'] == 'pendente'): ?>
                                            <form class="reservation-actions" method="post" action="locacao.php" onsubmit="return confirm('Tem certeza que deseja excluir esta reserva?');">
                                                <input type="hidden" name="id_emprestimo" value="<?php echo (int) $emp['id_emprestimo']; ?>">
                                                <button class="btn btn-danger" type="submit" name="excluir_emprestimo" value="1">Excluir reserva</button>
                                            </form>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

<script>
var reservas = <?php echo json_encode($reservas); ?>;
var itemSelecionado = 0;
var situacaoSelecionada = '';
var inicio = '';
var fim = '';
var hoje = new Date();
var ano = hoje.getFullYear();
var mes = hoje.getMonth();

var nomesMeses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

function escreverData(anoRecebido, mesRecebido, diaRecebido) {
    var m = mesRecebido < 10 ? '0' + mesRecebido : mesRecebido;
    var d = diaRecebido < 10 ? '0' + diaRecebido : diaRecebido;
    return anoRecebido + '-' + m + '-' + d;
}

// Função para formatar a data de AAAA-MM-DD para DD/MM/AAAA
function formatarDataBR(dataISO) {
    if (!dataISO) return '';
    var partes = dataISO.split('-');
    if (partes.length !== 3) return dataISO;
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function diaOcupado(data) {
    if (itemSelecionado === 0) return true;

    for (var i = 0; i < reservas.length; i++) {
        if (Number(reservas[i].id_item) == itemSelecionado && data >= reservas[i].inicio && data <= reservas[i].fim) {
            return true;
        }
    }

    return false;
}

function mostrarPeriodo() {
    if (itemSelecionado == 0) {
        document.getElementById('periodoTexto').textContent = 'Nenhum item selecionado.';
    } else if (inicio == '') {
        document.getElementById('periodoTexto').textContent = 'Escolha o primeiro dia e depois o último.';
    } else if (fim == '') {
        document.getElementById('periodoTexto').textContent = 'Início: ' + formatarDataBR(inicio) + ' | Agora escolha o último dia.';
    } else {
        document.getElementById('periodoTexto').textContent = 'Período: ' + formatarDataBR(inicio) + ' até ' + formatarDataBR(fim);
    }

    document.getElementById('data_inicio').value = inicio;
    document.getElementById('data_fim').value = fim;

    document.getElementById('botaoSolicitar').disabled =
        (situacaoSelecionada != 'disponivel' || inicio == '' || fim == '');
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

        if (
            itemSelecionado == 0 ||
            data < escreverData(hoje.getFullYear(), hoje.getMonth() + 1, hoje.getDate()) ||
            diaOcupado(data) ||
            situacaoSelecionada != 'disponivel'
        ) {
            botao.disabled = true;
            botao.classList.add('is-disabled');
        } else if (
            inicio != '' &&
            ((fim == '' && data == inicio) || (fim != '' && data >= inicio && data <= fim))
        ) {
            botao.classList.add('is-selected');
        }

        botao.onclick = function() {
            escolherDia(this.value);
        };

        calendario.appendChild(botao);
    }
}

function escolherDia(data) {
    if (itemSelecionado == 0) return;

    if (inicio == '' || fim != '' || data < inicio) {
        inicio = data;
        fim = '';
    } else {
        fim = data;

        for (var i = 0; i < reservas.length; i++) {
            if (
                Number(reservas[i].id_item) == itemSelecionado &&
                reservas[i].inicio <= fim &&
                reservas[i].fim >= inicio
            ) {
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
    mes = mes + passo;

    if (mes > 11) {
        mes = 0;
        ano = ano + 1;
    } else if (mes < 0) {
        mes = 11;
        ano = ano - 1;
    }

    desenharCalendario();
}

function selecionarItem(linha) {
    if (linha.dataset.situacao != 'disponivel') {
        return;
    }

    var idClicado = Number(linha.dataset.id);

    if (itemSelecionado === idClicado) {
        limparFormulario();
        return;
    }

    var linhas = document.querySelectorAll('tr[data-id]');

    for (var i = 0; i < linhas.length; i++) {
        linhas[i].classList.remove('is-selected');
    }

    itemSelecionado = idClicado;
    situacaoSelecionada = linha.dataset.situacao;
    linha.classList.add('is-selected');

    document.getElementById('id_item').value = itemSelecionado;
    document.getElementById('instrucaoItem').style.display = 'none';

    inicio = '';
    fim = '';

    document.getElementById('botaoSolicitar').disabled = true;

    mostrarPeriodo();
    desenharCalendario();
}

function limparFormulario() {
    var linhas = document.querySelectorAll('tr[data-id]');

    for (var i = 0; i < linhas.length; i++) {
        linhas[i].classList.remove('is-selected');
    }

    document.getElementById('id_item').value = '';
    document.getElementById('instrucaoItem').style.display = 'block';

    itemSelecionado = 0;
    situacaoSelecionada = '';
    inicio = '';
    fim = '';

    document.getElementById('data_inicio').value = '';
    document.getElementById('data_fim').value = '';
    
    sessionStorage.removeItem('pi_id_item');
    sessionStorage.removeItem('pi_data_inicio');
    sessionStorage.removeItem('pi_data_fim');

    mostrarPeriodo();
    desenharCalendario();
}

function salvarEstadoFormulario() {
    sessionStorage.setItem('pi_id_item', itemSelecionado);
    sessionStorage.setItem('pi_data_inicio', inicio);
    sessionStorage.setItem('pi_data_fim', fim);
}

// Restaura o estado salvo ou pega do PHP após refresh
var idAnterior = <?php echo (int) $idSelecionado; ?>;
var salvoItem = sessionStorage.getItem('pi_id_item');
var salvoInicio = sessionStorage.getItem('pi_data_inicio');
var salvoFim = sessionStorage.getItem('pi_data_fim');

if (idAnterior > 0) {
    salvoItem = idAnterior;
    salvoInicio = <?php echo json_encode($dataInicio); ?>;
    salvoFim = <?php echo json_encode($dataFim); ?>;
}

if (salvoItem && Number(salvoItem) > 0) {
    var linhas = document.querySelectorAll('tr[data-id]');

    for (var i = 0; i < linhas.length; i++) {
        if (
            Number(linhas[i].dataset.id) == Number(salvoItem) &&
            linhas[i].dataset.situacao == 'disponivel'
        ) {
            selecionarItem(linhas[i]);

            if (salvoInicio) inicio = salvoInicio;
            if (salvoFim) fim = salvoFim;

            mostrarPeriodo();
            desenharCalendario();
            break;
        }
    }
} else {
    desenharCalendario();
}

setTimeout(function() {
    var mensagens = document.querySelectorAll('.mensagem-temporaria');

    for (var i = 0; i < mensagens.length; i++) {
        mensagens[i].style.display = 'none';
    }
}, 5000);

// Configura o Refresh automático da página a cada 30 segundos
setInterval(function() {
    salvarEstadoFormulario();
    location.reload();
}, 30000);
</script>
</body>
</html>