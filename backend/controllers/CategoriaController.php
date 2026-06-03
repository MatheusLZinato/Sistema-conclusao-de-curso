<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\Categoria;
use App\Helpers\{Response, Validator};

class CategoriaController {
    private Categoria $m;
    public function __construct() { $this->m = new Categoria(); }
    public function listar(): void { Response::ok($this->m->listar()); }
    public function criar(array $b): void {
        $nome = Validator::sanitize($b['nome'] ?? '');
        if (!$nome) Response::error('Nome obrigatório.');
        if ($this->m->findByNome($nome)) Response::error('Categoria já existe.');
        $id = $this->m->criar($nome);
        Response::created(['id' => $id], 'Categoria criada.');
    }
    public function deletar(int $id): void {
        if ($this->m->emUso($id)) Response::error('Categoria está em uso por produtos.');
        if (!$this->m->deletar($id)) Response::notFound();
        Response::ok(null, 'Categoria removida.');
    }
}
