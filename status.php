<?php
require_once __DIR__ . '/app/Core/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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
  <style>
    .card{background:#232323;border:1px solid #333;border-radius:12px;padding:16px;margin-bottom:16px;color:#fff}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
    .pill{display:inline-block;border:1px solid #444;border-radius:9999px;padding:6px 10px;margin-right:8px;font-size:12px}
    code{background:#1e1e1e;border:1px solid #333;border-radius:6px;padding:2px 6px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #333;padding:8px;text-align:left}
    th{color:#cbd5e1}
    .ok{color:#86efac}
    .warn{color:#fde68a}
    .err{color:#fca5a5}
  </style>
</head>
<body class="bg-[#181818] text-white">
  <div class="container-pro section">
    <h1 class="h2">Status del Sistema</h1>
    <div class="grid">
      <div class="card">
        <h3 class="h3">Entorno</h3>
        <div class="pill">APP_ENV: <strong><?= e(APP_ENV) ?></strong></div>
        <div class="pill">Base URL: <code><?= e(base_url()) ?></code></div>
        <div class="pill">PHP: <strong><?= e(PHP_VERSION) ?></strong></div>
      </div>
      <div class="card">
        <h3 class="h3">Sesión</h3>
        <div>Usuario: <strong><?= isset($_SESSION['usuario']['nombre']) ? e($_SESSION['usuario']['nombre']) : '—' ?></strong></div>
        <div>Items en carrito: <strong><?= isset($_SESSION['carrito']) ? array_sum(array_column($_SESSION['carrito'],'cantidad')) : 0 ?></strong></div>
      </div>
    </div>

    <div class="card">
      <h3 class="h3">Assets y Versiones</h3>
      <table>
        <thead><tr><th>Asset</th><th>Existe</th><th>mtime</th><th>md5</th><th>URL (cache-busting)</th></tr></thead>
        <tbody>
          <?php foreach ($assets as $name => $info): ?>
            <tr>
              <td><?= e($name) ?></td>
              <td class="<?= $info['exists'] ? 'ok' : 'err' ?>"><?= $info['exists'] ? 'Sí' : 'No' ?></td>
              <td><?= $info['mtime'] ? date('Y-m-d H:i:s', $info['mtime']) : '—' ?></td>
              <td><code><?= e($info['md5'] ?? '—') ?></code></td>
              <td><a class="text-blue-400" href="<?= e($info['url']) ?>" target="_blank"><?= e($info['url']) ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3 class="h3">Checks rápidos</h3>
      <ul>
        <li>Cabeceras no‑caché activas (dev): <strong class="<?= APP_ENV==='dev' ? 'ok' : 'warn' ?>"><?= APP_ENV==='dev' ? 'Sí' : 'No (prod)' ?></strong></li>
        <li>Ruta base correcta: <strong class="ok"><?= strpos(base_url(), 'http')===0 ? 'Sí' : 'No' ?></strong></li>
      </ul>
    </div>
  </div>
</body>
</html>