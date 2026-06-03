<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Insumo;
use App\Helpers\Response;

class EstoqueController {
    private Insumo $m;
    public function __construct() { $this->m = new Insumo(); }

    public function listar(): void {
        $ins = $this->m->listar();
        foreach ($ins as &$i) {
            $i['qtd_total'] = $this->m->qtdTotal($i['id']);
            $i['qtd_alocada'] = $this->m->qtdAlocada($i['id']);
            $i['qtd_disponivel'] = $i['qtd_total'] - $i['qtd_alocada'];
        }
        Response::ok($ins);
    }
    public function alertas(): void { Response::ok($this->m->alertas()); }

    public function entradaLote(array $b): void {
        $iid = $b['insumo_id'] ?? null;
        $qtd = (float)($b['quantidade'] ?? 0);
        $custo = (float)($b['custo_unitario'] ?? 0);
        $entrada = $b['data_inclusao'] ?? date('Y-m-d');
        $venc = $b['data_vencimento'] ?? '';
        if (!$iid && empty($b['nome'])) Response::error('Insumo ou nome obrigatório.');
        if ($qtd <= 0 || !$venc) Response::error('Quantidade e vencimento obrigatórios.');
        if (!$iid) {
            $iid = $this->m->criar([
                'nome' => $b['nome'], 'custo_unitario' => $custo,
                'capacidade_max' => $qtd * 2, 'unidade' => $b['unidade'] ?? 'un',
            ]);
        } else {
            $this->m->atualizarCusto($iid, $custo);
        }
        $loteId = $this->m->adicionarLote($iid, $qtd, $entrada, $venc);
        Response::created(['lote_id' => $loteId, 'insumo_id' => $iid], 'Lote registrado.');
    }

    public function removerLote(string $lid): void {
        if (!$this->m->removerLote($lid)) Response::notFound();
        Response::ok(null, 'Lote removido.');
    }

    public function baixa(array $b): void {
        $iid = $b['insumo_id'] ?? null;
        $lid = $b['lote_id'] ?? null;
        $qtd = (float)($b['quantidade'] ?? 0);
        $mot = $b['motivo'] ?? 'Vencimento';
        if (!$iid || $qtd <= 0) Response::error('Insumo e quantidade obrigatórios.');
        $ins = $this->m->findById($iid);
        if (!$ins) Response::notFound();
        $custo = (float)$ins['custo_unitario'] * $qtd;
        $this->m->registrarPerda($iid, $lid, $qtd, $mot, $custo);
        Response::ok(null, 'Baixa registrada.');
    }

    public function alocado(): void {
        $r = [];
        foreach ($this->m->listar() as $i) {
            $a = $this->m->qtdAlocada($i['id']);
            if ($a > 0) $r[] = ['insumo_id' => $i['id'], 'nome' => $i['nome'], 'qtd_alocada' => $a, 'unidade' => $i['unidade']];
        }
        Response::ok($r);
    }
}
