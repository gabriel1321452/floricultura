USE loja;

SET @descricao_existe = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'produtos'
      AND column_name = 'descricao'
);
SET @comando = IF(
    @descricao_existe = 0,
    'ALTER TABLE produtos ADD COLUMN descricao TEXT NULL',
    'SELECT 1'
);
PREPARE migracao FROM @comando;
EXECUTE migracao;
DEALLOCATE PREPARE migracao;

SET @admin_existe = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'usuarios_login'
      AND column_name = 'is_admin'
);
SET @comando = IF(
    @admin_existe = 0,
    'ALTER TABLE usuarios_login ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE migracao FROM @comando;
EXECUTE migracao;
DEALLOCATE PREPARE migracao;

CREATE TABLE IF NOT EXISTS favoritos (
    usuario_id INT NOT NULL,
    produto_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, produto_id),
    CONSTRAINT favoritos_usuario_fk
        FOREIGN KEY (usuario_id) REFERENCES usuarios_login(id) ON DELETE CASCADE,
    CONSTRAINT favoritos_produto_fk
        FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitacoes_devolucao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_pedido_id INT NOT NULL UNIQUE,
    motivo VARCHAR(1000) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'solicitada',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT devolucao_item_fk
        FOREIGN KEY (item_pedido_id) REFERENCES itens_pedido(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE produtos
SET descricao = CASE
    WHEN LOWER(nome) LIKE '%rosa%' THEN 'Rosa de pétalas aveludadas, ideal para presentear. Flores naturais podem variar de tonalidade e tamanho.'
    WHEN LOWER(nome) LIKE '%girassol%' THEN 'Flor de cor vibrante para alegrar ambientes. A disponibilidade e o tamanho podem variar conforme a estação.'
    WHEN LOWER(nome) LIKE '%orqu%' THEN 'Orquídea ornamental elegante. Prefere luz indireta e regas moderadas; a cor pode variar conforme o lote.'
    WHEN LOWER(nome) LIKE '%tulip%' THEN 'Tulipa colorida, delicada e elegante. As cores podem variar conforme a disponibilidade da estação.'
    ELSE COALESCE(descricao, 'Flor ornamental selecionada para presentear ou decorar ambientes.')
END
WHERE descricao IS NULL OR TRIM(descricao) = '';
