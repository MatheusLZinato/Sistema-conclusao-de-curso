<?php
declare(strict_types=1);
namespace App\Helpers;
use App\Config\App;

class Upload {
    private const ALLOWED_TYPES = ['image/jpeg','image/png','image/webp','image/gif'];
    private const ALLOWED_EXT = ['jpg','jpeg','png','webp','gif'];

    /**
     * Recebe um arquivo do $_FILES e salva em /public/assets/images/uploads/
     * Retorna a URL relativa (ex: /assets/images/uploads/prod-abc123.jpg)
     */
    public static function imagem(array $file, string $prefix = 'img'): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'erro' => 'Falha no upload: ' . ($file['error'] ?? 'sem arquivo')];
        }
        $maxBytes = App::uploadMaxMB() * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            return ['ok' => false, 'erro' => "Arquivo maior que " . App::uploadMaxMB() . "MB."];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, self::ALLOWED_TYPES, true)) {
            return ['ok' => false, 'erro' => "Tipo não permitido: $mime. Use JPG, PNG, WEBP ou GIF."];
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) $ext = 'jpg';
        $nome = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destino = UPLOADS . '/' . $nome;
        @mkdir(UPLOADS, 0775, true);
        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            return ['ok' => false, 'erro' => 'Não foi possível salvar o arquivo.'];
        }
        return ['ok' => true, 'url' => '/assets/images/uploads/' . $nome, 'arquivo' => $nome];
    }

    public static function deletar(string $url): bool {
        $arquivo = basename(parse_url($url, PHP_URL_PATH) ?: $url);
        $path = UPLOADS . '/' . $arquivo;
        if (file_exists($path) && str_starts_with(realpath($path) ?: '', realpath(UPLOADS) ?: '')) {
            return @unlink($path);
        }
        return false;
    }
}
