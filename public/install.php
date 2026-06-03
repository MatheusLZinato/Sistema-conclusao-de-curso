<?php
/**
 * Instalador Web — Aura Gastronômica V4
 * Acesse http://localhost:8000/install.php para (re)instalar o banco.
 */
declare(strict_types=1);
define('SKIP_AUTO_INSTALL', true);
define('SKIP_JSON_HEADER', true);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

use App\Database\Setup;

$setup = new Setup();
$acao = $_GET['acao'] ?? '';
$resultado = null;

if ($acao === 'instalar') {
    $resultado = $setup->run();
} elseif ($acao === 'reinstalar') {
    $setup->reset();
    $resultado = $setup->run();
}

$jaInstalado = $setup->isInstalled();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalador — Aura Gastronômica V4</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,Segoe UI,sans-serif;background:linear-gradient(135deg,#1a1a2e,#16213e);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:#fff;border-radius:16px;max-width:560px;width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4)}
  .head{background:linear-gradient(135deg,#D4AF37,#B8941F);padding:32px;text-align:center;color:#1a1a2e}
  .head h1{font-size:1.5rem;margin-bottom:6px}
  .body{padding:32px}
  .status{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:.95rem}
  .status.ok{background:#EAF3DE;color:#3B6D11;border:1px solid #97C459}
  .status.warn{background:#FFF8E6;color:#8B6914;border:1px solid #E8C547}
  .log{background:#1a1a2e;color:#7FD962;font-family:'SF Mono',Monaco,monospace;font-size:.82rem;padding:18px;border-radius:10px;max-height:300px;overflow:auto;margin-bottom:20px;line-height:1.7}
  .log .err{color:#FF6B6B}
  .btn{display:block;width:100%;padding:14px;border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;text-decoration:none;text-align:center;margin-bottom:10px}
  .btn-primary{background:#D4AF37;color:#1a1a2e}
  .btn-primary:hover{background:#B8941F}
  .btn-danger{background:#fff;color:#C53030;border:1px solid #C53030}
  .btn-success{background:#38A169;color:#fff}
  .info{font-size:.85rem;color:#666;line-height:1.6;margin-top:16px}
  .info code{background:#f0f0f0;padding:2px 6px;border-radius:4px;font-size:.8rem}
</style>
</head>
<body>
<div class="card">
  <div class="head"><h1>🎂 Aura Gastronômica V4</h1><p>Instalador Automático do Sistema</p></div>
  <div class="body">
    <?php if ($resultado): ?>
      <div class="status <?= $resultado['success'] ? 'ok' : 'warn' ?>">
        <?= $resultado['success'] ? '✓ Instalação concluída com sucesso!' : '✗ Erro durante a instalação.' ?>
      </div>
      <div class="log">
        <?php foreach ($resultado['log'] as $linha): ?>
          <div class="<?= str_contains($linha, 'ERRO') || str_contains($linha, '✗') ? 'err' : '' ?>"><?= htmlspecialchars($linha) ?></div>
        <?php endforeach; ?>
      </div>
      <?php if ($resultado['success']): ?>
        <a href="/" class="btn btn-success">Abrir o Sistema →</a>
      <?php endif; ?>
    <?php elseif ($jaInstalado): ?>
      <div class="status ok">✓ O sistema já está instalado e pronto para uso.</div>
      <a href="/" class="btn btn-primary">Abrir o Sistema →</a>
      <a href="?acao=reinstalar" class="btn btn-danger" onclick="return confirm('Isso vai recriar as tabelas. Os dados de seed serão restaurados (dados criados depois serão preservados se já existirem). Continuar?')">Reinstalar do Zero</a>
    <?php else: ?>
      <div class="status warn">⚠ O sistema ainda não foi instalado.</div>
      <p class="info">Antes de instalar, confirme que o arquivo <code>.env</code> existe na raiz com as credenciais corretas do MySQL (<code>DB_USER</code> e <code>DB_PASS</code>).</p>
      <a href="?acao=instalar" class="btn btn-primary" style="margin-top:16px">Instalar Agora</a>
    <?php endif; ?>
    <p class="info">
      <strong>Login Admin:</strong> tel <code>31999999999</code> / senha <code>@admin</code><br>
      <strong>Login Cliente:</strong> tel <code>31999999998</code> / senha <code>@user</code>
    </p>
  </div>
</div>
</body>
</html>
