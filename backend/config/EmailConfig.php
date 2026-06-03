<?php
declare(strict_types=1);
namespace App\Config;

class EmailConfig {
    public static function host(): string { return $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com'; }
    public static function port(): int { return (int)($_ENV['MAIL_PORT'] ?? 587); }
    public static function user(): string { return $_ENV['MAIL_USER'] ?? ''; }
    public static function pass(): string { return $_ENV['MAIL_PASS'] ?? ''; }
    public static function fromName(): string { return $_ENV['MAIL_FROM_NAME'] ?? 'Aura Gastronômica'; }
    public static function fromAddr(): string { return $_ENV['MAIL_FROM_ADDRESS'] ?? ''; }
    public static function isConfigured(): bool { return !empty($_ENV['MAIL_USER']) && !empty($_ENV['MAIL_PASS']); }
}
