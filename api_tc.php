<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!Auth::checkRole([ROL_ADMIN, ROL_OPERADOR], false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!Auth::verifyCsrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
    exit;
}

$tc = (float)($input['tc'] ?? 0);
if ($tc <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tipo de cambio inválido']);
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("UPDATE configuraciones SET valor = :val WHERE clave = 'tc_usd_bs'");
    $stmt->execute([':val' => (string)$tc]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al guardar en base de datos: ' . $e->getMessage()]);
}
