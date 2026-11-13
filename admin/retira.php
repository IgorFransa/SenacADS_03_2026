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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitações de retirada | Condomínio Compartilha</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <header class="app-header">
            <a class="app-brand" href="retira.php" aria-label="Condomínio Compartilha">
                <span class="brand-mark" aria-hidden="true">C+</span>
                <span class="brand-copy">
                    <span class="brand-name">Condomínio Compartilha</span>
                    <span class="brand-tagline">Uso inteligente dos espaços e itens</span>
                </span>
            </a>

            <div class="user-menu">
                <span class="user-welcome">
                    <small>Área da administração</small>
                    <strong><?php echo htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </span>
                <a class="logout-link" href="../auth/logout.php">Sair</a>
            </div>
        </header>

        <main class="page-main">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Central de atendimento</p>
                    <h1>Solicitações de retirada</h1>
                    <p>Revise os pedidos pendentes e confirme a entrega dos itens aos moradores.</p>
                </div>
                <span class="auto-refresh">Atualização automática a cada 30s</span>
            </div>

            <?php if (!empty($mensagem)): ?>
                <div class="flash flash-success" role="status"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if (!empty($erro)): ?>
                <div class="flash flash-error" role="alert"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <section class="panel admin-panel">
                <div class="panel-header">
                    <div>
                        <h2>Pedidos aguardando confirmação</h2>
                        <p>As solicitações mais antigas aparecem primeiro.</p>
                    </div>
                </div>

                <div class="panel-body">
                    <?php if (empty($emprestimosPendentes)): ?>
                        <div class="empty-state">
                            <strong>Tudo em dia</strong>
                            <span>Nenhuma solicitação de empréstimo pendente para retirada no momento.</span>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table admin-table">
                                <thead>
                                    <tr>
                                        <th># ID</th>
                                        <th>Morador</th>
                                        <th>Item solicitado</th>
                                        <th>Data/hora</th>
                                        <th>Observações</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($emprestimosPendentes as $emp): ?>
                                        <tr>
                                            <td><span class="request-id">#<?php echo $emp['id_emprestimo']; ?></span></td>
                                            <td><span class="request-person"><?php echo htmlspecialchars($emp['nome_morador']); ?></span></td>
                                            <td><?php echo htmlspecialchars($emp['nome_item']); ?></td>
                                            <td><span class="request-date"><?php echo date('d/m/Y H:i', strtotime($emp['data_hora_solicitacao'])); ?></span></td>
                                            <td><span class="request-note"><?php echo htmlspecialchars($emp['observacoes'] ?? '-'); ?></span></td>
                                            <td>
                                                <form class="admin-action-form" method="POST" onsubmit="pararTemporizador(); return confirm('Confirma a entrega do item?');">
                                                    <input type="hidden" name="id_emprestimo" value="<?php echo $emp['id_emprestimo']; ?>">
                                                    <button class="btn" type="submit">Confirmar retirada</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

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