<?php
function iniciarRespostaJson(): void
{
    header('Content-Type: application/json; charset=UTF-8');
}

function responderJson(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function exigirUsuarioAutenticado(): void
{
    iniciarSessao();

    if (!isset($_SESSION['nome'])) {
        responderJson([
            'erro' => 'É necessário fazer login para continuar.',
            'redirect' => '/auth/login.html',
        ], 401);
    }
}
