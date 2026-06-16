<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

Auth::startSession();
Auth::checkRole([ROL_ADMIN, ROL_VENDEDOR, ROL_OPERADOR, ROL_CLIENTE]);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { header('Location: dashboard.php'); exit; }

$ctrl = new CotizacionController();
$cot  = $ctrl->obtener($id);
if (!$cot) { http_response_code(404); die('Cotización no encontrada.'); }

$detallesUSD  = array_filter($cot['detalles'], fn($d) => $d['moneda']==='USD' && !$d['es_recargo']);
$detallesBS   = array_filter($cot['detalles'], fn($d) => $d['moneda']==='BS');
$recargos     = array_filter($cot['detalles'], fn($d) => $d['es_recargo']);
$totalUSD     = array_sum(array_column(array_filter($cot['detalles'],fn($d)=>$d['moneda']==='USD'),'costo_calculado'));
$totalBS      = array_sum(array_column(array_filter($cot['detalles'],fn($d)=>$d['moneda']==='BS'),'costo_calculado'));
$totalEquiv   = $totalUSD + ($totalBS / TC_USD_BS);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cotización <?= htmlspecialchars($cot['numero_cotizacion']) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,'Helvetica Neue',sans-serif;font-size:12px;color:#1a1a1a;background:#fff;padding:0}
.no-print{padding:1rem;background:#f0f0f0;border-bottom:1px solid #ccc;display:flex;gap:.75rem;align-items:center}
.no-print button{padding:6px 16px;border-radius:6px;border:1px solid #ccc;cursor:pointer;font-size:13px;background:#fff}
.no-print button.primary{background:#003087;color:#fff;border-color:#003087}
.page{max-width:800px;margin:0 auto;background:#fff}

/* ENCABEZADO */
.doc-header{display:flex;padding:1.25rem 1.5rem 1rem;border-bottom:3px solid #003087;align-items:flex-start;gap:2rem;position:relative}
.logo-area{flex-shrink:0}
.logo-box{background:#003087;border-radius:8px;padding:.5rem 1rem;color:#fff;font-size:20px;font-weight:700;letter-spacing:-.5px;display:inline-block}
.logo-box .sub{color:#f97316;font-size:10px;display:block;font-weight:400;letter-spacing:1.5px;text-transform:uppercase}
.company-info{font-size:10px;color:#555;margin-top:.5rem;line-height:1.6}
.doc-title{position:absolute;left:50%;transform:translateX(-50%);top:1.5rem;text-align:center}
.doc-title h1{font-size:24px;font-weight:700;letter-spacing:2px;color:#003087;text-transform:uppercase}
.meta-table{margin-left:auto;font-size:11.5px;border-collapse:collapse;min-width:280px}
.meta-table td{padding:3px 6px}
.meta-table td:first-child{font-weight:700;color:#003087;text-align:right;white-space:nowrap}
.meta-table td:last-child{color:#333}
.orange-bar{height:4px;background:linear-gradient(90deg,#003087 60%,#f97316 100%);margin-bottom:1rem}

/* CUERPO */
.doc-body{padding:1rem 1.5rem}
.section-header{display:flex;align-items:center;gap:.5rem;margin:1rem 0 .4rem;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#555}
.badge-usd{background:#E6F1FB;color:#0C447C;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700}
.badge-bs{background:#E1F5EE;color:#085041;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700}

/* TABLAS */
.cot-table{width:100%;border-collapse:collapse;font-size:11.5px}
.cot-table th{background:#003087;color:#fff;padding:5px 8px;text-align:left;font-weight:600;font-size:10px;text-transform:uppercase;letter-spacing:.4px}
.cot-table td{padding:5px 8px;border-bottom:1px solid #eee;vertical-align:middle}
.cot-table tr:nth-child(even) td{background:#f9fbff}
.cot-table tr.usd-row td:last-child{color:#003087;font-weight:700;text-align:right}
.cot-table tr.bs-row td:last-child{color:#085041;font-weight:700;text-align:right}
.cot-table tr.recargo-row td{color:#b45309;font-style:italic}
.cot-table tr.total-row td{font-weight:700;background:#f0f4ff;font-size:12.5px;border-top:2px solid #003087}
.cot-table .right{text-align:right}

/* TOTAL BANNER */
.total-banner{background:#003087;color:#fff;padding:.875rem 1.25rem;display:flex;justify-content:space-between;align-items:center;border-radius:6px;margin-top:.75rem}
.total-banner .label{font-size:11px;opacity:.8}
.total-banner .amount{font-size:20px;font-weight:700}
.total-banner .sub{font-size:10px;opacity:.65;margin-top:2px}

/* CONDICIONES */
.conditions{margin-top:1rem;border:1px solid #e8d88a;border-radius:6px;background:#fffef5;padding:.875rem 1rem;font-size:10.5px;color:#555;line-height:1.75}
.conditions h4{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#92400e;margin-bottom:.4rem}
.conditions p{margin-bottom:3px}

/* PIE DE PÁGINA */
.doc-footer{border-top:3px solid #003087;margin-top:1rem;padding:.75rem 1.5rem;display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center}
.doc-footer .contact-item{font-size:10px;color:#555;display:flex;align-items:center;gap:.25rem}
.firma{text-align:center;margin-top:1.25rem;padding-top:.75rem;border-top:1px solid #ddd;font-size:12px}
.firma strong{font-size:13px;text-transform:uppercase;letter-spacing:.5px}
.firma .cargo{font-size:10px;color:#888;margin-top:2px}

@media print{
  .no-print{display:none!important}
  .page{max-width:100%;margin:0;padding:0}
  body{padding:0}
  @page{margin:1.5cm;size:A4}
}
</style>
</head>
<body>
<div class="no-print">
  <button class="primary" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
  <button onclick="history.back()">← Volver</button>
  <span style="font-size:12px;color:#666">Use Ctrl+P → "Guardar como PDF" para exportar</span>
</div>

<div class="page">
  <div class="doc-header">
    <div class="logo-area">
      <div class="logo-box">Gemzbo<span class="sub">srl.</span></div>
      <div class="company-info">
        Grupo Logístico GEMZ Bolivia SRL<br>
        Zona Central C/Mercado Nro. 1328<br>
        Edif. Mariscal, Ballivián Piso 4 Of. 402<br>
        La Paz - Bolivia<br>
        ☎ (591) 606 56019 | 631 47601<br>
        ✉ info@gemz.com.bo
      </div>
    </div>
    <div class="doc-title">
      <h1>Cotización</h1>
    </div>
    <table class="meta-table">
      <tr><td>Nº de Cotización:</td><td><?= htmlspecialchars($cot['numero_cotizacion']) ?></td></tr>
      <tr><td>Fecha:</td><td><?= date('d/m/Y', strtotime($cot['fecha_emision'])) ?></td></tr>
      <tr><td>Cliente:</td><td><?= htmlspecialchars(strtoupper($cot['nombre_razon'])) ?></td></tr>
      <tr><td>Tipo de Carga:</td><td><?= htmlspecialchars(strtoupper($cot['tipo_carga'])) ?></td></tr>
      <tr><td>Validez de Oferta:</td><td><?= date('d/m/Y', strtotime($cot['validez_oferta'])) ?></td></tr>
      <tr><td>Estado:</td><td><?= htmlspecialchars($cot['estado']) ?></td></tr>
    </table>
  </div>
  <div class="orange-bar"></div>

  <div class="doc-body">
    <!-- TABLA USD -->
    <div class="section-header">
      <span class="badge-usd">USD</span>
      Flete Marítimo y Cargos — Pago Obligatorio en Dólares
    </div>
    <table class="cot-table">
      <thead>
        <tr>
          <th style="width:40px">Ict.</th>
          <th>Servicio</th>
          <th>Unidad</th>
          <th>Desde</th>
          <th>Hasta</th>
          <th style="width:60px">Vol. Aprox.</th>
          <th style="width:90px;text-align:right">Precio USD</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=1; foreach($detallesUSD as $d): ?>
        <tr class="usd-row">
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($d['concepto']) ?></td>
          <td>LCL/LCL</td>
          <td><?= htmlspecialchars($cot['origen']) ?></td>
          <td><?= htmlspecialchars($cot['destino']) ?></td>
          <td><?= (float)$d['cantidad']!=1 ? number_format((float)$d['cantidad'],2).' W/M' : '' ?></td>
          <td class="right">$<?= number_format((float)$d['costo_calculado'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="6">Subtotal USD</td>
          <td class="right">$<?= number_format(array_sum(array_column(array_values($detallesUSD),'costo_calculado')),2) ?></td>
        </tr>
      </tbody>
    </table>

    <!-- TABLA BS -->
    <div class="section-header" style="margin-top:1.25rem">
      <span class="badge-bs">Bs.</span>
      Servicios Locales Bolivia — Cobro en Bolivianos
    </div>
    <table class="cot-table">
      <thead>
        <tr>
          <th style="width:40px">Ict.</th>
          <th>Servicio</th>
          <th colspan="4">Descripción</th>
          <th style="width:100px;text-align:right">Precio Bs.</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=1; foreach($detallesBS as $d): ?>
        <tr class="bs-row">
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($d['concepto']) ?></td>
          <td colspan="4" style="color:#888;font-size:10.5px">Costo local Bolivia (TC: <?= TC_USD_BS ?>)</td>
          <td class="right">Bs. <?= number_format((float)$d['costo_calculado'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="6">Subtotal Bs.</td>
          <td class="right">Bs. <?= number_format($totalBS,2) ?></td>
        </tr>
      </tbody>
    </table>

    <!-- RECARGOS -->
    <?php if (!empty($recargos)): ?>
    <div class="section-header" style="margin-top:1.25rem;color:#b45309">
      ⚠️ Recargos Especiales
    </div>
    <table class="cot-table">
      <tbody>
        <?php foreach($recargos as $r): ?>
        <tr class="recargo-row">
          <td colspan="6"><?= htmlspecialchars($r['concepto']) ?></td>
          <td class="right"><?= $r['moneda']==='USD' ? '$'.number_format((float)$r['costo_calculado'],2) : 'Bs. '.number_format((float)$r['costo_calculado'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- TOTAL -->
    <div class="total-banner">
      <div>
        <div class="label">Forma de Pago: CONTRA ENTREGA DE DOCS</div>
        <div class="sub">Tiempo estimado de tránsito: Shanghai, CN – Iquique, Chile: 51 a 60 días</div>
      </div>
      <div style="text-align:right">
        <div class="label">TOTAL USD</div>
        <div class="amount">$<?= number_format($totalEquiv, 2) ?></div>
        <div class="sub">USD $<?= number_format($totalUSD,2) ?> + Bs. <?= number_format($totalBS,2) ?></div>
      </div>
    </div>

    <!-- CONDICIONES -->
    <div class="conditions">
      <h4>Condiciones / Notas Importantes</h4>
      <p><strong>*Notar que por disposición de Gerencia General, los ítems de FLETE MARÍTIMO, CARGOS ORIGEN, DESCONSOLIDACIÓN, COLLECT FEE se deben realizar en USD</strong> y el resto en BS, con el fin de poder cancelar los mismos en puerto como corresponde.</p>
      <p>*Cotización basada en peso y volumen iniciales informados por el cliente. En caso de variación se facturará según W/M final recibido en bodega, mismo que figurará en su BL.</p>
      <p>*Cotización aplica para carga GENERAL y APILABLE.</p>
      <p>*Cargas de Asia: Considerar posibles retrasos en obtener el booking por falta de disponibilidad de contenedores en origen, roleos o reprogramaciones en puertos de conexión, sin responsabilidad de GEMZ Bolivia.</p>
      <p>*Cargas con +12 pies lineales de largo: USD 12 por pie adicional.</p>
      <p>*Cargas con peso >5,000 lbs por bulto: OWS USD 170 Fijo + USD 300 por uso de forklift por cada manipuleo.</p>
      <p>*Carga menor o igual a 2,500 kg o 2.9 m³ es "mínimo" en Desconsolidación.</p>
      <p>*CARGAS IMO: No se aceptan Clase 1, 5, 6, 7 y Clase 3 con flash point inferior a -18°C. Consulta caso por caso con envío previo de MSDS.</p>
      <p>*Toda carga debe contar con marcas desde origen. Sin marcas: USD 300 de aclaración al manifiesto (TPA, ASPB y Aduana).</p>
      <p>*LEY 20001 Chile: cargas >25 kg deben contar con base o pallets. Sin paletizar, se cobra paletizaje según origen.</p>
      <p>*Los clientes deberán tener sus mercancías aseguradas. GEMZ no se hace cargo de siniestros, pérdidas o daños. El seguro no está incluido.</p>
      <p>*Carga con altura >2.40 m requiere contenedor High Cube, con recargos adicionales por espacio muerto.</p>
      <p>*En caso de transmisión errónea de Sidemar: multa USD 100. Fuera de plazo: USD 50.</p>
      <p>*Tarifa válida para cargas en tránsito a Bolivia. No incluye pago ASPB (por cuenta del consignatario).</p>
    </div>

    <!-- FIRMA -->
    <div class="firma">
      <div><?= htmlspecialchars($cot['vendedor_nombre']) ?></div>
      <strong>GRUPO LOGÍSTICO GEMZ BOLIVIA SRL</strong>
      <div class="cargo">Ejecutivo Comercial de Importaciones</div>
    </div>
  </div>

  <!-- PIE -->
  <div class="doc-footer">
    <div class="contact-item">📞 (591) 606 56019</div>
    <div class="contact-item">📱 631 47601</div>
    <div class="contact-item">✉ info@gemz.com.bo</div>
    <div class="contact-item">🌐 www.gemz.com.bo</div>
    <div class="contact-item">📍 Zona Central C/Mercado Nro. 1328 Edif. Mariscal, Ballivián Piso 4 Of. 402, La Paz - Bolivia</div>
  </div>
</div>
</body>
</html>
