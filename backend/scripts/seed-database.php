<?php
/**
 * Dados de demonstração para ambiente Docker (executado na subida do Apache).
 * Senha de todos os usuários de teste: Senha123 
 */
require_once __DIR__ . '/../config/conexao.php';

$db = conectarBanco();
$count = (int) $db->query('SELECT COUNT(*) FROM Usuario')->fetchColumn();

if ($count > 0) {
    exit(0);
}

$senhaHash = password_hash('Senha123', PASSWORD_DEFAULT);

$insertUsuario = $db->prepare(
    'INSERT INTO Usuario (
        nome, tipo_usuario, cpf, data_nascimento, email, status_conta,
        nivel_acesso, nome_usuario, senha_hash
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insertUsuario->execute([
    'Administrador Demo',
    'funcionario',
    '11111111111',
    '1990-01-01',
    'admin@condominio.local',
    'ativo',
    'administrador',
    'admin_demo',
    $senhaHash,
]);

$insertUsuario->execute([
    'Morador Demo',
    'proprietario',
    '22222222222',
    '1995-06-15',
    'morador@condominio.local',
    'ativo',
    'usuario',
    'morador_demo',
    $senhaHash,
]);

$db->exec("INSERT INTO Item (descricao, categoria, situacao_item, taxa_uso) VALUES
    ('Parafusadeira', 'Ferramentas', 'disponivel', 5.00),
    ('Extensão 10m', 'Elétrica', 'disponivel', 2.50),
    ('Escada de alumínio', 'Ferramentas', 'disponivel', 8.00)
");

fwrite(STDERR, "Seed: usuários e itens de demonstração criados (senha: Senha123).\n");
