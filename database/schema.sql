-- ============================================================
-- DIEGO GOURMET V4 — Schema de Referência
-- ============================================================
-- IMPORTANTE: você NÃO precisa rodar este arquivo manualmente!
-- O sistema cria tudo automaticamente no primeiro acesso via
-- database/Setup.php (chamado por backend/bootstrap.php).
--
-- Este arquivo serve apenas como documentação/referência da
-- estrutura, ou para criação manual caso prefira.
--
-- Para criar manualmente (opcional):
--   mysql -u root -p < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS diego_gourmet_v4
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE diego_gourmet_v4;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    endereco VARCHAR(255) NOT NULL DEFAULT '',
    email VARCHAR(150) DEFAULT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('cliente','admin','cozinha') NOT NULL DEFAULT 'cliente',
    data_nascimento DATE DEFAULT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email),
    KEY idx_telefone (telefone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS preferencias_usuario (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    preferencia VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    KEY fk_pref_usuario (usuario_id),
    CONSTRAINT fk_pref_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cat_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS produtos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoria_id INT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT DEFAULT NULL,
    nutricional TEXT DEFAULT NULL,
    modo_preparo TEXT DEFAULT NULL,
    imagem_url VARCHAR(500) DEFAULT NULL,
    grid_vitrine ENUM('item-standard','item-featured','item-horizontal','item-vertical') NOT NULL DEFAULT 'item-standard',
    ordem_vitrine SMALLINT UNSIGNED NOT NULL DEFAULT 99,
    permite_encomenda ENUM('ambos','apenas-encomenda','apenas-pronta') NOT NULL DEFAULT 'ambos',
    sinal_minimo_perc TINYINT UNSIGNED NOT NULL DEFAULT 30,
    antecedencia_min_dias TINYINT UNSIGNED NOT NULL DEFAULT 3,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_prod_categoria (categoria_id),
    CONSTRAINT fk_prod_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS variacoes_produto (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id INT UNSIGNED NOT NULL,
    nome VARCHAR(80) NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    multiplicador DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    PRIMARY KEY (id),
    KEY fk_var_produto (produto_id),
    CONSTRAINT fk_var_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS insumos (
    id VARCHAR(20) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    custo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
    capacidade_max DECIMAL(10,3) NOT NULL DEFAULT 100,
    unidade ENUM('kg','g','L','ml','un','cx','lata','pacote') NOT NULL DEFAULT 'un',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_insumo_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lotes (
    id VARCHAR(30) NOT NULL,
    insumo_id VARCHAR(20) NOT NULL,
    quantidade DECIMAL(10,3) NOT NULL DEFAULT 0,
    data_inclusao DATE NOT NULL,
    data_vencimento DATE NOT NULL,
    PRIMARY KEY (id),
    KEY fk_lote_insumo (insumo_id),
    CONSTRAINT fk_lote_insumo FOREIGN KEY (insumo_id) REFERENCES insumos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ingredientes_produto (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id INT UNSIGNED NOT NULL,
    insumo_id VARCHAR(20) NOT NULL,
    quantidade DECIMAL(10,3) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ing (produto_id, insumo_id),
    CONSTRAINT fk_ing_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE CASCADE,
    CONSTRAINT fk_ing_insumo FOREIGN KEY (insumo_id) REFERENCES insumos (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos (
    id VARCHAR(20) NOT NULL,
    usuario_id INT UNSIGNED DEFAULT NULL,
    cliente_nome VARCHAR(120) NOT NULL,
    cliente_telefone VARCHAR(20) NOT NULL DEFAULT '',
    cliente_endereco VARCHAR(255) NOT NULL DEFAULT '',
    modalidade ENUM('entrega','retirada') NOT NULL DEFAULT 'entrega',
    tipo_venda ENUM('pronta-entrega','encomenda') NOT NULL DEFAULT 'pronta-entrega',
    data_pedido DATE NOT NULL,
    data_entrega DATE NOT NULL,
    hora_entrega TIME NOT NULL DEFAULT '12:00:00',
    forma_pagamento ENUM('pix','dinheiro','debito','credito') NOT NULL DEFAULT 'pix',
    taxa_pagamento_perc DECIMAL(5,2) NOT NULL DEFAULT 0,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_liquido DECIMAL(10,2) NOT NULL DEFAULT 0,
    sinal_pago DECIMAL(10,2) NOT NULL DEFAULT 0,
    saldo_devedor DECIMAL(10,2) NOT NULL DEFAULT 0,
    status_pagamento ENUM('pendente','sinal-pago','pago-total') NOT NULL DEFAULT 'pendente',
    status_pedido ENUM('Recebido','Preparando','Pronto','Entregue') NOT NULL DEFAULT 'Recebido',
    alergias VARCHAR(255) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    resposta_admin TEXT DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_ped_usuario (usuario_id),
    KEY idx_data_entrega (data_entrega),
    KEY idx_status (status_pedido),
    CONSTRAINT fk_ped_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS itens_pedido (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id VARCHAR(20) NOT NULL,
    produto_id INT UNSIGNED DEFAULT NULL,
    variacao_nome VARCHAR(80) NOT NULL DEFAULT '',
    valor_unitario DECIMAL(10,2) NOT NULL,
    valor_liquido DECIMAL(10,2) NOT NULL,
    custo_insumos DECIMAL(10,2) NOT NULL DEFAULT 0,
    personalizacao VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE,
    CONSTRAINT fk_item_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pagamentos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id VARCHAR(20) NOT NULL,
    mp_payment_id VARCHAR(60) DEFAULT NULL,
    mp_status VARCHAR(30) DEFAULT NULL,
    forma ENUM('pix','cartao') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    taxa_aplicada DECIMAL(10,2) NOT NULL DEFAULT 0,
    payload_json JSON DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_pgto_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_alocado (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    insumo_id VARCHAR(20) NOT NULL,
    pedido_id VARCHAR(20) NOT NULL,
    quantidade DECIMAL(10,3) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_aloc_insumo FOREIGN KEY (insumo_id) REFERENCES insumos (id) ON DELETE CASCADE,
    CONSTRAINT fk_aloc_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria_perdas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    insumo_id VARCHAR(20) NOT NULL,
    lote_id VARCHAR(30) DEFAULT NULL,
    data_perda DATE NOT NULL,
    motivo ENUM('Vencimento','Avaria / Quebra','Uso Interno','Outro') NOT NULL DEFAULT 'Vencimento',
    quantidade DECIMAL(10,3) NOT NULL,
    custo_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_perda_insumo FOREIGN KEY (insumo_id) REFERENCES insumos (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configuracoes (
    chave VARCHAR(80) NOT NULL,
    valor TEXT NOT NULL,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS custos_fixos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_fidelidade (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_fid (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS regras_fidelidade (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    valor_meta DECIMAL(10,2) NOT NULL DEFAULT 0,
    produto_id INT UNSIGNED DEFAULT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_fid_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS datas_bloqueadas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    data_bloqueada DATE NOT NULL,
    motivo VARCHAR(120) DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data (data_bloqueada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tokens_recuperacao (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_token_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
