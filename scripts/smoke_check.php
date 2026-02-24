<?php
// Smoke test simple para verificar que estilos clave estén presentes
require_once __DIR__ . '/../app/Core/bootstrap.php';

$checks = [];

function check_css_contains(string $relPath, string $needle): array {
    $file = BASE_PATH . '/assets/' . ltrim($relPath, '/');
    if (!is_file($file)) return ['file' => $relPath, 'exists' => false, 'ok' => false, 'msg' => 'Archivo no existe'];
    $css = @file_get_contents($file);
    $ok = $css !== false && strpos($css, $needle) !== false;
    return ['file' => $relPath, 'exists' => true, 'ok' => $ok, 'msg' => $ok ? 'OK' : 'No se encontró el selector'];
}

$checks[] = check_css_contains('css/pedidos.css', '.order-state-badge');
$checks[] = check_css_contains('css/theme.css', '--primary-color');

header('Content-Type: application/json');
echo json_encode([
  'env' => APP_ENV,
  'base_url' => base_url(),
  'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>