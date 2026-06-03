<?php
/**
 * Regenera os hashes de senha dos usuários seed.
 * Uso: php database/seeders/gerar_hashes.php
 * (Normalmente NÃO é necessário — o Setup.php já gera no auto-install)
 */
define('SKIP_AUTO_INSTALL', true);
require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
use App\Config\Database;
use App\Helpers\Hash;

$db = Database::get();
$usuarios = [[1,'@admin'],[2,'@user'],[3,'@user']];
$st = $db->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?");
foreach ($usuarios as [$id,$senha]) {
    $st->execute([Hash::make($senha), $id]);
    echo "Usuário ID $id → hash atualizado.\n";
}
echo "\nPronto! admin=@admin, clientes=@user\n";
