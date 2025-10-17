<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 3); // .../FIXTIME21

require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';
require_once $ROOT . '/clases/EmpleadoRepositorio.php';

Sesion::requiereLogin();

$app  = require $ROOT . '/config/app.php';
$base = rtrim($app['base_url'], '/');

// === Favicon embebido ===
$favicon_base64 = '';
$favicon_path = $ROOT . '/publico/widoo.png';
if (file_exists($favicon_path)) {
    $favicon_data = file_get_contents($favicon_path);
    $favicon_base64 = 'data:image/png;base64,' . base64_encode($favicon_data);
}

$uid = (int)($_SESSION['uid'] ?? 0);
$nom = (string)($_SESSION['nombre'] ?? '');
$ape = (string)($_SESSION['apellido'] ?? '');

$db = Conexion::obtener();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// === Resolver persona/empleado y gate por cargo ===
function fixtime_resolverIds(PDO $db, int $uid): array {
  // si uid es Persona.id
  $st=$db->prepare("SELECT id FROM Personas WHERE id=?");
  $st->execute([$uid]);
  $pid=(int)$st->fetchColumn();
  if($pid>0){
    $st=$db->prepare("SELECT id FROM Empleados WHERE Persona_id=? AND (fecha_baja IS NULL OR fecha_baja > CURDATE()) ORDER BY id DESC LIMIT 1");
    $st->execute([$pid]); $eid=(int)($st->fetchColumn() ?: 0);
    return ['persona_id'=>$pid,'empleado_id'=>$eid];
  }
  // si uid es Empleados.id
  $st=$db->prepare("SELECT Persona_id FROM Empleados WHERE id=?");
  $st->execute([$uid]); $pid=(int)($st->fetchColumn() ?: 0);
  return ['persona_id'=>$pid,'empleado_id'=>$uid];
}

$ids = fixtime_resolverIds($db, (int)($_SESSION['uid'] ?? 0));
$personaId  = (int)$ids['persona_id'];
$empleadoId = (int)$ids['empleado_id'];

$repoE = new EmpleadoRepositorio();
if (!$repoE->esEmpleado($personaId)) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}
$cargo = (string)($repoE->obtenerCargoUnicoActivo($personaId) ?? '');
$cargoNorm = mb_strtolower(trim($cargo), 'UTF-8');
if ($cargoNorm !== 'mecánico' && $cargoNorm !== 'mecanico') {
  $ruta = EmpleadoRepositorio::rutaPanelPorCargo($cargo) ?? '/modules/selector';
  header('Location: ' . $base . rtrim($ruta,'/') . '/');
  exit;
}

// === Perfil (mismo helper que venimos usando) ===
function fixtime_cargarPerfil(PDO $db, int $personaId, int $empleadoId): array {
  $out = [
    'persona_id'=>$personaId,'empleado_id'=>$empleadoId, 'nombre'=>'','apellido'=>'','dni'=>'','email'=>'','telefonos'=>[],
    'pais'=>'','provincia'=>'','localidad'=>'','barrio'=>'','calle'=>'','altura'=>'','piso'=>'','departamento'=>''
  ];
  if ($personaId<=0) return $out;
  $st=$db->prepare("SELECT * FROM Personas WHERE id=? LIMIT 1");
  $st->execute([$personaId]);
  $p=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  if($p){
    $out['nombre']   = (string)($p['nombre']   ?? $p['Nombre']   ?? '');
    $out['apellido'] = (string)($p['apellido'] ?? $p['Apellido'] ?? '');
    foreach($p as $k=>$v){
      $lk=strtolower($k);
      if($lk==='dni' || $lk==='documento' || $lk==='nro_documento' || $lk==='num_documento' || preg_match('/\bdni\b/',$lk)){
        $out['dni']=trim((string)$v); if($out['dni']!=='') break;
      }
    }
  }
  try{
    $st=$db->prepare("SELECT LOWER(tc.descripcion) AS tipo, cp.valor FROM Contacto_Persona cp JOIN Tipos_Contactos tc ON tc.id=cp.Tipo_Contacto_id WHERE cp.Persona_id=?");
    $st->execute([$personaId]);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $tipo=(string)($r['tipo']??''); $val=trim((string)($r['valor']??''));
      if($val==='') continue;
      if(strpos($tipo,'mail')!==false) $out['email']=$val;
      if(strpos($tipo,'tel')!==false || strpos($tipo,'phone')!==false) $out['telefonos'][]=$val;
    }
  }catch(Throwable $e){}
  // Domicilio por tablas relacionales si existen
  try{
    $sql="SELECT d.calle,d.altura,d.piso,d.departamento, b.descripcion AS barrio, l.descripcion AS localidad, pr.descripcion AS provincia, pa.descripcion AS pais
          FROM Personas_Domicilios pd
          JOIN Domicilios d ON d.id=pd.Domicilio_id
          LEFT JOIN Barrios b ON b.id=d.Barrio_id
          LEFT JOIN Localidades l ON l.id=b.Localidad_id
          LEFT JOIN Provincias pr ON pr.id=l.Provincia_id
          LEFT JOIN Paises pa ON pa.id=pr.Pais_id
          WHERE pd.Persona_id=? LIMIT 1";
    $st=$db->prepare($sql); $st->execute([$personaId]);
    if($d=$st->fetch(PDO::FETCH_ASSOC)){
      foreach(['pais','provincia','localidad','barrio','calle','altura','piso','departamento'] as $k)
        $out[$k]=(string)($d[$k]??'');
    }
  }catch(Throwable $e){}
  if($out['telefonos']) $out['telefonos']=array_values(array_unique(array_map('trim',$out['telefonos'])));
  return $out;
}
$perfil = fixtime_cargarPerfil($db, $personaId, $empleadoId);
$mecanicoId = (int)$empleadoId;

// === Estados de Turno + mapa para badges ===
$estadosTurnos = $db->query("SELECT id, descripcion FROM Estados_Turnos ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$ID_TURNO_TERMINADO = null;
$mapEstados = [];
foreach ($estadosTurnos as $e) {
  $desc = mb_strtolower(trim((string)$e['descripcion']),'UTF-8');
  $mapEstados[(int)$e['id']] = $desc;
  if ($desc === 'terminado') $ID_TURNO_TERMINADO = (int)$e['id'];
}
function badgeClassPorEstado(?string $desc): string {
  $d = mb_strtolower(trim((string)$desc),'UTF-8');
  switch ($d) {
    case 'terminado': return 'badge--done';
    case 'cancelado': return 'badge--canc';
    case 'pendiente': return 'badge--pend';
    case 'en proceso':
    case 'proceso':
    case 'asignado': return 'badge--prog';
    default: return 'badge--unk';
  }
}

// === ordenes_reparaciones: detectar columnas (compatibilidad) ===
$colsOR = $db->query("SHOW COLUMNS FROM ordenes_reparaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
$colTurnoFK = null; foreach (['Turnos_id','Turno_id','turno_id','id_turno'] as $c) if (in_array($c,$colsOR,true)) {$colTurnoFK=$c; break;}
$colMecFK   = null; foreach (['Mecanicos_id','Mecanico_id','mecanico_id','Empleado_id'] as $c) if (in_array($c,$colsOR,true)) {$colMecFK=$c; break;}
$pkOR = 'id';
try{ $pkRow = $db->query("SHOW KEYS FROM ordenes_reparaciones WHERE Key_name='PRIMARY'")->fetch(PDO::FETCH_ASSOC);
     if (!empty($pkRow['Column_name'])) $pkOR = $pkRow['Column_name'];
}catch(Throwable $e){}

// === Última OR por turno para reimpresión ===
$ultimaOrdenPorTurno = [];
if ($colTurnoFK) {
  $rows = $db->query("SELECT `$colTurnoFK` AS turno_id, MAX(`$pkOR`) AS orden_id FROM ordenes_reparaciones GROUP BY `$colTurnoFK`")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) {
    $tid = (int)($r['turno_id'] ?? 0); $oid = (int)($r['orden_id'] ?? 0);
    if ($tid>0 && $oid>0) $ultimaOrdenPorTurno[$tid] = $oid;
  }
}

// === Órdenes asignadas al mecánico ===
$ordenesAsignadas = [];
if ($colMecFK && $colTurnoFK) {
  $sql = "SELECT orp.`$pkOR` AS orden_id, orp.`$colTurnoFK` AS turno_id, t.fecha_turno, t.hora_turno, t.Estado_Turno_id,
                 t.motivo, t.descripcion, a.patente, ma.descripcion AS marca, mo.descripcion AS modelo, a.anio
          FROM ordenes_reparaciones orp
          LEFT JOIN Turnos t ON t.id = orp.`$colTurnoFK`
          LEFT JOIN Automoviles a ON a.id = t.Automovil_id
          LEFT JOIN Modelos_Automoviles mo ON mo.id=a.Modelo_Automovil_id
          LEFT JOIN Marcas_Automoviles ma ON ma.id=mo.Marca_Automvil_id
          WHERE orp.`$colMecFK` = :mid
          ORDER BY t.fecha_turno DESC, t.hora_turno DESC";
  $st = $db->prepare($sql); $st->execute(['mid'=>$mecanicoId]);
  $ordenesAsignadas = $st->fetchAll(PDO::FETCH_ASSOC);
}

// === Historial de vehículos (todos / míos) ===
// Detectar cómo se llama la FK al modelo en Automoviles
$colsAuto = $db->query("SHOW COLUMNS FROM Automoviles")->fetchAll(PDO::FETCH_COLUMN, 0);
$colModeloFk = null;
foreach (['Modelo_Automovil_id','Modelo_Automoviles_id','Modelo_id'] as $c) {
  if (in_array($c, $colsAuto, true)) { $colModeloFk = $c; break; }
}
if (!$colModeloFk) {
  // Fallback razonable (la más común); si tampoco existe, las queries fallarán y lo verás enseguida.
  $colModeloFk = 'Modelo_Automovil_id';
}

// Vehículos: todos
$vehiculosTodos = [];
try {
  $sqlTodos = "SELECT a.id,
                      ma.descripcion AS marca,
                      mo.descripcion AS modelo,
                      a.anio,
                      a.patente
               FROM Automoviles a
               JOIN Modelos_Automoviles mo ON mo.id = a.`$colModeloFk`
               JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
               ORDER BY ma.descripcion, mo.descripcion, a.anio DESC";
  $vehiculosTodos = $db->query($sqlTodos)->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $vehiculosTodos = [];
}

// Vehículos: reparados por mí (distintos)
$vehiculosReparados = [];
if ($colMecFK && $colTurnoFK) {
  try {
    $sqlMine = "SELECT DISTINCT a.id,
                              ma.descripcion AS marca,
                              mo.descripcion AS modelo,
                              a.anio,
                              a.patente
                FROM ordenes_reparaciones orp
                JOIN Turnos t ON t.id = orp.`$colTurnoFK`
                JOIN Automoviles a ON a.id = t.Automovil_id
                JOIN Modelos_Automoviles mo ON mo.id = a.`$colModeloFk`
                JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
                WHERE orp.`$colMecFK` = :mid
                ORDER BY ma.descripcion, mo.descripcion, a.anio DESC";
    $st = $db->prepare($sqlMine);
    $st->execute(['mid' => $mecanicoId]);
    $vehiculosReparados = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $vehiculosReparados = [];
  }
}
// === ÓRDENES: todas las órdenes (para el Historial -> "Todos") ===
$ordenesTodas = [];
if ($colTurnoFK) {
  try {
    $sqlAllOrders = "SELECT orp.`$pkOR` AS orden_id,
                            t.id AS turno_id,
                            t.fecha_turno, t.hora_turno, t.Estado_Turno_id,
                            t.motivo, t.descripcion,
                            a.patente, ma.descripcion AS marca, mo.descripcion AS modelo, a.anio
                     FROM ordenes_reparaciones orp
                     LEFT JOIN Turnos t ON t.id = orp.`$colTurnoFK`
                     LEFT JOIN Automoviles a ON a.id = t.Automovil_id
                     LEFT JOIN Modelos_Automoviles mo ON mo.id = a.`$colModeloFk`
                     LEFT JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
                     ORDER BY t.fecha_turno DESC, t.hora_turno DESC, orp.`$pkOR` DESC";
    $ordenesTodas = $db->query($sqlAllOrders)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $ordenesTodas = [];
  }
}

// === Flash ===
$flash = (string)($_GET['ok'] ?? '');
$err   = (string)($_GET['error'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fixtime — Panel de Mecánico</title>
<link rel="icon" type="image/png" href="<?= $favicon_base64 ?>">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{
  --bg:#0b1226; --panel:#0f1a33; --panel-2:#0b162b; --card:#0c1730;
  --muted:#9db0d0; --text:#e9f0ff; --brand:#3b82f6; --brand-2:#2563eb;
  --ring:rgba(59,130,246,.40); --shadow:0 12px 40px rgba(2,6,23,.45); --radius:18px;
}
*{box-sizing:border-box}html,body{height:100%;margin:0}body{min-height:100vh;background:radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.22), transparent 70%),radial-gradient(900px 480px at 10% 110%, rgba(37,99,235,.16), transparent 60%),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial}.app{display:grid;grid-template-columns:320px 1fr;min-height:100vh}.sidebar{padding:22px;background:linear-gradient(180deg,var(--panel),var(--bg));border-right:1px solid rgba(157,176,208,.15);position: sticky; top:0; height:100vh; z-index:40}.brand{display:flex;gap:12px;align-items:center;margin-bottom:22px}.brand-badge{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(40px 30px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2));box-shadow:0 12px 30px var(--ring), inset 0 1px 0 rgba(255,255,255,.25)}.brand-badge img{width:32px;height:32px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}.brand-name{font-weight:800;letter-spacing:.35px;font-size:22px}.brand-sub{opacity:.8;font-size:12px}.theme-btn{margin-left:auto;appearance:none;border:1px solid rgba(157,176,208,.28);background:rgba(255,255,255,.06);color:var(--text);border-radius:12px;padding:10px 12px;cursor:pointer;font-size:16px;box-shadow:0 6px 16px rgba(0,0,0,.2)}.nav{display:flex;flex-direction:column;gap:12px;margin-top:10px}.nav button, .nav a{display:flex;gap:12px;align-items:center;justify-content:flex-start;padding:14px 16px;border-radius:14px;border:1px solid rgba(157,176,208,.18);background:rgba(255,255,255,.03);color:var(--text);cursor:pointer;font-size:16px;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.25); text-decoration:none}.nav .active{background:linear-gradient(135deg, rgba(59,130,246,.20), rgba(37,99,235,.20));border-color:rgba(59,130,246,.55);box-shadow:0 10px 28px var(--ring)}.topbar-salir{display:block;margin-top:14px;text-align:center;text-decoration:none}.main{padding:26px 32px;display:flex;flex-direction:column;min-height:100vh}.hero{display:flex;align-items:center;gap:16px;background:linear-gradient(135deg, rgba(59,130,246,.22), rgba(37,99,235,.18));border:1px solid rgba(59,130,246,.40);border-radius:var(--radius);padding:18px;box-shadow:0 14px 32px var(--ring);margin-bottom:16px}.hero .avatar{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(24px 16px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2))}.hero .avatar img{width:38px;height:38px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}.hero .greet{font-weight:800;font-size:22px}.card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.03));border:1px solid rgba(157,176,208,.16);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;font-size:14px;table-layout:fixed}.table th,.table td{padding:12px 10px;border-bottom:1px solid rgba(157,176,208,.14);word-break:break-word;background:transparent}.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800;border:1px solid rgba(157,176,208,.22)}
/* Badges por estado */
.badge--done{ background:rgba(34,197,94,.14); border-color:rgba(34,197,94,.45); color:#86efac; }
.badge--prog{ background:rgba(59,130,246,.14); border-color:rgba(59,130,246,.45); color:#bfdbfe; }
.badge--pend{ background:rgba(234,179,8,.14);  border-color:rgba(234,179,8,.45);  color:#fde68a; }
.badge--canc{ background:rgba(239,68,68,.14);  border-color:rgba(239,68,68,.45);  color:#fecaca; }
.badge--unk { background:rgba(148,163,184,.14);border-color:rgba(148,163,184,.35); color:#cbd5e1; }

.btn{cursor:pointer;border:0;border-radius:12px;padding:12px 16px;background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#0b1220;font-weight:800;box-shadow:0 12px 28px var(--ring)}.btn.ghost{background:transparent;color:var(--text);border:1px solid rgba(157,176,208,.30);box-shadow:none}.small{font-size:12px;opacity:.85}.header-mobile{display:none;align-items:center;gap:10px;position:sticky;top:0;z-index:1100;padding:12px 16px;background:rgba(12,23,48,.75);backdrop-filter:blur(8px);border-bottom:1px solid rgba(255,255,255,.06)}.burger{appearance:none;background:transparent;border:0;color:var(--text);font-size:24px;cursor:pointer}.sidebar__overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000}.sidebar__overlay.show{display:block}@media (max-width:1080px){.app{grid-template-columns:1fr}.sidebar{position:fixed; inset:0 auto 0 0; width:84%; max-width:320px; height:100vh; transform:translateX(-105%); transition:transform .22s ease; z-index:1001; box-shadow:var(--shadow)}.sidebar.open{transform:translateX(0)}.header-mobile{display:flex}}.theme-light{--bg:#f3f6fc;--panel:#ffffff;--panel-2:#f7f9ff;--card:#ffffff;--muted:#5b6b85;--text:#0b1220;--ring:rgba(59,130,246,.28);--shadow:0 8px 26px rgba(15,23,42,.08)}.theme-light body{background:radial-gradient(1000px 500px at 80% -10%, rgba(59,130,246,.12), transparent 70%),radial-gradient(700px 380px at 10% 110%, rgba(37,99,235,.10), transparent 60%),var(--bg)}.theme-light .sidebar{background:linear-gradient(180deg,var(--panel),#eaf0ff);border-right:1px solid rgba(15,23,42,.06)}.theme-light .nav button, .theme-light .nav a{background:#fff;border-color:rgba(15,23,42,.08);color:#0b1220}.theme-light .card{background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:var(--shadow)}.theme-light .table th,.theme-light .table td{border-bottom:1px solid rgba(15,23,42,.08)}.theme-light .theme-btn{background:#fff;border-color:rgba(15,23,42,.08);color:#0b1220}.imprimir-modal.swal2-popup { background: transparent !important; border: none !important; box-shadow: none !important; }
.sidebar{display:flex;flex-direction:column;min-height:100%;}
.sidebar .nav{display:grid;gap:.75rem;}
.sidebar .sidebar-footer{margin-top:auto;display:grid;gap:1rem;padding-top:1rem;}
.sidebar .sidebar-footer .btn.ghost{justify-content:center;background:transparent;color:var(--text);border:1px solid rgba(157,176,208,.30);box-shadow:none;transition:none;font-weight:700;letter-spacing:.2px;}
.sidebar .sidebar-footer .btn.ghost:hover{background:transparent;color:var(--text);border-color:rgba(157,176,208,.30);}
</style>
</head>
<body>

<div class="header-mobile">
  <button class="burger" id="btnMenu" aria-label="Abrir menú">☰</button>
  <div style="display:flex;align-items:center;gap:10px">
    <div class="brand-badge" style="width:36px;height:36px;border-radius:10px"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
    <strong>Fixtime</strong>
  </div>
  <button id="themeToggleMobile" class="theme-btn" title="Cambiar tema">🌙</button>
</div>

<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-badge"><img src="<?= $base ?>/publico/widoo.png" alt="Fixtime"></div>
      <div style="flex:1">
        <div class="brand-name">Fixtime</div>
        <div class="brand-sub">Panel de Mecánico</div>
      </div>
      <button id="themeToggle" class="theme-btn" title="Cambiar tema">🌙</button>
    </div>

    <nav class="nav" id="nav">
      <button class="active" data-tab="home">🏠 Inicio</button>
      <button data-tab="ordenes">🧰 Órdenes asignadas</button>
      <button data-tab="historial">🚗 Historial de vehículos</button>
      <button data-tab="perfil">👤 Mi perfil</button>

      <div class="sidebar-footer">
        <a href="<?= $base ?>/modules/selector/" class="btn ghost topbar-salir">⬅️ Volver al selector</a>
        <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">Cerrar sesión</a>
      </div>
    </nav>
  </aside>
  <div class="sidebar__overlay" id="overlay"></div>

  <main class="main">
    <div class="hero">
      <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
      <div style="flex:1">
        <div class="greet">¡Hola, <?= h($nom.' '.$ape) ?>!</div>
        <div class="hint">Revisá tus órdenes, registrá observaciones y cerrá trabajos.</div>
      </div>
    </div>

    <!-- ====== INICIO ====== -->
    <section id="tab-home" style="display:block">
      <?php
        $hoy = (new DateTime())->format('Y-m-d');
        $pendientes = 0; $totales = count($ordenesAsignadas); $hoyCnt = 0;
        foreach ($ordenesAsignadas as $o) {
          $f = (string)($o['fecha_turno'] ?? '');
          if ($f === $hoy) $hoyCnt++;
          $estadoId = (int)($o['Estado_Turno_id'] ?? 0);
          if ($ID_TURNO_TERMINADO !== null && $estadoId !== $ID_TURNO_TERMINADO) $pendientes++;
        }
      ?>
      <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:20px">
        <div class="card"><div style="font-size:13px;color:#9db0d0">Órdenes hoy</div><div style="font-size:24px;font-weight:800"><?= $hoyCnt ?></div><div class="hint">Asignadas con fecha de hoy.</div></div>
        <div class="card"><div style="font-size:13px;color:#9db0d0">Pendientes</div><div style="font-size:24px;font-weight:800"><?= $pendientes ?></div><div class="hint">Aún no terminadas.</div></div>
        <div class="card"><div style="font-size:13px;color:#9db0d0">Total asignadas</div><div style="font-size:24px;font-weight:800"><?= $totales ?></div><div class="hint">Histórico de tus órdenes.</div></div>
      </div>

      <section class="card" style="margin-top:4px">
        <h3 style="margin:0 0 10px">Órdenes recientes</h3>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th># OR</th><th>Fecha</th><th>Hora</th><th>Vehículo</th><th>Patente</th><th>Motivo</th><th>Acción</th></tr></thead>
            <tbody>
            <?php if (!$ordenesAsignadas): ?>
              <tr><td colspan="7">No tenés órdenes asignadas.</td></tr>
            <?php else:
              foreach ($ordenesAsignadas as $o):
                $veh = trim(($o['marca'] ?? '').' '.($o['modelo'] ?? '').' '.($o['anio'] ?? ''));
                $oid = (int)$o['orden_id'];
                $tid = (int)$o['turno_id'];
            ?>
              <tr>
                <td>OR-<?= $oid ?></td>
                <td><?= h((string)$o['fecha_turno'] ?? '') ?></td>
                <td><?= h(substr((string)($o['hora_turno'] ?? ''),0,5)) ?></td>
                <td><?= $veh !== '' ? h($veh) : '—' ?></td>
                <td><?= h((string)($o['patente'] ?? '')) ?></td>
                <td><?= h((string)($o['motivo'] ?? '')) ?></td>
                <td style="display:flex;gap:8px;flex-wrap:wrap">
                  <button class="btn ghost btn-ver" data-oid="<?= $oid ?>" data-tid="<?= $tid ?>">Ver / Editar</button>
                  <?php if (!empty($ultimaOrdenPorTurno[$tid])): ?>
                    <a class="btn ghost btn-reprint" href="<?= $base ?>/modules/empleado/mecanico/imprimir.php?orden_id=<?= (int)$ultimaOrdenPorTurno[$tid] ?>">Reimprimir</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <!-- ====== ÓRDENES ASIGNADAS ====== -->
    <section id="tab-ordenes" style="display:none">
      <div class="card">
        <h3 style="margin:0 0 10px">Órdenes asignadas</h3>
        <div class="table-wrap">
          <table class="table" id="ord_table">
            <thead><tr><th># OR</th><th>Fecha</th><th>Hora</th><th>Vehículo</th><th>Patente</th><th>Motivo</th><th>Estado</th><th style="width:360px">Acciones</th></tr></thead>
            <tbody>
              <?php if (!$ordenesAsignadas): ?>
                <tr><td colspan="8">No hay órdenes asignadas.</td></tr>
              <?php else: foreach ($ordenesAsignadas as $o):
              $veh = trim(($o['marca'] ?? '').' '.($o['modelo'] ?? '').' '.($o['anio'] ?? ''));
              $oid = (int)$o['orden_id'];
              $tid = (int)$o['turno_id'];
              $estadoId   = (int)($o['Estado_Turno_id'] ?? 0);
              $estadoDesc = $mapEstados[$estadoId] ?? (($ID_TURNO_TERMINADO !== null && $estadoId === $ID_TURNO_TERMINADO) ? 'terminado' : 'en proceso');
              $badgeClass = badgeClassPorEstado($estadoDesc);
              $estadoTitle = ucwords($estadoDesc);
              $estaTerminado = ($ID_TURNO_TERMINADO !== null && $estadoId === $ID_TURNO_TERMINADO);

              // 🟡 OMITIR ÓRDENES TERMINADAS
              if ($estaTerminado || strtolower($estadoDesc) === 'terminado') {
                continue; // salta esta orden, no se muestra
              }
            ?>
            <tr>
              <td>OR-<?= $oid ?></td>
              <td><?= h((string)$o['fecha_turno'] ?? '') ?></td>
              <td><?= h(substr((string)($o['hora_turno'] ?? ''),0,5)) ?></td>
              <td><?= $veh !== '' ? h($veh) : '—' ?></td>
              <td><?= h((string)($o['patente'] ?? '')) ?></td>
              <td><?= h((string)($o['motivo'] ?? '')) ?></td>
              <td><span class="badge <?= $badgeClass ?> js-estado-badge"><?= h($estadoTitle) ?></span></td>
              <td style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn ghost btn-ver" data-oid="<?= $oid ?>" data-tid="<?= $tid ?>">Ver / Editar</button>
                <?php if (!$estaTerminado): ?>
                  <form method="post"
                        action="<?= $base ?>/modules/empleado/mecanico/orden_estado.php"
                        class="validate-form form-terminar" style="margin:0">
                    <input type="hidden" name="orden_id" value="<?= $oid ?>">
                    <input type="hidden" name="turno_id" value="<?= $tid ?>">
                    <input type="hidden" name="estado" value="terminado">
                    <button class="btn ghost js-btn-terminar">Marcar Terminado</button>
                  </form>
                <?php endif; ?>
                <?php if (!empty($ultimaOrdenPorTurno[$tid])): ?>
                  <a class="btn ghost btn-reprint" href="<?= $base ?>/modules/empleado/mecanico/imprimir.php?orden_id=<?= (int)$ultimaOrdenPorTurno[$tid] ?>">Reimprimir</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>

            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ====== HISTORIAL VEHÍCULOS ====== -->
    <!-- ====== HISTORIAL ====== -->
<section id="tab-historial" style="display:none">
  <div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 10px">Historial</h3>
    <div class="small" style="margin-bottom:8px">Elegí si ver <b>todas las órdenes</b> o <b>vehículos que reparaste</b>.</div>
    <div style="display:flex;gap:10px;margin-bottom:10px">
      <button class="btn" id="btnAllOrders">Todos (órdenes)</button>
      <button class="btn ghost" id="btnMineVehicles">Reparados por mí (vehículos)</button>
    </div>
    <div class="table-wrap">
      <table class="table" id="hist_table">
        <thead id="hist_thead"></thead>
        <tbody id="hist_tbody"></tbody>
      </table>
    </div>
  </div>
</section>


    <!-- ====== PERFIL ====== -->
    <section id="tab-perfil" style="display:none">
      <div class="card" style="margin-bottom:12px">
        <h3 style="margin:0 0 10px">Mis datos</h3>
        <form method="post" action="<?= $base ?>/modules/empleado/mecanico/perfil_guardar.php" class="validate-form" style="display:grid;grid-template-columns:repeat(12,1fr);gap:12px">
          <input type="hidden" name="empleado_id" value="<?= (int)$perfil['empleado_id'] ?>"><input type="hidden" name="persona_id"  value="<?= (int)$perfil['persona_id'] ?>">
          <div style="grid-column:span 6"><label class="small">Nombre</label><input name="nombre" required value="<?= h($perfil['nombre']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 6"><label class="small">Apellido</label><input name="apellido" required value="<?= h($perfil['apellido']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 6"><label class="small">DNI</label><input name="dni" value="<?= h($perfil['dni']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 6"><label class="small">Email</label><input type="email" name="email" value="<?= h($perfil['email']) ?>" placeholder="tucorreo@dominio.com" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 12">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><label class="small" style="margin:0">Teléfonos</label><button type="button" id="btnAddTel" class="btn ghost">+ Agregar teléfono</button></div>
            <div id="tel_list" style="display:flex;flex-direction:column;gap:8px">
              <?php $tels = $perfil['telefonos']; if(empty($tels)) $tels = ['']; foreach ($tels as $t): ?>
              <div class="tel-item" style="display:flex;gap:8px;align-items:center">
                <input name="telefonos[]" value="<?= h($t) ?>" placeholder="+54 9 11 2345 6789" style="flex:1;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"><button type="button" class="btn ghost btn-del-tel">Quitar</button>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div style="grid-column:span 6"><label class="small">País</label><input name="pais" value="<?= h($perfil['pais']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 6"><label class="small">Provincia</label><input name="provincia" value="<?= h($perfil['provincia']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 6"><label class="small">Localidad</label><input name="localidad" value="<?= h($perfil['localidad']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 6"><label class="small">Barrio</label><input name="barrio" value="<?= h($perfil['barrio']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 6"><label class="small">Calle</label><input name="calle" value="<?= h($perfil['calle']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 2"><label class="small">Altura</label><input name="altura" value="<?= h($perfil['altura']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 2"><label class="small">Piso</label><input name="piso" value="<?= h($perfil['piso']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 2"><label class="small">Dto.</label><input name="departamento" value="<?= h($perfil['departamento']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
          <div style="grid-column:span 12;display:flex;gap:10px;justify-content:flex-end"><button class="btn">Guardar cambios</button></div>
        </form>
      </div>
    </section>

    <footer class="card" style="margin-top:auto;text-align:center;font-size:13px;color:var(--muted)">
      © <?= date('Y') ?> Fixtime — Todos los derechos reservados
      <div style="margin-top:8px;display:flex;justify-content:center;gap:14px">
        <a href="mailto:contacto@fixtime.com" title="Email">📧</a> <a href="https://wa.me/5491123456789" target="_blank" title="WhatsApp">💬</a> <a href="https://facebook.com/fixtime" target="_blank" title="Facebook">📘</a> <a href="https://instagram.com/fixtime" target="_blank" title="Instagram">📷</a>
      </div>
    </footer>
  </main>
</div>

<!-- ====== MODAL VER/EDITAR ORDEN + OBSERVACIONES ====== -->
<dialog id="ordenModal">
  <form method="post" action="<?= $base ?>/modules/empleado/mecanico/orden_guardar.php" style="background:var(--panel);padding:18px;border-radius:18px;min-width:860px">
    <input type="hidden" name="orden_id" id="o_id">
    <input type="hidden" name="turno_id" id="o_tid">

    <div style="grid-column:span 12; margin-bottom:6px">
  <span class="small">Estado:</span>
  <span id="o_estado_badge" class="badge badge--unk" style="margin-left:6px">—</span>
</div>

    <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:12px">
      <div style="grid-column:span 6"><label class="small">Motivo</label><input name="motivo" id="o_motivo" maxlength="100" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
      <div style="grid-column:span 6"><label class="small">Descripción</label><input name="descripcion" id="o_desc" maxlength="500" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>

      <div style="grid-column:span 3"><label class="small">Km. actual</label><input name="km" id="o_km" type="number" min="0" step="1" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
      <div style="grid-column:span 3"><label class="small">Tiempo estimado (hs)</label><input name="tiempo_estimado" id="o_tiempo" type="number" min="0" step="0.5" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
      <div style="grid-column:span 3"><label class="small">Costo estimado</label><input name="costo_estimado" id="o_costo" type="number" min="0" step="0.01" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px"></div>
      <div style="grid-column:span 3"><label class="small">Prioridad</label>
        <select name="prioridad" id="o_prioridad" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          <option value="">—</option><option>Alta</option><option>Media</option><option>Baja</option>
        </select>
      </div>

      <div style="grid-column:span 12">
        <label class="small">Nueva observación</label>
        <textarea name="observacion" id="o_obs" rows="4" placeholder="Anotá síntomas, pruebas realizadas, piezas revisadas, etc." style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px;resize:vertical"></textarea>
        <div class="small" style="opacity:.7;margin-top:6px">Al guardar, la observación se adjuntará a la OR.</div>
      </div>

      <div style="grid-column:span 12">
        <label class="small">Historial de observaciones</label>
        <div id="obs_list" style="display:flex;flex-direction:column;gap:8px;max-height:240px;overflow:auto;border:1px solid rgba(157,176,208,.2);border-radius:12px;padding:10px;background:var(--panel-2)">
          <div class="small" style="opacity:.7">Cargando…</div>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn ghost" id="o_close">Cancelar</button>
      <button class="btn">Guardar cambios</button>
      <button type="button" class="btn ghost" id="o_terminar">Marcar Terminado</button>
    </div>
  </form>
</dialog>

<script>
// ====== Navegación / sidebar / theme ======
const nav=document.getElementById('nav');
const sections={home:document.getElementById('tab-home'), ordenes:document.getElementById('tab-ordenes'), historial:document.getElementById('tab-historial'), perfil:document.getElementById('tab-perfil')};
nav.addEventListener('click',e=>{ const b=e.target.closest('button'); if(!b) return; [...nav.querySelectorAll('button')].forEach(x=>x.classList.remove('active')); b.classList.add('active'); const tab=b.dataset.tab; Object.values(sections).forEach(s=>s&&(s.style.display='none')); sections[tab]&&(sections[tab].style.display='block'); window.scrollTo({top:0,behavior:'smooth'}); });
const sidebar=document.getElementById('sidebar'), overlay=document.getElementById('overlay'), btnMenu=document.getElementById('btnMenu');
function openSidebar(){sidebar?.classList.add('open');overlay?.classList.add('show'); if(btnMenu) btnMenu.setAttribute('aria-expanded','true');}
function closeSidebar(){sidebar?.classList.remove('open');overlay?.classList.remove('show'); if(btnMenu) btnMenu.setAttribute('aria-expanded','false');}
btnMenu?.addEventListener('click',()=>{ (sidebar?.classList.contains('open')?closeSidebar:openSidebar)(); });
overlay?.addEventListener('click',closeSidebar);window.addEventListener('keydown',(e)=>{ if(e.key==='Escape') closeSidebar(); });
nav.addEventListener('click',()=>{ if (window.matchMedia('(max-width:1080px)').matches) closeSidebar(); });
const root=document.documentElement, btnTheme=document.getElementById('themeToggle'), btnThemeMobile=document.getElementById('themeToggleMobile');
function setIcon(i){ if(btnTheme)btnTheme.textContent=i; if(btnThemeMobile)btnThemeMobile.textContent=i; }
function applyTheme(t){ if(t==='light'){ root.classList.add('theme-light'); setIcon('☀️'); } else{ root.classList.remove('theme-light'); setIcon('🌙'); } localStorage.setItem('fixtime_theme',t); }
(function initTheme(){ const saved = localStorage.getItem('fixtime_theme') || 'dark'; applyTheme(saved); })();
function toggleTheme(){ const isLight=root.classList.contains('theme-light'); applyTheme(isLight?'dark':'light'); }
btnTheme?.addEventListener('click',toggleTheme); btnThemeMobile?.addEventListener('click',toggleTheme);

// ====== Toast / Flash ======
const toast = (title, icon='success') => Swal.fire({toast:true, position:'top-end', icon, title, showConfirmButton:false, timer:3200, timerProgressBar:true});
(function showFlashFromQuery(){ const usp = new URLSearchParams(location.search); const ok=usp.get('ok'); const err=usp.get('error'); if(ok) toast(ok,'success'); if(err) toast(err,'error'); if(ok||err){ usp.delete('ok'); usp.delete('error'); const newUrl=location.pathname+(usp.toString()?('?'+usp.toString()):''); history.replaceState({},'',newUrl); } })();

// ====== Validación simple ======
document.querySelectorAll('.validate-form').forEach(f=>{ f.addEventListener('submit',e=>{ const req=f.querySelectorAll('input[required], select[required], textarea[required]'); for(const inp of req){ if(!String(inp.value||'').trim()){ e.preventDefault(); Swal.fire({icon:'error',title:'Faltan datos',text:'Completá los campos obligatorios.'}); inp.focus(); return; } } }); });

// ====== Historial: toggle Todos / Míos ======

// ====== Historial: Orders (all) vs Vehicles (mine) ======
(function(){
  const btnAll = document.getElementById('btnAllOrders');
  const btnMine= document.getElementById('btnMineVehicles');
  btnAll?.addEventListener('click', ()=>{ setMode('all'); });
  btnMine?.addEventListener('click',()=>{ setMode('mine'); });

  function setActive(which){
    if(!btnAll || !btnMine) return;
    if(which==='all'){
      btnAll.classList.remove('ghost'); btnMine.classList.add('ghost');
    }else{
      btnMine.classList.remove('ghost'); btnAll.classList.add('ghost');
    }
  }

  const thead = document.getElementById('hist_thead');
  const tbody = document.getElementById('hist_tbody');

  // HTML server-side pre-render (seguro y rápido)
  const HTML_HEAD_ALL = <?php
    $headAll = '<tr><th># OR</th><th>Fecha</th><th>Hora</th><th>Vehículo</th><th>Patente</th><th>Motivo</th><th>Estado</th></tr>';
    echo json_encode($headAll, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?>;
  const HTML_HEAD_MINE = <?php
    $headMine = '<tr><th>#</th><th>Marca</th><th>Modelo</th><th>Año</th><th>Patente</th></tr>';
    echo json_encode($headMine, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?>;

  const HTML_BODY_ALL = <?php
    ob_start();
    if (!$ordenesTodas) {
      echo '<tr><td colspan="7">Sin órdenes.</td></tr>';
    } else {
      foreach ($ordenesTodas as $o) {
        $veh = trim(($o['marca'] ?? '').' '.($o['modelo'] ?? '').' '.($o['anio'] ?? ''));
        $oid = (int)($o['orden_id'] ?? 0);
        $estadoId   = (int)($o['Estado_Turno_id'] ?? 0);
        $estadoDesc = $mapEstados[$estadoId] ?? (($ID_TURNO_TERMINADO !== null && $estadoId === $ID_TURNO_TERMINADO) ? 'terminado' : 'en proceso');
        $badgeClass = badgeClassPorEstado($estadoDesc);
        $estadoTitle = ucwords($estadoDesc);
        echo '<tr>';
        echo '<td>OR-'.h((string)$oid).'</td>';
        echo '<td>'.h((string)($o['fecha_turno'] ?? '')).'</td>';
        echo '<td>'.h(substr((string)($o['hora_turno'] ?? ''),0,5)).'</td>';
        echo '<td>'.($veh!=='' ? h($veh) : '—').'</td>';
        echo '<td>'.h((string)($o['patente'] ?? '')).'</td>';
        echo '<td>'.h((string)($o['motivo'] ?? '')).'</td>';
        echo '<td><span class="badge '.$badgeClass.'">'.h($estadoTitle).'</span></td>';
        echo '</tr>';
      }
    }
    $htmlAll = ob_get_clean();
    echo json_encode($htmlAll, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?>;

  const HTML_BODY_MINE = <?php
    ob_start();
    if (!$vehiculosReparados) {
      echo '<tr><td colspan="5">Sin vehículos reparados.</td></tr>';
    } else {
      $i=1;
      foreach ($vehiculosReparados as $v) {
        echo '<tr>';
        echo '<td>'.($i++).'</td>';
        echo '<td>'.h((string)$v['marca']).'</td>';
        echo '<td>'.h((string)$v['modelo']).'</td>';
        echo '<td>'.h((string)$v['anio']).'</td>';
        echo '<td>'.h((string)$v['patente']).'</td>';
        echo '</tr>';
      }
    }
    $htmlMine = ob_get_clean();
    echo json_encode($htmlMine, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?>;

  function render(mode){
    if(mode==='all'){
      thead.innerHTML = HTML_HEAD_ALL;
      tbody.innerHTML = HTML_BODY_ALL;
    }else{
      thead.innerHTML = HTML_HEAD_MINE;
      tbody.innerHTML = HTML_BODY_MINE;
    }
    setActive(mode);
    // set querystring to persist current view
    const usp = new URLSearchParams(location.search);
    usp.set('tab','historial'); usp.set('vh',mode);
    history.replaceState({},'', location.pathname + '?' + usp.toString());
  }

  function setMode(mode){ render(mode); }

  // Init from URL or default
  const usp = new URLSearchParams(location.search);
  const modeInit = (usp.get('vh') === 'all') ? 'all' : 'mine';
  render(modeInit);
})();

function badgeClassFrom(desc){
  const d = String(desc||'').toLowerCase().trim();
  if (d==='terminado') return 'badge--done';
  if (d==='cancelado') return 'badge--canc';
  if (d==='pendiente') return 'badge--pend';
  if (d==='en proceso' || d==='proceso' || d==='asignado') return 'badge--prog';
  return 'badge--unk';
}



// ====== Modal OR: ver/editar, observaciones, terminar ======
const modal=document.getElementById('ordenModal'), oClose=document.getElementById('o_close');
function openModal(){ if(typeof modal.showModal==='function') modal.showModal(); else modal.setAttribute('open',''); }
function closeModal(){ if(typeof modal.close==='function') modal.close(); else modal.removeAttribute('open'); }
oClose?.addEventListener('click',closeModal); modal?.addEventListener('cancel',e=>{e.preventDefault();closeModal();});

function loadOrden(oid, tid){
  Swal.fire({title:'Cargando orden...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
  fetch(`<?= $base ?>/modules/empleado/mecanico/orden_detalle.php?orden_id=${encodeURIComponent(oid)}`, { headers:{'Accept':'application/json'} })
   .then(r=>{ if(!r.ok) throw new Error('No se pudo cargar la orden'); return r.json(); })
   .then(j=>{
    // Estado en el modal
const badge = document.getElementById('o_estado_badge');
const estadoTxt = (j.estado || '').trim() || '—';
const clase = badgeClassFrom(estadoTxt);
badge.textContent = estadoTxt.charAt(0).toUpperCase() + estadoTxt.slice(1);
badge.className = 'badge ' + clase;

// Si ya está terminado, oculto el botón del modal
const btnTerm = document.getElementById('o_terminar');
if (estadoTxt.toLowerCase()==='terminado') {
  btnTerm?.setAttribute('disabled','disabled');
  btnTerm?.classList.add('ghost');
  btnTerm?.style.setProperty('display','none');
} else {
  btnTerm?.removeAttribute('disabled');
  btnTerm?.classList.remove('ghost');
  btnTerm?.style.removeProperty('display');
}

      Swal.close();
      o_id.value = oid; o_tid.value = tid;
      o_motivo.value = j.motivo || '';
      o_desc.value   = j.descripcion || '';
      o_km.value     = j.km ?? '';
      o_tiempo.value = j.tiempo_estimado ?? '';
      o_costo.value  = j.costo_estimado ?? '';
      o_prioridad.value = j.prioridad || '';
      const list=document.getElementById('obs_list'); list.innerHTML='';
      const arr = Array.isArray(j.observaciones)? j.observaciones : [];
      if(!arr.length){ list.innerHTML = '<div class="small" style="opacity:.7">Sin observaciones.</div>'; }
      else{
        arr.forEach(o=>{
          const item=document.createElement('div');
          item.style.cssText='border:1px solid rgba(157,176,208,.2);border-radius:10px;padding:8px;background:rgba(255,255,255,.03)';
          item.innerHTML = `<div style="display:flex;justify-content:space-between;gap:10px"><strong>${(o.autor||'')}</strong><span class="small" style="opacity:.7">${(o.fecha||'')}</span></div><div style="margin-top:4px;white-space:pre-wrap">${(o.texto||'')}</div>`;
          list.appendChild(item);
        });
      }
      openModal();
   })
   .catch(err=> Swal.fire({icon:'error',title:'Error',text:err.message}));
}

document.querySelectorAll('.btn-ver').forEach(b=>{
  b.addEventListener('click', ()=>{
    const oid=b.dataset.oid, tid=b.dataset.tid;
    loadOrden(oid, tid);
  });
});

document.getElementById('o_terminar')?.addEventListener('click', ()=>{
  const oid=o_id.value, tid=o_tid.value;
  Swal.fire({icon:'question',title:'¿Marcar la orden como terminada?', text:'Esta acción cerrará el turno como Terminado.', showCancelButton:true,confirmButtonText:'Sí, terminar',confirmButtonColor:'#3b82f6'})
    .then(res=>{
      if(!res.isConfirmed) return;
      const fd=new FormData(); fd.append('orden_id', oid); fd.append('turno_id', tid); fd.append('estado','terminado');
      Swal.fire({title:'Actualizando...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
      fetch('<?= $base ?>/modules/empleado/mecanico/orden_estado.php',{method:'POST', body:fd, headers:{'Accept':'application/json'}})
        .then(r=>{ if(!r.ok) throw new Error('No se pudo actualizar el estado'); return r.json(); })
        .then(()=>{ Swal.close(); Swal.fire({toast:true, position:'top-end', icon:'success', title:'Orden terminada', showConfirmButton:false, timer:2200}); closeModal(); setTimeout(()=> location.reload(), 400); })
        .catch(e=> Swal.fire({icon:'error',title:'Error',text:e.message}));
    });
});

// Guardar OR + posible observación
document.querySelector('#ordenModal form')?.addEventListener('submit', (e)=>{
  e.preventDefault();
  const form=e.currentTarget; const fd=new FormData(form);
  const obsTxt=(document.getElementById('o_obs')?.value||'').trim();
  fetch(form.action,{method:'POST', body:fd, headers:{'Accept':'application/json'}})
   .then(r=>{ if(!r.ok) return r.text().then(t=>{throw new Error(t||'No se pudo guardar la orden');}); return r.json(); })
   .then(()=>{
     if(!obsTxt){ toast('Orden actualizada'); closeModal(); setTimeout(()=> location.reload(), 350); return; }
     const fd2=new FormData(); fd2.append('orden_id', fd.get('orden_id')); fd2.append('texto', obsTxt);
     return fetch('<?= $base ?>/modules/empleado/mecanico/observacion_guardar.php',{method:'POST', body:fd2, headers:{'Accept':'application/json'}})
       .then(r=>{ if(!r.ok) return r.text().then(t=>{throw new Error(t||'No se pudo guardar la observación');}); return r.json(); })
       .then(()=>{ toast('Orden y observación guardadas'); closeModal(); setTimeout(()=> location.reload(), 350); });
   })
   .catch(err=> Swal.fire({icon:'error',title:'Error',text:err.message}));
});

// ====== Reimprimir OR ======
document.querySelectorAll('.btn-reprint').forEach(a => {
  a.addEventListener('click', (e) => {
    e.preventDefault();
    const url = a.getAttribute('href');
    Swal.fire({title: 'Cargando orden...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() }});
    fetch(url)
      .then(response => { if (!response.ok) { throw new Error('No se pudo cargar la orden.') } return response.text(); })
      .then(html => {
        Swal.close();
        Swal.fire({
          title: 'Orden de Reparación', html: html, width: '900px', showCancelButton: true,
          confirmButtonText: '🖨️ Imprimir', cancelButtonText: 'Cerrar', confirmButtonColor: '#3b82f6',
          customClass: { popup: 'imprimir-modal' }
        }).then((result) => {
          if (result.isConfirmed) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`<html><head><title>Imprimir Orden</title><link rel="icon" type="image/png" href="<?= $favicon_base64 ?>"></head><body>`);
            printWindow.document.write(html);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.onload = function() { printWindow.focus(); printWindow.print(); printWindow.close(); };
          }
        });
      })
      .catch(error => { Swal.fire({ icon: 'error', title: 'Error', text: error.message }); });
  });
});

// ====== Confirmación + ocultar botón en listado (sin recargar) ======
document.querySelectorAll('form.form-terminar').forEach((f) => {
  f.addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = f.querySelector('.js-btn-terminar');
    Swal.fire({
      icon:'question',
      title:'¿Marcar la orden como terminada?',
      text:'Una vez marcada, no podrás volver a “En proceso” desde este panel.',
      showCancelButton:true,
      confirmButtonText:'Sí, terminar',
      cancelButtonText:'Cancelar',
      confirmButtonColor:'#3b82f6'
    }).then(res=>{
      if(!res.isConfirmed) return;
      const fd = new FormData(f);
      Swal.fire({title:'Actualizando...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
      fetch(f.action, { method:'POST', body:fd, headers:{ 'Accept':'application/json' } })
        .then(r=>{ if(!r.ok) throw new Error('No se pudo actualizar'); return r.json(); })
        .then(() => {
          Swal.close();
          // Cambiar badge a Terminado
          const tr = f.closest('tr');
          const badge = tr?.querySelector('.js-estado-badge');
          if (badge) {
            badge.textContent = 'Terminado';
            badge.classList.remove('badge--prog','badge--pend','badge--canc','badge--unk');
            badge.classList.add('badge--done');
          }
          // Ocultar el botón "Marcar Terminado"
          btn?.closest('form')?.remove();
          Swal.fire({toast:true, position:'top-end', icon:'success', title:'Orden marcada como terminada', showConfirmButton:false, timer:2200});
        })
        .catch(err=>{
          Swal.fire({icon:'error', title:'Error', text:err.message || 'Intentalo nuevamente.'});
        });
    });
  });
});
</script>
</body>
</html>
