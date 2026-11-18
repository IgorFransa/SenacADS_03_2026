<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/conexao.php';

iniciarRespostaJson();
exigirUsuarioAutenticado();

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

responderJson([
    'usuario' => [
        'nome' => $_SESSION['nome'],
    ],
    'mensagem' => $mensagem,
    'erro' => $erro,
    'itens' => $itens,
    'reservas' => $reservas,
    'meusEmprestimos' => $meusEmprestimos,
]);
