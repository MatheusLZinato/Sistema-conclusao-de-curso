<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Helpers\Response;

class AdminOnly {
    public static function check(): array {
        $p = Auth::check();
        if (!in_array($p['perfil'] ?? '', ['admin','cozinha'])) Response::forbidden('Acesso restrito a gestores.');
        return $p;
    }
    public static function strict(): array {
        $p = Auth::check();
        if (($p['perfil'] ?? '') !== 'admin') Response::forbidden('Acesso restrito ao administrador.');
        return $p;
    }
}
