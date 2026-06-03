<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Configuracao;
use App\Helpers\Response;

class ConfiguracaoController {
    private Configuracao $m;
    public function __construct() { $this->m = new Configuracao(); }

    public function listar(): void {
        Response::ok([
            'taxas' => $this->m->todas(),
            'custos_fixos' => $this->m->custosFixos(),
            'fidelidade' => $this->m->regrasFidelidade(),
            'tipos_fidelidade' => $this->m->tiposFidelidade(),
            'datas_bloqueadas' => $this->m->datasBloqueadas(),
        ]);
    }

    public function salvar(array $b): void {
        foreach (['taxa_debito','taxa_credito','taxa_pix','taxa_dinheiro','prazo_recebimento_credito','politica_taxa','nome_loja','whatsapp_loja'] as $k) {
            if (isset($b[$k])) $this->m->set($k, (string)$b[$k]);
        }
        if (!empty($b['custos_fixos'])) $this->m->salvarCustosFixos($b['custos_fixos']);
        if (!empty($b['regras_fidelidade'])) $this->m->salvarRegrasFidelidade($b['regras_fidelidade']);
        Response::ok(null, 'Configurações salvas.');
    }

    public function bloquearData(array $b): void {
        $d = $b['data'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) Response::error('Data inválida (use YYYY-MM-DD).');
        if (!$this->m->bloquearData($d, $b['motivo'] ?? null)) Response::error('Data já estava bloqueada.', 409);
        Response::created(null, 'Data bloqueada.');
    }
    public function desbloquearData(string $data): void {
        if (!$this->m->desbloquearData($data)) Response::notFound();
        Response::ok(null, 'Data desbloqueada.');
    }
}
