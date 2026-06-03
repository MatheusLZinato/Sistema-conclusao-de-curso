<?php
declare(strict_types=1);
namespace App\Models;
use App\Config\Database;
use PDO;

class Categoria {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }
    public function listar(): array {
        return $this->db->query("SELECT * FROM categorias ORDER BY nome")->fetchAll();
    }
    public function findByNome(string $nome): ?array {
        $s = $this->db->prepare("SELECT * FROM categorias WHERE nome=?");
        $s->execute([$nome]); return $s->fetch() ?: null;
    }
    public function criar(string $nome): int {
        $this->db->prepare("INSERT INTO categorias (nome) VALUES (?)")->execute([$nome]);
        return (int)$this->db->lastInsertId();
    }
    public function deletar(int $id): bool {
        $s = $this->db->prepare("DELETE FROM categorias WHERE id=?");
        $s->execute([$id]); return $s->rowCount() > 0;
    }
    public function emUso(int $id): bool {
        $s = $this->db->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id=?");
        $s->execute([$id]); return (int)$s->fetchColumn() > 0;
    }
}
