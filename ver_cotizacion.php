<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();
Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR, ROL_OPERADOR, ROL_CLIENTE]);

$user = Auth::user();
$id   = (int) ($_GET['id'] ?? 0);

if (!$id) {
    header('Location: dashboard.php');
    exit;
}

$controller = new CotizacionController();
$cot        = $controller->obtener($id);

if (!$cot) {
    http_response_code(404);
    die('Cotización no encontrada o acceso denegado.');
}

$detallesUSD  = array_filter($cot['detalles'], fn($d) => $d['moneda'] === 'USD' && !$d['es_recargo']);
$detallesBS   = array_filter($cot['detalles'], fn($d) => $d['moneda'] === 'BS');
$recargos     = array_filter($cot['detalles'], fn($d) => $d['es_recargo']);
$totalUSD     = array_sum(array_column(array_filter($cot['detalles'], fn($d) => $d['moneda'] === 'USD'), 'costo_calculado'));
$totalBS      = array_sum(array_column(array_filter($cot['detalles'], fn($d) => $d['moneda'] === 'BS'), 'costo_calculado'));
$totalEquiv   = $totalUSD + ($totalBS / TC_USD_BS);

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

$csrf = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cotización <?= htmlspecialchars($cot['numero_cotizacion']) ?> — <?= APP_NAME ?></title>
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
.card{border-radius:10px;border:1px solid #e8e8e8;margin-bottom:1.25rem}
.card-header{background:#fff;border-bottom:1px solid #eee;font-weight:600;font-size:14px;padding:.875rem 1.25rem}
.meta-table{width:100%;font-size:13px}
.meta-table td{padding:6px 8px;border-bottom:1px solid #f8f9fa}
.meta-table td.label-cell{font-weight:600;color:#555;width:35%}
.cot-table{width:100%;border-collapse:collapse;font-size:13px}
.cot-table th{background:#f5f7fa;padding:8px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#888;font-weight:600;border-bottom:1px solid #eee}
.cot-table td{padding:8px 10px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.cot-table tr.total-row td{font-weight:700;background:#f5f7fa;font-size:14px}
.total-banner{background:#003087;color:#fff;padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-radius:8px}
.total-banner .lbl{font-size:12px;opacity:.75}
.total-banner .amt{font-size:22px;font-weight:700}
.log-item{position:relative;padding-left:1.5rem;padding-bottom:1rem;font-size:12.5px}
.log-item::before{content:'';position:absolute;left:4px;top:4px;width:8px;height:8px;border-radius:50%;background:#003087;z-index:2}
.log-item::after{content:'';position:absolute;left:7px;top:4px;bottom:0;width:2px;background:#e0e0e0;z-index:1}
.log-item:last-child::after{display:none}
.badge-moneda{font-size:10px;padding:2px 7px;border-radius:12px;font-weight:600}
.badge-usd{background:#E6F1FB;color:#0C447C}
.badge-bs{background:#E1F5EE;color:#085041}
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
    <h4>📋 Detalle de Cotización: <?= htmlspecialchars($cot['numero_cotizacion']) ?></h4>
    <div class="d-flex gap-2">
      <?php if (!Auth::isCliente()): ?>
      <a href="pdf_cotizacion.php?id=<?= $cot['id'] ?>" class="btn btn-outline-secondary btn-sm" target="_blank">🖨 Versión PDF</a>
      <?php endif; ?>
      <a href="cotizaciones.php" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
  </div>

  <div class="row">
    <!-- COLUMNA IZQUIERDA: DETALLES GENERALES Y CARGA -->
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-light">📋 Información General</div>
        <div class="card-body p-0">
          <table class="meta-table">
            <tr><td class="label-cell">N° Cotización</td><td><strong><?= htmlspecialchars($cot['numero_cotizacion']) ?></strong></td></tr>
            <tr><td class="label-cell">Estado Actual</td><td>
              <span class="badge bg-<?= $coloresEstado[$cot['estado']] ?? 'secondary' ?> badge-estado">
                <?= htmlspecialchars($cot['estado']) ?>
              </span>
            </td></tr>
            <tr><td class="label-cell">Cliente</td><td><?= htmlspecialchars($cot['nombre_razon']) ?></td></tr>
            <tr><td class="label-cell">NIT / CI</td><td><?= htmlspecialchars($cot['nit_ci'] ?? '—') ?></td></tr>
            <tr><td class="label-cell">Email Cliente</td><td><?= htmlspecialchars($cot['cliente_email'] ?? '—') ?></td></tr>
            <tr><td class="label-cell">Vendedor</td><td><?= htmlspecialchars($cot['vendedor_nombre']) ?></td></tr>
            <tr><td class="label-cell">Fecha Emisión</td><td><?= date('d/m/Y', strtotime($cot['fecha_emision'])) ?></td></tr>
            <tr><td class="label-cell">Validez Oferta</td><td><?= date('d/m/Y', strtotime($cot['validez_oferta'])) ?></td></tr>
          </table>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-light">📦 Especificaciones de Carga</div>
        <div class="card-body p-0">
          <table class="meta-table">
            <tr><td class="label-cell">Origen</td><td><?= htmlspecialchars($cot['origen']) ?></td></tr>
            <tr><td class="label-cell">Destino</td><td><?= htmlspecialchars($cot['destino']) ?></td></tr>
            <tr><td class="label-cell">Tipo Carga</td><td><?= htmlspecialchars($cot['tipo_carga']) ?></td></tr>
            <tr><td class="label-cell">Servicio</td><td><?= htmlspecialchars($cot['servicio']) ?></td></tr>
            <tr><td class="label-cell">Peso Informado</td><td><?= number_format((float)$cot['peso_kg'], 2) ?> kg</td></tr>
            <tr><td class="label-cell">Volumen Informado</td><td><?= number_format((float)$cot['volumen_m3'], 2) ?> m³</td></tr>
            <tr><td class="label-cell">W/M Aplicado</td><td><?= number_format((float)$cot['wm_aplicado'], 2) ?> W/M</td></tr>
            <tr><td class="label-cell">Valor Mercadería</td><td><?= $cot['valor_mercaderia'] ? '$'.number_format((float)$cot['valor_mercaderia'], 2).' USD' : '—' ?></td></tr>
            <tr><td class="label-cell">Marcas Origen</td><td><?= $cot['tiene_marcas'] ? '✔ Sí cuenta' : '❌ Sin marcas' ?></td></tr>
            <tr><td class="label-cell">Paletizado</td><td><?= $cot['viene_paletizado'] ? '✔ Paletizado' : '❌ Sin paletizar' ?></td></tr>
            <tr><td class="label-cell">Apilable</td><td><?= $cot['es_apilable'] ? '✔ Sí' : '❌ No apilable' ?></td></tr>
          </table>
        </div>
      </div>
    </div>

    <!-- COLUMNA DERECHA: DESGLOSE DE COSTOS Y ESTADOS -->
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-light">💰 Desglose Financiero</div>
        <div class="card-body">
          <!-- USD -->
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge badge-moneda badge-usd">USD</span>
            <span style="font-size:12px;font-weight:600;text-transform:uppercase;color:#555">Pago Obligatorio en Dólares</span>
          </div>
          <table class="cot-table mb-4">
            <thead>
              <tr><th>Servicio</th><th>Unidad</th><th class="text-end">Costo USD</th></tr>
            </thead>
            <tbody>
              <?php foreach ($detallesUSD as $d): ?>
              <tr>
                <td><?= htmlspecialchars($d['concepto']) ?></td>
                <td><small class="text-muted"><?= $d['cantidad'] != 1 ? number_format((float)$d['cantidad'], 2).' W/M' : '—' ?></small></td>
                <td class="text-end">$<?= number_format((float)$d['costo_calculado'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr class="total-row">
                <td colspan="2">Subtotal USD</td>
                <td class="text-end">$<?= number_format($totalUSD, 2) ?></td>
              </tr>
            </tbody>
          </table>

          <!-- BS -->
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge badge-moneda badge-bs">Bs.</span>
            <span style="font-size:12px;font-weight:600;text-transform:uppercase;color:#555">Servicios Locales Bolivia (Moneda Nacional)</span>
          </div>
          <table class="cot-table mb-4">
            <thead>
              <tr><th>Servicio</th><th colspan="2" class="text-end">Costo Bs.</th></tr>
            </thead>
            <tbody>
              <?php foreach ($detallesBS as $d): ?>
              <tr>
                <td><?= htmlspecialchars($d['concepto']) ?></td>
                <td colspan="2" class="text-end">Bs. <?= number_format((float)$d['costo_calculado'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr class="total-row">
                <td>Subtotal Bs.</td>
                <td colspan="2" class="text-end">Bs. <?= number_format($totalBS, 2) ?></td>
              </tr>
            </tbody>
          </table>

          <!-- RECARGOS -->
          <?php if (!empty($recargos)): ?>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span style="font-size:12px;font-weight:600;text-transform:uppercase;color:#b45309">⚠️ Recargos Especiales</span>
          </div>
          <table class="cot-table mb-4">
            <tbody>
              <?php foreach ($recargos as $r): ?>
              <tr style="color:#b45309;font-style:italic">
                <td><?= htmlspecialchars($r['concepto']) ?></td>
                <td class="text-end"><?= $r['moneda'] === 'USD' ? '$'.number_format((float)$r['costo_calculado'], 2) : 'Bs. '.number_format((float)$r['costo_calculado'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>

          <!-- TOTAL BANNER -->
          <div class="total-banner mt-3">
            <div>
              <div class="lbl">Forma de pago: CONTRA ENTREGA DE DOCS</div>
              <div style="font-size:11px;opacity:.75;margin-top:2px">Estimado tránsito: 51 a 60 días</div>
            </div>
            <div class="text-end">
              <div class="lbl">Total Estimado USD</div>
              <div class="amt">$<?= number_format($totalEquiv, 2) ?></div>
              <div style="font-size:11px;opacity:.75">USD $<?= number_format($totalUSD, 2) ?> + Bs. <?= number_format($totalBS, 2) ?> (TC: <?= TC_USD_BS ?>)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- CONTROL DE OPERADOR Y AUDITORÍA -->
      <?php if (Auth::isAdmin() || Auth::isOperador()): ?>
      <div class="card shadow-sm">
        <div class="card-header bg-light">🚢 Gestión Logística de Embarque</div>
        <div class="card-body">
          <form id="form-estado" class="row g-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label small fw-semibold text-muted mb-1" style="font-size:11px">Cambiar Estado</label>
              <select id="op_estado" class="form-select form-select-sm">
                <?php foreach (array_keys($coloresEstado) as $est): ?>
                <option value="<?= $est ?>" <?= $cot['estado'] === $est ? 'selected' : '' ?>><?= $est ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-7">
              <label class="form-label small fw-semibold text-muted mb-1" style="font-size:11px">Notas de Tránsito / Operaciones</label>
              <input type="text" id="op_nota" class="form-control form-control-sm" placeholder="Ej: Arribó a Aduana La Paz. Manifiesto cargado.">
            </div>
            <div class="col-12 text-end">
              <button type="button" class="btn btn-primary btn-sm px-4" onclick="guardarCambioEstado()">Actualizar Estado</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- LOG DE ESTADOS -->
      <div class="card shadow-sm">
        <div class="card-header bg-light">📋 Bitácora y Auditoría de Estados</div>
        <div class="card-body">
          <?php if (empty($cot['log_estados'])): ?>
          <p class="text-muted small mb-0">No se registran cambios de estado.</p>
          <?php else: foreach ($cot['log_estados'] as $log): ?>
          <div class="log-item">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <div>
                <strong><?= htmlspecialchars($log['estado_nuevo']) ?></strong>
                <?php if ($log['estado_anterior']): ?>
                <span class="text-muted font-monospace" style="font-size:11px">(de <?= htmlspecialchars($log['estado_anterior']) ?>)</span>
                <?php endif; ?>
              </div>
              <small class="text-muted"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></small>
            </div>
            <div class="text-muted small">
              Por: <?= htmlspecialchars($log['nombre']) ?>
              <?php if (!empty($log['nota'])): ?>
              <br><span class="text-dark font-italic">💬 "<?= htmlspecialchars($log['nota']) ?>"</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function guardarCambioEstado() {
  const est  = document.getElementById('op_estado').value;
  const nota = document.getElementById('op_nota').value;
  
  if (!confirm(`¿Confirmas cambiar el estado a "${est}"?`)) {
    return;
  }

  try {
    const res = await fetch('api_estado.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        cotizacion_id: <?= $id ?>,
        estado: est,
        nota: nota,
        csrf_token: '<?= $csrf ?>'
      })
    });
    
    const data = await res.json();
    if (data.ok) {
      alert('✅ Estado de embarque actualizado.');
      location.reload();
    } else {
      alert('❌ Error: ' + (data.error || 'No se pudo guardar el estado.'));
    }
  } catch (e) {
    alert('❌ Error de conexión: ' + e.message);
  }
}
</script>
</body>
</html>
