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

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar | Condomínio Compartilha</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-visual" aria-label="Sobre o Condomínio Compartilha">
            <a class="app-brand" href="../index.php" aria-label="Condomínio Compartilha">
                <span class="brand-mark" aria-hidden="true">C+</span>
                <span class="brand-copy">
                    <span class="brand-name">Condomínio Compartilha</span>
                    <span class="brand-tagline">Uso inteligente dos espaços e itens</span>
                </span>
            </a>

            <div class="auth-copy">
                <p class="eyebrow eyebrow-light">Portal do condomínio</p>
                <h1>Compartilhe o que torna a vida mais prática.</h1>
                <p>Consulte itens disponíveis, escolha um período e acompanhe suas solicitações em um só lugar.</p>
            </div>

            <ul class="auth-feature-list" aria-label="Recursos do portal">
                <li>Catálogo de itens compartilhados</li>
                <li>Calendário de disponibilidade</li>
                <li>Acompanhamento de reservas</li>
            </ul>
        </section>

        <section class="auth-content">
            <div class="auth-form-wrap">
                <h2>Bem-vindo de volta</h2>
                <p class="auth-form-intro">Entre com seus dados para acessar o portal.</p>

                <?php if (!empty($erro)) : ?>
                    <div class="flash flash-error" role="alert">
                        <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form class="auth-form" method="post">
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input class="form-control" type="email" id="email" name="email" autocomplete="email" placeholder="seu@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input class="form-control" type="password" id="senha" name="senha_hash" autocomplete="current-password" placeholder="Digite sua senha" required>
                    </div>

                    <button class="btn btn-primary auth-submit" type="submit">Entrar no portal</button>
                </form>

                <p class="auth-footer">Acesso seguro para moradores e administração.</p>
            </div>
        </section>
    </main>
</body>
</html>
