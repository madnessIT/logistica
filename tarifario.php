<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();
Auth::checkRole([ROL_ADMIN]);

$ctrl = new TarifarioController();
$msg  = '';

// Procesar actualizacion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'actualizar') {
            $ok = $ctrl->actualizar((int)$_POST['id'], (float)$_POST['tarifa_usd'], $_POST['moneda_cobro']);
            $msg = $ok ? 'success:Tarifa actualizada correctamente.' : 'error:Error al actualizar.';
        } elseif ($_POST['accion'] === 'nueva') {
            $id = $ctrl->crear($_POST);
            $msg = $id > 0 ? 'success:Tarifa creada con ID #'.$id.'.' : 'error:Error al crear.';
        }
    }
}

$tarifas = $ctrl->listar();
$csrf    = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tarifario Base — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f0f2f5;font-family:'Segoe UI',sans-serif}
.sidebar{background:#003087;min-height:100vh;width:220px;position:fixed;top:0;left:0;z-index:100}
.sidebar .brand{padding:1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar .brand-name{font-size:1.4rem;font-weight:700;color:#fff;letter-spacing:-.5px}
.sidebar .brand-name span{color:#f97316}
.sidebar a.nav-link{color:rgba(255,255,255,.8);padding:.5rem 1rem;font-size:13px;display:block}
.sidebar a.nav-link:hover,.sidebar a.nav-link.active{background:rgba(255,255,255,.14);color:#fff}
.main{margin-left:220px;padding:1.5rem}
.topbar{background:#fff;border-radius:10px;padding:.75rem 1.25rem;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;border:1px solid #e8e8e8}
.card{border-radius:10px;border:1px solid #e8e8e8}
.badge-usd{background:#E6F1FB;color:#0C447C;font-size:10px;padding:2px 7px;border-radius:12px;font-weight:600}
.badge-bs{background:#E1F5EE;color:#085041;font-size:10px;padding:2px 7px;border-radius:12px;font-weight:600}
.table th{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#888;font-weight:600}
.table td{font-size:13px;vertical-align:middle}
.btn-primary{background:#003087;border-color:#003087}
.edit-form{display:none}
</style>
</head>
<body>
<div class="sidebar">
  <div class="brand"><div class="brand-name">Gemzbo<span>srl.</span></div></div>
  <nav class="pt-2">
    <a href="dashboard.php"   class="nav-link">📊 Dashboard</a>
    <a href="usuarios.php"    class="nav-link">👥 Usuarios</a>
    <a href="tarifario.php"   class="nav-link active">💲 Tarifario</a>
    <a href="clientes.php"    class="nav-link">🏢 Clientes</a>
    <a href="cotizaciones.php"class="nav-link">📋 Cotizaciones</a>
    <a href="logout.php"      class="nav-link mt-4" style="color:#f87171">Cerrar sesión</a>
  </nav>
</div>

<div class="main">
  <div class="topbar">
    <h4 style="margin:0;font-size:16px;font-weight:600">💲 Gestión del Tarifario Base</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNueva">+ Nueva tarifa</button>
  </div>

  <?php if ($msg): ?>
    <?php [$tipo,$texto] = explode(':', $msg, 2) ?>
    <div class="alert alert-<?= $tipo==='success'?'success':'danger' ?> alert-dismissible fade show py-2">
      <?= htmlspecialchars($texto) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th><th>Concepto</th><th>Unidad</th><th>Origen</th><th>Destino</th>
              <th>Tarifa USD</th><th>Moneda</th><th>Actualizado por</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tarifas as $t): ?>
            <tr>
              <td class="text-muted"><?= $t['id'] ?></td>
              <td><strong><?= htmlspecialchars($t['concepto_servicio']) ?></strong></td>
              <td style="font-size:12px;color:#888"><?= htmlspecialchars($t['unidad_medida']) ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($t['origen']) ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($t['destino']) ?></td>
              <td><strong>$<?= number_format((float)$t['tarifa_usd'],2) ?></strong></td>
              <td><span class="badge-<?= strtolower($t['moneda_cobro']) ?>"><?= $t['moneda_cobro'] ?></span></td>
              <td style="font-size:12px;color:#888"><?= htmlspecialchars($t['actualizado_por_nombre'] ?? '—') ?></td>
              <td>
                <button class="btn btn-outline-primary btn-sm" style="font-size:12px"
                  onclick="editarTarifa(<?= $t['id'] ?>,'<?= htmlspecialchars($t['concepto_servicio'],ENT_QUOTES) ?>',<?= $t['tarifa_usd'] ?>,'<?= $t['moneda_cobro'] ?>')">
                  Editar
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="accion" value="actualizar">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Editar tarifa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <p class="text-muted mb-3" id="edit_nombre" style="font-size:13px"></p>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Tarifa (USD)</label>
            <input type="number" step="0.01" name="tarifa_usd" id="edit_tarifa" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Moneda de cobro</label>
            <select name="moneda_cobro" id="edit_moneda" class="form-select">
              <option value="USD">USD — Dólares</option>
              <option value="BS">BS — Bolivianos</option>
            </select>
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

<!-- Modal nueva -->
<div class="modal fade" id="modalNueva" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="accion" value="nueva">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Nueva tarifa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label small fw-semibold">Concepto</label><input type="text" name="concepto_servicio" class="form-control" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">Unidad</label><input type="text" name="unidad_medida" class="form-control" placeholder="por W/M / fijo" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">Tarifa USD</label><input type="number" step="0.01" name="tarifa_usd" class="form-control" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">Origen</label><input type="text" name="origen" class="form-control" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">Destino</label><input type="text" name="destino" class="form-control" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">Moneda de cobro</label>
              <select name="moneda_cobro" class="form-select"><option value="USD">USD</option><option value="BS">BS</option></select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear tarifa</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editarTarifa(id, nombre, tarifa, moneda){
  document.getElementById('edit_id').value     = id;
  document.getElementById('edit_nombre').textContent = nombre;
  document.getElementById('edit_tarifa').value = tarifa;
  document.getElementById('edit_moneda').value = moneda;
  new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
</body>
</html>
