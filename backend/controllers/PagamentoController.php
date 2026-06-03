<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\{Pagamento, Pedido, Configuracao};
use App\Helpers\{MercadoPago, Response};
use App\Config\MercadoPagoConfig;

class PagamentoController {
    private Pagamento $pgm; private Pedido $pm;
    public function __construct() { $this->pgm = new Pagamento(); $this->pm = new Pedido(); }

    public function criarPix(array $b): void {
        if (!MercadoPagoConfig::isConfigured()) {
            Response::error('Mercado Pago não configurado. Defina MP_ACCESS_TOKEN em .env', 503);
        }
        $pid = $b['pedido_id'] ?? '';
        $email = $b['email'] ?? 'cliente@diegogourmet.com.br';
        $nome = $b['nome_pagador'] ?? 'Cliente';
        $cpf = $b['cpf'] ?? '';
        $p = $this->pm->findById($pid);
        if (!$p) Response::notFound();

        $r = MercadoPago::criarPix((float)$p['valor_total'], "Pedido #$pid", $pid, $email, $nome, $cpf);
        if ($r['status'] !== 201) Response::error('Erro ao gerar PIX.', 502, $r['data']);

        $mp = $r['data'];
        $this->pgm->criar($pid, 'pix', (float)$p['valor_total'], 0, (string)($mp['id']??''), $mp['status']??'pending', $mp);
        Response::ok([
            'mp_payment_id' => $mp['id'] ?? null,
            'status' => $mp['status'] ?? 'pending',
            'qr_code' => $mp['point_of_interaction']['transaction_data']['qr_code'] ?? null,
            'qr_code_base64' => $mp['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null,
            'copia_cola' => $mp['point_of_interaction']['transaction_data']['qr_code'] ?? null,
        ], 'PIX gerado.');
    }

    public function criarCartao(array $b): void {
        if (!MercadoPagoConfig::isConfigured()) Response::error('Mercado Pago não configurado.', 503);
        $pid = $b['pedido_id'] ?? ''; $token = $b['card_token'] ?? '';
        if (!$pid || !$token) Response::error('pedido_id e card_token obrigatórios.');
        $parc = (int)($b['parcelas'] ?? 1);
        $email = $b['email'] ?? 'cliente@diegogourmet.com.br';
        $p = $this->pm->findById($pid);
        if (!$p) Response::notFound();

        $r = MercadoPago::criarCartao((float)$p['valor_total'], $token, $parc, "Pedido #$pid", $pid, $email);
        if (!in_array($r['status'], [200,201])) Response::error('Pagamento recusado.', 502, $r['data']);

        $mp = $r['data'];
        $taxa = (float)(new Configuracao())->get('taxa_credito', 3.49);
        $this->pgm->criar($pid, 'cartao', (float)$p['valor_total'], $taxa, (string)($mp['id']??''), $mp['status']??'', $mp);
        if (($mp['status']??'') === 'approved') $this->pm->atualizarStatus($pid, 'Recebido');
        Response::ok(['mp_payment_id' => $mp['id']??null, 'status' => $mp['status']??'', 'detail' => $mp['status_detail']??''], 'Processado.');
    }

    public function webhook(string $raw, string $sig): void {
        if (!MercadoPago::validarWebhook($raw, $sig)) Response::error('Assinatura inválida.', 401);
        $d = json_decode($raw, true) ?? [];
        if (($d['type'] ?? '') !== 'payment' || empty($d['data']['id'])) {
            http_response_code(200); echo json_encode(['ok'=>true]); exit;
        }
        $mpId = (string)$d['data']['id'];
        $r = MercadoPago::consultarStatus($mpId);
        if ($r['status'] !== 200) { http_response_code(200); echo json_encode(['ok'=>true]); exit; }
        $mp = $r['data'];
        $this->pgm->atualizarStatus($mpId, $mp['status']??'', $mp);
        if (($mp['status']??'') === 'approved' && !empty($mp['external_reference'])) {
            $this->pm->atualizarStatus($mp['external_reference'], 'Recebido');
        }
        http_response_code(200); echo json_encode(['ok'=>true]); exit;
    }

    public function consultarStatus(string $mpId): void {
        $r = MercadoPago::consultarStatus($mpId);
        if ($r['status'] !== 200) Response::notFound();
        Response::ok(['status' => $r['data']['status']??'', 'detail' => $r['data']['status_detail']??'']);
    }
    public function cancelar(string $mpId): void {
        $r = MercadoPago::cancelar($mpId);
        if ($r['status'] !== 200) Response::error('Não foi possível cancelar.');
        Response::ok(null, 'Pagamento cancelado.');
    }
}
