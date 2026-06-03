<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\{Pedido, Produto, Insumo, Configuracao};
use App\Helpers\{Response, Validator, Email};

class PedidoController {
    private Pedido $pm; private Produto $pdm; private Insumo $im;
    public function __construct() {
        $this->pm = new Pedido(); $this->pdm = new Produto(); $this->im = new Insumo();
    }

    public function listar(array $f = []): void { Response::ok($this->pm->listar($f)); }

    public function detalhe(string $id): void {
        $p = $this->pm->findById($id);
        if (!$p) Response::notFound();
        Response::ok($p);
    }
    public function detalheRaw(string $id): array {
        return $this->pm->findById($id) ?? [];
    }

    public function criar(array $b, ?int $userId = null): void {
        $v = new Validator();
        $v->required('cliente_nome', $b['cliente_nome'] ?? null)
          ->required('itens', $b['itens'] ?? null);
        if ($v->fails()) Response::error('Dados obrigatórios faltando.', 422, $v->errors());

        $cfg = new Configuracao();
        $taxas = [
            'pix' => (float)$cfg->get('taxa_pix', 0),
            'dinheiro' => (float)$cfg->get('taxa_dinheiro', 0),
            'debito' => (float)$cfg->get('taxa_debito', 1.99),
            'credito' => (float)$cfg->get('taxa_credito', 3.49),
        ];
        $forma = $b['forma_pagamento'] ?? 'pix';
        $taxaPerc = $taxas[$forma] ?? 0;
        $repassa = ($b['repassa_taxa'] ?? false) === true;

        $itensDB = []; $subtotal = 0.0;
        foreach ((array)$b['itens'] as $it) {
            $prod = $this->pdm->findById((int)$it['produto_id']);
            if (!$prod) Response::error("Produto {$it['produto_id']} inexistente.");

            $var = null;
            foreach ($prod['opcoes'] ?? [] as $o) {
                if ($o['nome'] === ($it['variacao_nome'] ?? '')) { $var = $o; break; }
            }
            if (!$var) Response::error("Variação '{$it['variacao_nome']}' não encontrada.");

            $mult = (float)($var['multiplicador'] ?? 1);
            $preco = (float)$var['preco'];

            $custo = 0;
            foreach ($prod['receita'] ?? [] as $ing) {
                $custo += (float)$ing['custo_unitario'] * (float)$ing['quantidade'] * $mult;
            }
            $subtotal += $preco;
            $itensDB[] = [
                'produto_id' => $prod['id'],
                'variacao_nome' => $var['nome'],
                'valor_unitario' => $preco,
                'valor_liquido' => $preco * (1 - $taxaPerc / 100),
                'custo_insumos' => $custo,
                'personalizacao' => $it['personalizacao'] ?? null,
            ];
        }

        $valorTot = $repassa ? $subtotal / (1 - $taxaPerc / 100) : $subtotal;
        $valorLiq = $subtotal * (1 - $taxaPerc / 100);
        $tipo = $b['tipo_venda'] ?? 'pronta-entrega';
        $sinal = $tipo === 'encomenda' ? ($b['sinal_pago'] ?? $valorTot * 0.3) : $valorTot;

        $pedidoData = array_merge($b, [
            'usuario_id' => $userId,
            'valor_total' => round($valorTot, 2),
            'valor_liquido' => round($valorLiq, 2),
            'taxa_pagamento_perc' => $taxaPerc,
            'sinal_pago' => $sinal,
            'saldo_devedor' => max(0, $valorTot - $sinal),
            'status_pagamento' => ($valorTot - $sinal < 0.01) ? 'pago-total' : 'sinal-pago',
        ]);

        $pid = $this->pm->criar($pedidoData, $itensDB);

        // Estoque
        foreach ((array)$b['itens'] as $it) {
            $prod = $this->pdm->findById((int)$it['produto_id']);
            $var = null;
            foreach ($prod['opcoes'] ?? [] as $o) {
                if ($o['nome'] === ($it['variacao_nome'] ?? '')) { $var = $o; break; }
            }
            $mult = (float)($var['multiplicador'] ?? 1);
            foreach ($prod['receita'] ?? [] as $ing) {
                $qtd = (float)$ing['quantidade'] * $mult;
                if ($tipo === 'pronta-entrega') $this->im->baixarEstoque($ing['insumo_id'], $qtd);
                else $this->im->alocar($ing['insumo_id'], $pid, $qtd);
            }
        }

        Response::created(['id' => $pid], 'Pedido registrado com sucesso!');
    }

    public function atualizarStatus(string $id, string $novoStatus): void {
        $p = $this->pm->findById($id);
        if (!$p) Response::notFound();
        if (!in_array($novoStatus, ['Recebido','Preparando','Pronto','Entregue'])) Response::error('Status inválido.');

        // Encomenda indo para preparando: baixar estoque
        if ($novoStatus === 'Preparando' && $p['tipo_venda'] === 'encomenda') {
            foreach ($p['itens'] as $it) {
                if (!$it['produto_id']) continue;
                $prod = $this->pdm->findById((int)$it['produto_id']);
                $var = null;
                foreach ($prod['opcoes'] ?? [] as $o) {
                    if ($o['nome'] === $it['variacao_nome']) { $var = $o; break; }
                }
                $mult = (float)($var['multiplicador'] ?? 1);
                foreach ($prod['receita'] ?? [] as $ing) {
                    $this->im->baixarEstoque($ing['insumo_id'], (float)$ing['quantidade'] * $mult);
                }
            }
            $this->im->desalocar($id);
        }

        $this->pm->atualizarStatus($id, $novoStatus);
        Response::ok(null, "Pedido atualizado para '$novoStatus'.");
    }

    public function receberSaldo(string $id): void {
        $this->pm->receberSaldo($id);
        Response::ok(null, 'Saldo recebido. Pedido despachado.');
    }
    public function agenda(string $mes): void { Response::ok($this->pm->agenda($mes)); }
    public function responder(string $id, string $msg): void {
        $this->pm->salvarResposta($id, $msg);
        Response::ok(null, 'Mensagem enviada.');
    }
}
