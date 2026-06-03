<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Helpers\{JWT, Response};

class Auth {
    public static function check(): array {
        $p = JWT::fromRequest();
        if (!$p) Response::unauthorized('Token inválido ou expirado.');
        return $p;
    }
    public static function optional(): ?array { return JWT::fromRequest(); }
}
