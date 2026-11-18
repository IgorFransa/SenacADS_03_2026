<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/conexao.php';

iniciarRespostaJson();
iniciarSessao();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['erro' => 'Método não permitido.'], 405);
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha_hash'] ?? '';

$db = conectarBanco();
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

    $redirect = $dados['nivel_acesso'] === 'administrador'
        ? '/admin/retira.html'
        : '/usuario/locacao.html';

    responderJson(['redirect' => $redirect]);
}

responderJson(['erro' => 'E-mail ou senha inválidos.'], 422);
