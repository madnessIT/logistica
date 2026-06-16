<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();
Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR]);

$db   = Database::getInstance();
$user = Auth::user();
$csrf = Auth::csrfToken();
$msg  = '';

// ── Procesar POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        // Si el rol es Vendedor, se asigna a sí mismo; Admin puede elegir
        $vendedorId = Auth::isAdmin() ? (int)$_POST['vendedor_id'] : $user['id'];
        try {
            $db->prepare(
                "INSERT INTO clientes (vendedor_id, nombre_razon, nit_ci, email, telefono, ciudad)
                 VALUES (:vid, :nom, :nit, :email, :tel, :ciu)"
            )->execute([
                ':vid'   => $vendedorId,
                ':nom'   => trim($_POST['nombre_razon']),
                ':nit'   => trim($_POST['nit_ci']   ?? ''),
                ':email' => trim($_POST['email']     ?? ''),
                ':tel'   => trim($_POST['telefono']  ?? ''),
                ':ciu'   => trim($_POST['ciudad']    ?? ''),
            ]);
            $msg = 'success:Cliente registrado correctamente.';
        } catch (PDOException $e) {
            $msg = 'error:Error al registrar cliente: ' . $e->getMessage();
        }

    } elseif ($accion === 'editar') {
        $vendedorId = Auth::isAdmin() ? (int)$_POST['vendedor_id'] : $user['id'];
        $db->prepare(
            "UPDATE clientes SET nombre_razon=:nom, nit_ci=:nit, email=:email,
             telefono=:tel, ciudad=:ciu, vendedor_id=:vid WHERE id=:id"
        )->execute([
            ':nom'   => trim($_POST['nombre_razon']),
            ':nit'   => trim($_POST['nit_ci']   ?? ''),
            ':email' => trim($_POST['email']     ?? ''),
            ':tel'   => trim($_POST['telefono']  ?? ''),
            ':ciu'   => trim($_POST['ciudad']    ?? ''),
            ':vid'   => $vendedorId,
            ':id'    => (int)$_POST['id'],
        ]);
        $msg = 'success:Cliente actualizado.';

    } elseif ($accion === 'eliminar') {
        $cid = (int)$_POST['id'];
        // Verificar que no tenga cotizaciones
        $stmt = $db->prepare("SELECT COUNT(*) FROM cotizaciones WHERE cliente_id=:id");
        $stmt->execute([':id' => $cid]);
        $cnt = (int)$stmt->fetchColumn();
        if ($cnt > 0) {
            $msg = 'error:No se puede eliminar: el cliente tiene '.$cnt.' cotización(es) asociada(s).';
        } else {
            $db->prepare("DELETE FROM clientes WHERE id=:id")->execute([':id'=>$cid]);
            $msg = 'success:Cliente eliminado.';
        }
    }
}

// ── Cargar clientes según rol ──
if (Auth::isAdmin()) {
    $clientes = $db->query(
        "SELECT c.*, u.nombre AS vendedor_nombre,
                (SELECT COUNT(*) FROM cotizaciones ct WHERE ct.cliente_id=c.id) AS num_cot
         FROM clientes c JOIN usuarios u ON u.id=c.vendedor_id ORDER BY c.nombre_razon"
    )->fetchAll();
} else {
    $stmt = $db->prepare(
        "SELECT c.*, u.nombre AS vendedor_nombre,
                (SELECT COUNT(*) FROM cotizaciones ct WHERE ct.cliente_id=c.id) AS num_cot
         FROM clientes c JOIN usuarios u ON u.id=c.vendedor_id
         WHERE c.vendedor_id=:uid ORDER BY c.nombre_razon"
    );
    $stmt->execute([':uid' => $user['id']]);
    $clientes = $stmt->fetchAll();
}

// Vendedores para el select (solo Admin)
$vendedores = [];
if (Auth::isAdmin()) {
    $vendedores = $db->query(
        "SELECT id, nombre FROM usuarios WHERE rol_id IN (1,2) AND estado='activo' ORDER BY nombre"
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Clientes — <?= APP_NAME ?></title>
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
.table-card{background:#fff;border-radius:10px;border:1px solid #e8e8e8;overflow:hidden}
.table th{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#888;font-weight:600;border-top:0}
.table td{font-size:13px;vertical-align:middle}
.btn-primary{background:#003087;border-color:#003087}
.btn-primary:hover{background:#002060}
.search-box{position:relative}
.search-box input{padding-left:2rem;border-radius:8px;font-size:13px}
.search-box::before{content:'🔍';position:absolute;left:.6rem;top:50%;transform:translateY(-50%);font-size:13px;z-index:1}
</style>
</head>
<body>
<div class="sidebar">
  <div class="brand"><div class="brand-name">Gemzbo<span>srl.</span></div></div>
  <nav class="pt-2">
    <a href="dashboard.php"    class="nav-link">📊 Dashboard</a>
    <?php if(Auth::isAdmin()): ?>
    <a href="usuarios.php"     class="nav-link">👥 Usuarios</a>
    <a href="tarifario.php"    class="nav-link">💲 Tarifario</a>
    <?php endif; ?>
    <a href="clientes.php"     class="nav-link active">🏢 Clientes</a>
    <a href="cotizaciones.php" class="nav-link">📋 Cotizaciones</a>
    <?php if(!Auth::isAdmin()): ?>
    <a href="formulario.php"   class="nav-link">➕ Nueva Cotización</a>
    <?php endif; ?>
  </nav>
  <div class="user-box">
    <div class="uname"><?= htmlspecialchars($user['nombre']) ?></div>
    <div class="urole"><?= htmlspecialchars($user['nombre_rol']) ?></div>
    <a href="logout.php" class="text-danger text-decoration-none" style="font-size:11px;display:block;margin-top:.4rem">Cerrar sesión</a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <h4>🏢 <?= Auth::isAdmin() ? 'Todos los Clientes' : 'Mis Clientes' ?></h4>
    <div class="d-flex gap-2 align-items-center">
      <div class="search-box">
        <input type="text" id="buscador" class="form-control form-control-sm" placeholder="Buscar cliente…" oninput="filtrarTabla()" style="width:200px">
      </div>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrear">+ Nuevo cliente</button>
    </div>
  </div>

  <?php if ($msg): [$tipo,$texto] = explode(':', $msg, 2); ?>
  <div class="alert alert-<?= $tipo==='success'?'success':'danger' ?> alert-dismissible fade show py-2 mb-3">
    <?= htmlspecialchars($texto) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <div class="table-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="tabla-clientes">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Razón Social</th><th>NIT / CI</th><th>Email</th>
            <th>Teléfono</th><th>Ciudad</th><th>Vendedor</th><th>Cotizaciones</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($clientes)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No hay clientes registrados</td></tr>
        <?php else: foreach ($clientes as $c): ?>
        <tr>
          <td class="text-muted"><?= $c['id'] ?></td>
          <td><strong><?= htmlspecialchars($c['nombre_razon']) ?></strong></td>
          <td><?= htmlspecialchars($c['nit_ci'] ?? '—') ?></td>
          <td><?= htmlspecialchars($c['email']  ?? '—') ?></td>
          <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
          <td><?= htmlspecialchars($c['ciudad'] ?? '—') ?></td>
          <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['vendedor_nombre']) ?></span></td>
          <td class="text-center">
            <?php if ($c['num_cot'] > 0): ?>
              <a href="cotizaciones.php?cliente_id=<?= $c['id'] ?>" class="badge bg-primary text-decoration-none"><?= $c['num_cot'] ?></a>
            <?php else: ?>
              <span class="text-muted">0</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-outline-primary btn-sm" style="font-size:12px"
              onclick="editarCliente(<?= htmlspecialchars(json_encode($c)) ?>)">Editar</button>
            <a href="formulario.php?cliente_id=<?= $c['id'] ?>" class="btn btn-outline-success btn-sm" style="font-size:12px">+ Cotizar</a>
            <?php if (Auth::isAdmin() && $c['num_cot'] == 0): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar cliente?')">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-outline-danger btn-sm" style="font-size:12px">Eliminar</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Crear -->
<div class="modal fade" id="modalCrear" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="accion" value="crear">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Nuevo Cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold">Razón Social / Nombre completo *</label>
              <input type="text" name="nombre_razon" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">NIT / CI</label>
              <input type="text" name="nit_ci" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email</label>
              <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Teléfono</label>
              <input type="text" name="telefono" class="form-control" placeholder="+591 7XXXXXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Ciudad</label>
              <select name="ciudad" class="form-select">
                <option>La Paz</option><option>Cochabamba</option><option>Santa Cruz</option>
                <option>Oruro</option><option>Potosí</option><option>Sucre</option><option>Tarija</option>
              </select>
            </div>
            <?php if (Auth::isAdmin() && !empty($vendedores)): ?>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Vendedor asignado *</label>
              <select name="vendedor_id" class="form-select" required>
                <?php foreach ($vendedores as $v): ?>
                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar cliente</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Editar Cliente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold">Razón Social / Nombre *</label>
              <input type="text" name="nombre_razon" id="edit_nombre" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">NIT / CI</label>
              <input type="text" name="nit_ci" id="edit_nit" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email</label>
              <input type="email" name="email" id="edit_email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Teléfono</label>
              <input type="text" name="telefono" id="edit_tel" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Ciudad</label>
              <select name="ciudad" id="edit_ciudad" class="form-select">
                <option>La Paz</option><option>Cochabamba</option><option>Santa Cruz</option>
                <option>Oruro</option><option>Potosí</option><option>Sucre</option><option>Tarija</option>
              </select>
            </div>
            <?php if (Auth::isAdmin() && !empty($vendedores)): ?>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Vendedor asignado</label>
              <select name="vendedor_id" id="edit_vendedor" class="form-select">
                <?php foreach ($vendedores as $v): ?>
                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
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
function editarCliente(c) {
  document.getElementById('edit_id').value     = c.id;
  document.getElementById('edit_nombre').value = c.nombre_razon;
  document.getElementById('edit_nit').value    = c.nit_ci    || '';
  document.getElementById('edit_email').value  = c.email     || '';
  document.getElementById('edit_tel').value    = c.telefono  || '';
  const ciudadSel = document.getElementById('edit_ciudad');
  if (ciudadSel) {
    for (let opt of ciudadSel.options) {
      if (opt.value === c.ciudad) { opt.selected = true; break; }
    }
  }
  const vendSel = document.getElementById('edit_vendedor');
  if (vendSel) vendSel.value = c.vendedor_id;
  new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function filtrarTabla() {
  const q   = document.getElementById('buscador').value.toLowerCase();
  const rows= document.querySelectorAll('#tabla-clientes tbody tr');
  rows.forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
