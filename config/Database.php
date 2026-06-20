<?php
declare(strict_types=1);

// ============================================================
// config/Database.php — Singleton PDO
// ============================================================

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = DB_HOST;
            $port = '';
            if (strpos($host, ':') !== false) {
                list($host, $port) = explode(':', $host, 2);
            }

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $host, DB_NAME, DB_CHARSET
            );
            if ($port !== '') {
                $dsn .= ';port=' . $port;
            }

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }
}
