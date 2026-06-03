<?php
declare(strict_types=1);
namespace App\Models;
use App\Config\Database;
use PDO;

class Pagamento {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }

    public function criar(string $pid, string $forma, float $valor, float $taxa, ?string $mpId=null, ?string $mpSt=null, ?array $payload=null): int {
        $s = $this->db->prepare("INSERT INTO pagamentos (pedido_id,mp_payment_id,mp_status,forma,valor,taxa_aplicada,payload_json) VALUES (?,?,?,?,?,?,?)");
        $s->execute([$pid,$mpId,$mpSt,$forma,$valor,$taxa,$payload?json_encode($payload):null]);
        return (int)$this->db->lastInsertId();
    }
    public function atualizarStatus(string $mpId, string $st, array $payload): void {
        $this->db->prepare("UPDATE pagamentos SET mp_status=?,payload_json=? WHERE mp_payment_id=?")->execute([$st,json_encode($payload),$mpId]);
    }
    public function findByPedido(string $pid): array {
        $s = $this->db->prepare("SELECT * FROM pagamentos WHERE pedido_id=? ORDER BY criado_em DESC");
        $s->execute([$pid]); return $s->fetchAll();
    }
}
