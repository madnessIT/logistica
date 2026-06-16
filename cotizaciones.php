<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();
Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR, ROL_OPERADOR, ROL_CLIENTE]);

$user       = Auth::user();
$controller = new CotizacionController();
$db         = Database::getInstance();

// Normalizar filtros de entrada
$filtros = [
    'estado'     => trim($_GET['estado'] ?? ''),
    'cliente_id' => trim($_GET['cliente_id'] ?? ''),
];

// Obtener cotizaciones filtradas
$cotizaciones = $controller->listar($filtros);

// Cargar clientes para el filtro según rol
if (Auth::isAdmin() || Auth::isOperador()) {
    $clientesFiltro = $db->query(
        "SELECT id, nombre_razon FROM clientes ORDER BY nombre_razon"
    )->fetchAll();
} elseif (Auth::isVendedor()) {
    $stmt = $db->prepare(
        "SELECT id, nombre_razon FROM clientes WHERE vendedor_id = :vid ORDER BY nombre_razon"
    );
    $stmt->execute([':vid' => $user['id']]);
    $clientesFiltro = $stmt->fetchAll();
} else {
    $clientesFiltro = []; // Cliente no filtra por otros clientes
}

$estadosValidos = ['Borrador','Enviada','Aprobada','En Tránsito','En Puerto','Desconsolidado','Finalizada','Cancelada'];

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
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cotizaciones — <?= APP_NAME ?></title>
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
.filter-card{background:#fff;border-radius:10px;border:1px solid #e8e8e8;padding:1.25rem;margin-bottom:1.25rem}
.table-card{background:#fff;border-radius:10px;border:1px solid #e8e8e8;overflow:hidden}
.table th{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#888;font-weight:600;border-top:0}
.table td{font-size:13px;vertical-align:middle}
.badge-estado{font-size:10px;padding:3px 8px;border-radius:20px;font-weight:600;letter-spacing:.3px}
.btn-sm-cust{font-size:12px;padding:3px 10px;border-radius:6px}
.btn-primary{background:#003087;border-color:#003087}
.btn-primary:hover{background:#002060}
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
    <?php if(!Auth::isCliente()): ?>
    <a href="clientes.php"     class="nav-link">🏢 Clientes</a>
    <?php endif; ?>
    <a href="cotizaciones.php" class="nav-link active">📋 Cotizaciones</a>
    <?php if(Auth::isAdmin() || Auth::isVendedor()): ?>
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
    <h4>📋 <?= Auth::isOperador() ? 'Embarques en Seguimiento' : 'Listado de Cotizaciones' ?></h4>
    <div style="font-size:12px;color:#888">
      TC: <strong>USD 1 = Bs. <?= TC_USD_BS ?></strong>
      <?php if (Auth::isAdmin() || Auth::isOperador()): ?>
      <a href="#" onclick="mostrarEditarTC(); return false;" class="text-decoration-none ms-1" title="Editar tipo de cambio">✏️</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- FILTROS -->
  <div class="filter-card shadow-sm">
    <form method="GET" class="row g-3 align-items-end">
      <?php if (!empty($clientesFiltro)): ?>
      <div class="col-md-4">
        <label class="form-label small fw-semibold text-muted mb-1 text-uppercase" style="font-size:11px">Cliente</label>
        <select name="cliente_id" class="form-select form-select-sm">
          <option value="">— Todos los clientes —</option>
          <?php foreach ($clientesFiltro as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filtros['cliente_id'] === (string)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre_razon']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1 text-uppercase" style="font-size:11px">Estado</label>
        <select name="estado" class="form-select form-select-sm">
          <option value="">— Todos los estados —</option>
          <?php foreach ($estadosValidos as $est): ?>
          <option value="<?= $est ?>" <?= $filtros['estado'] === $est ? 'selected' : '' ?>><?= $est ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm px-4">Filtrar</button>
        <a href="cotizaciones.php" class="btn btn-outline-secondary btn-sm px-3">Limpiar</a>
      </div>
    </form>
  </div>

  <!-- TABLA -->
  <div class="table-card">
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
          <tr><td colspan="9" class="text-center text-muted py-4">No se encontraron cotizaciones</td></tr>
          <?php else: foreach ($cotizaciones as $cot): ?>
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
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (Auth::isAdmin() || Auth::isOperador()): ?>
<script>
function mostrarEditarTC() {
  new bootstrap.Modal(document.getElementById('modalTC')).show();
}
async function guardarTC() {
  const tc = parseFloat(document.getElementById('input_tc').value);
  if (!tc || tc <= 0) {
    alert('Por favor introduce un tipo de cambio válido.');
    return;
  }
  const res = await fetch('api_tc.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ tc: tc, csrf_token: '<?= Auth::csrfToken() ?>' })
  });
  const data = await res.json();
  if (data.ok) {
    location.reload();
  } else {
    alert('Error: ' + data.error);
  }
}
</script>

<!-- Modal TC -->
<div class="modal fade" id="modalTC" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Actualizar TC</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">TC (USD 1 = Bs. ?)</label>
          <input type="number" step="0.01" min="0.01" id="input_tc" class="form-control" value="<?= TC_USD_BS ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="guardarTC()">Guardar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
</body>
</html>
