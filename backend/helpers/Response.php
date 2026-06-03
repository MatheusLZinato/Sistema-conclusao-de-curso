<?php
declare(strict_types=1);
namespace App\Helpers;

class Response {
    public static function ok($data = null, string $msg = 'OK', int $code = 200): void {
        http_response_code($code);
        echo json_encode(['success'=>true,'message'=>$msg,'data'=>$data], JSON_UNESCAPED_UNICODE);
        exit;
    }
    public static function created($data = null, string $msg = 'Criado.'): void { self::ok($data, $msg, 201); }
    public static function error(string $msg, int $code = 400, $details = null): void {
        http_response_code($code);
        $b = ['success'=>false,'error'=>$msg];
        if ($details !== null) $b['details'] = $details;
        echo json_encode($b, JSON_UNESCAPED_UNICODE);
        exit;
    }
    public static function unauthorized(string $m = 'Não autorizado.'): void { self::error($m, 401); }
    public static function forbidden(string $m = 'Acesso negado.'): void { self::error($m, 403); }
    public static function notFound(string $m = 'Não encontrado.'): void { self::error($m, 404); }
    public static function serverError(string $m = 'Erro interno.'): void { self::error($m, 500); }
}
