<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Helpers\{Upload, Response};

class UploadController {
    public function imagem(string $prefix = 'prod'): void {
        if (!isset($_FILES['arquivo'])) Response::error('Nenhum arquivo enviado (campo: arquivo).');
        $r = Upload::imagem($_FILES['arquivo'], $prefix);
        if (!$r['ok']) Response::error($r['erro']);
        Response::created(['url' => $r['url'], 'arquivo' => $r['arquivo']], 'Upload concluído.');
    }
}
