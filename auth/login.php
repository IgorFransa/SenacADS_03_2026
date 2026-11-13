<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
$db = conectarBanco();
$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha_hash'] ?? '';

    $sql = "SELECT * FROM usuario WHERE email = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$email]);

    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dados && password_verify($senha, $dados['senha_hash'])) {

        session_regenerate_id(true);

        $_SESSION['email'] = $dados['email'];
        $_SESSION['nome'] = $dados['nome'];
        $_SESSION['id_usuario'] = $dados['id_usuario'];
        $_SESSION['nivel_acesso'] = $dados['nivel_acesso'];

        if ($dados['nivel_acesso'] === 'administrador') {
            header("Location: ../admin/retira.php");
        } else {
            header("Location: ../usuario/locacao.php");
        }

        exit;

    } else {
        $erro = "E-mail ou senha inválidos.";
    }
}
?>

<form method="post">
    <?php if (!empty($erro)) : ?>
        <div style="color: red; background: #ffe6e6; padding: 10px; border: 1px solid red;">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <br>
    <?php endif; ?>

    Email:
    <input type="email" name="email" required>
    <br><br>

    Senha:
    <input type="password" name="senha_hash" required>
    <br><br>

    <input type="submit" value="Entrar">
</form>
