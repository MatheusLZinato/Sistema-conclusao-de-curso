<?php
declare(strict_types=1);
namespace App\Models;
use App\Config\Database;
use PDO;

class Insumo {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }

    public function listar(): array {
        $insumos = $this->db->query("SELECT * FROM insumos ORDER BY nome")->fetchAll();
        foreach ($insumos as &$i) $i['lotes'] = $this->lotes($i['id']);
        return $insumos;
    }
    public function findById(string $id): ?array {
        $s = $this->db->prepare("SELECT * FROM insumos WHERE id=?");
        $s->execute([$id]); $i = $s->fetch();
        if (!$i) return null;
        $i['lotes'] = $this->lotes($id); return $i;
    }
    public function lotes(string $id): array {
        $s = $this->db->prepare("SELECT * FROM lotes WHERE insumo_id=? ORDER BY data_vencimento");
        $s->execute([$id]); return $s->fetchAll();
    }
    public function qtdTotal(string $id): float {
        $s = $this->db->prepare("SELECT COALESCE(SUM(quantidade),0) FROM lotes WHERE insumo_id=?");
        $s->execute([$id]); return (float)$s->fetchColumn();
    }
    public function qtdAlocada(string $id): float {
        $s = $this->db->prepare("SELECT COALESCE(SUM(quantidade),0) FROM estoque_alocado WHERE insumo_id=?");
        $s->execute([$id]); return (float)$s->fetchColumn();
    }
    public function criar(array $d): string {
        $id = 'I' . substr((string)time(), -6);
        $this->db->prepare("INSERT INTO insumos (id,nome,custo_unitario,capacidade_max,unidade) VALUES (?,?,?,?,?)")
                 ->execute([$id,$d['nome'],$d['custo_unitario']??0,$d['capacidade_max']??100,$d['unidade']??'un']);
        return $id;
    }
    public function atualizarCusto(string $id, float $custo): void {
        $this->db->prepare("UPDATE insumos SET custo_unitario=? WHERE id=?")->execute([$custo,$id]);
    }
    public function adicionarLote(string $iid, float $qtd, string $entrada, string $venc): string {
        $loteId = 'L' . substr((string)microtime(true) * 1000, -8);
        $this->db->prepare("INSERT INTO lotes (id,insumo_id,quantidade,data_inclusao,data_vencimento) VALUES (?,?,?,?,?)")
                 ->execute([$loteId,$iid,$qtd,$entrada,$venc]);
        return $loteId;
    }
    public function removerLote(string $loteId): bool {
        $s = $this->db->prepare("DELETE FROM lotes WHERE id=?");
        $s->execute([$loteId]); return $s->rowCount() > 0;
    }
    public function baixarEstoque(string $iid, float $qtd): void {
        $lotes = $this->db->prepare("SELECT * FROM lotes WHERE insumo_id=? AND quantidade>0 ORDER BY data_vencimento");
        $lotes->execute([$iid]);
        foreach ($lotes->fetchAll() as $l) {
            if ($qtd <= 0) break;
            if ($l['quantidade'] >= $qtd) {
                $this->db->prepare("UPDATE lotes SET quantidade=quantidade-? WHERE id=?")->execute([$qtd,$l['id']]);
                $qtd = 0;
            } else {
                $qtd -= $l['quantidade'];
                $this->db->prepare("UPDATE lotes SET quantidade=0 WHERE id=?")->execute([$l['id']]);
            }
        }
    }
    public function alocar(string $iid, string $pid, float $qtd): void {
        $s = $this->db->prepare("SELECT id FROM estoque_alocado WHERE insumo_id=? AND pedido_id=?");
        $s->execute([$iid,$pid]);
        $row = $s->fetch();
        if ($row) $this->db->prepare("UPDATE estoque_alocado SET quantidade=quantidade+? WHERE id=?")->execute([$qtd,$row['id']]);
        else $this->db->prepare("INSERT INTO estoque_alocado (insumo_id,pedido_id,quantidade) VALUES (?,?,?)")->execute([$iid,$pid,$qtd]);
    }
    public function desalocar(string $pid): void {
        $this->db->prepare("DELETE FROM estoque_alocado WHERE pedido_id=?")->execute([$pid]);
    }
    public function registrarPerda(string $iid, ?string $lid, float $qtd, string $motivo, float $custo): void {
        $this->db->prepare("INSERT INTO auditoria_perdas (insumo_id,lote_id,data_perda,motivo,quantidade,custo_total) VALUES (?,?,NOW(),?,?,?)")
                 ->execute([$iid,$lid,$motivo,$qtd,$custo]);
        if ($lid) $this->db->prepare("UPDATE lotes SET quantidade=GREATEST(0,quantidade-?) WHERE id=?")->execute([$qtd,$lid]);
    }
    public function alertas(): array {
        $alertas = [];
        $hoje = new \DateTime();
        foreach ($this->listar() as $i) {
            $tot = $this->qtdTotal($i['id']);
            $alc = $this->qtdAlocada($i['id']);
            $disp = $tot - $alc;
            $perc = $i['capacidade_max'] > 0 ? ($tot / $i['capacidade_max']) * 100 : 0;
            if ($tot <= 0) $alertas[] = ['tipo'=>'danger','insumo'=>$i['nome'],'msg'=>'Estoque zerado'];
            elseif ($disp <= 0 && $alc > 0) $alertas[] = ['tipo'=>'danger','insumo'=>$i['nome'],'msg'=>'Totalmente alocado'];
            elseif ($perc < 25) $alertas[] = ['tipo'=>'warning','insumo'=>$i['nome'],'msg'=>round($perc) . '% restante'];
            foreach ($i['lotes'] as $l) {
                if ((float)$l['quantidade'] <= 0) continue;
                $dias = (int)$hoje->diff(new \DateTime($l['data_vencimento']))->format('%R%a');
                if ($dias < 0) $alertas[] = ['tipo'=>'danger','insumo'=>$i['nome'],'msg'=>"Vencido há " . abs($dias) . " dias"];
                elseif ($dias <= 3) $alertas[] = ['tipo'=>'warning','insumo'=>$i['nome'],'msg'=>"Vence em $dias dia(s)"];
            }
        }
        return $alertas;
    }
}
