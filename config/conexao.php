<?php
function conectarBanco() {
    try {
        $db = new PDO(
            'mysql:host=127.0.0.1;dbname=empcondominio2;charset=utf8',
            'admin',
            'Senha123'
        );

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $db;

    } catch (PDOException $e) {
        die('Erro na conexão: ' . $e->getMessage());
    }
}
