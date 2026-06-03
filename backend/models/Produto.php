<?php
declare(strict_types=1);
namespace App\Models;
use App\Config\Database;
use PDO;

class Produto {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }

    public function listar(bool $apenasAtivos = true, ?string $categoria = null): array {
        $sql = "SELECT p.*, c.nome AS categoria_nome FROM produtos p JOIN categorias c ON p.categoria_id=c.id";
        $w = []; $params = [];
        if ($apenasAtivos) $w[] = 'p.ativo=1';
        if ($categoria && $categoria !== 'Todos') { $w[] = 'c.nome=?'; $params[] = $categoria; }
        if ($w) $sql .= ' WHERE ' . implode(' AND ', $w);
        $sql .= ' ORDER BY p.ordem_vitrine, p.nome';
        $s = $this->db->prepare($sql);
        $s->execute($params);
        $produtos = $s->fetchAll();
        foreach ($produtos as &$p) {
            $p['opcoes'] = $this->variacoes((int)$p['id']);
            $p['receita'] = $this->ingredientes((int)$p['id']);
        }
        return $produtos;
    }

    public function findById(int $id): ?array {
        $s = $this->db->prepare("SELECT p.*, c.nome AS categoria_nome FROM produtos p JOIN categorias c ON p.categoria_id=c.id WHERE p.id=?");
        $s->execute([$id]);
        $p = $s->fetch();
        if (!$p) return null;
        $p['opcoes'] = $this->variacoes($id);
        $p['receita'] = $this->ingredientes($id);
        return $p;
    }

    public function variacoes(int $pid): array {
        $s = $this->db->prepare("SELECT * FROM variacoes_produto WHERE produto_id=? ORDER BY preco");
        $s->execute([$pid]); return $s->fetchAll();
    }

    public function ingredientes(int $pid): array {
        $s = $this->db->prepare("SELECT ip.*, i.nome AS nome_insumo, i.unidade, i.custo_unitario FROM ingredientes_produto ip JOIN insumos i ON ip.insumo_id=i.id WHERE ip.produto_id=?");
        $s->execute([$pid]); return $s->fetchAll();
    }

    public function criar(array $d): int {
        $s = $this->db->prepare("INSERT INTO produtos (categoria_id,nome,descricao,nutricional,modo_preparo,imagem_url,grid_vitrine,ordem_vitrine,permite_encomenda,sinal_minimo_perc,antecedencia_min_dias,ativo) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
        $s->execute([$d['categoria_id'],$d['nome'],$d['descricao']??'',$d['nutricional']??'',$d['modo_preparo']??'',$d['imagem_url']??'',$d['grid_vitrine']??'item-standard',$d['ordem_vitrine']??99,$d['permite_encomenda']??'ambos',$d['sinal_minimo_perc']??30,$d['antecedencia_min_dias']??3]);
        $id = (int)$this->db->lastInsertId();
        if (!empty($d['opcoes']))  $this->salvarVariacoes($id, $d['opcoes']);
        if (!empty($d['receita'])) $this->salvarIngredientes($id, $d['receita']);
        return $id;
    }

    public function atualizar(int $id, array $d): bool {
        $s = $this->db->prepare("UPDATE produtos SET categoria_id=?,nome=?,descricao=?,nutricional=?,modo_preparo=?,imagem_url=?,grid_vitrine=?,ordem_vitrine=?,permite_encomenda=?,sinal_minimo_perc=?,antecedencia_min_dias=? WHERE id=?");
        $s->execute([$d['categoria_id'],$d['nome'],$d['descricao']??'',$d['nutricional']??'',$d['modo_preparo']??'',$d['imagem_url']??'',$d['grid_vitrine']??'item-standard',$d['ordem_vitrine']??99,$d['permite_encomenda']??'ambos',$d['sinal_minimo_perc']??30,$d['antecedencia_min_dias']??3,$id]);
        if (isset($d['opcoes']))  $this->salvarVariacoes($id, $d['opcoes']);
        if (isset($d['receita'])) $this->salvarIngredientes($id, $d['receita']);
        return true;
    }

    public function toggleAtivo(int $id): bool {
        $this->db->prepare("UPDATE produtos SET ativo = NOT ativo WHERE id=?")->execute([$id]);
        return true;
    }

    public function deletar(int $id): bool {
        $s = $this->db->prepare("DELETE FROM produtos WHERE id=?");
        $s->execute([$id]); return $s->rowCount() > 0;
    }

    public function reordenar(int $id, string $dir): void {
        $all = $this->db->query("SELECT id,ordem_vitrine FROM produtos WHERE ativo=1 ORDER BY ordem_vitrine")->fetchAll();
        $idx = array_search($id, array_column($all, 'id'));
        if ($idx === false) return;
        $swap = $dir === 'up' ? $idx - 1 : $idx + 1;
        if ($swap < 0 || $swap >= count($all)) return;
        $s = $this->db->prepare("UPDATE produtos SET ordem_vitrine=? WHERE id=?");
        $s->execute([$all[$swap]['ordem_vitrine'], $all[$idx]['id']]);
        $s->execute([$all[$idx]['ordem_vitrine'], $all[$swap]['id']]);
    }

    private function salvarVariacoes(int $pid, array $ops): void {
        $this->db->prepare("DELETE FROM variacoes_produto WHERE produto_id=?")->execute([$pid]);
        $s = $this->db->prepare("INSERT INTO variacoes_produto (produto_id,nome,preco,multiplicador) VALUES (?,?,?,?)");
        foreach ($ops as $o) $s->execute([$pid,$o['nome'],$o['preco'],$o['mult']??$o['multiplicador']??1.0]);
    }
    private function salvarIngredientes(int $pid, array $rec): void {
        $this->db->prepare("DELETE FROM ingredientes_produto WHERE produto_id=?")->execute([$pid]);
        $s = $this->db->prepare("INSERT INTO ingredientes_produto (produto_id,insumo_id,quantidade) VALUES (?,?,?)");
        foreach ($rec as $r) $s->execute([$pid,$r['id']??$r['insumo_id'],$r['qtd']??$r['quantidade']]);
    }
}
