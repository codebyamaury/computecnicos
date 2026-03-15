<?php
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
// Sesión manejada por bootstrap (DB handler)

$assets = asset_versions([
  'css/main.css',
  'css/theme.css',
  'css/pedidos.css',
  'css/checkout.css',
  'js/cart.js'
]);

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Status del Sistema | Computécnicos</title>
  <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
  <body class="bg-[#181818] text-white">
  <div class="container-pro section">
    <h1 class="h2">Status del Sistema</h1>
    <div class="status-grid">
      <div class="status-card">
        <h3 class="h3">Entorno</h3>
        <div class="status-pill">APP_ENV: <strong><?= e(APP_ENV) ?></strong></div>
        <div class="status-pill">Base URL: <span class="status-code"><?= e(base_url()) ?></span></div>
        <div class="status-pill">PHP: <strong><?= e(PHP_VERSION) ?></strong></div>
      </div>
      <div class="status-card">
        <h3 class="h3">Sesión</h3>
        <div>Usuario: <strong><?= isset($_SESSION['usuario']['nombre']) ? e($_SESSION['usuario']['nombre']) : '—' ?></strong></div>
        <div>Items en carrito: <strong><?= isset($_SESSION['carrito']) ? array_sum(array_column($_SESSION['carrito'],'cantidad')) : 0 ?></strong></div>
      </div>
    </div>

    <div class="status-card">
      <h3 class="h3">Assets y Versiones</h3>
      <table class="status-table">
        <thead><tr><th>Asset</th><th>Existe</th><th>mtime</th><th>md5</th><th>URL (cache-busting)</th></tr></thead>
        <tbody>
          <?php foreach ($assets as $name => $info): ?>
            <tr>
              <td><?= e($name) ?></td>
              <td class="<?= $info['exists'] ? 'status-ok' : 'status-err' ?>"><?= $info['exists'] ? 'Sí' : 'No' ?></td>
              <td><?= $info['mtime'] ? date('Y-m-d H:i:s', $info['mtime']) : '—' ?></td>
              <td><span class="status-code"><?= e($info['md5'] ?? '—') ?></span></td>
              <td><a class="text-blue-400" href="<?= e($info['url']) ?>" target="_blank"><?= e($info['url']) ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="status-card">
      <h3 class="h3">Checks rápidos</h3>
      <ul>
        <li>Cabeceras no‑caché activas (dev): <strong class="<?= APP_ENV==='dev' ? 'status-ok' : 'status-warn' ?>"><?= APP_ENV==='dev' ? 'Sí' : 'No (prod)' ?></strong></li>
        <li>Ruta base correcta: <strong class="status-ok"><?= strpos(base_url(), 'http')===0 ? 'Sí' : 'No' ?></strong></li>
      </ul>
    </div>
  </div>
</body>
</html>