<?php
declare(strict_types=1);
namespace App\Models;
use App\Config\Database;
use PDO;

class Usuario {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }

    public function findById(int $id): ?array {
        $s = $this->db->prepare("SELECT id,nome,telefone,endereco,email,perfil,data_nascimento,data_cadastro FROM usuarios WHERE id=? AND ativo=1");
        $s->execute([$id]); return $s->fetch() ?: null;
    }
    public function findByEmail(string $e): ?array {
        $s = $this->db->prepare("SELECT * FROM usuarios WHERE email=? AND ativo=1");
        $s->execute([$e]); return $s->fetch() ?: null;
    }
    public function findByTelefone(string $t): ?array {
        $s = $this->db->prepare("SELECT * FROM usuarios WHERE telefone=? AND ativo=1");
        $s->execute([$t]); return $s->fetch() ?: null;
    }
    public function findByLogin(string $login): ?array {
        // Tenta por telefone, depois email
        $tel = preg_replace('/\D/', '', $login);
        if ($tel && strlen($tel) >= 10) {
            $u = $this->findByTelefone($tel);
            if ($u) return $u;
        }
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) return $this->findByEmail($login);
        return null;
    }
    public function criar(array $d): int {
        $s = $this->db->prepare("INSERT INTO usuarios (nome,telefone,endereco,email,senha_hash,perfil,data_nascimento) VALUES (?,?,?,?,?,?,?)");
        $s->execute([$d['nome'],$d['telefone'],$d['endereco']??'',$d['email']??null,$d['senha_hash'],$d['perfil']??'cliente',$d['data_nascimento']??null]);
        return (int)$this->db->lastInsertId();
    }
    public function atualizar(int $id, array $d): bool {
        $f=[]; $p=[];
        foreach (['nome','telefone','endereco','email','senha_hash','data_nascimento'] as $k) {
            if (array_key_exists($k, $d)) { $f[] = "$k=?"; $p[] = $d[$k]; }
        }
        if (!$f) return false;
        $p[] = $id;
        $s = $this->db->prepare("UPDATE usuarios SET " . implode(',', $f) . " WHERE id=?");
        return $s->execute($p);
    }
    public function preferencias(int $id): array {
        $s = $this->db->prepare("SELECT preferencia FROM preferencias_usuario WHERE usuario_id=?");
        $s->execute([$id]);
        return array_column($s->fetchAll(), 'preferencia');
    }
    public function salvarPreferencias(int $id, array $prefs): void {
        $this->db->prepare("DELETE FROM preferencias_usuario WHERE usuario_id=?")->execute([$id]);
        $s = $this->db->prepare("INSERT INTO preferencias_usuario (usuario_id,preferencia) VALUES (?,?)");
        foreach (array_unique($prefs) as $p) if ($p) $s->execute([$id, $p]);
    }
    public function listarClientes(): array {
        return $this->db->query("SELECT id,nome,telefone,endereco,email,data_nascimento,data_cadastro FROM usuarios WHERE perfil='cliente' AND ativo=1 ORDER BY nome")->fetchAll();
    }
    public function criarToken(int $id, string $h, string $exp): void {
        $this->db->prepare("INSERT INTO tokens_recuperacao (usuario_id,token_hash,expira_em) VALUES (?,?,?)")->execute([$id,$h,$exp]);
    }
    public function verificarToken(string $h): ?array {
        $s = $this->db->prepare("SELECT * FROM tokens_recuperacao WHERE token_hash=? AND usado=0 AND expira_em > NOW()");
        $s->execute([$h]); return $s->fetch() ?: null;
    }
    public function invalidarToken(int $id): void {
        $this->db->prepare("UPDATE tokens_recuperacao SET usado=1 WHERE id=?")->execute([$id]);
    }
}
