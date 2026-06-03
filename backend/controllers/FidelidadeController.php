<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Config\Database;
use App\Models\Configuracao;
use App\Helpers\Response;
use PDO;

class FidelidadeController {
    private Configuracao $cfg; private PDO $db;
    public function __construct() { $this->cfg = new Configuracao(); $this->db = Database::get(); }

    public function regras(): void { Response::ok($this->cfg->regrasFidelidade()); }

    public function progressoCliente(int $uid): void {
        $regras = array_filter($this->cfg->regrasFidelidade(), fn($r) => $r['ativo']);
        $s = $this->db->prepare("SELECT COUNT(DISTINCT id) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM pedidos WHERE usuario_id=?");
        $s->execute([$uid]); $stats = $s->fetch();
        $resultado = [];
        foreach ($regras as $r) {
            $meta = (float)$r['valor_meta']; $tipo = $r['tipo'];
            if (str_contains($tipo, 'Qtd')) {
                $atual = (int)$stats['qtd'] % max(1, (int)$meta);
                $resultado[] = ['regra' => $r['nome'], 'tipo' => $tipo, 'atual' => $atual, 'meta' => $meta, 'perc' => $meta > 0 ? round($atual / $meta * 100) : 0];
            } elseif (str_contains($tipo, 'Valor')) {
                $atual = fmod((float)$stats['total'], max(0.01, $meta));
                $resultado[] = ['regra' => $r['nome'], 'tipo' => $tipo, 'atual' => round($atual,2), 'meta' => $meta, 'perc' => $meta > 0 ? round($atual / $meta * 100) : 0];
            } elseif (str_contains($tipo, 'Aniversário')) {
                $b = $this->db->prepare("SELECT data_nascimento FROM usuarios WHERE id=?");
                $b->execute([$uid]); $u = $b->fetch();
                $is = $u && $u['data_nascimento'] && date('m') === date('m', strtotime($u['data_nascimento']));
                $resultado[] = ['regra' => $r['nome'], 'tipo' => $tipo, 'ativo' => $is, 'desconto_perc' => $meta];
            }
        }
        Response::ok($resultado);
    }
}
