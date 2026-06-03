<?php
declare(strict_types=1);
namespace App\Models;
use App\Config\Database;
use PDO;

class Configuracao {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }

    public function get(string $k, $def = null) {
        $s = $this->db->prepare("SELECT valor FROM configuracoes WHERE chave=?");
        $s->execute([$k]); $r = $s->fetch(); return $r ? $r['valor'] : $def;
    }
    public function set(string $k, string $v): void {
        $s = $this->db->prepare("INSERT INTO configuracoes (chave,valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=?");
        $s->execute([$k,$v,$v]);
    }
    public function todas(): array {
        return $this->db->query("SELECT chave,valor FROM configuracoes")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    public function custosFixos(): array {
        return $this->db->query("SELECT * FROM custos_fixos ORDER BY nome")->fetchAll();
    }
    public function salvarCustosFixos(array $custos): void {
        $this->db->exec("DELETE FROM custos_fixos");
        $s = $this->db->prepare("INSERT INTO custos_fixos (nome,valor,ativo) VALUES (?,?,?)");
        foreach ($custos as $c) $s->execute([$c['nome'],(float)$c['valor'],(int)($c['ativo']??1)]);
    }
    public function regrasFidelidade(): array {
        return $this->db->query("SELECT * FROM regras_fidelidade ORDER BY id")->fetchAll();
    }
    public function salvarRegrasFidelidade(array $r): void {
        $this->db->exec("DELETE FROM regras_fidelidade");
        $s = $this->db->prepare("INSERT INTO regras_fidelidade (nome,tipo,valor_meta,produto_id,ativo) VALUES (?,?,?,?,?)");
        foreach ($r as $reg) $s->execute([$reg['nome'],$reg['tipo'],(float)$reg['valor_meta'],$reg['produto_id']??null,(int)($reg['ativo']??1)]);
    }
    public function datasBloqueadas(): array {
        return array_column(
            $this->db->query("SELECT data_bloqueada FROM datas_bloqueadas ORDER BY data_bloqueada")->fetchAll(),
            'data_bloqueada'
        );
    }
    public function bloquearData(string $data, ?string $motivo = null): bool {
        $s = $this->db->prepare("INSERT IGNORE INTO datas_bloqueadas (data_bloqueada,motivo) VALUES (?,?)");
        return $s->execute([$data, $motivo]) && $s->rowCount() > 0;
    }
    public function desbloquearData(string $data): bool {
        $s = $this->db->prepare("DELETE FROM datas_bloqueadas WHERE data_bloqueada=?");
        $s->execute([$data]); return $s->rowCount() > 0;
    }
    public function tiposFidelidade(): array {
        return array_column($this->db->query("SELECT nome FROM tipos_fidelidade ORDER BY id")->fetchAll(), 'nome');
    }
}
