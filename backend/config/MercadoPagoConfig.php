<?php
declare(strict_types=1);
namespace App\Config;

class MercadoPagoConfig {
    public static function accessToken(): string { return $_ENV['MP_ACCESS_TOKEN'] ?? ''; }
    public static function publicKey(): string { return $_ENV['MP_PUBLIC_KEY'] ?? ''; }
    public static function webhookSecret(): string { return $_ENV['MP_WEBHOOK_SECRET'] ?? ''; }
    public static function baseUrl(): string { return 'https://api.mercadopago.com'; }
    public static function isConfigured(): bool { return !empty($_ENV['MP_ACCESS_TOKEN']); }
}
