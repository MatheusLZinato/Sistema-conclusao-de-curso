<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Helpers\Response;

class RateLimit {
    public static function check(string $key, int $max = 60, int $window = 60): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unk';
        $file = sys_get_temp_dir() . '/rl_' . md5($key . $ip) . '.json';
        $now = time();
        $d = file_exists($file) ? json_decode(file_get_contents($file), true) : ['c'=>0,'r'=>$now+$window];
        if ($now > $d['r']) $d = ['c'=>0,'r'=>$now+$window];
        $d['c']++;
        file_put_contents($file, json_encode($d));
        if ($d['c'] > $max) {
            header('Retry-After: ' . ($d['r'] - $now));
            Response::error('Muitas requisições. Tente em alguns segundos.', 429);
        }
    }
    public static function login(): void { self::check('login', 10, 300); }
}
