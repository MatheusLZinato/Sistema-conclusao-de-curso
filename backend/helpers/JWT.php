<?php
declare(strict_types=1);
namespace App\Helpers;
use App\Config\Security;

class JWT {
    public static function generate(array $payload): string {
        $h = self::b64(json_encode(['alg'=>'HS256','typ'=>'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + Security::jwtExpiry();
        $p = self::b64(json_encode($payload));
        $s = self::b64(hash_hmac('sha256', "$h.$p", Security::jwtSecret(), true));
        return "$h.$p.$s";
    }
    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$h.$p", Security::jwtSecret(), true));
        if (!hash_equals($expected, $s)) return null;
        $payload = json_decode(self::b64d($p), true);
        if (!$payload || ($payload['exp'] ?? 0) < time()) return null;
        return $payload;
    }
    public static function fromRequest(): ?array {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($auth, 'Bearer ')) return null;
        return self::verify(substr($auth, 7));
    }
    private static function b64(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
    private static function b64d(string $d): string { return base64_decode(strtr($d, '-_', '+/')); }
}
