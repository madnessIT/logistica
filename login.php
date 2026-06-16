<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();

// Redirigir si ya hay sesión
if (Auth::check()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $user     = Auth::login($email, $password);
    if ($user) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Credenciales inválidas o cuenta inactiva.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f0f2f5;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif}
  .login-card{width:100%;max-width:400px;border-radius:12px;border:1px solid #e0e0e0;background:#fff;overflow:hidden}
  .login-header{background:#003087;padding:2rem;text-align:center;color:#fff}
  .login-header .brand{font-size:2rem;font-weight:700;letter-spacing:-1px}
  .login-header .brand span{color:#f97316}
  .login-header p{font-size:12px;opacity:.75;margin:4px 0 0}
  .login-body{padding:2rem}
  .btn-primary{background:#003087;border-color:#003087}
  .btn-primary:hover{background:#002060;border-color:#002060}
  .form-control:focus{border-color:#003087;box-shadow:0 0 0 .2rem rgba(0,48,135,.15)}
  .roles-hint{background:#f8f9ff;border:1px solid #e0e7ff;border-radius:8px;padding:.75rem;font-size:12px;margin-top:1rem}
  .roles-hint h6{font-size:11px;font-weight:600;color:#6366f1;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.5px}
  .roles-hint table{width:100%;border-collapse:collapse}
  .roles-hint td{padding:2px 6px;font-size:11px;color:#555}
  .roles-hint td:first-child{font-weight:600;color:#333}
</style>
</head>
<body>
<div class="login-card shadow-sm">
  <div class="login-header">
    <div class="brand">Gemzbo<span>srl.</span></div>
    <p>Grupo Logístico · Sistema de Cotizaciones</p>
  </div>
  <div class="login-body">
    <h5 class="mb-1 fw-semibold">Iniciar sesión</h5>
    <p class="text-muted small mb-3">Ingresa con las credenciales asignadas por el administrador.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="mb-3">
        <label class="form-label small fw-semibold">Correo electrónico</label>
        <input type="email" name="email" class="form-control" required autofocus
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="form-label small fw-semibold">Contraseña</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-primary w-100 fw-semibold" type="submit">Ingresar al sistema</button>
    </form>

    <div class="roles-hint mt-3">
      <h6>Cuentas de demostración (contraseña: Gemz2025!)</h6>
      <table>
        <tr><td>Administrador</td><td>admin@gemz.com.bo</td></tr>
        <tr><td>Vendedor</td><td>deyanira@gemz.com.bo</td></tr>
        <tr><td>Operador</td><td>operador@gemz.com.bo</td></tr>
        <tr><td>Cliente</td><td>sergio@cliente.com</td></tr>
      </table>
    </div>
  </div>
</div>
</body>
</html>
