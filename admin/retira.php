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

if (!isset($_SESSION['nivel_acesso']) || $_SESSION['nivel_acesso'] !== 'administrador') {
    header("Location: ../usuario/locacao.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_emprestimo'])) {
    $id_emprestimo  = (int) $_POST['id_emprestimo'];
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
                ':id_emprestimo'  => $id_emprestimo
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Retirada de Itens</title>
    <style>
        body { font-family: sans-serif; margin: 15px; color: #000; background: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background: #eee; }
        .alert { background: #eee; padding: 8px; margin-bottom: 15px; border: 1px solid #000; }
        .error { background: #f8d7da; color: #721c24; padding: 8px; margin-bottom: 15px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8'); ?> | <a href="../auth/logout.php">Sair</a></p>
    
    <h2>Painel do Administrador - Confirmar Retirada</h2>

    <?php if (!empty($mensagem)): ?>
        <div class="alert"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
        <div class="error"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (empty($emprestimosPendentes)): ?>
        <p>Nenhuma solicitação de empréstimo pendente para retirada no momento.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th># ID</th>
                    <th>Morador</th>
                    <th>Item Solicitado</th>
                    <th>Data/Hora Solicitação</th>
                    <th>Observações</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emprestimosPendentes as $emp): ?>
                    <tr>
                        <td><?php echo $emp['id_emprestimo']; ?></td>
                        <td><?php echo htmlspecialchars($emp['nome_morador']); ?></td>
                        <td><?php echo htmlspecialchars($emp['nome_item']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($emp['data_hora_solicitacao'])); ?></td>
                        <td><?php echo htmlspecialchars($emp['observacoes'] ?? '-'); ?></td>
                        <td>
                            <form method="POST" onsubmit="pararTemporizador(); return confirm('Confirma a entrega do item?');">
                                <input type="hidden" name="id_emprestimo" value="<?php echo $emp['id_emprestimo']; ?>">
                                <button type="submit">Confirmar Retirada</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <script>
        var timerRefresh = setInterval(function() {
            location.reload();
        }, 30000);

        function pararTemporizador() {
            clearInterval(timerRefresh);
        }
        window.addEventListener('click', function() {
         });
    </script>
</body>
</html>