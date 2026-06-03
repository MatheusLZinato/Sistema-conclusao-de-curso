<?php
declare(strict_types=1);
namespace App\Config;

class App {
    public static function name(): string { return $_ENV['APP_NAME'] ?? 'Aura Gastronômica'; }
    public static function url(): string { return rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/'); }
    public static function debug(): bool { return ($_ENV['APP_DEBUG'] ?? 'false') === 'true'; }
    public static function env(): string { return $_ENV['APP_ENV'] ?? 'local'; }
    public static function uploadMaxMB(): int { return (int)($_ENV['UPLOAD_MAX_MB'] ?? 5); }
}
