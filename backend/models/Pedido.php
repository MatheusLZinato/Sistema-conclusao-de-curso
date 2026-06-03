<?php
declare(strict_types=1);
namespace App\Models;
use App\Config\Database;
use PDO;

class Pedido {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }

    public function listar(array $f = []): array {
        $sql = "SELECT p.*, u.email AS email_cliente FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id=u.id WHERE 1=1";
        $params = [];
        if (!empty($f['status']))     { $sql .= " AND p.status_pedido=?"; $params[] = $f['status']; }
        if (!empty($f['usuario_id'])) { $sql .= " AND p.usuario_id=?"; $params[] = $f['usuario_id']; }
        if (!empty($f['mes']))        { $sql .= " AND p.data_pedido LIKE ?"; $params[] = $f['mes'] . '%'; }
        $sql .= " ORDER BY p.criado_em DESC";
        $s = $this->db->prepare($sql);
        $s->execute($params);
        $pedidos = $s->fetchAll();
        foreach ($pedidos as &$p) $p['itens'] = $this->itens($p['id']);
        return $pedidos;
    }
    public function findById(string $id): ?array {
        $s = $this->db->prepare("SELECT p.*, u.email AS email_cliente FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id=u.id WHERE p.id=?");
        $s->execute([$id]);
        $p = $s->fetch();
        if (!$p) return null;
        $p['itens'] = $this->itens($id);
        return $p;
    }
    public function itens(string $pid): array {
        $s = $this->db->prepare("SELECT ip.*, pr.nome AS produto_nome, pr.imagem_url FROM itens_pedido ip LEFT JOIN produtos pr ON ip.produto_id=pr.id WHERE ip.pedido_id=?");
        $s->execute([$pid]); return $s->fetchAll();
    }
    public function criar(array $d, array $itens): string {
        $prefix = ($d['tipo_venda'] ?? 'pronta-entrega') === 'encomenda' ? 'ENC' : 'REQ';
        $id = $prefix . '-' . substr((string)time(), -6) . rand(10,99);
        $hoje = date('Y-m-d');
        $dataEntrega = !empty($d['data_entrega']) ? $d['data_entrega'] : $hoje;
        $horaEntrega = !empty($d['hora_entrega']) ? $d['hora_entrega'] : '12:00:00';
        $s = $this->db->prepare("INSERT INTO pedidos (id,usuario_id,cliente_nome,cliente_telefone,cliente_endereco,modalidade,tipo_venda,data_pedido,data_entrega,hora_entrega,forma_pagamento,taxa_pagamento_perc,valor_total,valor_liquido,sinal_pago,saldo_devedor,status_pagamento,status_pedido,alergias,observacoes) VALUES (?,?,?,?,?,?,?,CURDATE(),?,?,?,?,?,?,?,?,?,?,?,?)");
        $s->execute([
            $id, $d['usuario_id']??null, $d['cliente_nome'], $d['cliente_telefone']??'', $d['cliente_endereco']??'',
            $d['modalidade']??'entrega', $d['tipo_venda']??'pronta-entrega', $dataEntrega, $horaEntrega,
            $d['forma_pagamento']??'pix', $d['taxa_pagamento_perc']??0, $d['valor_total'], $d['valor_liquido']??$d['valor_total'],
            $d['sinal_pago']??$d['valor_total'], $d['saldo_devedor']??0,
            $d['status_pagamento']??'pago-total', 'Recebido', $d['alergias']??null, $d['observacoes']??null,
        ]);
        $si = $this->db->prepare("INSERT INTO itens_pedido (pedido_id,produto_id,variacao_nome,valor_unitario,valor_liquido,custo_insumos,personalizacao) VALUES (?,?,?,?,?,?,?)");
        foreach ($itens as $it) $si->execute([$id,$it['produto_id'],$it['variacao_nome'],$it['valor_unitario'],$it['valor_liquido']??$it['valor_unitario'],$it['custo_insumos']??0,$it['personalizacao']??null]);
        return $id;
    }
    public function atualizarStatus(string $id, string $s): bool {
        $st = $this->db->prepare("UPDATE pedidos SET status_pedido=? WHERE id=?");
        $st->execute([$s,$id]); return $st->rowCount() > 0;
    }
    public function receberSaldo(string $id): bool {
        $s = $this->db->prepare("UPDATE pedidos SET sinal_pago=valor_total,saldo_devedor=0,status_pagamento='pago-total',status_pedido='Entregue' WHERE id=?");
        $s->execute([$id]); return $s->rowCount() > 0;
    }
    public function salvarResposta(string $id, string $r): void {
        $this->db->prepare("UPDATE pedidos SET resposta_admin=? WHERE id=?")->execute([$r,$id]);
    }
    public function agenda(string $mes): array {
        $s = $this->db->prepare("SELECT p.*, ip.produto_id, pr.nome AS produto_nome, ip.variacao_nome FROM pedidos p JOIN itens_pedido ip ON p.id=ip.pedido_id LEFT JOIN produtos pr ON ip.produto_id=pr.id WHERE p.status_pedido != 'Entregue' AND p.data_entrega LIKE ? ORDER BY p.data_entrega, p.hora_entrega");
        $s->execute([$mes . '%']); return $s->fetchAll();
    }
}
