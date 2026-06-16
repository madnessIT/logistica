<?php
declare(strict_types=1);

// ============================================================
// bootstrap.php — Cargador central de clases
// Incluir este archivo en TODOS los puntos de entrada (.php)
// en lugar de incluir config y clases por separado.
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';

// Clases base (sin dependencias)
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/MotorCotizacion.php';

// Controladores (dependen de las clases anteriores)
require_once __DIR__ . '/controllers/TarifarioController.php';
require_once __DIR__ . '/controllers/CotizacionController.php';
