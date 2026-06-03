<?php
declare(strict_types=1);
namespace App\Config;

class Security {
    public static function jwtSecret(): string { return $_ENV['JWT_SECRET'] ?? 'CHANGE_ME_TO_32_CHARS_MINIMUM_OK1'; }
    public static function jwtExpiry(): int { return (int)($_ENV['JWT_EXPIRY'] ?? 3600); }
    public static function pepper(): string { return $_ENV['PASSWORD_PEPPER'] ?? 'pepper'; }
    public static function bcryptCost(): int { return 12; }
}
