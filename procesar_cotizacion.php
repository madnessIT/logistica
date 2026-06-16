<?php
declare(strict_types=1);

// ============================================================
// procesar_cotizacion.php — Endpoint JSON para Fetch API
// ============================================================

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

Auth::startSession();

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// Solo Vendedor y Admin pueden crear cotizaciones
if (!Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR], false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Leer JSON o form data
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'application/json')) {
    $body  = file_get_contents('php://input');
    $input = json_decode($body, true) ?? [];
} else {
    $input = $_POST;
}

// Verificar CSRF
if (!Auth::verifyCsrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido']);
    exit;
}

// Validación básica
$requeridos = ['cliente_id', 'peso_kg', 'volumen_m3', 'origen', 'destino'];
foreach ($requeridos as $campo) {
    if (empty($input[$campo]) && $input[$campo] !== '0') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Campo requerido: {$campo}"]);
        exit;
    }
}

// Sanitizar
$input['peso_kg']            = abs((float) $input['peso_kg']);
$input['volumen_m3']         = abs((float) $input['volumen_m3']);
$input['valor_mercaderia']   = abs((float) ($input['valor_mercaderia'] ?? 0));
$input['largo_pies']         = abs((float) ($input['largo_pies']       ?? 0));
$input['peso_bulto_lbs']     = abs((float) ($input['peso_bulto_lbs']   ?? 0));
$input['altura_m']           = abs((float) ($input['altura_m']         ?? 0));
$input['cliente_id']         = (int) $input['cliente_id'];
$input['clase_imo']          = (int) ($input['clase_imo']    ?? 0);
$input['flash_point']        = (float) ($input['flash_point'] ?? 0);
$input['tiene_marcas']       = filter_var($input['tiene_marcas']     ?? true,  FILTER_VALIDATE_BOOLEAN);
$input['viene_paletizado']   = filter_var($input['viene_paletizado'] ?? true,  FILTER_VALIDATE_BOOLEAN);
$input['es_apilable']        = filter_var($input['es_apilable']      ?? true,  FILTER_VALIDATE_BOOLEAN);

// Acción
$accion = $input['accion'] ?? 'calcular';

try {
    $controller = new CotizacionController();

    if ($accion === 'calcular') {
        // Solo calcular, sin guardar
        $motor     = new MotorCotizacion();
        $resultado = $motor->calcular($input);
        echo json_encode($resultado);

    } elseif ($accion === 'guardar') {
        $resultado = $controller->crear($input);
        echo json_encode($resultado);

    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción no reconocida']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor']);
    // En desarrollo: error_log($e->getMessage());
}
