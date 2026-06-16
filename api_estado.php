<?php
declare(strict_types=1);
// api_estado.php — Actualizar estado de cotización (Operador / Admin)
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
    exit;
}

if (!Auth::checkRole([ROL_ADMIN, ROL_OPERADOR], false)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Acceso denegado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!Auth::verifyCsrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'CSRF inválido']);
    exit;
}

$id    = (int)   ($input['cotizacion_id'] ?? 0);
$est   = trim(   $input['estado']         ?? '');
$nota  = trim(   $input['nota']           ?? '');

if (!$id || !$est) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Datos incompletos']);
    exit;
}

try {
    $ctrl = new CotizacionController();
    $ok   = $ctrl->actualizarEstado($id, $est, $nota);
    echo json_encode(['ok' => $ok]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Error interno']);
}
