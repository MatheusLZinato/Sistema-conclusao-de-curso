<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Produto;
use App\Helpers\{Response, Validator, Upload};

class ProdutoController {
    private Produto $m;
    public function __construct() { $this->m = new Produto(); }

    public function listar(?string $cat = null, bool $admin = false): void {
        Response::ok($this->m->listar(!$admin, $cat));
    }
    public function detalhe(int $id): void {
        $p = $this->m->findById($id);
        if (!$p) Response::notFound();
        Response::ok($p);
    }
    public function criar(array $b): void {
        $v = new Validator();
        $v->required('nome', $b['nome'] ?? null)
          ->required('categoria_id', $b['categoria_id'] ?? null);
        if ($v->fails()) Response::error('Dados inválidos.', 422, $v->errors());
        if (empty($b['opcoes'])) Response::error('Ao menos uma variação é obrigatória.');
        $id = $this->m->criar($b);
        Response::created(['id' => $id], 'Produto criado.');
    }
    public function editar(int $id, array $b): void {
        $atual = $this->m->findById($id);
        if (!$atual) Response::notFound();
        // Se trocou imagem e a antiga era upload local, remove
        if (!empty($b['imagem_url']) && $b['imagem_url'] !== $atual['imagem_url']
            && str_starts_with((string)$atual['imagem_url'], '/assets/images/uploads/')) {
            Upload::deletar($atual['imagem_url']);
        }
        $this->m->atualizar($id, $b);
        Response::ok(null, 'Produto atualizado.');
    }
    public function deletar(int $id): void {
        $p = $this->m->findById($id);
        if (!$p) Response::notFound();
        if (str_starts_with((string)$p['imagem_url'], '/assets/images/uploads/')) {
            Upload::deletar($p['imagem_url']);
        }
        $this->m->deletar($id);
        Response::ok(null, 'Produto removido.');
    }
    public function toggleAtivo(int $id): void {
        $this->m->toggleAtivo($id);
        Response::ok(null, 'Status alterado.');
    }
    public function reordenar(int $id, string $dir): void {
        if (!in_array($dir, ['up','down'])) Response::error('Direção inválida.');
        $this->m->reordenar($id, $dir);
        Response::ok(null, 'Ordem atualizada.');
    }
}
