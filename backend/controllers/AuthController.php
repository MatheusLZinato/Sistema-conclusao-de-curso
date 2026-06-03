<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Usuario;
use App\Helpers\{JWT, Hash, Response, Validator, Email};
use App\Config\App;

class AuthController {
    private Usuario $m;
    public function __construct() { $this->m = new Usuario(); }

    public function login(array $body): void {
        $v = new Validator();
        $v->required('login', $body['login'] ?? null)
          ->required('senha', $body['senha'] ?? null);
        if ($v->fails()) Response::error('Dados inválidos.', 422, $v->errors());

        $usuario = $this->m->findByLogin(Validator::sanitize($body['login']));
        if (!$usuario || !Hash::verify($body['senha'], $usuario['senha_hash'])) {
            Response::error('Credenciais inválidas.', 401);
        }

        $token = JWT::generate([
            'sub' => $usuario['id'],
            'perfil' => $usuario['perfil'],
            'nome' => $usuario['nome'],
        ]);
        unset($usuario['senha_hash']);
        $usuario['preferencias'] = $this->m->preferencias((int)$usuario['id']);
        Response::ok(['token' => $token, 'usuario' => $usuario], 'Login realizado!');
    }

    public function register(array $body): void {
        $v = new Validator();
        $v->required('nome', $body['nome'] ?? null)
          ->required('telefone', $body['telefone'] ?? null)
          ->required('senha', $body['senha'] ?? null);
        if (!empty($body['email'])) $v->email('email', $body['email']);
        if ($v->fails()) Response::error('Dados inválidos.', 422, $v->errors());

        // Telefone: exatamente 11 dígitos (DDD + número)
        $tel = preg_replace('/\D/', '', $body['telefone']);
        if (strlen($tel) !== 11) Response::error('O telefone deve ter exatamente 11 dígitos com DDD (ex: 31999999999).', 422);

        // Senha: mínimo 8 chars, 1 maiúscula, 1 símbolo
        $senha = $body['senha'] ?? '';
        if (strlen($senha) < 8) Response::error('A senha deve ter pelo menos 8 caracteres.', 422);
        if (!preg_match('/[A-Z]/', $senha)) Response::error('A senha deve conter pelo menos uma letra maiúscula.', 422);
        if (!preg_match('/[$#@!%*?&]/', $senha)) Response::error('A senha deve conter pelo menos um símbolo: $ # @ ! % * ? &', 422);

        if ($this->m->findByTelefone($tel)) Response::error('Telefone já cadastrado.', 409);
        if (!empty($body['email']) && $this->m->findByEmail($body['email'])) Response::error('E-mail já cadastrado.', 409);

        $id = $this->m->criar([
            'nome' => Validator::sanitize($body['nome']),
            'telefone' => $tel,
            'endereco' => Validator::sanitize($body['endereco'] ?? ''),
            'email' => $body['email'] ?? null,
            'senha_hash' => Hash::make($body['senha']),
            'perfil' => 'cliente',
            'data_nascimento' => $body['data_nascimento'] ?? null,
        ]);
        if (!empty($body['preferencias'])) $this->m->salvarPreferencias($id, (array)$body['preferencias']);

        $token = JWT::generate(['sub' => $id, 'perfil' => 'cliente', 'nome' => $body['nome']]);
        Response::created(['token' => $token, 'id' => $id], 'Cadastro realizado!');
    }

    public function refresh(array $p): void {
        $u = $this->m->findById((int)$p['sub']);
        if (!$u) Response::unauthorized();
        $token = JWT::generate(['sub' => $u['id'], 'perfil' => $u['perfil'], 'nome' => $u['nome']]);
        Response::ok(['token' => $token]);
    }

    public function forgotPassword(array $body): void {
        $email = Validator::sanitize($body['email'] ?? '');
        if (!$email) Response::error('E-mail obrigatório.');
        $u = $this->m->findByEmail($email);
        if ($u) {
            $token = Hash::token();
            $this->m->criarToken((int)$u['id'], hash('sha256', $token), date('Y-m-d H:i:s', strtotime('+1 hour')));
            $link = App::url() . "/reset-password?token=$token";
            Email::recuperarSenha($email, $u['nome'], $link);
        }
        Response::ok(null, 'Se o e-mail existir, instruções foram enviadas.');
    }

    public function resetPassword(array $body): void {
        $token = Validator::sanitize($body['token'] ?? '');
        $nova = $body['nova_senha'] ?? '';
        if (!$token || !$nova) Response::error('Token e nova senha são obrigatórios.');
        $reg = $this->m->verificarToken(hash('sha256', $token));
        if (!$reg) Response::error('Token inválido ou expirado.', 400);
        $this->m->atualizar((int)$reg['usuario_id'], ['senha_hash' => Hash::make($nova)]);
        $this->m->invalidarToken((int)$reg['id']);
        Response::ok(null, 'Senha redefinida com sucesso.');
    }
}
