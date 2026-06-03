<?php
declare(strict_types=1);
namespace App\Helpers;
use App\Config\Security;

class Hash {
    public static function make(string $pass): string {
        return password_hash($pass . Security::pepper(), PASSWORD_BCRYPT, ['cost' => Security::bcryptCost()]);
    }
    public static function verify(string $pass, string $hash): bool {
        return password_verify($pass . Security::pepper(), $hash);
    }
    public static function token(int $bytes = 32): string {
        return bin2hex(random_bytes($bytes));
    }
}
