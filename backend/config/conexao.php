<?php
function conectarBanco() {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $dbname = getenv('DB_NAME') ?: 'empcondominio2';
    $user = getenv('DB_USER') ?: 'admin';
    $pass = getenv('DB_PASS') ?: 'Senha123';

    try {
        $db = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8",
            $user,
            $pass
        );

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $db;

    } catch (PDOException $e) {
        die('Erro na conexão: ' . $e->getMessage());
    }
}
