<?php
declare(strict_types=1);

/**
 * /modules/empleado/recepcionista/imprimir.php?id=###   (id de Ordenes_Reparaciones)
 */

$ROOT = dirname(__DIR__, 3); // C:\xampp\htdocs\FIXTIME21

require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';
$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : (int)($_GET['id'] ?? 0);

Sesion::requiereLogin();

$app  = require $ROOT . '/config/app.php';
$base = rtrim($app['base_url'], '/');




// === ESTADO ACTUAL (vivo) PARA LA ORDEN A IMPRIMIR ===
$ordenId = (int)($_GET['orden_id'] ?? $_GET['or'] ?? 0);
$estadoMostrar = 'En proceso';

if ($ordenId > 0) {
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Detectar si la tabla OR tiene FK al turno
  $colTurno = null;
  try {
    $cols = $db->query("SHOW COLUMNS FROM ordenes_reparaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach (['Turno_id','turno_id','id_turno','Turnos_id','turnos_id'] as $c) {
      if (in_array($c, $cols, true)) { $colTurno = $c; break; }
    }
  } catch (Throwable $e) {}

  // Estado de la OR
  $sql = "SELECT orp.EstadoOrdenReparacion_id, eo.descripcion AS estado_or"
       . ($colTurno ? ", orp.`$colTurno` AS tid" : "")
       . " FROM ordenes_reparaciones orp
           LEFT JOIN Estados_Ordenes eo ON eo.id = orp.EstadoOrdenReparacion_id
          WHERE orp.id = ?";
  $st = $db->prepare($sql);
  $st->execute([$ordenId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  $estadoOR = strtolower(trim((string)($row['estado_or'] ?? '')));
  $estadoTurno = '';

  // Si hay FK al turno, traemos su estado actual
  if ($colTurno && (int)($row['tid'] ?? 0) > 0) {
    $st2 = $db->prepare("SELECT et.descripcion
                           FROM Turnos t
                           JOIN Estados_Turnos et ON et.id = t.Estado_Turno_id
                          WHERE t.id = ?");
    $st2->execute([(int)$row['tid']]);
    $estadoTurno = strtolower(trim((string)($st2->fetchColumn() ?: '')));
  }

  $finales = ['terminado','finalizado','finalizada','cerrado','cerrada','completado','completada'];
  $esFinal = in_array($estadoOR, $finales, true) || in_array($estadoTurno, $finales, true);

  $estadoMostrar = $esFinal ? 'Terminado' : ($row['estado_or'] ?: ($estadoTurno ?: 'En proceso'));
}


if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* -------- Entrada -------- */
$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : (int)($_GET['id'] ?? 0);
if ($ordenId <= 0) {
  http_response_code(400);
  echo 'Falta el parámetro de orden (id).';
  exit;
}


if ($ordenId <= 0) {
  http_response_code(400);
  echo "<p style='font-family:system-ui,Segoe UI,Arial'>Falta el parámetro de orden (<code>id</code>).</p>";
  exit;
}

/* -------- Consulta principal OR + vehículo + mecánico + estado -------- */
$db = Conexion::obtener();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);




$sql = "
SELECT 
  orr.id                          AS orden_id,
  orr.fecha_ingreso,
  orr.descripcion                 AS trabajo,
  orr.presupuesto_id,
  eo.descripcion                  AS estado_orden,

  e.id                            AS mecanico_id,
  pm.nombre                       AS mec_nombre,
  pm.apellido                     AS mec_apellido,

  a.id                            AS auto_id,
  a.color, a.anio, a.patente, a.descripcion_extra,
  mo.descripcion                  AS modelo,
  ma.descripcion                  AS marca
FROM Ordenes_Reparaciones orr
JOIN Empleados e              ON e.id = orr.Empleado_id
JOIN Personas pm              ON pm.id = e.Persona_id
JOIN Estados_Ordenes eo       ON eo.id = orr.EstadoOrdenReparacion_id
JOIN Automoviles a            ON a.id = orr.Automovil_id
JOIN Modelos_Automoviles mo   ON mo.id = a.Modelo_Automovil_id
JOIN Marcas_Automoviles ma    ON ma.id = mo.Marca_Automvil_id
WHERE orr.id = ?
LIMIT 1";
$st = $db->prepare($sql);
$st->execute([$ordenId]);
$or = $st->fetch(PDO::FETCH_ASSOC);

if (!$or) {
  http_response_code(404);
  echo "<p style='font-family:system-ui,Segoe UI,Arial'>No se encontró la Orden #".(int)$ordenId."</p>";
  exit;
}

/* -------- Titular (persona o empresa) + contactos -------- */
$cliente = [
  'tipo' => 'persona',   // o 'empresa'
  'nombre' => '',
  'doc' => '',
  'email' => '',
  'tel' => ''
];

// Intentar persona titular
$st = $db->prepare("
  SELECT p.id, p.nombre, p.apellido, p.dni
    FROM Vehiculos_Personas vp
    JOIN Personas p ON p.id = vp.Persona_id
   WHERE vp.automoviles_id = ?
   LIMIT 1
");
$st->execute([$or['auto_id']]);
if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
  $cliente['tipo']   = 'persona';
  $cliente['nombre'] = trim(($row['nombre'] ?? '').' '.($row['apellido'] ?? ''));
  $cliente['doc']    = 'DNI: '.(string)($row['dni'] ?? '');

  // contactos
  $email = $db->prepare("
    SELECT cp.valor 
      FROM Contacto_Persona cp 
      JOIN Tipos_Contactos t ON t.id = cp.Tipo_Contacto_id
     WHERE cp.Persona_id = ? AND UPPER(t.descripcion) = 'EMAIL'
     LIMIT 1
  ");
  $email->execute([$row['id']]);
  $cliente['email'] = (string)$email->fetchColumn();

  $tel = $db->prepare("
    SELECT cp.valor 
      FROM Contacto_Persona cp 
      JOIN Tipos_Contactos t ON t.id = cp.Tipo_Contacto_id
     WHERE cp.Persona_id = ? AND (UPPER(t.descripcion) IN ('TELEFONO','TEL','PHONE'))
     LIMIT 1
  ");
  $tel->execute([$row['id']]);
  $cliente['tel'] = (string)$tel->fetchColumn();
} else {
  // Empresa
  $st = $db->prepare("
    SELECT e.id, e.razon_social, e.CUIT
      FROM Vehiculos_Empresas ve
      JOIN Empresas e ON e.id = ve.Empresas_id
     WHERE ve.automoviles_id = ?
     LIMIT 1
  ");
  $st->execute([$or['auto_id']]);
  if ($emp = $st->fetch(PDO::FETCH_ASSOC)) {
    $cliente['tipo']   = 'empresa';
    $cliente['nombre'] = (string)$emp['razon_social'];
    $cliente['doc']    = 'CUIT: '.(string)$emp['CUIT'];

    $email = $db->prepare("
      SELECT ce.valor 
        FROM Contactos_Empresas ce 
        JOIN Tipos_Contactos t ON t.id = ce.Tipo_Contacto_id
       WHERE ce.Empresas_id = ? AND UPPER(t.descripcion) = 'EMAIL' LIMIT 1
    ");
    $email->execute([$emp['id']]);
    $cliente['email'] = (string)$email->fetchColumn();

    $tel = $db->prepare("
      SELECT ce.valor 
        FROM Contactos_Empresas ce 
        JOIN Tipos_Contactos t ON t.id = ce.Tipo_Contacto_id
       WHERE ce.Empresas_id = ? AND (UPPER(t.descripcion) IN ('TELEFONO','TEL','PHONE')) LIMIT 1
    ");
    $tel->execute([$emp['id']]);
    $cliente['tel'] = (string)$tel->fetchColumn();
  }
}

/* -------- Presupuesto asociado (si existe) --------
   En tu esquema, presupuesto -> Detalles_Presupuestos (1 detalle).
   Lo mostramos si está cargado. */
$presu = null;
if (!empty($or['presupuesto_id'])) {
  $st = $db->prepare("
    SELECT pr.id AS presupuesto_id, pr.descripcion AS titulo,
           dp.cantidad, dp.precio_mano_obra, 
           r.Marca AS rep_marca, r.Modelo AS rep_modelo, r.Codigo AS rep_codigo,
           r.Descripcion AS rep_desc, r.Precio AS rep_precio
      FROM presupuestos pr
      JOIN Detalles_Presupuestos dp ON dp.id = pr.Detalle_Presupuesto_id
      LEFT JOIN Repuestos r ON r.id = dp.Repuesto_id
     WHERE pr.id = ?
     LIMIT 1
  ");
  $st->execute([$or['presupuesto_id']]);
  $presu = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* -------- Totales presupuesto -------- */
$importeRepuestos = 0.0;
$importeManoObra  = 0.0;
if ($presu) {
  $cant = (int)($presu['cantidad'] ?? 0);
  $preR = (float)($presu['rep_precio'] ?? 0);
  $importeRepuestos = $cant * $preR;
  $importeManoObra  = (float)($presu['precio_mano_obra'] ?? 0);
}
$importeTotal = $importeRepuestos + $importeManoObra;

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Orden de Reparación #<?= (int)$or['orden_id'] ?> — Fixtime</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<style>
  :root{
    --ink:#0b1220; --muted:#5b6b85; --brand:#2563eb; --soft:#f5f7ff;
    --line:#e6ecf5;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;color:var(--ink)}
  .sheet{
    width:210mm; min-height:297mm; margin:0 auto; padding:18mm 16mm;
    background:#fff;
  }
  .header{
    display:flex; align-items:center; gap:16px; padding:14px 16px;
    border:1px solid var(--line); border-radius:14px;
    background: linear-gradient(135deg, #eff5ff, #ffffff);
  }
  .logo{
    width:64px; height:64px; border-radius:14px; display:grid; place-items:center;
    background: radial-gradient(40px 30px at 30% 20%, rgba(255,255,255,.6), transparent 40%),
                linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 8px 22px rgba(37,99,235,.25), inset 0 1px 0 rgba(255,255,255,.6);
    border:1px solid rgba(37,99,235,.35);
  }
  .logo img{width:40px;height:40px;object-fit:contain;filter:drop-shadow(0 0 8px rgba(255,255,255,.8))}
  .h-meta{margin-left:auto;text-align:right}
  .h-meta .big{font-size:22px;font-weight:900;letter-spacing:.3px}
  .h-meta .small{color:var(--muted);font-size:12px}

  .grid{display:grid;grid-template-columns:1fr 1fr; gap:12px; margin-top:14px}
  .card{
    border:1px solid var(--line); border-radius:12px; padding:12px 14px; background:#fff;
  }
  h3{margin:0 0 8px 0; font-size:14px; text-transform:uppercase; letter-spacing:.5px; color:#374151}
  .row{display:grid; grid-template-columns:140px 1fr; gap:8px; font-size:13px; padding:4px 0}
  .label{color:#6b7280; font-weight:700}
  .value{color:#111827}

  table{width:100%; border-collapse:collapse; font-size:13px}
  th, td{padding:10px 8px; border-bottom:1px solid var(--line); text-align:left}
  th{font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.4px}
  .totalbox{
    margin-top:10px; display:flex; justify-content:flex-end;
  }
  .totalbox table{width:auto; min-width:280px}
  .pill{
    display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:800;
    background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe;
  }
  .notes{font-size:12px;color:#374151;line-height:1.5;background:var(--soft);border:1px dashed var(--line);border-radius:12px;padding:10px 12px}
  .signs{display:grid;grid-template-columns:1fr 1fr; gap:24px; margin-top:30px}
  .sign{height:80px; display:flex; flex-direction:column; justify-content:flex-end}
  .sign .line{height:1px; background:#111827; margin-top:50px}
  .sign .who{font-size:12px; color:#6b7280; margin-top:6px; text-align:center}

  .toolbar{
    position:sticky; top:0; z-index:4; background:#ffffffcc; backdrop-filter:blur(6px);
    display:flex; gap:8px; justify-content:flex-end; padding:10px 0 12px;
    border-bottom:1px solid #eef2f7;
  }
  .btn{
    appearance:none; border:0; padding:10px 14px; border-radius:10px; cursor:pointer; font-weight:800;
    background:linear-gradient(135deg, #3b82f6, #2563eb); color:white; box-shadow:0 10px 20px rgba(37,99,235,.25)
  }
  .btn.ghost{
    background:#fff; color:#0b1220; border:1px solid #d8e0ee; box-shadow:none
  }

  @media print{
    .toolbar{display:none}
    body{background:#fff}
    .sheet{padding:0; margin:0; width:auto; min-height:auto}
    @page{ size:A4; margin:14mm }
  }
</style>
</head>
<body>

<div class="toolbar">
  <a class="btn ghost" href="<?= $base ?>/modules/empleado/recepcionista/?tab=agenda">⬅ Volver</a>
  <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
</div>

<div class="sheet">

  <!-- Encabezado -->
  <div class="header">
    <div class="logo"><img src="<?= $base ?>/publico/widoo.png" alt="Fixtime"></div>
    <div>
      <div style="font-weight:900; font-size:20px; letter-spacing:.3px;">Fixtime</div>
      <div style="color:#374151; font-size:12px">Taller Mecánico – Servicio integral</div>
      <div style="color:#6b7280; font-size:12px">Formosa, Formosa Argentina • fixtimear@gmail.com • +54 3704 </div>
    </div>
    <div class="h-meta">
      <div class="big">Orden de Reparación</div>
      <div class="small">N.º <b>#<?= (int)$or['orden_id'] ?></b></div>
      <div class="small">Fecha ingreso: <b><?= h($or['fecha_ingreso']) ?></b></div>
      <div class="small">Estado: <span class="pill"><?= h($estadoMostrar) ?></span></div>

    </div>
  </div>

  <!-- Datos principales -->
  <div class="grid">
    <div class="card">
      <h3>Vehículo</h3>
      <div class="row"><div class="label">Marca / Modelo</div><div class="value"><?= h($or['marca'].' '.$or['modelo']) ?></div></div>
      <div class="row"><div class="label">Año</div><div class="value"><?= h($or['anio']) ?></div></div>
      <div class="row"><div class="label">Patente</div><div class="value"><?= h($or['patente'] ?: '—') ?></div></div>
      <div class="row"><div class="label">Color</div><div class="value"><?= h($or['color'] ?: '—') ?></div></div>
      <?php if (!empty($or['descripcion_extra'])): ?>
      <div class="row"><div class="label">Observaciones</div><div class="value"><?= h($or['descripcion_extra']) ?></div></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3><?= $cliente['tipo']==='empresa' ? 'Empresa' : 'Cliente' ?></h3>
      <div class="row"><div class="label">Nombre</div><div class="value"><?= h($cliente['nombre'] ?: '—') ?></div></div>
      <div class="row"><div class="label"><?= $cliente['tipo']==='empresa' ? 'CUIT' : 'DNI' ?></div><div class="value"><?= h($cliente['doc'] ?: '—') ?></div></div>
      <div class="row"><div class="label">Email</div><div class="value"><?= h($cliente['email'] ?: '—') ?></div></div>
      <div class="row"><div class="label">Teléfono</div><div class="value"><?= h($cliente['tel'] ?: '—') ?></div></div>
    </div>
  </div>

  <!-- Trabajo / Mecánico -->
  <div class="grid" style="grid-template-columns:1.2fr .8fr">
    <div class="card">
      <h3>Trabajo solicitado / Descripción</h3>
      <div class="notes"><?= nl2br(h($or['trabajo'] ?: '—')) ?></div>
    </div>
    <div class="card">
      <h3>Mecánico asignado</h3>
      <div class="row"><div class="label">Nombre</div><div class="value"><?= h($or['mec_apellido'].' '.$or['mec_nombre']) ?></div></div>
      <div class="row"><div class="label">Legajo</div><div class="value">#<?= (int)$or['mecanico_id'] ?></div></div>
    </div>
  </div>

  <!-- Presupuesto (si hay) -->
  <?php if ($presu): ?>
  <div class="card" style="margin-top:12px">
    <h3>Presupuesto</h3>
    <div class="row"><div class="label">Descripción</div><div class="value"><?= h($presu['titulo'] ?: '—') ?></div></div>

    <table style="margin-top:8px">
      <thead>
        <tr>
          <th style="width:120px">Código</th>
          <th>Repuesto</th>
          <th style="width:100px">Cant.</th>
          <th style="width:120px">Precio unit.</th>
          <th style="width:120px">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= h($presu['rep_codigo'] ?: '—') ?></td>
          <td><?= h(trim(($presu['rep_marca']?'['.$presu['rep_marca'].'] ':'').($presu['rep_desc'] ?? ''))) ?></td>
          <td><?= (int)($presu['cantidad'] ?? 0) ?></td>
          <td>$ <?= number_format((float)($presu['rep_precio'] ?? 0), 2, ',', '.') ?></td>
          <td>$ <?= number_format($importeRepuestos, 2, ',', '.') ?></td>
        </tr>
      </tbody>
    </table>

    <div class="totalbox">
      <table>
        <tr>
          <th style="text-align:right">Repuestos</th>
          <td style="text-align:right">$ <?= number_format($importeRepuestos, 2, ',', '.') ?></td>
        </tr>
        <tr>
          <th style="text-align:right">Mano de obra</th>
          <td style="text-align:right">$ <?= number_format($importeManoObra, 2, ',', '.') ?></td>
        </tr>
        <tr>
          <th style="text-align:right">Total</th>
          <td style="text-align:right"><b>$ <?= number_format($importeTotal, 2, ',', '.') ?></b></td>
        </tr>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Checklist breve (opcional visual) -->
  <div class="grid" style="margin-top:12px">
    <div class="card">
      <h3>Checklist de recepción</h3>
      <div class="row"><div class="label">Nivel de combustible</div><div class="value">□ Bajo □ Medio □ Alto</div></div>
      <div class="row"><div class="label">Rueda auxilio / Gato</div><div class="value">□ Sí □ No</div></div>
      <div class="row"><div class="label">Rayones / Golpes</div><div class="value">□ Sí □ No</div></div>
      <div class="row"><div class="label">Observaciones</div><div class="value">______________________________</div></div>
    </div>
    <div class="card">
      <h3>Entrega</h3>
      <div class="row"><div class="label">Fecha estimada</div><div class="value">________________</div></div>
      <div class="row"><div class="label">Garantía del servicio</div><div class="value">30 días / 1000 km</div></div>
      <div class="row"><div class="label">Observaciones</div><div class="value">______________________________</div></div>
    </div>
  </div>

  <!-- Firmas -->
  <div class="signs">
    <div class="sign">
      <div class="line"></div>
      <div class="who"><?= $cliente['tipo']==='empresa' ? 'Representante Empresa' : 'Cliente' ?></div>
    </div>
    <div class="sign">
      <div class="line"></div>
      <div class="who">Mecánico</div>
    </div>
  </div>

  <div style="margin-top:14px; font-size:11px; color:#6b7280; text-align:center">
    © <?= date('Y') ?> Fixtime — Taller Mecánico. Este documento no es una factura.
  </div>
</div>

<script>
  // Si querés abrir el diálogo de impresión automáticamente, agregá "?print=1" en la URL
  (function(){
    const p = new URLSearchParams(location.search);
    if (p.get('print') === '1') setTimeout(()=>window.print(), 200);
  })();
</script>

</body>
</html>
