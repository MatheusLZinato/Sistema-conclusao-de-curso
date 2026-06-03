<?php
declare(strict_types=1);
namespace App\Database;

use PDO;
use PDOException;
use Throwable;

/**
 * Auto-setup: cria banco, tabelas e dados iniciais em uma única chamada.
 * Chamado por bootstrap.php quando o lock file não existe.
 */
class Setup
{
    private string $host;
    private string $port;
    private string $name;
    private string $user;
    private string $pass;
    private string $lockFile;
    public array $log = [];

    public function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $this->port = $_ENV['DB_PORT'] ?? '3306';
        $this->name = $_ENV['DB_NAME'] ?? 'diego_gourmet_v4';
        $this->user = $_ENV['DB_USER'] ?? 'root';
        $this->pass = $_ENV['DB_PASS'] ?? '';
        $this->lockFile = dirname(__DIR__) . '/storage/installed.lock';
    }

    public function isInstalled(): bool
    {
        return file_exists($this->lockFile);
    }

    public function run(): array
    {
        try {
            $this->log[] = "Conectando ao MySQL em {$this->host}:{$this->port}…";
            $sock = $_ENV['DB_SOCKET'] ?? '';
            $dsn = $sock
                ? "mysql:unix_socket={$sock};charset=utf8mb4"
                : "mysql:host={$this->host};port={$this->port};charset=utf8mb4";
            $pdo = new PDO($dsn, $this->user, $this->pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->log[] = "✓ Conectado.";

            $this->log[] = "Criando banco `{$this->name}` (se não existir)…";
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$this->name}`");
            $this->log[] = "✓ Banco pronto.";

            $this->log[] = "Criando tabelas…";
            $pdo->exec($this->schemaSQL());
            $this->log[] = "✓ 18 tabelas criadas.";

            $this->log[] = "Populando dados iniciais…";
            $this->seed($pdo);
            $this->log[] = "✓ Seed concluído.";

            // Lock file impede re-execução
            @mkdir(dirname($this->lockFile), 0775, true);
            file_put_contents($this->lockFile, date('c'));
            $this->log[] = "✓ Sistema instalado em " . date('d/m/Y H:i:s');

            return ['success' => true, 'log' => $this->log];
        } catch (Throwable $e) {
            $this->log[] = "✗ ERRO: " . $e->getMessage();
            return ['success' => false, 'log' => $this->log, 'error' => $e->getMessage()];
        }
    }

    public function reset(): void
    {
        if (file_exists($this->lockFile)) unlink($this->lockFile);
    }

    private function schemaSQL(): string
    {
        return <<<'SQL'
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
SQL;
    }

    private function seed(PDO $pdo): void
    {
        $pepper = $_ENV['PASSWORD_PEPPER'] ?? 'pepper';
        $hashAdmin = password_hash('@admin' . $pepper, PASSWORD_BCRYPT, ['cost' => 12]);
        $hashUser  = password_hash('@user'  . $pepper, PASSWORD_BCRYPT, ['cost' => 12]);

        // Usuários
        $pdo->prepare("INSERT IGNORE INTO usuarios (id, nome, telefone, endereco, email, senha_hash, perfil, data_nascimento, data_cadastro)
            VALUES (?,?,?,?,?,?,?,?,?)")->execute(
            [1, 'Administrador', '31999999999', 'Viçosa - MG', 'admin@diegogourmet.com.br', $hashAdmin, 'admin', null, date('Y-m-d H:i:s')]);
        $pdo->prepare("INSERT IGNORE INTO usuarios (id, nome, telefone, endereco, email, senha_hash, perfil, data_nascimento, data_cadastro)
            VALUES (?,?,?,?,?,?,?,?,?)")->execute(
            [2, 'Diego Araújo', '31999999998', 'Rua Principal, 123 - Viçosa, MG', 'diego@email.com', $hashUser, 'cliente', '1990-05-12', date('Y-m-d H:i:s')]);
        $pdo->prepare("INSERT IGNORE INTO usuarios (id, nome, telefone, endereco, email, senha_hash, perfil, data_nascimento, data_cadastro)
            VALUES (?,?,?,?,?,?,?,?,?)")->execute(
            [3, 'Maria Silva Oliveira', '31988887777', 'Av. das Flores, 45 - Teixeiras, MG', 'maria@email.com', $hashUser, 'cliente', '1985-11-03', date('Y-m-d H:i:s')]);

        // Preferências
        $pdo->exec("INSERT IGNORE INTO preferencias_usuario (usuario_id, preferencia) VALUES
            (2,'Chocolate'),(2,'Tradicional'),(3,'Frutas'),(3,'Sem Lactose')");

        // Categorias
        $pdo->exec("INSERT IGNORE INTO categorias (id, nome) VALUES
            (1,'Bolos'),(2,'Tortas'),(3,'Doces'),(4,'Salgados'),(5,'Bolos de Pote')");

        // Insumos
        $pdo->exec("INSERT IGNORE INTO insumos (id, nome, custo_unitario, capacidade_max, unidade) VALUES
            ('I01','Farinha Premium',4.50,100,'kg'),
            ('I02','Chocolate 54%',65.00,20,'kg'),
            ('I03','Morangos Frescos',12.00,10,'cx'),
            ('I04','Creme de Leite',18.50,15,'L'),
            ('I05','Leite Condensado',8.50,50,'lata'),
            ('I06','Frango Desfiado',22.00,15,'kg'),
            ('I07','Ovos Brancos',0.70,200,'un'),
            ('I08','Limão Siciliano',2.50,50,'un'),
            ('I09','Manteiga Extra',45.00,10,'kg'),
            ('I10','Leite Ninho',35.00,20,'kg'),
            ('I11','Nutella',48.00,20,'kg')");

        // Lotes
        $pdo->exec("INSERT IGNORE INTO lotes (id, insumo_id, quantidade, data_inclusao, data_vencimento) VALUES
            ('L1','I01',40,'2026-02-05','2026-07-31'),
            ('L2','I02',15,'2026-03-10','2026-07-31'),
            ('L3','I03',8,'2026-04-18','2026-07-31'),
            ('L4','I04',10,'2026-04-12','2026-07-31'),
            ('L5','I05',40,'2026-03-01','2026-07-31'),
            ('L6','I06',8,'2026-04-15','2026-07-31'),
            ('L7','I07',120,'2026-03-10','2026-07-31'),
            ('L8','I08',30,'2026-04-15','2026-07-31'),
            ('L9','I09',5,'2026-03-10','2026-07-31'),
            ('L10','I10',10,'2026-03-10','2026-07-31'),
            ('L11','I11',5,'2026-03-10','2026-07-31')");

        // Produtos
        $produtos = [
            [1,1,'Bolo Sup. de Morango','Massa baunilha, creme belga e morangos.','1. Asse a massa branca a 180°C.','https://images.unsplash.com/photo-1565958011703-44f9829ba187?q=80&w=800','item-featured',1,'ambos',30,3],
            [3,1,'Bolo Assinatura Cacau','Blend de cacau 54%.','1. Asse a massa de chocolate.','https://images.unsplash.com/photo-1578985545062-69928b1d9587?q=80&w=800','item-horizontal',4,'ambos',50,3],
            [5,2,'Torta de Limão','Creme aveludado cítrico.','1. Asse a base sablée.','https://images.unsplash.com/photo-1519915028121-7d3463d20b13?q=80&w=400','item-standard',7,'ambos',30,2],
            [8,3,'Macarons Sortidos','Cores sortidas.','1. Bata o merengue.','https://images.unsplash.com/photo-1558326567-98ae2405596b?q=80&w=800','item-featured',10,'apenas-encomenda',50,5],
            [9,3,'Brigadeiro Gourmet','O clássico.','1. Cozinhe Leite Condensado.','https://images.unsplash.com/photo-1606890737304-57a1ca8a5b62?q=80&w=400','item-standard',5,'ambos',30,2],
            [12,4,'Empadão de Frango','Massa podre cremosa.','1. Prepare a massa podre.','https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?q=80&w=800','item-featured',6,'ambos',30,1],
            [13,4,'Coxinha Creme','Massa cremosa e frango.','1. Molde a massa.','https://images.unsplash.com/photo-1626082896492-766af4eb6501?q=80&w=400','item-standard',8,'ambos',30,1],
            [14,4,'Croissant (10 un)','Massa folhada.','1. Dobre a massa.','https://images.unsplash.com/photo-1555507036-ab1f40ce88cb?q=80&w=800','item-horizontal',9,'apenas-pronta',0,0],
            [18,5,'Bolo de Pote Morango','O sucesso em formato prático.','1. Esfarele a massa.','https://images.unsplash.com/photo-1550617931-e17a7b70dce2?q=80&w=400','item-standard',2,'apenas-pronta',0,0],
            [19,5,'Bolo Pote Ninho c/ Nutella','Leite ninho e creme de avelã.','1. Misture Ninho e Leite Condensado.','https://images.unsplash.com/photo-1550617931-e17a7b70dce2?q=80&w=400','item-standard',3,'apenas-pronta',0,0],
        ];
        $stP = $pdo->prepare("INSERT IGNORE INTO produtos (id, categoria_id, nome, descricao, modo_preparo, imagem_url, grid_vitrine, ordem_vitrine, permite_encomenda, sinal_minimo_perc, antecedencia_min_dias, ativo) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
        foreach ($produtos as $p) $stP->execute($p);

        // Variações
        $pdo->exec("INSERT IGNORE INTO variacoes_produto (produto_id, nome, preco, multiplicador) VALUES
            (1,'12 Fatias',180.00,1.0),(1,'24 Fatias',320.00,2.0),
            (3,'Padrão',210.00,1.0),(5,'Grande',120.00,1.0),
            (8,'Caixa 12',85.00,1.0),(9,'Cento',180.00,1.0),
            (12,'Família',95.00,1.0),(13,'Cento',160.00,1.0),
            (14,'Pacote 10',85.00,1.0),(18,'Pote 250ml',15.00,1.0),
            (19,'Pote 250ml',18.00,1.0)");

        // Ingredientes
        $pdo->exec("INSERT IGNORE INTO ingredientes_produto (produto_id, insumo_id, quantidade) VALUES
            (1,'I01',1.000),(1,'I03',2.000),(1,'I04',0.500),
            (3,'I01',1.000),(3,'I02',1.500),
            (5,'I01',0.500),(5,'I08',8.000),
            (8,'I07',4.000),
            (9,'I05',3.000),(9,'I02',0.500),
            (12,'I01',0.800),(12,'I06',1.000),
            (13,'I01',1.500),(13,'I06',2.000),
            (14,'I01',1.000),(14,'I09',0.500),
            (18,'I01',0.200),(18,'I03',0.500),(18,'I05',0.200),
            (19,'I01',0.200),(19,'I10',0.300),(19,'I11',0.200)");

        // Configurações
        $pdo->exec("INSERT IGNORE INTO configuracoes (chave, valor) VALUES
            ('taxa_debito','1.99'),('taxa_credito','3.49'),('taxa_pix','0.00'),
            ('taxa_dinheiro','0.00'),('prazo_recebimento_credito','30'),
            ('politica_taxa','manual'),('nome_loja','Diego Gourmet'),
            ('whatsapp_loja','31999999999')");

        // Custos Fixos
        $pdo->exec("INSERT IGNORE INTO custos_fixos (nome, valor, ativo) VALUES
            ('Aluguel',3500.00,1),('Energia',800.00,1),
            ('Mão de Obra',4500.00,1),('Embalagens Especiais',300.00,0)");

        // Tipos de Fidelidade
        $pdo->exec("INSERT IGNORE INTO tipos_fidelidade (nome) VALUES
            ('Meta de Compras (Qtd)'),('Meta de Compras (Valor R$)'),
            ('Desconto Aniversário (%)'),('Desconto Fixo (%)')");

        // Regras de Fidelidade
        $pdo->exec("INSERT IGNORE INTO regras_fidelidade (nome, tipo, valor_meta, produto_id, ativo) VALUES
            ('Doce Premium Gratuito','Meta de Compras (Qtd)',10,NULL,1),
            ('Desconto Mês de Aniversário','Desconto Aniversário (%)',15,NULL,1)");

        // Pedidos de exemplo
        $pdo->exec("INSERT IGNORE INTO pedidos (id, usuario_id, cliente_nome, cliente_telefone, cliente_endereco, modalidade, tipo_venda, data_pedido, data_entrega, hora_entrega, forma_pagamento, taxa_pagamento_perc, valor_total, valor_liquido, sinal_pago, saldo_devedor, status_pagamento, status_pedido) VALUES
            ('REQ-001',2,'Diego Araújo','31999999998','Rua Principal','entrega','pronta-entrega','2026-02-10','2026-02-10','14:00:00','pix',0,120,120,120,0,'pago-total','Entregue'),
            ('REQ-002',3,'Maria Silva','31988887777','Av. Flores','entrega','pronta-entrega','2026-02-20','2026-02-20','15:30:00','credito',3.49,180,173.72,180,0,'pago-total','Entregue'),
            ('REQ-003',2,'Diego Araújo','31999999998','Retirada','retirada','pronta-entrega','2026-03-05','2026-03-05','10:00:00','dinheiro',0,180,180,180,0,'pago-total','Entregue'),
            ('ENC-004',3,'Maria Silva','31988887777','Av. Flores','entrega','encomenda','2026-04-10','2026-04-14','18:00:00','pix',0,180,180,54,126,'sinal-pago','Preparando')");

        $pdo->exec("INSERT IGNORE INTO itens_pedido (pedido_id, produto_id, variacao_nome, valor_unitario, valor_liquido, custo_insumos) VALUES
            ('REQ-001',5,'Grande',120,120,35),
            ('REQ-002',1,'12 Fatias',180,173.72,68.5),
            ('REQ-003',9,'Cento',180,180,70),
            ('ENC-004',1,'12 Fatias',180,180,68.5)");
    }
}
