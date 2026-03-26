<?php
/**
 * API: Quitar cupón aplicado
 * POST: {}
 * Response: { ok: true }
 */
require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json');

unset($_SESSION['cupon_aplicado']);

echo json_encode(['ok' => true, 'msg' => 'Cupón removido']);
