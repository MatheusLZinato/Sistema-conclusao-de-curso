<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Config\Database;
use App\Models\Configuracao;
use App\Helpers\{Response, Exportador};
use PDO;

class BIController {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }

    public function kpis(?string $mes = null): void {
        $where = $mes ? "WHERE p.data_pedido LIKE :m" : '';
        $sql = "SELECT
            COALESCE(SUM(p.valor_total),0) AS receita_bruta,
            COALESCE(SUM(p.valor_liquido),0) AS receita_liquida,
            COALESCE(SUM(ip.custo_insumos),0) AS custo_total,
            COUNT(DISTINCT p.id) AS qtd_pedidos,
            COUNT(DISTINCT p.usuario_id) AS clientes_ativos
        FROM pedidos p LEFT JOIN itens_pedido ip ON p.id=ip.pedido_id $where";
        $s = $this->db->prepare($sql);
        $s->execute($mes ? [':m' => $mes . '%'] : []);
        $r = $s->fetch();

        $bruta = (float)$r['receita_bruta'];
        $liq = (float)$r['receita_liquida'];
        $custo = (float)$r['custo_total'];
        $qtd = (int)$r['qtd_pedidos'];
        $cmv = $bruta > 0 ? ($custo / $bruta) * 100 : 0;

        $cf = array_sum(array_column(
            array_filter((new Configuracao())->custosFixos(), fn($c) => $c['ativo']),
            'valor'
        ));
        $pend = $this->db->query("SELECT COUNT(*) FROM pedidos WHERE status_pedido != 'Entregue'")->fetchColumn();

        Response::ok([
            'receita_bruta' => round($bruta, 2),
            'receita_liquida' => round($liq, 2),
            'custo_insumos' => round($custo, 2),
            'custos_fixos' => round($cf, 2),
            'lucro_bruto' => round($liq - $custo, 2),
            'lucro_liquido' => round($liq - $custo - $cf, 2),
            'cmv_perc' => round($cmv, 2),
            'qtd_pedidos' => $qtd,
            'ticket_medio' => $qtd > 0 ? round($bruta / $qtd, 2) : 0,
            'clientes_ativos' => (int)$r['clientes_ativos'],
            'pedidos_pendentes' => (int)$pend,
        ]);
    }

    public function receitaPorCategoria(?string $mes = null): void {
        $w = $mes ? "AND p.data_pedido LIKE :m" : '';
        $s = $this->db->prepare("SELECT c.nome AS categoria, COALESCE(SUM(ip.valor_unitario),0) AS receita
            FROM itens_pedido ip JOIN produtos pr ON ip.produto_id=pr.id JOIN categorias c ON pr.categoria_id=c.id JOIN pedidos p ON ip.pedido_id=p.id
            WHERE 1=1 $w GROUP BY c.nome ORDER BY receita DESC");
        $s->execute($mes ? [':m' => $mes . '%'] : []);
        Response::ok($s->fetchAll());
    }

    public function roiProdutos(?string $mes = null): void {
        $w = $mes ? "AND p.data_pedido LIKE :m" : '';
        $s = $this->db->prepare("SELECT pr.nome AS produto, COUNT(ip.id) AS qtd_vendida,
            COALESCE(SUM(ip.valor_unitario),0) AS receita_total,
            COALESCE(SUM(ip.custo_insumos),0) AS custo_total,
            COALESCE(SUM(ip.valor_unitario - ip.custo_insumos),0) AS margem_total
        FROM itens_pedido ip JOIN produtos pr ON ip.produto_id=pr.id JOIN pedidos p ON ip.pedido_id=p.id
        WHERE 1=1 $w GROUP BY pr.id ORDER BY margem_total DESC");
        $s->execute($mes ? [':m' => $mes . '%'] : []);
        $rows = $s->fetchAll();
        foreach ($rows as &$r) {
            $r['margem_perc'] = (float)$r['receita_total'] > 0 ? round((float)$r['margem_total']/(float)$r['receita_total']*100,1) : 0;
        }
        Response::ok($rows);
    }

    public function volumeInsumos(?string $mes = null): void {
        $w = $mes ? "AND p.data_pedido LIKE :m" : '';
        $s = $this->db->prepare("SELECT i.nome AS insumo, i.unidade, COALESCE(SUM(ing.quantidade),0) AS volume_gasto
            FROM itens_pedido ip JOIN produtos pr ON ip.produto_id=pr.id JOIN ingredientes_produto ing ON pr.id=ing.produto_id
            JOIN insumos i ON ing.insumo_id=i.id JOIN pedidos p ON ip.pedido_id=p.id
            WHERE 1=1 $w GROUP BY i.id ORDER BY volume_gasto DESC LIMIT 10");
        $s->execute($mes ? [':m' => $mes . '%'] : []);
        Response::ok($s->fetchAll());
    }

    public function auditoriaPerdas(?string $mes = null): void {
        $w = $mes ? "WHERE ap.data_perda LIKE :m" : '';
        $s = $this->db->prepare("SELECT ap.*, i.nome AS nome_insumo FROM auditoria_perdas ap JOIN insumos i ON ap.insumo_id=i.id $w ORDER BY ap.data_perda DESC");
        $s->execute($mes ? [':m' => $mes . '%'] : []);
        Response::ok($s->fetchAll());
    }

    public function exportarCSV(?string $mes = null): void {
        $w = $mes ? "WHERE p.data_pedido LIKE '$mes%'" : '';
        $rows = $this->db->query("SELECT p.id,p.data_pedido,p.cliente_nome,p.forma_pagamento,p.valor_total,p.valor_liquido,p.status_pedido,pr.nome AS produto,ip.variacao_nome,ip.custo_insumos
            FROM pedidos p JOIN itens_pedido ip ON p.id=ip.pedido_id LEFT JOIN produtos pr ON ip.produto_id=pr.id $w ORDER BY p.data_pedido DESC")->fetchAll();
        $headers = ['Pedido','Data','Cliente','Pagamento','Total','Líquido','Status','Produto','Variação','Custo'];
        $data = array_map(fn($r) => [
            $r['id'],$r['data_pedido'],$r['cliente_nome'],$r['forma_pagamento'],
            number_format($r['valor_total'],2,',','.'),
            number_format($r['valor_liquido'],2,',','.'),
            $r['status_pedido'],$r['produto'],$r['variacao_nome'],
            number_format($r['custo_insumos'],2,',','.'),
        ], $rows);
        Exportador::csv($data, $headers, "BI_" . ($mes ?? 'completo') . ".csv");
    }
}
