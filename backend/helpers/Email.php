<?php
declare(strict_types=1);
namespace App\Helpers;
use App\Config\{EmailConfig, App};

class Email {
    public static function send(string $to, string $subject, string $html): bool {
        if (!EmailConfig::isConfigured()) {
            error_log("[Email] SMTP não configurado. Email para $to: $subject");
            return false;
        }
        // PHPMailer se disponível
        $pmFile = BACKEND . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        if (file_exists($pmFile)) {
            return self::viaPHPMailer($to, $subject, $html);
        }
        // Fallback mail()
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                 . "From: " . EmailConfig::fromName() . " <" . EmailConfig::fromAddr() . ">\r\n";
        return @mail($to, $subject, $html, $headers);
    }

    private static function viaPHPMailer(string $to, string $subject, string $html): bool {
        require_once BACKEND . '/vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = EmailConfig::host();
            $mail->SMTPAuth = true;
            $mail->Username = EmailConfig::user();
            $mail->Password = EmailConfig::pass();
            $mail->SMTPSecure = 'tls';
            $mail->Port = EmailConfig::port();
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(EmailConfig::fromAddr(), EmailConfig::fromName());
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log("Email: " . $e->getMessage());
            return false;
        }
    }

    public static function template(string $name, array $vars = []): string {
        $file = ROOT . "/emails/templates/$name.html";
        if (!file_exists($file)) return '';
        $html = file_get_contents($file);
        foreach ($vars as $k => $v) $html = str_replace("{{$k}}", (string)$v, $html);
        return $html;
    }

    public static function confirmacaoPedido(string $to, string $nome, string $pedidoId, float $total): bool {
        return self::send($to, "Pedido #$pedidoId confirmado", self::template('confirmacao-pedido', [
            'nome_cliente' => $nome, 'pedido_id' => $pedidoId,
            'total' => 'R$ ' . number_format($total, 2, ',', '.'),
            'nome_loja' => App::name(),
        ]));
    }
    public static function atualizacaoStatus(string $to, string $nome, string $pedidoId, string $status): bool {
        return self::send($to, "Pedido #$pedidoId: $status", self::template('atualizacao-status', [
            'nome_cliente' => $nome, 'pedido_id' => $pedidoId,
            'status' => $status, 'nome_loja' => App::name(),
        ]));
    }
    public static function recuperarSenha(string $to, string $nome, string $link): bool {
        return self::send($to, "Recuperação de senha", self::template('recuperar-senha', [
            'nome_cliente' => $nome, 'link_reset' => $link, 'nome_loja' => App::name(),
        ]));
    }
}
