<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('BACKEND', __DIR__);
define('STORAGE', ROOT . '/storage');
define('UPLOADS', ROOT . '/public/assets/images/uploads');

// Carrega .env
if (file_exists(ROOT . '/.env')) {
    foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\"'");
    }
}

// Autoloader PSR-4
spl_autoload_register(function (string $class): void {
    $map = [
        'App\\Config\\'      => BACKEND . '/config/',
        'App\\Models\\'      => BACKEND . '/models/',
        'App\\Controllers\\' => BACKEND . '/controllers/',
        'App\\Middleware\\'  => BACKEND . '/middleware/',
        'App\\Helpers\\'     => BACKEND . '/helpers/',
        'App\\Database\\'    => ROOT . '/database/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) require_once $file;
            return;
        }
    }
});

// Headers padrão (não para upload multipart)
if (!defined('SKIP_JSON_HEADER')) {
    header('Content-Type: application/json; charset=utf-8');
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Auto-install: se ainda não instalado, executa setup
$setup = new App\Database\Setup();
if (!$setup->isInstalled() && !defined('SKIP_AUTO_INSTALL')) {
    $result = $setup->run();
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Falha no auto-setup do banco. Verifique credenciais em .env',
            'details' => $result['error'] ?? null,
            'log'     => $result['log'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Tratamento global de erros não capturados
set_exception_handler(function (Throwable $e) {
    $log = STORAGE . '/logs/erros.log';
    @file_put_contents($log, "[" . date('c') . "] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    http_response_code(500);
    $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    echo json_encode([
        'success' => false,
        'error'   => $debug ? $e->getMessage() : 'Erro interno do servidor.',
        'file'    => $debug ? basename($e->getFile()) . ':' . $e->getLine() : null,
    ], JSON_UNESCAPED_UNICODE);
});
