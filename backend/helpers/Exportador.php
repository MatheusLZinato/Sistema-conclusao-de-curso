<?php
declare(strict_types=1);
namespace App\Helpers;

class Exportador {
    public static function csv(array $rows, array $headers, string $filename): void {
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, $headers, ';');
        foreach ($rows as $r) fputcsv($out, $r, ';');
        fclose($out);
        exit;
    }
}
