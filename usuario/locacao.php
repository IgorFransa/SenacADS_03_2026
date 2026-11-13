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

            if ($quantidade > 0) {
                $erro = 'Já existe um pedido para esse item nesse período.';
            } else {
                $gravar = $db->prepare("INSERT INTO Emprestimo (id_morador, id_item, data_hora_solicitacao, status, data_hora_retirada, data_hora_devolucao) VALUES (?, ?, NOW(), 'pendente', ?, ?)");
                $gravar->execute([
                    $idUsuarioLogado,
                    $idSelecionado,
                    $dataInicio . ' 08:00:00',
                    $dataFim . ' 20:00:00'
                ]);

                $mensagem = 'Solicitação enviada! Aguarde a aprovação do responsável.';
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

<p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8'); ?> | <a href="../auth/logout.php">Sair</a></p>

<?php if ($mensagem != ''): ?>
    <p class="mensagem-temporaria" style="color: green;"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if ($erro != ''): ?>
    <p class="mensagem-temporaria" style="color: red;"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<div style="display: flex; flex-direction: row; gap: 20px; align-items: flex-start; flex-wrap: wrap;">

    <div style="flex: 1; min-width: 280px;">
        <h4>Lista de itens</h4>

        <table border="1" cellpadding="6" cellspacing="0" style="width: 100%;">
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
                        <?php if ($item['situacao_item'] == 'disponivel'): ?>
                            onclick="selecionarItem(this)"
                            style="cursor: pointer;"
                            onmouseover="if(itemSelecionado != <?php echo (int) $item['id_item']; ?>) this.style.backgroundColor='#e8f2ff'"
                            onmouseout="if(itemSelecionado != <?php echo (int) $item['id_item']; ?>) this.style.backgroundColor=''"
                        <?php endif; ?>
                    >
                        <td><?php echo htmlspecialchars((string) $item['id_item']); ?></td>
                        <td><?php echo htmlspecialchars((string) $item['descricao']); ?></td>
                        <td><?php echo htmlspecialchars((string) $item['situacao_item']); ?></td>
                        <td><?php echo htmlspecialchars((string) $item['taxa_uso']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>  
      </div>

    <div style="flex: 1; min-width: 280px;">
        <form method="post" action="locacao.php" onsubmit="salvarEstadoFormulario()">
            <input type="hidden" id="id_item" name="id_item">

            <div id="areaCalendario">
                <h4>Calendário para aluguel</h4>
                <p id="instrucaoItem" style="font-size: 12px; color: #c0392b; font-weight: bold;">Selecione um item disponível na tabela ao lado para agendar.</p>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" onclick="mudarMes(-1)">&lt;</button>
                    <strong id="mesAno"></strong>
                    <button type="button" onclick="mudarMes(1)">&gt;</button>
                </div>

                <div id="calendario" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; margin-top: 10px;"></div>

                <p style="font-size: 12px; margin-top: 8px;">Cinza: indisponível | Azul: selecionado</p>
                <p id="periodoTexto">Nenhum item selecionado.</p>

                <input type="hidden" id="data_inicio" name="data_inicio">
                <input type="hidden" id="data_fim" name="data_fim">

                <div style="margin-top: 10px; display: flex; gap: 5px;">
                    <button type="submit" name="solicitar" value="1" id="botaoSolicitar" disabled>Solicitar aluguel</button>
                    <button type="button" onclick="limparFormulario()">Limpar</button>
                </div>
            </div>
        </form>
    </div>

    <div style="flex: 1; min-width: 240px;">
        <h4>Meus Itens Locados</h4>

        <?php if (empty($meusEmprestimos)): ?>
            <p style="font-size: 13px; color: #666;">Você ainda não realizou nenhuma solicitação de aluguel.</p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr; gap: 10px; max-height: 450px; overflow-y: auto; padding-right: 5px;">
                <?php foreach ($meusEmprestimos as $emp): ?>
                    <div style="background: #fdfdfd; border: 1px solid #ccc; border-radius: 6px; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <strong style="font-size: 14px; color: #333;"><?php echo htmlspecialchars($emp['descricao']); ?></strong>
                            <span style="font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: bold;
                                <?php
                                if ($emp['status'] == 'confirmado') echo 'background: #d4edda; color: #155724;';
                                elseif ($emp['status'] == 'pendente') echo 'background: #fff3cd; color: #856404;';
                                else echo 'background: #f8d7da; color: #721c24;';
                                ?>">
                                <?php echo ucfirst(htmlspecialchars($emp['status'])); ?>
                            </span>
                        </div>

                        <p style="font-size: 12px; margin: 3px 0; color: #555;">
                            <strong>Retirada:</strong> <?php echo date('d/m/Y', strtotime($emp['data_hora_retirada'])); ?>
                        </p>

                        <p style="font-size: 12px; margin: 3px 0; color: #555;">
                            <strong>Devolução:</strong> <?php echo date('d/m/Y', strtotime($emp['data_hora_devolucao'])); ?>
                        </p>

                        <p style="font-size: 11px; margin: 4px 0 6px 0; color: #888;">
                            Solicitado em: <?php echo date('d/m/Y H:i', strtotime($emp['data_hora_solicitacao'])); ?>
                        </p>

                        <!-- O botão de excluir reserva aparece APENAS se o status for estritamente 'pendente' -->
                        <?php if ($emp['status'] == 'pendente'): ?>
                            <form method="post" action="locacao.php" onsubmit="return confirm('Tem certeza que deseja excluir esta reserva?');" style="margin-top: 8px;">
                                <input type="hidden" name="id_emprestimo" value="<?php echo (int) $emp['id_emprestimo']; ?>">
                                <button type="submit" name="excluir_emprestimo" value="1" style="background-color: #dc3545; color: white; border: none; padding: 4px 8px; font-size: 11px; border-radius: 4px; cursor: pointer;">Excluir Reserva</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

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
        document.getElementById('periodoTexto').textContent = 'Início: ' + inicio + ' | Agora escolha o último dia.';
    } else {
        document.getElementById('periodoTexto').textContent = 'Período: ' + inicio + ' até ' + fim;
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
        nomeDia.textContent = semana[s];
        nomeDia.style.textAlign = 'center';
        nomeDia.style.fontSize = '12px';
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

        botao.type = 'button';
        botao.textContent = dia;
        botao.value = data;
        botao.style.height = '32px';

        if (
            itemSelecionado == 0 ||
            data < escreverData(hoje.getFullYear(), hoje.getMonth() + 1, hoje.getDate()) ||
            diaOcupado(data) ||
            situacaoSelecionada != 'disponivel'
        ) {
            botao.disabled = true;
            botao.style.backgroundColor = '#dddddd';
        } else if (
            inicio != '' &&
            ((fim == '' && data == inicio) || (fim != '' && data >= inicio && data <= fim))
        ) {
            botao.style.backgroundColor = '#a8d4ff';
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
        linhas[i].style.backgroundColor = '';
    }

    itemSelecionado = idClicado;
    situacaoSelecionada = linha.dataset.situacao;
    linha.style.backgroundColor = '#cfe2ff';

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
        linhas[i].style.backgroundColor = '';
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