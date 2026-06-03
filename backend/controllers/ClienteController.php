<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\{Usuario, Pedido};
use App\Helpers\{Hash, Response, Validator};

class ClienteController {
    private Usuario $m;
    public function __construct() { $this->m = new Usuario(); }

    public function listar(): void {
        $c = $this->m->listarClientes();
        foreach ($c as &$x) $x['preferencias'] = $this->m->preferencias((int)$x['id']);
        Response::ok($c);
    }
    public function perfil(int $id): void {
        $u = $this->m->findById($id);
        if (!$u) Response::notFound();
        $u['preferencias'] = $this->m->preferencias($id);
        Response::ok($u);
    }
    public function atualizar(int $id, array $b): void {
        $d = [];
        if (!empty($b['nome'])) $d['nome'] = Validator::sanitize($b['nome']);
        if (!empty($b['telefone'])) $d['telefone'] = preg_replace('/\D/', '', $b['telefone']);
        if (!empty($b['endereco'])) $d['endereco'] = Validator::sanitize($b['endereco']);
        if (!empty($b['email'])) {
            $v = new Validator(); $v->email('email', $b['email']);
            if ($v->fails()) Response::error('E-mail inválido.', 422);
            $d['email'] = $b['email'];
        }
        if (!empty($b['nova_senha'])) {
            if ($b['nova_senha'] !== ($b['confirmar_senha'] ?? '')) Response::error('Senhas não coincidem.');
            $d['senha_hash'] = Hash::make($b['nova_senha']);
        }
        if (!empty($b['data_nascimento'])) $d['data_nascimento'] = $b['data_nascimento'];
        $this->m->atualizar($id, $d);
        if (isset($b['preferencias'])) $this->m->salvarPreferencias($id, (array)$b['preferencias']);
        Response::ok(null, 'Perfil atualizado.');
    }
    public function historico(int $id): void {
        $p = (new Pedido())->listar(['usuario_id' => $id]);
        Response::ok($p);
    }
}
