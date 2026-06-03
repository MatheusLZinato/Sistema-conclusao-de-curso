<?php
declare(strict_types=1);
namespace App\Config;
use PDO; use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $h = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $p = $_ENV['DB_PORT'] ?? '3306';
            $n = $_ENV['DB_NAME'] ?? 'diego_gourmet_v4';
            $u = $_ENV['DB_USER'] ?? 'root';
            $pw = $_ENV['DB_PASS'] ?? '';
            try {
                $sock = $_ENV['DB_SOCKET'] ?? '';
                $dsn = $sock
                    ? "mysql:unix_socket=$sock;dbname=$n;charset=utf8mb4"
                    : "mysql:host=$h;port=$p;dbname=$n;charset=utf8mb4";
                self::$instance = new PDO($dsn, $u, $pw, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success'=>false,'error'=>'Erro de conexão com o banco.']);
                exit;
            }
        }
        return self::$instance;
    }
}
