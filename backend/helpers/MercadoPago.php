<?php
declare(strict_types=1);
namespace App\Helpers;
use App\Config\MercadoPagoConfig;

class MercadoPago {
    private static function req(string $method, string $path, array $body = []): array {
        $url = MercadoPagoConfig::baseUrl() . $path;
        $token = MercadoPagoConfig::accessToken();
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . bin2hex(random_bytes(16)),
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $code, 'data' => json_decode($r ?: '{}', true) ?? []];
    }

    public static function criarPix(float $valor, string $desc, string $pedidoId, string $email, string $nome, string $cpf = ''): array {
        return self::req('POST', '/v1/payments', [
            'transaction_amount' => $valor,
            'description' => $desc,
            'payment_method_id' => 'pix',
            'external_reference' => $pedidoId,
            'payer' => [
                'email' => $email,
                'first_name' => explode(' ', $nome)[0],
                'last_name' => implode(' ', array_slice(explode(' ', $nome), 1)) ?: '-',
                'identification' => ['type' => 'CPF', 'number' => preg_replace('/\D/', '', $cpf)],
            ],
        ]);
    }

    public static function criarCartao(float $valor, string $token, int $parcelas, string $desc, string $pedidoId, string $email): array {
        return self::req('POST', '/v1/payments', [
            'transaction_amount' => $valor,
            'token' => $token,
            'description' => $desc,
            'installments' => $parcelas,
            'external_reference' => $pedidoId,
            'payer' => ['email' => $email],
        ]);
    }

    public static function consultarStatus(string $mpId): array { return self::req('GET', "/v1/payments/$mpId"); }
    public static function cancelar(string $mpId): array { return self::req('PUT', "/v1/payments/$mpId", ['status' => 'cancelled']); }

    public static function validarWebhook(string $payload, string $signature): bool {
        $secret = MercadoPagoConfig::webhookSecret();
        if (!$secret) return true;
        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }
}
