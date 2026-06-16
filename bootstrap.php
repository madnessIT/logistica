<?php
declare(strict_types=1);

// ============================================================
// bootstrap.php — Cargador central de clases
// Incluir este archivo en TODOS los puntos de entrada (.php)
// en lugar de incluir config y clases por separado.
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';

// Cargar TC dinámico de la base de datos (con auto-migración)
try {
    $db = Database::getInstance();
    $db->exec("CREATE TABLE IF NOT EXISTS configuraciones (
        clave VARCHAR(50) NOT NULL PRIMARY KEY,
        valor VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB");

    // Insertar tipo de cambio semilla si no existe
    $db->exec("INSERT IGNORE INTO configuraciones (clave, valor) VALUES ('tc_usd_bs', '6.96')");

    $stmt = $db->query("SELECT valor FROM configuraciones WHERE clave = 'tc_usd_bs' LIMIT 1");
    $dbVal = $stmt->fetchColumn();
    if ($dbVal !== false) {
        define('TC_USD_BS', (float)$dbVal);
    }
} catch (Throwable $e) {
    // Si algo falla, definiremos el fallback más adelante
}

// Fallback por si falló la base de datos
if (!defined('TC_USD_BS')) {
    define('TC_USD_BS', 6.96);
}

// Clases base (sin dependencias)
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/MotorCotizacion.php';

// Controladores (dependen de las clases anteriores)
require_once __DIR__ . '/controllers/TarifarioController.php';
require_once __DIR__ . '/controllers/CotizacionController.php';
