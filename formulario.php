<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();
Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR]);

$user = Auth::user();
$db   = Database::getInstance();

// Cargar clientes según rol
if (Auth::isAdmin()) {
    $clientes = $db->query(
        "SELECT c.id, c.nombre_razon, c.nit_ci, u.nombre AS vendedor
         FROM clientes c JOIN usuarios u ON u.id = c.vendedor_id ORDER BY c.nombre_razon"
    )->fetchAll();
} else {
    $stmt = $db->prepare(
        "SELECT c.id, c.nombre_razon, c.nit_ci, u.nombre AS vendedor
         FROM clientes c JOIN usuarios u ON u.id = c.vendedor_id
         WHERE c.vendedor_id = :uid ORDER BY c.nombre_razon"
    );
    $stmt->execute([':uid' => $user['id']]);
    $clientes = $stmt->fetchAll();
}

$csrf = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nueva Cotización — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f0f2f5;font-family:'Segoe UI',sans-serif}
.sidebar{background:#003087;min-height:100vh;width:220px;position:fixed;top:0;left:0;z-index:100}
.sidebar .brand{padding:1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar .brand-name{font-size:1.4rem;font-weight:700;color:#fff;letter-spacing:-.5px}
.sidebar .brand-name span{color:#f97316}
.sidebar a.nav-link{color:rgba(255,255,255,.8);padding:.5rem 1rem;font-size:13px;display:block}
.sidebar a.nav-link:hover{background:rgba(255,255,255,.12);color:#fff}
.sidebar .user-box{position:absolute;bottom:0;left:0;right:0;padding:.75rem 1rem;border-top:1px solid rgba(255,255,255,.1)}
.sidebar .user-box .name{color:#fff;font-weight:600;font-size:13px}
.sidebar .user-box .role{color:rgba(255,255,255,.55);font-size:11px}
.main{margin-left:220px;padding:1.5rem}
.topbar{background:#fff;border-radius:10px;padding:.75rem 1.25rem;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;border:1px solid #e8e8e8}
.card{border-radius:10px;border:1px solid #e8e8e8}
.card-header{background:#fff;border-bottom:1px solid #eee;font-weight:600;font-size:14px;padding:.875rem 1.25rem;border-radius:10px 10px 0 0 !important}
.form-label{font-size:12px;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.4px}
.form-control,.form-select{font-size:13px}
.form-control:focus,.form-select:focus{border-color:#003087;box-shadow:0 0 0 .2rem rgba(0,48,135,.12)}
.btn-primary{background:#003087;border-color:#003087}
.btn-primary:hover{background:#002060}
.check-card{border:1px solid #e8e8e8;border-radius:8px;padding:.75rem 1rem;cursor:pointer;transition:.15s}
.check-card:hover{border-color:#003087;background:#f8f9ff}
.check-card input[type=checkbox]{width:16px;height:16px}
.check-title{font-size:13px;font-weight:600;color:#333}
.check-desc{font-size:11px;color:#888;margin-top:2px}
.badge-moneda{font-size:10px;padding:2px 7px;border-radius:12px;font-weight:600}
.badge-usd{background:#E6F1FB;color:#0C447C}
.badge-bs{background:#E1F5EE;color:#085041}

/* Tabla de resultado */
#resultado-cotizacion{display:none}
.result-header{background:#003087;color:#fff;padding:1.5rem;border-radius:10px 10px 0 0;display:flex;gap:1.5rem;align-items:flex-start}
.result-logo{background:#fff;border-radius:8px;padding:.35rem .75rem;color:#003087;font-weight:700;font-size:15px;flex-shrink:0}
.result-logo span{color:#f97316;font-size:10px;display:block;font-weight:400;letter-spacing:1px}
.result-meta table{font-size:12px;border-collapse:collapse}
.result-meta td{padding:2px 8px 2px 0;color:rgba(255,255,255,.9)}
.result-meta td:first-child{font-weight:600;color:rgba(255,255,255,.65);white-space:nowrap}
.cot-table{width:100%;border-collapse:collapse;font-size:13px}
.cot-table th{background:#f5f7fa;padding:8px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#888;font-weight:600;border-bottom:1px solid #eee}
.cot-table td{padding:8px 10px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.cot-table tr.usd-row td:last-child{color:#003087;font-weight:600}
.cot-table tr.bs-row td:last-child{color:#0a6e56;font-weight:600}
.cot-table tr.recargo-row td{color:#b45309;font-style:italic}
.cot-table tr.total-row td{font-weight:700;background:#f5f7fa;font-size:14px}
.total-banner{background:#003087;color:#fff;padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-radius:0 0 8px 8px;margin-top:.5rem}
.total-banner .lbl{font-size:12px;opacity:.75}
.total-banner .amt{font-size:22px;font-weight:700}
.conditions-box{background:#fffdf5;border:1px solid #fde68a;border-radius:8px;padding:1rem;font-size:11.5px;color:#78350f;line-height:1.8}
.conditions-box h6{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem;color:#92400e}
.alerta-item{display:flex;gap:.5rem;padding:.45rem .6rem;border-radius:6px;font-size:12px;margin-bottom:.35rem}
.alerta-danger{background:#FEE2E2;color:#991B1B}
.alerta-warning{background:#FEF3C7;color:#92400E}
.alerta-info{background:#EFF6FF;color:#1E40AF}
.spinner-overlay{display:none;position:fixed;inset:0;background:rgba(255,255,255,.6);z-index:9999;align-items:center;justify-content:center}
.spinner-overlay.show{display:flex}
@media print{
  .sidebar,.topbar,.form-section,.action-bar{display:none!important}
  .main{margin-left:0!important;padding:0!important}
  #resultado-cotizacion{display:block!important}
}
</style>
</head>
<body>

<div class="spinner-overlay" id="spinner">
  <div class="text-center">
    <div class="spinner-border text-primary" style="width:3rem;height:3rem"></div>
    <div class="mt-2 fw-semibold text-primary" style="font-size:14px">Calculando cotización…</div>
  </div>
</div>

<!-- SIDEBAR -->
<div class="sidebar">
  <div class="brand"><div class="brand-name">Gemzbo<span>srl.</span></div></div>
  <nav class="pt-2">
    <a href="dashboard.php"  class="nav-link">🏠 Dashboard</a>
    <a href="formulario.php" class="nav-link" style="background:rgba(255,255,255,.14);color:#fff">➕ Nueva Cotización</a>
    <a href="clientes.php"   class="nav-link">🏢 Clientes</a>
    <a href="cotizaciones.php" class="nav-link">📋 Cotizaciones</a>
  </nav>
  <div class="user-box">
    <div class="name"><?= htmlspecialchars($user['nombre']) ?></div>
    <div class="role"><?= htmlspecialchars($user['nombre_rol']) ?></div>
    <a href="logout.php" class="text-danger text-decoration-none" style="font-size:11px;display:block;margin-top:.4rem">Cerrar sesión</a>
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <h4 style="margin:0;font-size:16px;font-weight:600">➕ Nueva Cotización LCL</h4>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">← Volver</a>
  </div>

  <div class="form-section">
  <form id="form-cotizacion" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <!-- ── CLIENTE ── -->
    <div class="card mb-3">
      <div class="card-header">👤 Datos del Cliente</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Cliente *</label>
            <select name="cliente_id" id="cliente_id" class="form-select" required>
              <option value="">— Seleccionar cliente —</option>
              <?php foreach ($clientes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_razon']) ?> <?= $c['nit_ci'] ? '('.$c['nit_ci'].')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tipo de carga *</label>
            <select name="tipo_carga" class="form-select">
              <option>Mercadería General</option>
              <option>Electrónicos</option>
              <option>Maquinaria</option>
              <option>Textil</option>
              <option>Alimentos</option>
              <option>Repuestos</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Servicio</label>
            <select name="servicio" class="form-select">
              <option value="LCL">Marítimo LCL</option>
              <option value="FCL20">FCL 20'</option>
              <option value="FCL40">FCL 40'</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Puerto / Ciudad Origen *</label>
            <select name="origen" id="sel_origen" class="form-select" onchange="checkOrigen()">
              <option value="Shanghai, China">Shanghai, China</option>
              <option value="Shenzhen, China">Shenzhen, China</option>
              <option value="Guangzhou, China">Guangzhou, China</option>
              <option value="Ningbo, China">Ningbo, China</option>
              <option value="Canadá">Canadá</option>
              <option value="Miami, USA">Miami, USA</option>
              <option value="Los Ángeles, USA">Los Ángeles, USA</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Destino Final</label>
            <select name="destino" class="form-select">
              <option value="Cochabamba, Bolivia">Cochabamba, Bolivia</option>
              <option value="La Paz, Bolivia">La Paz, Bolivia</option>
              <option value="Santa Cruz, Bolivia">Santa Cruz, Bolivia</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Validez de Oferta</label>
            <input type="date" name="validez_oferta" id="validez_oferta" class="form-control">
          </div>
        </div>
      </div>
    </div>

    <!-- ── CARGA ── -->
    <div class="card mb-3">
      <div class="card-header">📦 Datos de la Carga</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Peso total (kg) *</label>
            <input type="number" name="peso_kg" id="peso_kg" class="form-control" placeholder="Ej. 1850" min="0" step="0.01" required oninput="calcWM()">
          </div>
          <div class="col-md-3">
            <label class="form-label">Volumen total (m³) *</label>
            <input type="number" name="volumen_m3" id="volumen_m3" class="form-control" placeholder="Ej. 18.44" min="0" step="0.01" required oninput="calcWM()">
          </div>
          <div class="col-md-3">
            <label class="form-label">N° de bultos</label>
            <input type="number" name="bultos" class="form-control" placeholder="Ej. 12" min="1">
          </div>
          <div class="col-md-3">
            <label class="form-label">Valor mercadería (USD)</label>
            <input type="number" name="valor_mercaderia" class="form-control" placeholder="Ej. 15000" min="0" step="0.01">
          </div>
        </div>
        <!-- W/M Indicator -->
        <div id="wm-box" class="alert alert-info py-2 px-3 mt-3 mb-0" style="display:none;font-size:13px">
          <strong>⚖️ Regla W/M:</strong> <span id="wm-result"></span>
        </div>
      </div>
    </div>

    <!-- ── RECARGOS ── -->
    <div class="card mb-3">
      <div class="card-header">⚠️ Especificaciones Especiales</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Largo máx. por pieza (pies)</label>
            <input type="number" name="largo_pies" id="largo_pies" class="form-control" placeholder="Ej. 10" step="0.1" min="0" oninput="checkEspeciales()">
            <div class="form-text">Extra largo si > 12 pies: +USD 12/pie</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Peso mayor bulto individual (lbs)</label>
            <input type="number" name="peso_bulto_lbs" id="peso_bulto_lbs" class="form-control" placeholder="Ej. 200" min="0" oninput="checkEspeciales()">
            <div class="form-text">OWS si > 5,000 lbs: +USD 470</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Altura máxima (m)</label>
            <input type="number" name="altura_m" id="altura_m" class="form-control" placeholder="Ej. 2.10" step="0.01" min="0" oninput="checkEspeciales()">
            <div class="form-text">Alerta High Cube si > 2.40 m</div>
          </div>
        </div>
        <div id="alertas-especiales" class="mt-3"></div>
      </div>
    </div>

    <!-- ── IMO ── -->
    <div class="card mb-3">
      <div class="card-header">🚨 Clasificación IMO (si aplica)</div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Clase IMO (0 = No IMO)</label>
            <select name="clase_imo" id="clase_imo" class="form-select" onchange="checkIMO()">
              <option value="0">0 — No es carga IMO</option>
              <option value="1">Clase 1 — Explosivos ⛔</option>
              <option value="2">Clase 2 — Gases</option>
              <option value="3">Clase 3 — Líquidos inflamables ⚠️</option>
              <option value="4">Clase 4 — Sólidos inflamables</option>
              <option value="5">Clase 5 — Comburentes ⛔</option>
              <option value="6">Clase 6 — Tóxicos ⛔</option>
              <option value="7">Clase 7 — Radiactivos ⛔</option>
              <option value="8">Clase 8 — Corrosivos</option>
            </select>
          </div>
          <div class="col-md-3" id="fp-box" style="display:none">
            <label class="form-label">Flash Point (°C) — Clase 3</label>
            <input type="number" name="flash_point" id="flash_point" class="form-control" value="0" oninput="checkIMO()">
          </div>
          <div class="col-md-5" id="imo-result"></div>
        </div>
      </div>
    </div>

    <!-- ── CONDICIONALES ── -->
    <div class="card mb-3">
      <div class="card-header">✅ Condicionales Rápidos</div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="check-card d-flex align-items-start gap-2">
              <input type="checkbox" name="tiene_marcas" id="chk_marcas" value="1" checked class="mt-1" onchange="checkCondicionales()">
              <div><div class="check-title">✔ La carga cuenta con marcas de origen</div><div class="check-desc">Cajas y embalajes identificados con marcas y números desde origen.<br>Sin marcas → USD 300 aclaración al manifiesto (TPA, ASPB, Aduana)</div></div>
            </label>
          </div>
          <div class="col-md-6">
            <label class="check-card d-flex align-items-start gap-2">
              <input type="checkbox" name="viene_paletizado" id="chk_paletizado" value="1" checked class="mt-1" onchange="checkCondicionales()">
              <div><div class="check-title">✔ La carga viene paletizada</div><div class="check-desc">Ley 20001 Chile — Cargas >25 kg deben venir en pallets.<br>Sin paletizar → costo de paletizaje obligatorio en puerto.</div></div>
            </label>
          </div>
          <div class="col-md-6">
            <label class="check-card d-flex align-items-start gap-2">
              <input type="checkbox" name="es_apilable" id="chk_apilable" value="1" checked class="mt-1">
              <div><div class="check-title">✔ La carga es apilable</div><div class="check-desc">Permite manejo estándar LCL con manipuleos y trasbordos.</div></div>
            </label>
          </div>
          <div class="col-md-6">
            <label class="check-card d-flex align-items-start gap-2">
              <input type="checkbox" name="solicita_seguro" id="chk_seguro" value="1" class="mt-1">
              <div><div class="check-title">☐ El cliente solicita incluir seguro</div><div class="check-desc">GEMZ no cubre siniestros ni daños. El seguro es responsabilidad del cliente.<br>Marcar para incluir gestión de seguro en cotización.</div></div>
            </label>
          </div>
        </div>
        <div id="alertas-condicionales" class="mt-3"></div>
      </div>
    </div>

    <!-- BOTONES -->
    <div class="d-flex gap-2 mb-4 action-bar">
      <button type="button" class="btn btn-primary px-4" onclick="enviarFormulario('calcular')">
        🧮 Calcular cotización
      </button>
      <button type="button" class="btn btn-success px-4" id="btn-guardar" style="display:none" onclick="enviarFormulario('guardar')">
        💾 Guardar en sistema
      </button>
      <button type="reset" class="btn btn-outline-secondary" onclick="limpiarResultado()">
        🗑 Limpiar
      </button>
    </div>
  </form>
  </div><!-- /form-section -->

  <!-- ══════════════════════════════════════════════ -->
  <!-- RESULTADO COTIZACIÓN                          -->
  <!-- ══════════════════════════════════════════════ -->
  <div id="resultado-cotizacion">
    <div class="result-header">
      <div class="result-logo">Gemzbo<span>Grupo Logístico</span></div>
      <div class="result-meta">
        <h2 style="font-size:18px;font-weight:700;color:#fff;letter-spacing:1px;margin-bottom:6px">COTIZACIÓN</h2>
        <table>
          <tr><td>Nº de Cotización:</td><td id="r-num" style="font-weight:600">—</td></tr>
          <tr><td>Fecha:</td><td id="r-fecha"></td></tr>
          <tr><td>Cliente:</td><td id="r-cliente"></td></tr>
          <tr><td>Tipo de Carga:</td><td id="r-tipo"></td></tr>
          <tr><td>Validez:</td><td id="r-validez"></td></tr>
        </table>
      </div>
    </div>
    <div class="card border-0" style="border-radius:0 0 10px 10px">
      <div class="card-body">

        <!-- ALERTAS -->
        <div id="res-alertas" class="mb-3"></div>

        <!-- TABLA USD -->
        <div class="d-flex align-items-center gap-2 mb-2 mt-2">
          <span class="badge badge-moneda badge-usd">USD</span>
          <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#555">Flete Marítimo y Cargos — Pago obligatorio en Dólares</span>
        </div>
        <table class="cot-table">
          <thead><tr><th>Ict.</th><th>Servicio</th><th>Unidad</th><th>Desde</th><th>Hasta</th><th style="text-align:right">Precio USD</th></tr></thead>
          <tbody id="tbody-usd"></tbody>
        </table>

        <!-- TABLA BS -->
        <div class="d-flex align-items-center gap-2 mb-2 mt-4">
          <span class="badge badge-moneda badge-bs">Bs.</span>
          <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#555">Servicios Locales Bolivia — Cobro en Bolivianos</span>
        </div>
        <table class="cot-table">
          <thead><tr><th>Ict.</th><th>Servicio</th><th colspan="3">Descripción</th><th style="text-align:right">Precio Bs.</th></tr></thead>
          <tbody id="tbody-bs"></tbody>
        </table>

        <!-- RECARGOS -->
        <div id="res-recargos"></div>

        <!-- TOTAL -->
        <div class="total-banner mt-3" id="res-total"></div>

        <!-- NOTA W/M -->
        <div id="res-wm-nota" class="mt-3 text-muted" style="font-size:12px"></div>

        <!-- CONDICIONES -->
        <div class="conditions-box mt-3">
          <h6>Condiciones y notas importantes</h6>
          <div id="res-condiciones"></div>
        </div>

        <!-- ACCIÓN -->
        <div class="d-flex gap-2 mt-3 action-bar">
          <button class="btn btn-outline-primary btn-sm" onclick="window.print()">🖨 Imprimir / PDF</button>
          <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('btn-guardar').click()">💾 Guardar cotización</button>
        </div>

        <!-- PIE DE PÁGINA -->
        <div class="mt-3 pt-3 border-top d-flex gap-4 flex-wrap" style="font-size:11px;color:#888">
          <span>📞 (591) 606 56019</span>
          <span>📱 631 47601</span>
          <span>✉️ info@gemz.com.bo</span>
          <span>🌐 www.gemz.com.bo</span>
          <span>📍 Zona Central C/Mercado Nro. 1328 Edif. Mariscal, Ballivián Piso 4 Of. 402, La Paz - Bolivia</span>
        </div>
        <div class="text-center mt-3" style="font-size:13px;color:#555">
          <strong id="res-vendedor"></strong><br>
          <span style="font-size:12px">GRUPO LOGÍSTICO GEMZ BOLIVIA SRL</span>
        </div>
      </div>
    </div>
  </div><!-- /resultado -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const TC = <?= TC_USD_BS ?>;
let cotizacionGuardada = null;

// ── Validez por defecto (+21 días) ──
document.getElementById('validez_oferta').value =
  new Date(Date.now() + 21*864e5).toISOString().split('T')[0];

// ── W/M en tiempo real ──
function calcWM(){
  const kg  = parseFloat(document.getElementById('peso_kg').value)    || 0;
  const m3  = parseFloat(document.getElementById('volumen_m3').value) || 0;
  if (!kg && !m3){ document.getElementById('wm-box').style.display='none'; return; }
  const pesoT = kg/1000;
  const wm    = Math.max(pesoT, m3);
  const base  = pesoT >= m3 ? 'Peso' : 'Volumen';
  document.getElementById('wm-result').textContent =
    `Se usa ${base} → ${wm.toFixed(2)} W/M  (Peso: ${pesoT.toFixed(3)} T | Volumen: ${m3.toFixed(2)} m³)`;
  document.getElementById('wm-box').style.display='block';
}

// ── Alertas especiales ──
function checkEspeciales(){
  const largo = parseFloat(document.getElementById('largo_pies').value)    || 0;
  const lbs   = parseFloat(document.getElementById('peso_bulto_lbs').value)|| 0;
  const alt   = parseFloat(document.getElementById('altura_m').value)      || 0;
  let html = '';
  if(largo > 12) html += alerta('warning',`Extra largo: ${largo} pies > 12 pies → recargo USD ${((largo-12)*12).toFixed(0)}`);
  if(lbs > 5000) html += alerta('warning',`Sobrepeso bulto: ${lbs} lbs > 5,000 lbs → OWS USD 470 fijo`);
  if(alt  > 2.40)html += alerta('info',   `Altura ${alt}m > 2.40m → requiere contenedor High Cube`);
  document.getElementById('alertas-especiales').innerHTML = html;
}

// ── Condicionales rápidos ──
function checkCondicionales(){
  const marcas = document.getElementById('chk_marcas').checked;
  const palet  = document.getElementById('chk_paletizado').checked;
  const kg     = parseFloat(document.getElementById('peso_kg').value) || 0;
  let html = '';
  if(!marcas) html += alerta('danger', 'Sin marcas de origen → USD 300 por aclaración al manifiesto (TPA, ASPB, Aduana)');
  if(!palet && kg>25) html += alerta('warning', 'Sin paletizar y carga >25 kg → paletizaje obligatorio por Ley 20001 Chile');
  document.getElementById('alertas-condicionales').innerHTML = html;
}

// ── Origen Canadá ──
function checkOrigen(){
  const orig = document.getElementById('sel_origen').value;
  if(orig.toLowerCase().includes('canad')){
    document.getElementById('alertas-especiales').innerHTML +=
      alerta('info','Origen Canadá → se agrega Inbond Fee USD 55');
  }
}

// ── IMO ──
function checkIMO(){
  const cl  = parseInt(document.getElementById('clase_imo').value);
  const fp  = parseFloat(document.getElementById('flash_point')?.value) || 0;
  const fpBox = document.getElementById('fp-box');
  const res   = document.getElementById('imo-result');
  fpBox.style.display = cl===3 ? 'block':'none';
  const bloqueados = [1,5,6,7];
  if(cl===0){ res.innerHTML=''; return; }
  if(bloqueados.includes(cl)){
    res.innerHTML = alerta('danger',`Clase IMO ${cl} BLOQUEADA. No se puede procesar sin aprobación de gerencia + MSDS.`);
  } else if(cl===3 && fp<-18){
    res.innerHTML = alerta('danger',`Clase 3 con flash point ${fp}°C < -18°C: RECHAZADA.`);
  } else {
    res.innerHTML = alerta('info',`Clase IMO ${cl} aceptada. Requiere MSDS y condiciones especiales de manejo.`);
  }
}

// ── Helper alerta ──
function alerta(tipo, msg){
  const map = {danger:'alerta-danger',warning:'alerta-warning',info:'alerta-info'};
  const ico = {danger:'⛔',warning:'⚠️',info:'ℹ️'};
  return `<div class="alerta-item ${map[tipo]}">${ico[tipo]} ${msg}</div>`;
}

// ── ENVIAR FORMULARIO ──
async function enviarFormulario(accion){
  const form = document.getElementById('form-cotizacion');
  if(!form.checkValidity()){ form.reportValidity(); return; }

  // Verificar IMO bloqueado
  const clIMO = parseInt(document.getElementById('clase_imo').value);
  const fp    = parseFloat(document.getElementById('flash_point')?.value)||0;
  if([1,5,6,7].includes(clIMO) || (clIMO===3 && fp<-18)){
    alert('No se puede procesar: la carga IMO seleccionada está bloqueada.');
    return;
  }

  document.getElementById('spinner').classList.add('show');

  const data = Object.fromEntries(new FormData(form));
  data.accion           = accion;
  data.tiene_marcas     = document.getElementById('chk_marcas').checked;
  data.viene_paletizado = document.getElementById('chk_paletizado').checked;
  data.es_apilable      = document.getElementById('chk_apilable').checked;

  try {
    const res  = await fetch('procesar_cotizacion.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(data)
    });
    const json = await res.json();
    document.getElementById('spinner').classList.remove('show');

    if(!json.ok){
      alert('Error: ' + (json.error || 'No se pudo calcular la cotización.'));
      return;
    }
    renderResultado(json, accion);
  } catch(e){
    document.getElementById('spinner').classList.remove('show');
    alert('Error de conexión: ' + e.message);
  }
}

// ── RENDERIZAR RESULTADO ──
function renderResultado(data, accion){
  const clienteNombre = document.getElementById('cliente_id').selectedOptions[0]?.text || '—';
  const tipoCarga     = document.querySelector('[name=tipo_carga]').value;
  const validez       = document.getElementById('validez_oferta').value;
  const origen        = document.querySelector('[name=origen]').value;
  const destino       = document.querySelector('[name=destino]').value;

  document.getElementById('r-num').textContent     = data.numero_cotizacion || 'BORRADOR';
  document.getElementById('r-fecha').textContent   = new Date().toLocaleDateString('es-BO');
  document.getElementById('r-cliente').textContent = clienteNombre.toUpperCase();
  document.getElementById('r-tipo').textContent    = tipoCarga.toUpperCase();
  document.getElementById('r-validez').textContent = validez ? new Date(validez+'T12:00').toLocaleDateString('es-BO') : '—';
  document.getElementById('res-vendedor').textContent = '<?= htmlspecialchars($user['nombre']) ?>';

  // Alertas
  const alertas = (data.alertas||[]).map(a=>alerta(a.tipo, a.msg)).join('');
  document.getElementById('res-alertas').innerHTML = alertas;

  // Detalles USD
  const detusd = (data.detalles||[]).filter(d=>d.moneda==='USD'&&!d.es_recargo);
  let rowsUSD = detusd.map((d,i)=>`
    <tr class="usd-row">
      <td>${i+1}</td>
      <td>${d.concepto}</td>
      <td><small class="text-muted">${d.cantidad!==1?d.cantidad+' W/M':'—'}</small></td>
      <td><small class="text-muted">${origen}</small></td>
      <td><small class="text-muted">${destino}</small></td>
      <td style="text-align:right">$${d.costo_calculado.toFixed(2)}</td>
    </tr>`).join('');
  const subUSD = detusd.reduce((s,d)=>s+d.costo_calculado,0);
  rowsUSD += `<tr class="total-row"><td colspan="5">Subtotal USD</td><td style="text-align:right">$${subUSD.toFixed(2)}</td></tr>`;
  document.getElementById('tbody-usd').innerHTML = rowsUSD;

  // Detalles BS
  const detbs = (data.detalles||[]).filter(d=>d.moneda==='BS');
  let rowsBS = detbs.map((d,i)=>`
    <tr class="bs-row">
      <td>${i+1}</td>
      <td>${d.concepto}</td>
      <td colspan="3"><small class="text-muted">Costo local Bolivia (TC: ${TC})</small></td>
      <td style="text-align:right">Bs. ${d.costo_calculado.toFixed(2)}</td>
    </tr>`).join('');
  const subBS = detbs.reduce((s,d)=>s+d.costo_calculado,0);
  rowsBS += `<tr class="total-row"><td colspan="5">Subtotal Bs.</td><td style="text-align:right">Bs. ${subBS.toFixed(2)}</td></tr>`;
  document.getElementById('tbody-bs').innerHTML = rowsBS;

  // Recargos
  const recargos = (data.detalles||[]).filter(d=>d.es_recargo);
  if(recargos.length){
    let html = `<div class="d-flex align-items-center gap-2 mb-2 mt-4"><span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#b45309">⚠️ Recargos Especiales</span></div>
      <table class="cot-table"><thead><tr><th>Concepto</th><th colspan="4"></th><th style="text-align:right">Costo</th></tr></thead><tbody>`;
    recargos.forEach(r=>{
      html += `<tr class="recargo-row"><td colspan="5">${r.concepto}</td><td style="text-align:right">${r.moneda==='USD'?'$'+r.costo_calculado.toFixed(2):'Bs. '+r.costo_calculado.toFixed(2)}</td></tr>`;
    });
    html += '</tbody></table>';
    document.getElementById('res-recargos').innerHTML = html;
  } else {
    document.getElementById('res-recargos').innerHTML = '';
  }

  // Total
  const totalEquiv = data.total_equiv || (data.total_usd + data.total_bs/TC);
  document.getElementById('res-total').innerHTML = `
    <div>
      <div class="lbl">Forma de pago: CONTRA ENTREGA DE DOCS</div>
      <div style="font-size:12px;margin-top:2px;opacity:.75">Tránsito estimado Shanghai → Iquique: 51 a 60 días</div>
    </div>
    <div style="text-align:right">
      <div class="lbl">Total USD estimado</div>
      <div class="amt">$${totalEquiv.toFixed(2)}</div>
      <div style="font-size:11px;opacity:.75">USD $${data.total_usd.toFixed(2)} + Bs. ${data.total_bs.toFixed(2)}</div>
    </div>`;

  // Nota W/M
  document.getElementById('res-wm-nota').innerHTML =
    `ℹ️ Cotización basada en W/M inicial informado: <strong>${data.wm.toFixed(2)} W/M</strong>. En caso de variación de peso o volumen, se facturará según W/M final recibido en bodega y que figurará en el B/L.`;

  // Condiciones fijas
  document.getElementById('res-condiciones').innerHTML = condicionesFijas(origen, data.alertas||[]);

  // Mostrar resultado
  document.getElementById('resultado-cotizacion').style.display = 'block';
  document.getElementById('btn-guardar').style.display = accion==='guardar' ? 'none':'inline-block';
  document.getElementById('resultado-cotizacion').scrollIntoView({behavior:'smooth'});

  if(accion==='guardar' && data.numero_cotizacion){
    document.getElementById('r-num').textContent = data.numero_cotizacion;
    document.getElementById('btn-guardar').style.display='none';
    alert(`✅ Cotización ${data.numero_cotizacion} guardada exitosamente.`);
  }
}

function condicionesFijas(origen, alertas){
  const conds = [
    'Cotización aplica para carga GENERAL y APILABLE.',
    'Cargas de Asia: Pueden darse retrasos por falta de disponibilidad de contenedores, roleos o reprogramaciones en puertos de conexión, sin responsabilidad de GEMZ Bolivia.',
    'Cotización basada en peso y volumen iniciales informados por el cliente. En caso de variación, se facturará según W/M final en BL.',
    'Cargas con +12 pies lineales de largo: USD 12 por pie adicional.',
    'Cargas con peso >5,000 lbs por bulto: OWS USD 170 fijo + USD 300 por uso de forklift por cada manipuleo.',
    'Carga ≤2,500 kg o ≤2.9 m³: aplica tarifa mínima de desconsolidación.',
    'Los clientes deberán tener sus mercancías aseguradas. GEMZ no se hace cargo de siniestros, pérdidas o daños. El seguro no está incluido en esta cotización.',
    'CARGAS IMO: No se aceptan Clase 1, 5, 6, 7 ni Clase 3 con flash point <-18°C sin consulta previa y envío de MSDS.',
    'Toda carga de importación debe contar con marcas desde origen. Sin marcas: USD 300 de aclaración al manifiesto (TPA, ASPB y Aduana).',
    'LEY 20001 Chile: Cargas >25 kg deben contar con base o pallets. Sin paletizar, se cobrará paletizaje de acuerdo al origen.',
    'Carga con altura >2.40 m requiere contenedor High Cube, con recargos adicionales por espacio muerto.',
    'En caso de transmisión errónea de Sidemar: multa USD 100. Transmisión fuera de plazo: USD 50.',
    'Tarifa válida para cargas en tránsito a Bolivia. No incluye pago de ASPB (por cuenta del consignatario).',
    'Los pallets en Bolivia no constituyen un tipo de bulto. Se deben declarar cajas/tambores/paquetes que conforman el pallet, con sus respectivas marcas y números.',
    'Emisiones de BL en destino deben ser aprobadas por el shipper en origen.',
  ];
  if(origen.toLowerCase().includes('canad'))
    conds.push('Cargas de origen Canadá: Inbond Fee USD 55. Aplican mínimos debajo de 2 CBM.');
  return conds.map(c=>`<p style="margin-bottom:4px">• ${c}</p>`).join('');
}

function limpiarResultado(){
  document.getElementById('resultado-cotizacion').style.display='none';
  document.getElementById('btn-guardar').style.display='none';
  document.getElementById('wm-box').style.display='none';
  document.getElementById('alertas-especiales').innerHTML='';
  document.getElementById('alertas-condicionales').innerHTML='';
}
</script>
</body>
</html>
