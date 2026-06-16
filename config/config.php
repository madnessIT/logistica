<?php
declare(strict_types=1);

// ============================================================
// config/config.php — Configuración global GEMZ Bolivia
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'gemz_cotizaciones');
define('DB_USER', 'root');
define('DB_PASS', 'M4nd4m4s');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'GEMZ Bolivia — Sistema de Cotizaciones');
define('APP_URL',  'https://cotizaciones.gemz.com.bo');


// Parámetros logísticos
define('PESO_MIN_WM_KG',    1000);    // Por encima de este peso/volumen aplica W/M
define('VOL_MIN_WM_M3',     1.0);
define('KG_MINIMO_DESC',    2500);    // Mínimo desconsolidación
define('M3_MINIMO_DESC',    2.9);
define('LARGO_EXTRA_PIES',  12);      // Umbral extra largo
define('COSTO_EXTRA_PIE',   12.0);    // USD por pie adicional
define('PESO_OWS_LBS',      5000);    // Umbral sobrepeso
define('COSTO_OWS_FIJO',    170.0);
define('COSTO_OWS_FORK',    300.0);
define('ALTURA_HC_M',       2.40);    // Umbral High Cube
define('VALOR_SED_USD',     2500.0);  // Umbral SED
define('COSTO_SED',         50.0);
define('COSTO_MARCAS',      300.0);   // Aclaración manifiesto
define('SIDEMAR_ERRORE',    100.0);
define('SIDEMAR_FUERA',     50.0);
define('INBOND_CANADA',     55.0);

// Sesión
define('SESSION_LIFETIME', 7200); // 2 horas en segundos
define('SESSION_NAME', 'GEMZ_SES');

// Roles
define('ROL_ADMIN',    1);
define('ROL_VENDEDOR', 2);
define('ROL_OPERADOR', 3);
define('ROL_CLIENTE',  4);
