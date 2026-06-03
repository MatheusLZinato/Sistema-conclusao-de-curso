<?php
/**
 * OPCIONAL — só é necessário se você criar o banco MANUALMENTE via schema.sql.
 * Com o auto-setup (padrão), os hashes já são gerados corretamente.
 *
 * Uso: php backend/helpers/gerar_hashes_seed.php
 */
require_once __DIR__ . '/../bootstrap.php';
use App\Config\Database;
use App\Helpers\Hash;

$db = Database::get();
$usuarios = [[1,'@admin'],[2,'@user'],[3,'@user']];
$st = $db->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?");
foreach ($usuarios as [$id,$senha]) {
    $st->execute([Hash::make($senha), $id]);
    echo "Usuário ID $id → hash atualizado.\n";
}
echo "\nSenhas: Admin=@admin (tel 31999999999), Clientes=@user\n";
