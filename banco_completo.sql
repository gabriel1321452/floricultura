CREATE DATABASE IF NOT EXISTS loja CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE loja;

CREATE TABLE IF NOT EXISTS usuarios_login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    imagem TEXT NOT NULL, 
    estoque INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    forma_pagamento VARCHAR(20) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS itens_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    quantidade INT NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS solicitacoes_devolucao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_pedido_id INT NOT NULL UNIQUE,
    motivo VARCHAR(1000) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'solicitada',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_pedido_id) REFERENCES itens_pedido(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS favoritos (
    usuario_id INT NOT NULL,
    produto_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, produto_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios_login(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);

INSERT INTO produtos (nome, descricao, preco, imagem, estoque) VALUES
('Rosa vermelha', 'Rosa vermelha de pétalas aveludadas, ideal para presentear. Flores naturais podem variar de tonalidade e tamanho.', 12.90, 'flores/rosa.jpg', 10),
('Girassol', 'Girassol de cor vibrante para alegrar ambientes. A disponibilidade e o tamanho podem variar conforme a estação.', 9.50, 'flores/girassol.jpg', 10),
('Orquídea', 'Orquídea ornamental elegante. Prefere luz indireta e regas moderadas; a cor pode variar conforme o lote.', 59.90, 'flores/orquidea.jpg', 5);
