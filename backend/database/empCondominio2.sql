-- ==========================================================
-- Dump de criação do Banco de dados: empCondominio (MySQL)
-- Sistema de empréstimo de itens em condomínio
-- ==========================================================

DROP DATABASE IF EXISTS empCondominio2;
CREATE DATABASE empCondominio2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE empCondominio2;

-- =====================================================
-- Criar usuário de acesso ao banco
-- =====================================================
CREATE USER 'admin'@'localhost' IDENTIFIED BY 'Senha123';
GRANT SELECT, INSERT, DELETE, UPDATE ON empCondominio2.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;

-- -----------------------------------------------------
-- Tabela Usuario
-- -----------------------------------------------------
CREATE TABLE Usuario (
    id_usuario        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome              VARCHAR(150) NOT NULL,
    tipo_usuario      ENUM('proprietario','locatario','sindico','funcionario') NOT NULL,
    cpf               CHAR(11) NOT NULL,
    data_nascimento   DATE NOT NULL,
    email             VARCHAR(150) NOT NULL,
    telefone          VARCHAR(20),
    endereco          VARCHAR(255),
    status_conta      ENUM('ativo','suspenso','banido') NOT NULL DEFAULT 'ativo',
    nivel_acesso      ENUM('administrador','usuario') NOT NULL DEFAULT 'usuario',
    nome_usuario      VARCHAR(50) NOT NULL,
    senha_hash        VARCHAR(255) NOT NULL,
    data_ultimo_acesso DATETIME NULL,
    CONSTRAINT pk_usuario PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuario_cpf UNIQUE (cpf),
    CONSTRAINT uq_usuario_email UNIQUE (email),
    CONSTRAINT uq_usuario_nome_usuario UNIQUE (nome_usuario)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Tabela Item
-- -----------------------------------------------------
CREATE TABLE Item (
    id_item        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    descricao      VARCHAR(255) NOT NULL,
    categoria      VARCHAR(100) NOT NULL,
    estado         ENUM('novo','bom','regular','danificado') NOT NULL DEFAULT 'bom',
    situacao_item  ENUM('disponivel','indisponivel','locado','com_defeito') NOT NULL DEFAULT 'disponivel',
    preco_compra   DECIMAL(10,2),
    foto           VARCHAR(255), -- caminho/URL da imagem
    observacoes    TEXT,
    taxa_uso       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    multa_atraso   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT pk_item PRIMARY KEY (id_item)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Tabela Emprestimo
-- -----------------------------------------------------
CREATE TABLE Emprestimo (
    id_emprestimo          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_morador             INT UNSIGNED NOT NULL,
    id_item                INT UNSIGNED NOT NULL,
    data_hora_solicitacao  DATETIME NOT NULL,
    status                 ENUM('pendente','confirmado','cancelado','expirado') NOT NULL DEFAULT 'pendente',
    data_hora_confirmacao  DATETIME NULL,
    data_hora_retirada     DATETIME NULL,
    data_hora_devolucao    DATETIME NULL,
    id_responsavel         INT UNSIGNED NULL,
    observacoes            TEXT,
    CONSTRAINT pk_emprestimo PRIMARY KEY (id_emprestimo),
    CONSTRAINT fk_emprestimo_morador
        FOREIGN KEY (id_morador) REFERENCES Usuario (id_usuario),
    CONSTRAINT fk_emprestimo_item
        FOREIGN KEY (id_item) REFERENCES Item (id_item),
    CONSTRAINT fk_emprestimo_responsavel
        FOREIGN KEY (id_responsavel) REFERENCES Usuario (id_usuario)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Tabela Ocorrencia
-- -----------------------------------------------------
CREATE TABLE Ocorrencia (
    id_ocorrencia          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_morador             INT UNSIGNED NOT NULL,
    id_emprestimo          INT UNSIGNED NOT NULL,
    id_funcionario         INT UNSIGNED NOT NULL,
    descricao              TEXT NOT NULL,
    data_hora_registro     DATETIME NOT NULL,
    tipo_ocorrencia        ENUM('atraso','dano_item','ma_conduta','mau_uso') NOT NULL,
    status_ocorrencia      ENUM('resolvido','nao_resolvido') NOT NULL DEFAULT 'nao_resolvido',
    necessidade_penalidade BOOLEAN NOT NULL DEFAULT FALSE,
    tipo_penalidade        ENUM('advertencia','suspensao','banimento') NULL,
    data_inicio_penalidade DATE NULL,
    motivo_penalidade      TEXT,
    reincidencia           BOOLEAN NOT NULL DEFAULT FALSE,
    observacoes            TEXT,
    CONSTRAINT pk_ocorrencia PRIMARY KEY (id_ocorrencia),
    CONSTRAINT fk_ocorrencia_morador
        FOREIGN KEY (id_morador) REFERENCES Usuario (id_usuario),
    CONSTRAINT fk_ocorrencia_emprestimo
        FOREIGN KEY (id_emprestimo) REFERENCES Emprestimo (id_emprestimo),
    CONSTRAINT fk_ocorrencia_funcionario
        FOREIGN KEY (id_funcionario) REFERENCES Usuario (id_usuario)
) ENGINE = InnoDB;
