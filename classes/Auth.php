<?php
declare(strict_types=1);

// ============================================================
// classes/Auth.php — Middleware de Autenticación y Autorización RBAC
// ============================================================

class Auth
{
    // --------------------------------------------------------
    // Iniciar sesión segura
    // --------------------------------------------------------
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => true,   // HTTPS en producción
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    // --------------------------------------------------------
    // Login: valida credenciales y carga sesión
    // --------------------------------------------------------
    public static function login(string $email, string $password): array|false
    {
        $db  = Database::getInstance();
        $sql = "SELECT u.id, u.nombre, u.email, u.password_hash,
                       u.rol_id, r.nombre_rol, u.estado
                FROM   usuarios u
                JOIN   roles    r ON r.id = u.rol_id
                WHERE  u.email = :email
                LIMIT  1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || $user['estado'] !== 'activo') {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Regenerar ID de sesión para prevenir fijación
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['nombre']     = $user['nombre'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['rol_id']     = (int) $user['rol_id'];
        $_SESSION['nombre_rol'] = $user['nombre_rol'];
        $_SESSION['login_at']   = time();

        // Actualizar último acceso
        $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id")
           ->execute([':id' => $user['id']]);

        return $user;
    }

    // --------------------------------------------------------
    // Cerrar sesión
    // --------------------------------------------------------
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // --------------------------------------------------------
    // ¿Hay un usuario autenticado?
    // --------------------------------------------------------
    public static function check(): bool
    {
        self::startSession();
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        // Verificar expiración manual
        if ((time() - ($_SESSION['login_at'] ?? 0)) > SESSION_LIFETIME) {
            self::logout();
        }
        return true;
    }

    // --------------------------------------------------------
    // Verificar que el usuario tiene uno de los roles requeridos.
    // Uso: Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR]);
    // --------------------------------------------------------
    public static function checkRole(array $allowedRoles, bool $redirect = true): bool
    {
        if (!self::check()) {
            if ($redirect) {
                header('Location: login.php?msg=session_expired');
                exit;
            }
            return false;
        }

        $rolActual = (int) ($_SESSION['rol_id'] ?? 0);
        if (!in_array($rolActual, $allowedRoles, true)) {
            if ($redirect) {
                header('Location: dashboard.php?error=forbidden');
                exit;
            }
            return false;
        }
        return true;
    }

    // --------------------------------------------------------
    // Devolver datos del usuario en sesión
    // --------------------------------------------------------
    public static function user(): array
    {
        return [
            'id'        => (int) ($_SESSION['user_id']    ?? 0),
            'nombre'    => $_SESSION['nombre']     ?? '',
            'email'     => $_SESSION['email']      ?? '',
            'rol_id'    => (int) ($_SESSION['rol_id']     ?? 0),
            'nombre_rol'=> $_SESSION['nombre_rol'] ?? '',
        ];
    }

    // --------------------------------------------------------
    // Helper: ¿el usuario actual es Admin?
    // --------------------------------------------------------
    public static function isAdmin(): bool
    {
        return (int) ($_SESSION['rol_id'] ?? 0) === ROL_ADMIN;
    }

    public static function isVendedor(): bool
    {
        return (int) ($_SESSION['rol_id'] ?? 0) === ROL_VENDEDOR;
    }

    public static function isOperador(): bool
    {
        return (int) ($_SESSION['rol_id'] ?? 0) === ROL_OPERADOR;
    }

    public static function isCliente(): bool
    {
        return (int) ($_SESSION['rol_id'] ?? 0) === ROL_CLIENTE;
    }

    // --------------------------------------------------------
    // Protección CSRF
    // --------------------------------------------------------
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
