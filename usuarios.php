<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();
Auth::checkRole([ROL_ADMIN]);

$db   = Database::getInstance();
$user = Auth::user();
$csrf = Auth::csrfToken();
$msg  = '';

// ── Procesar acciones POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $db->prepare(
                "INSERT INTO usuarios (rol_id, nombre, email, password_hash, estado)
                 VALUES (:rol, :nom, :email, :hash, :est)"
            )->execute([
                ':rol'   => (int)   $_POST['rol_id'],
                ':nom'   => trim(   $_POST['nombre']),
                ':email' => strtolower(trim($_POST['email'])),
                ':hash'  => $hash,
                ':est'   => $_POST['estado'] ?? 'activo',
            ]);
            $msg = 'success:Usuario creado correctamente.';
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate')
                 ? 'error:El email ya está registrado.'
                 : 'error:Error al crear usuario: ' . $e->getMessage();
        }

    } elseif ($accion === 'editar') {
        $params = [
            ':rol'   => (int)   $_POST['rol_id'],
            ':nom'   => trim(   $_POST['nombre']),
            ':email' => strtolower(trim($_POST['email'])),
            ':est'   => $_POST['estado'],
            ':id'    => (int)   $_POST['id'],
        ];
        $sql = "UPDATE usuarios SET rol_id=:rol, nombre=:nom, email=:email, estado=:est WHERE id=:id";
        if (!empty($_POST['password'])) {
            $sql = "UPDATE usuarios SET rol_id=:rol, nombre=:nom, email=:email, estado=:est, password_hash=:hash WHERE id=:id";
            $params[':hash'] = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        try {
            $db->prepare($sql)->execute($params);
            $msg = 'success:Usuario actualizado correctamente.';
        } catch (PDOException $e) {
            $msg = 'error:Error al actualizar: ' . $e->getMessage();
        }

    } elseif ($accion === 'toggle') {
        $uid    = (int) $_POST['id'];
        $estado = $_POST['estado_actual'] === 'activo' ? 'inactivo' : 'activo';
        $db->prepare("UPDATE usuarios SET estado=:est WHERE id=:id")
           ->execute([':est' => $estado, ':id' => $uid]);
        $msg = 'success:Estado cambiado a ' . $estado . '.';
    }
}

// ── Cargar datos ──
$usuarios = $db->query(
    "SELECT u.*, r.nombre_rol FROM usuarios u JOIN roles r ON r.id = u.rol_id ORDER BY u.id"
)->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();

$stats = [
    'total'    => count($usuarios),
    'activos'  => count(array_filter($usuarios, fn($u) => $u['estado'] === 'activo')),
    'admin'    => count(array_filter($usuarios, fn($u) => $u['rol_id'] == ROL_ADMIN)),
    'vendedor' => count(array_filter($usuarios, fn($u) => $u['rol_id'] == ROL_VENDEDOR)),
    'operador' => count(array_filter($usuarios, fn($u) => $u['rol_id'] == ROL_OPERADOR)),
    'cliente'  => count(array_filter($usuarios, fn($u) => $u['rol_id'] == ROL_CLIENTE)),
];

$colores = ['Administrador'=>'danger','Vendedor'=>'primary','Operador'=>'warning','Cliente'=>'success'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f0f2f5;font-family:'Segoe UI',sans-serif}
.sidebar{background:#003087;min-height:100vh;width:220px;position:fixed;top:0;left:0;z-index:100}
.sidebar .brand{padding:1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar .brand-name{font-size:1.4rem;font-weight:700;color:#fff;letter-spacing:-.5px}
.sidebar .brand-name span{color:#f97316}
.sidebar a.nav-link{color:rgba(255,255,255,.8);padding:.5rem 1rem;font-size:13px;display:block;text-decoration:none}
.sidebar a.nav-link:hover,.sidebar a.nav-link.active{background:rgba(255,255,255,.14);color:#fff}
.sidebar .user-box{position:absolute;bottom:0;left:0;right:0;padding:.75rem 1rem;border-top:1px solid rgba(255,255,255,.1)}
.sidebar .user-box .uname{color:#fff;font-weight:600;font-size:13px}
.sidebar .user-box .urole{color:rgba(255,255,255,.55);font-size:11px}
.main{margin-left:220px;padding:1.5rem}
.topbar{background:#fff;border-radius:10px;padding:.75rem 1.25rem;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;border:1px solid #e8e8e8}
.topbar h4{margin:0;font-size:16px;font-weight:600}
.stat-card{background:#fff;border-radius:10px;padding:1rem 1.25rem;border:1px solid #e8e8e8;text-align:center}
.stat-card .val{font-size:26px;font-weight:700;color:#003087}
.stat-card .lbl{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.4px}
.table-card{background:#fff;border-radius:10px;border:1px solid #e8e8e8;overflow:hidden}
.table th{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#888;font-weight:600;border-top:0}
.table td{font-size:13px;vertical-align:middle}
.btn-primary{background:#003087;border-color:#003087}
.btn-primary:hover{background:#002060}
.avatar{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff;flex-shrink:0}
</style>
</head>
<body>
<div class="sidebar">
  <div class="brand"><div class="brand-name">Gemzbo<span>srl.</span></div></div>
  <nav class="pt-2">
    <a href="dashboard.php"    class="nav-link">📊 Dashboard</a>
    <a href="usuarios.php"     class="nav-link active">👥 Usuarios</a>
    <a href="tarifario.php"    class="nav-link">💲 Tarifario</a>
    <a href="clientes.php"     class="nav-link">🏢 Clientes</a>
    <a href="cotizaciones.php" class="nav-link">📋 Cotizaciones</a>
  </nav>
  <div class="user-box">
    <div class="uname"><?= htmlspecialchars($user['nombre']) ?></div>
    <div class="urole"><?= htmlspecialchars($user['nombre_rol']) ?></div>
    <a href="logout.php" class="text-danger text-decoration-none" style="font-size:11px;display:block;margin-top:.4rem">Cerrar sesión</a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <h4>👥 Gestión de Usuarios</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrear">+ Nuevo usuario</button>
  </div>

  <?php if ($msg): [$tipo,$texto] = explode(':', $msg, 2); ?>
  <div class="alert alert-<?= $tipo==='success'?'success':'danger' ?> alert-dismissible fade show py-2 mb-3">
    <?= htmlspecialchars($texto) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col"><div class="stat-card"><div class="val"><?= $stats['total'] ?></div><div class="lbl">Total</div></div></div>
    <div class="col"><div class="stat-card"><div class="val text-success"><?= $stats['activos'] ?></div><div class="lbl">Activos</div></div></div>
    <div class="col"><div class="stat-card"><div class="val text-danger"><?= $stats['admin'] ?></div><div class="lbl">Admin</div></div></div>
    <div class="col"><div class="stat-card"><div class="val text-primary"><?= $stats['vendedor'] ?></div><div class="lbl">Vendedores</div></div></div>
    <div class="col"><div class="stat-card"><div class="val text-warning"><?= $stats['operador'] ?></div><div class="lbl">Operadores</div></div></div>
    <div class="col"><div class="stat-card"><div class="val text-success"><?= $stats['cliente'] ?></div><div class="lbl">Clientes</div></div></div>
  </div>

  <div class="table-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>#</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Último acceso</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u):
          $inicial = mb_strtoupper(mb_substr($u['nombre'],0,1));
          $colRol  = ['Administrador'=>'#dc3545','Vendedor'=>'#0d6efd','Operador'=>'#ffc107','Cliente'=>'#198754'];
          $bg      = $colRol[$u['nombre_rol']] ?? '#6c757d';
        ?>
        <tr>
          <td class="text-muted"><?= $u['id'] ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="avatar" style="background:<?= $bg ?>"><?= $inicial ?></div>
              <div>
                <div style="font-weight:600"><?= htmlspecialchars($u['nombre']) ?></div>
                <div style="font-size:11px;color:#aaa">ID #<?= $u['id'] ?></div>
              </div>
            </div>
          </td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge bg-<?= $colores[$u['nombre_rol']] ?? 'secondary' ?>"><?= $u['nombre_rol'] ?></span></td>
          <td style="font-size:12px;color:#888"><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
          <td>
            <span class="badge bg-<?= $u['estado']==='activo'?'success':'secondary' ?>">
              <?= $u['estado'] ?>
            </span>
          </td>
          <td>
            <button class="btn btn-outline-primary btn-sm" style="font-size:12px"
              onclick="editarUsuario(<?= htmlspecialchars(json_encode($u)) ?>)">Editar</button>
            <?php if ($u['id'] !== $user['id']): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="accion" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="estado_actual" value="<?= $u['estado'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-<?= $u['estado']==='activo'?'warning':'success' ?>" style="font-size:12px">
                <?= $u['estado']==='activo'?'Desactivar':'Activar' ?>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Crear -->
<div class="modal fade" id="modalCrear" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="accion" value="crear">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Nuevo Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold">Nombre completo *</label>
              <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Email *</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Contraseña *</label>
              <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Rol *</label>
              <select name="rol_id" class="form-select" required>
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre_rol']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Estado</label>
              <select name="estado" class="form-select">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Editar Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold">Nombre completo *</label>
              <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Email *</label>
              <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Nueva contraseña <span class="text-muted fw-normal">(dejar vacío para no cambiar)</span></label>
              <input type="password" name="password" class="form-control" minlength="8">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Rol *</label>
              <select name="rol_id" id="edit_rol" class="form-select" required>
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre_rol']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Estado</label>
              <select name="estado" id="edit_estado" class="form-select">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editarUsuario(u) {
  document.getElementById('edit_id').value     = u.id;
  document.getElementById('edit_nombre').value = u.nombre;
  document.getElementById('edit_email').value  = u.email;
  document.getElementById('edit_rol').value    = u.rol_id;
  document.getElementById('edit_estado').value = u.estado;
  new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
</body>
</html>
