<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/conexao.php';

iniciarRespostaJson();
exigirUsuarioAutenticado();

if (!isset($_SESSION['nivel_acesso']) || $_SESSION['nivel_acesso'] !== 'administrador') {
    responderJson([
        'erro' => 'Acesso não autorizado.',
        'redirect' => '/usuario/locacao.html',
    ], 403);
}

$db = conectarBanco();
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_emprestimo'])) {
    $id_emprestimo = (int) $_POST['id_emprestimo'];
    $id_responsavel = $_SESSION['id_usuario'] ?? 1;

    try {
        $db->beginTransaction();
        $stmtCheck = $db->prepare("SELECT status FROM emprestimo WHERE id_emprestimo = :id_emprestimo FOR UPDATE");
        $stmtCheck->execute([':id_emprestimo' => $id_emprestimo]);
        $emprestimo = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($emprestimo && $emprestimo['status'] === 'pendente') {
            $sql = "UPDATE emprestimo
                    SET status = 'confirmado',
                        data_hora_retirada = NOW(),
                        id_responsavel = :id_responsavel
                    WHERE id_emprestimo = :id_emprestimo";

            $stmtUpdate = $db->prepare($sql);
            $stmtUpdate->execute([
                ':id_responsavel' => $id_responsavel,
                ':id_emprestimo' => $id_emprestimo
            ]);

            $db->commit();
            $mensagem = "Retirada do item confirmada com sucesso!";
        } else {
            $db->rollBack();
            $erro = "Esta solicitação já foi processada ou não está mais pendente.";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $erro = "Erro ao processar a operação: " . $e->getMessage();
    }
}

$sqlListar = "SELECT e.id_emprestimo, e.data_hora_solicitacao, e.observacoes,
                     u.nome AS nome_morador,
                     i.descricao AS nome_item
              FROM emprestimo e
              JOIN Usuario u ON e.id_morador = u.id_usuario
              JOIN Item i ON e.id_item = i.id_item
              WHERE e.status = 'pendente'
              ORDER BY e.data_hora_solicitacao ASC";

$stmtListar = $db->query($sqlListar);
$emprestimosPendentes = $stmtListar->fetchAll(PDO::FETCH_ASSOC);

responderJson([
    'usuario' => [
        'nome' => $_SESSION['nome'],
    ],
    'mensagem' => $mensagem,
    'erro' => $erro,
    'emprestimosPendentes' => $emprestimosPendentes,
]);
