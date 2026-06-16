<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();
Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR, ROL_OPERADOR, ROL_CLIENTE]);

$user        = Auth::user();
$controller  = new CotizacionController();
$cotizaciones= $controller->listar();
$db          = Database::getInstance();

// Estadísticas para Admin
$stats = [];
if (Auth::isAdmin()) {
    $stats['total_cot']     = (int)$db->query("SELECT COUNT(*) FROM cotizaciones")->fetchColumn();
    $stats['total_usd']     = (float)$db->query("SELECT IFNULL(SUM(total_usd),0) FROM cotizaciones WHERE estado NOT IN ('Cancelada')")->fetchColumn();
    $stats['cot_mes']       = (int)$db->query("SELECT COUNT(*) FROM cotizaciones WHERE MONTH(fecha_emision)=MONTH(NOW()) AND YEAR(fecha_emision)=YEAR(NOW())")->fetchColumn();
    $stats['pendientes']    = (int)$db->query("SELECT COUNT(*) FROM cotizaciones WHERE estado IN ('Aprobada','En Tránsito','En Puerto')")->fetchColumn();
    $stats['total_clientes']= (int)$db->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
    $stats['total_usuarios']= (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE estado='activo'")->fetchColumn();
}

$coloresEstado = [
    'Borrador'       => 'secondary',
    'Enviada'        => 'primary',
    'Aprobada'       => 'success',
    'En Tránsito'    => 'info',
    'En Puerto'      => 'warning',
    'Desconsolidado' => 'warning',
    'Finalizada'     => 'dark',
    'Cancelada'      => 'danger',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f0f2f5;font-family:'Segoe UI',sans-serif}
  .sidebar{background:#003087;min-height:100vh;width:220px;position:fixed;top:0;left:0;padding:0;z-index:100}
  .sidebar .brand{padding:1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.1)}
  .sidebar .brand-name{font-size:1.4rem;font-weight:700;color:#fff;letter-spacing:-.5px}
  .sidebar .brand-name span{color:#f97316}
  .sidebar .brand-sub{font-size:10px;color:rgba(255,255,255,.6);letter-spacing:1px;text-transform:uppercase}
  .sidebar nav{padding:1rem 0}
  .sidebar .nav-label{font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:1px;padding:.5rem 1rem .25rem;font-weight:600}
  .sidebar a.nav-link{color:rgba(255,255,255,.8);padding:.5rem 1rem;font-size:13.5px;display:flex;align-items:center;gap:.5rem;border-radius:0;transition:.15s}
  .sidebar a.nav-link:hover,.sidebar a.nav-link.active{background:rgba(255,255,255,.12);color:#fff}
  .sidebar .user-box{position:absolute;bottom:0;left:0;right:0;padding:.75rem 1rem;border-top:1px solid rgba(255,255,255,.1);font-size:12px}
  .sidebar .user-box .name{color:#fff;font-weight:600}
  .sidebar .user-box .role{color:rgba(255,255,255,.55);font-size:11px}
  .main{margin-left:220px;padding:1.5rem}
  .topbar{background:#fff;border-radius:10px;padding:.75rem 1.25rem;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;border:1px solid #e8e8e8}
  .topbar h4{margin:0;font-size:16px;font-weight:600}
  .stat-card{background:#fff;border-radius:10px;padding:1.25rem;border:1px solid #e8e8e8}
  .stat-card .label{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
  .stat-card .value{font-size:28px;font-weight:700;color:#003087;margin:.25rem 0 0}
  .stat-card .sub{font-size:11px;color:#aaa;margin:.1rem 0 0}
  .table-card{background:#fff;border-radius:10px;border:1px solid #e8e8e8;overflow:hidden}
  .table-card .card-header-custom{padding:.875rem 1.25rem;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
  .table-card .card-header-custom h5{margin:0;font-size:14px;font-weight:600}
  .table th{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#888;font-weight:600;border-top:0}
  .table td{font-size:13px;vertical-align:middle}
  .badge-estado{font-size:10px;padding:3px 8px;border-radius:20px;font-weight:600;letter-spacing:.3px}
  .btn-sm-cust{font-size:12px;padding:3px 10px;border-radius:6px}
  .alert-operador{background:#fffbeb;border:1px solid #fbbf24;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem}
  .portal-cliente{background:linear-gradient(135deg,#003087 0%,#0056d2 100%);border-radius:12px;color:#fff;padding:1.75rem;margin-bottom:1.25rem}
  .portal-cliente h3{font-size:1.4rem;font-weight:700;margin-bottom:.25rem}
  .portal-cliente p{font-size:13px;opacity:.85;margin:0}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
  <div class="brand">
    <div class="brand-name">Gemzbo<span>srl.</span></div>
    <div class="brand-sub">Logística Internacional</div>
  </div>
  <nav>
    <?php if (Auth::isAdmin()): ?>
      <div class="nav-label">Administración</div>
      <a href="dashboard.php"  class="nav-link active">📊 Dashboard</a>
      <a href="usuarios.php"   class="nav-link">👥 Usuarios</a>
      <a href="tarifario.php"  class="nav-link">💲 Tarifario Base</a>
      <a href="clientes.php"   class="nav-link">🏢 Clientes</a>
      <a href="cotizaciones.php" class="nav-link">📋 Cotizaciones</a>
    <?php elseif (Auth::isVendedor()): ?>
      <div class="nav-label">Comercial</div>
      <a href="dashboard.php"    class="nav-link active">🏠 Inicio</a>
      <a href="formulario.php"   class="nav-link">➕ Nueva Cotización</a>
      <a href="clientes.php"     class="nav-link">🏢 Mis Clientes</a>
      <a href="cotizaciones.php" class="nav-link">📋 Mis Cotizaciones</a>
    <?php elseif (Auth::isOperador()): ?>
      <div class="nav-label">Operaciones</div>
      <a href="dashboard.php"    class="nav-link active">🏠 Panel Operativo</a>
      <a href="cotizaciones.php" class="nav-link">📋 Embarques</a>
    <?php elseif (Auth::isCliente()): ?>
      <div class="nav-label">Mi Portal</div>
      <a href="dashboard.php"    class="nav-link active">🏠 Mis Cotizaciones</a>
    <?php endif; ?>
  </nav>
  <div class="user-box">
    <div class="name"><?= htmlspecialchars($user['nombre']) ?></div>
    <div class="role"><?= htmlspecialchars($user['nombre_rol']) ?></div>
    <a href="logout.php" class="text-danger text-decoration-none" style="font-size:11px;display:block;margin-top:.4rem">Cerrar sesión</a>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="main">

  <!-- TOP BAR -->
  <div class="topbar">
    <h4>
      <?php if (Auth::isAdmin()): ?>📊 Panel Administrativo
      <?php elseif (Auth::isVendedor()): ?>🛒 Panel Comercial
      <?php elseif (Auth::isOperador()): ?>🚢 Panel Operativo
      <?php else: ?>📦 Portal del Cliente
      <?php endif; ?>
    </h4>
    <div style="font-size:12px;color:#888">
      <?= date('d/m/Y H:i') ?> &nbsp;|&nbsp;
      TC: <strong>USD 1 = Bs. <?= TC_USD_BS ?></strong>
    </div>
  </div>

  <!-- ======================= ADMIN ======================= -->
  <?php if (Auth::isAdmin()): ?>
  <div class="row g-3 mb-4">
    <div class="col"><div class="stat-card"><div class="label">Cotizaciones totales</div><div class="value"><?= $stats['total_cot'] ?></div><div class="sub">Todas las fechas</div></div></div>
    <div class="col"><div class="stat-card"><div class="label">Facturación estimada</div><div class="value">$<?= number_format($stats['total_usd'], 0) ?></div><div class="sub">USD acumulado</div></div></div>
    <div class="col"><div class="stat-card"><div class="label">Este mes</div><div class="value"><?= $stats['cot_mes'] ?></div><div class="sub">Cotizaciones emitidas</div></div></div>
    <div class="col"><div class="stat-card"><div class="label">En tránsito</div><div class="value"><?= $stats['pendientes'] ?></div><div class="sub">Embarques activos</div></div></div>
    <div class="col"><div class="stat-card"><div class="label">Clientes activos</div><div class="value"><?= $stats['total_clientes'] ?></div><div class="sub"><?= $stats['total_usuarios'] ?> usuarios en sistema</div></div></div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-auto"><a href="formulario.php" class="btn btn-primary btn-sm">➕ Nueva Cotización</a></div>
    <div class="col-auto"><a href="tarifario.php"  class="btn btn-outline-secondary btn-sm">💲 Gestionar Tarifas</a></div>
    <div class="col-auto"><a href="usuarios.php"   class="btn btn-outline-secondary btn-sm">👥 Gestionar Usuarios</a></div>
  </div>

  <!-- ======================= VENDEDOR ======================= -->
  <?php elseif (Auth::isVendedor()): ?>
  <div class="mb-3">
    <a href="formulario.php" class="btn btn-primary">➕ Nueva Cotización</a>
  </div>

  <!-- ======================= OPERADOR ======================= -->
  <?php elseif (Auth::isOperador()): ?>
  <div class="alert-operador">
    <strong>🚢 Panel Operativo</strong> — Se muestran únicamente cotizaciones Aprobadas o en proceso logístico.
    Actualiza los estados y añade notas de operación o penalizaciones.
  </div>

  <!-- ======================= CLIENTE ======================= -->
  <?php elseif (Auth::isCliente()): ?>
  <div class="portal-cliente">
    <h3>Bienvenido, <?= htmlspecialchars(explode(' ', $user['nombre'])[0]) ?></h3>
    <p>Aquí puedes consultar el estado de tus importaciones y descargar tus cotizaciones.</p>
  </div>
  <?php endif; ?>

  <!-- TABLA DE COTIZACIONES -->
  <div class="table-card">
    <div class="card-header-custom">
      <h5>
        <?php if (Auth::isOperador()): ?>Embarques en Proceso
        <?php elseif (Auth::isCliente()): ?>Mis Cotizaciones
        <?php else: ?>Cotizaciones Recientes
        <?php endif; ?>
      </h5>
      <?php if (!Auth::isCliente() && !Auth::isOperador()): ?>
        <a href="cotizaciones.php" class="btn btn-outline-secondary btn-sm">Ver todas →</a>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>N° Cotización</th>
            <th>Cliente</th>
            <th>Origen → Destino</th>
            <th>W/M</th>
            <th>Total USD</th>
            <th>Total Bs.</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($cotizaciones)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No hay cotizaciones disponibles</td></tr>
          <?php else: ?>
          <?php foreach ($cotizaciones as $cot): ?>
          <tr>
            <td><strong><?= htmlspecialchars($cot['numero_cotizacion']) ?></strong></td>
            <td><?= htmlspecialchars($cot['cliente_nombre']) ?></td>
            <td style="font-size:12px"><?= htmlspecialchars($cot['origen']) ?> → <?= htmlspecialchars($cot['destino']) ?></td>
            <td><?= number_format((float)$cot['wm_aplicado'], 2) ?></td>
            <td><strong>$<?= number_format((float)$cot['total_usd'], 2) ?></strong></td>
            <td>Bs. <?= number_format((float)$cot['total_bs'], 2) ?></td>
            <td>
              <span class="badge bg-<?= $coloresEstado[$cot['estado']] ?? 'secondary' ?> badge-estado">
                <?= htmlspecialchars($cot['estado']) ?>
              </span>
            </td>
            <td style="font-size:12px;color:#888"><?= date('d/m/Y', strtotime($cot['fecha_emision'])) ?></td>
            <td>
              <a href="ver_cotizacion.php?id=<?= $cot['id'] ?>" class="btn btn-outline-primary btn-sm btn-sm-cust">Ver</a>
              <?php if (!Auth::isCliente()): ?>
              <a href="pdf_cotizacion.php?id=<?= $cot['id'] ?>" class="btn btn-outline-secondary btn-sm btn-sm-cust" target="_blank">PDF</a>
              <?php endif; ?>
              <?php if (Auth::isOperador()): ?>
              <button class="btn btn-outline-warning btn-sm btn-sm-cust"
                onclick="actualizarEstado(<?= $cot['id'] ?>,'<?= htmlspecialchars($cot['estado']) ?>')">Estado</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal actualizar estado (Operador) -->
<div class="modal fade" id="modalEstado" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Actualizar estado logístico</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="est_cot_id">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Nuevo estado</label>
          <select id="est_nuevo" class="form-select">
            <option>Aprobada</option>
            <option>En Tránsito</option>
            <option>En Puerto</option>
            <option>Desconsolidado</option>
            <option>Finalizada</option>
            <option>Cancelada</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Nota de operación / penalización</label>
          <textarea id="est_nota" class="form-control" rows="3" placeholder="Ej: Arribo al puerto de Iquique. Contenedor TCKU3456789."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" onclick="guardarEstado()">Guardar cambio</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function actualizarEstado(id, estadoActual){
  document.getElementById('est_cot_id').value = id;
  document.getElementById('est_nuevo').value   = estadoActual;
  new bootstrap.Modal(document.getElementById('modalEstado')).show();
}
async function guardarEstado(){
  const id    = document.getElementById('est_cot_id').value;
  const est   = document.getElementById('est_nuevo').value;
  const nota  = document.getElementById('est_nota').value;
  const res   = await fetch('api_estado.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({cotizacion_id:id, estado:est, nota, csrf_token:'<?= Auth::csrfToken() ?>'})
  });
  const data = await res.json();
  if(data.ok){ location.reload(); } else { alert('Error: '+data.error); }
}
</script>
</body>
</html>
