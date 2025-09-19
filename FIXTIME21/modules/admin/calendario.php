<?php
declare(strict_types=1);

require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');
require_once __DIR__ . '/../../clases/EstadoTurnoRepositorio.php';

require_once __DIR__ . '/../../clases/EstadoTurnoRepositorio.php';
$repoE = new EstadoTurnoRepositorio();
$estados = $repoE->listarTodos();

// Seguridad: solo administradores
require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';
require_once __DIR__ . '/../../clases/TurnoRepositorio.php';
$repoA = new AdministradorRepositorio();
if (!$repoA->esAdmin((int)($_SESSION['uid'] ?? 0))) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$repo   = new TurnoRepositorio();
$turnos = $repo->listarTodosLosTurnos();
// Procesar acciones POST
require_once __DIR__ . '/../../clases/EstadoTurnoRepositorio.php';
$repoE = new EstadoTurnoRepositorio();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_turno') {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        $fecha   = $_POST['fecha_turno'] !== '' ? $_POST['fecha_turno'] : null;
        $hora    = $_POST['hora_turno']  !== '' ? $_POST['hora_turno']  : null;
        $estadoId = (int)($_POST['estado_id'] ?? 1);

        if ($turnoId > 0 && $estadoId > 0 && $repo->actualizarTurno($turnoId, $fecha, $hora, $estadoId)) {
            header('Location: '.$base.'/modules/admin/calendario.php?ok=1'); exit;
        } else {
            header('Location: '.$base.'/modules/admin/calendario.php?error=1'); exit;
        }
    }
    if ($action === 'eliminar_turno') {
    $turnoId = (int)($_POST['turno_id'] ?? 0);
    if ($turnoId > 0 && $repo->eliminarTurno($turnoId)) {
        header('Location: '.$base.'/modules/admin/calendario.php?ok=1'); exit;
    } else {
        header('Location: '.$base.'/modules/admin/calendario.php?error=1'); exit;
    }
}



    if ($action === 'cancelar_turno') {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        if ($turnoId > 0) {
            // Buscar el ID de “cancelado” en la tabla Estados_Turnos
            $canceladoId = $repoE->obtenerIdPorDescripcion('cancelado');
            if ($canceladoId && $repo->actualizarTurno($turnoId, null, null, $canceladoId)) {
                header('Location: '.$base.'/modules/admin/calendario.php?ok=1'); exit;
            } else {
                header('Location: '.$base.'/modules/admin/calendario.php?error=1'); exit;
            }
        }
    }
} // <-- ESTA es la única llave de cierre del bloque POST


$ok    = $_GET['ok']    ?? null;
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fixtime — Calendario (Admin)</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
  <link rel="stylesheet" href="<?= $base ?>/publico/app.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0b1226; --panel:#0f1a33; --panel-2:#0b162b; --card:#0c1730;
  --muted:#9db0d0; --text:#e9f0ff; --brand:#3b82f6; --brand-2:#2563eb;
  --ring:rgba(59,130,246,.40); --shadow:0 12px 40px rgba(2,6,23,.45); --radius:18px;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0}
body{
  min-height:100vh;
  background:
    radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.22), transparent 70%),
    radial-gradient(900px 480px at 10% 110%, rgba(37,99,235,.16), transparent 60%),
    var(--bg);
  color:var(--text);
  font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;
}
.app{display:grid;grid-template-columns:320px 1fr;min-height:100vh}
.sidebar{padding:22px;background:linear-gradient(180deg,var(--panel),var(--bg));border-right:1px solid rgba(157,176,208,.15);position: sticky; top:0; height:100vh; z-index:40;}
.brand{display:flex;gap:12px;align-items:center;margin-bottom:22px}
.brand-badge{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(40px 30px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2));box-shadow:0 12px 30px var(--ring), inset 0 1px 0 rgba(255,255,255,.25)}
.brand-badge img{width:32px;height:32px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.brand-name{font-weight:800;letter-spacing:.35px;font-size:22px}
.brand-sub{opacity:.8;font-size:12px}
.theme-btn{margin-left:auto;appearance:none;border:1px solid rgba(157,176,208,.28);background:rgba(255,255,255,.06);color:var(--text);border-radius:12px;padding:10px 12px;cursor:pointer;font-size:16px;box-shadow:0 6px 16px rgba(0,0,0,.2)}
.nav{display:flex;flex-direction:column;gap:12px;margin-top:10px}
.nav a{display:flex;gap:12px;align-items:center;justify-content:flex-start;padding:14px 16px;border-radius:14px;border:1px solid rgba(157,176,208,.18);background:rgba(255,255,255,.03);color:var(--text);text-decoration:none;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.25)}
.nav a.active{background:linear-gradient(135deg, rgba(59,130,246,.20), rgba(37,99,235,.20));border-color:rgba(59,130,246,.55);box-shadow:0 10px 28px var(--ring)}
.topbar-salir{display:block;margin-top:14px;text-align:center;text-decoration:none}
.main{padding:26px 32px;display:flex;flex-direction:column;min-height:100vh}
.hero{display:flex;align-items:center;gap:16px;background:linear-gradient(135deg, rgba(59,130,246,.22), rgba(37,99,235,.18));border:1px solid rgba(59,130,246,.40);border-radius:var(--radius);padding:18px;box-shadow:0 14px 32px var(--ring);margin-bottom:16px}
.hero .avatar{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(24px 16px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2))}
.hero .avatar img{width:38px;height:38px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.03));border:1px solid rgba(157,176,208,.16);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.header-mobile{display:none;align-items:center;gap:10px;position:sticky;top:0;z-index:1100;padding:12px 16px;background:rgba(12,23,48,.75);backdrop-filter:blur(8px);border-bottom:1px solid rgba(255,255,255,.06)}
.burger{appearance:none;background:transparent;border:0;color:var(--text);font-size:24px;cursor:pointer}
.sidebar__overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000}
.sidebar__overlay.show{display:block}
@media (max-width:1080px){
  .app{grid-template-columns:1fr}
  .sidebar{position:fixed; inset:0 auto 0 0; width:84%; max-width:320px; height:100vh; transform:translateX(-105%); transition:transform .22s ease; z-index:1001; box-shadow:var(--shadow)}
  .sidebar.open{transform:translateX(0)}
  .header-mobile{display:flex}
}
.theme-light{
  --bg:#f3f6fc;--panel:#ffffff;--panel-2:#f7f9ff;--card:#ffffff;--muted:#5b6b85;--text:#0b1220;--ring:rgba(59,130,246,.28);--shadow:0 8px 26px rgba(15,23,42,.08)
}
.theme-light .sidebar{background:linear-gradient(180deg,var(--panel),#eaf0ff);border-right:1px solid rgba(15,23,42,.06)}
.theme-light .nav a{background:#fff;border-color:rgba(15,23,42,.08)}
.theme-light .card{background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:var(--shadow)}
.theme-light .theme-btn{background:#fff;border-color:rgba(15,23,42,.08);color:#0b1220}
    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px
    }

    .table th,
    .table td {
      padding: 12px 10px;
      border-bottom: 1px solid rgba(157, 176, 208, .14)
    }

    
    .row {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 12px
    }

    .col-12 {
      grid-column: span 12
    }

    .col-6 {
      grid-column: span 6
    }

    .col-4 {
      grid-column: span 4
    }

    .col-3 {
      grid-column: span 3
    }

    .col-8 {
      grid-column: span 8
    }

    .col-5 {
      grid-column: span 5
    }

    .col-7 {
      grid-column: span 7
    }

    .col-9 {
      grid-column: span 9
    }

    .col-2 {
      grid-column: span 2
    }

    .col-10 {
      grid-column: span 10
    }

    .col-11 {
      grid-column: span 11
    }

    label {
      font-size: 12px;
      color: var(--muted)
    }

    input,
    select {
      width: 100%;
      background: var(--panel-2);
      border: 1px solid rgba(157, 176, 208, .2);
      color: var(--text);
      border-radius: 12px;
      padding: 12px 14px
    }
    
    .btn {
      cursor: pointer;
      border: 0;
      border-radius: 12px;
      padding: 12px 16px;
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      color: #0b1220;
      font-weight: 800;
      box-shadow: 0 12px 28px var(--ring)
    }

    .btn.ghost {
      background: transparent;
      color: var(--text);
      border: 1px solid rgba(157, 176, 208, .30);
      box-shadow: none
    }

    .pill {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800
    }

    .ok {
      background: rgba(16, 185, 129, .12);
      color: #34d399
    }

    .warn {
      background: rgba(245, 158, 11, .12);
      color: #fbbf24
    }

    .bad {
      background: rgba(239, 68, 68, .12);
      color: #f87171
    }

    .small {
      font-size: 12px;
      opacity: .85
    }

    .thumb,
    .thumb-sm {
      display: inline-block;
      width: 52px;
      height: 52px;
      border-radius: 10px;
      background: #0b162b center/cover no-repeat;
      border: 1px solid rgba(157, 176, 208, .25);
      position: relative;
      cursor: zoom-in
    }

    .thumb::after,
    .thumb-sm::after {
      content: "";
      position: absolute;
      left: 50%;
      bottom: 100%;
      transform: translate(-50%, -10px) scale(.95);
      width: 240px;
      height: 180px;
      border-radius: 12px;
      background: inherit;
      background-size: contain;
      background-repeat: no-repeat;
      background-position: center;
      box-shadow: 0 20px 40px rgba(2, 6, 23, .45);
      border: 1px solid rgba(157, 176, 208, .25);
      opacity: 0;
      pointer-events: none;
      transition: opacity .12s ease, transform .12s ease;
      z-index: 20
    }

    .thumb:hover::after,
    .thumb-sm:hover::after {
      opacity: 1;
      transform: translate(-50%, -12px) scale(1)
    }

    .table td {
      overflow: visible
    }

    dialog#vehiculoModal {
      position: fixed;
      inset: 50% auto auto 50%;
      transform: translate(-50%, -50%);
      z-index: 1000;
      border: 0;
      border-radius: 18px;
      padding: 0;
      max-width: 880px;
      width: 95%;
      background: var(--panel);
      box-shadow: 0 30px 80px rgba(2, 6, 23, .60)
    }

    dialog#vehiculoModal::backdrop {
      background: rgba(5, 10, 25, .55);
      backdrop-filter: blur(4px) saturate(120%)
    }

    body.modal-open {
      overflow: hidden
    }

    body.modal-open .thumb::after,
    body.modal-open .thumb-sm::after {
      display: none !important
    }

    .theme-light {
      --bg: #f3f6fc;
      --panel: #ffffff;
      --panel-2: #f7f9ff;
      --card: #ffffff;
      --muted: #5b6b85;
      --text: #0b1220;
      --ring: rgba(59, 130, 246, .28);
      --shadow: 0 8px 26px rgba(15, 23, 42, .08)
    }

    .theme-light body {
      background: radial-gradient(1000px 500px at 80% -10%, rgba(59, 130, 246, .12), transparent 70%), radial-gradient(700px 380px at 10% 110%, rgba(37, 99, 235, .10), transparent 60%), var(--bg)
    }

    .theme-light .sidebar {
      background: linear-gradient(180deg, var(--panel), #eaf0ff);
      border-right: 1px solid rgba(15, 23, 42, .06)
    }

    .theme-light .nav button {
      background: #fff;
      border-color: rgba(15, 23, 42, .08)
    }

    .theme-light .card {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .06);
      box-shadow: var(--shadow)
    }

    .theme-light .table th,
    .theme-light .table td {
      border-bottom: 1px solid rgba(15, 23, 42, .08)
    }

    .theme-light .theme-btn {
      background: #fff;
      border-color: rgba(15, 23, 42, .08);
      color: #0b1220
    }

    .header-mobile {
      display: none;
      align-items: center;
      gap: 10px;
      position: sticky;
      top: 0;
      z-index: 1100;
      padding: 12px 16px;
      background: rgba(12, 23, 48, .75);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid rgba(255, 255, 255, .06)
    }

    .burger {
      appearance: none;
      background: transparent;
      border: 0;
      color: var(--text);
      font-size: 24px;
      cursor: pointer
    }

    .sidebar__overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .45);
      z-index: 1000
    }

    .sidebar__overlay.show {
      display: block
    }

    @media (max-width:1080px) {
      .app {
        grid-template-columns: 1fr
      }

      .sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: 84%;
        max-width: 320px;
        height: 100vh;
        transform: translateX(-105%);
        transition: transform .22s ease;
        z-index: 1001;
        box-shadow: var(--shadow)
      }

      .sidebar.open {
        transform: translateX(0)
      }

      .header-mobile {
        display: flex
      }

      .kpis {
        grid-template-columns: 1fr
      }

      .row {
        grid-template-columns: repeat(6, 1fr)
      }

      .col-6 {
        grid-column: span 6
      }

      .col-4 {
        grid-column: span 6
      }

      .col-3 {
        grid-column: span 3
      }

      .col-5 {
        grid-column: span 6
      }

      .col-7 {
        grid-column: span 6
      }

      .col-9 {
        grid-column: span 6
      }

      .col-10 {
        grid-column: span 6
      }

      .col-11 {
        grid-column: span 6
      }
    }

    .table.responsive {
      width: 100%;
    }

    @media (max-width: 740px) {
      .table.responsive thead {
        display: none;
      }

      .table.responsive tbody tr {
        display: block;
        background: linear-gradient(180deg, rgba(255, 255, 255, .05), rgba(255, 255, 255, .03));
        border: 1px solid rgba(157, 176, 208, .16);
        border-radius: 14px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: 0 10px 24px rgba(2, 6, 23, .25);
      }

      .table.responsive tbody td {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 8px;
        padding: 8px 4px;
        border: 0;
      }

      .table.responsive tbody td::before {
        content: attr(data-label);
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
      }

      .table.responsive .thumb-sm {
        width: 46px;
        height: 46px;
        border-radius: 10px;
      }

      .veh-acciones {
        display: flex !important;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 4px;
      }
    }

    .table.responsive th,
    .table.responsive td {
      vertical-align: middle;
    }

    .input-patente {
      min-width: 220px
    }

    @media (min-width: 740px) {
      #vehiculoModal form .patente-wide {
        grid-column: span 6;
      }
    }

    .banner-empresa {
      background: linear-gradient(135deg, rgba(59, 130, 246, .18), rgba(37, 99, 235, .14));
      border: 1px solid rgba(59, 130, 246, .35);
      padding: 12px 14px;
      border-radius: 12px;
      margin-bottom: 10px;
      font-size: 14px
    }

    /* Toggle estilizado */
    .toggle {
      display: flex;
      border: 1px solid rgba(157, 176, 208, .3);
      border-radius: 12px;
      overflow: hidden;
      width: max-content
    }

    .toggle input {
      display: none
    }

    .toggle label {
      padding: 10px 14px;
      cursor: pointer;
      user-select: none;
      font-weight: 800
    }

    .toggle input:checked+label {
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      color: #0b1220
    }
  .alert {
  padding: 14px 18px;
  border-radius: 12px;
  font-weight: 700;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
  opacity: 1;
  transition: opacity 0.6s ease;
}

.alert.success {
  background: rgba(16, 185, 129, .15);
  color: #10b981;
  border: 1px solid #10b981;
}

.alert.error {
  background: rgba(239, 68, 68, .15);
  color: #ef4444;
  border: 1px solid #ef4444;
}

.alert.hide {
  opacity: 0;
}

</style>
</head>
<body>

<div class="header-mobile">
  <button class="burger" id="btnMenu" aria-label="Menú" aria-expanded="false">☰</button>
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
      <div style="flex:1"><div class="brand-name">Fixtime</div><div class="brand-sub">Panel de Administrador</div></div>
      <button id="themeToggle" class="theme-btn" title="Cambiar tema">🌙</button>
    </div>
    <nav class="nav" id="nav">
      <a href="<?= $base ?>/modules/cliente/" class="btn ghost" style="margin-left:auto">↔ Cambiar a panel cliente</a>
      <a href="<?= $base ?>/modules/admin/">🏠 Inicio</a>
      <a href="<?= $base ?>/modules/admin/empleados.php">👥 Empleados</a>
      <a class="active" href="<?= $base ?>/modules/admin/calendario.php">🗓️ Calendario</a>
      <a href="<?= $base ?>/modules/admin/vehiculos.php">🚗 Mis vehículos</a>
      <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">Cerrar sesión</a>
    </nav>
  </aside>
  <div class="sidebar__overlay" id="overlay"></div>

  <main class="main">
    <div class="hero">
      <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
      <div>
        <div style="font-weight:800;font-size:22px">Calendario</div>
        <div style="color:var(--muted)">Placeholder (sin lógica). Acá luego conectamos reglas de feriados, cupos y bloqueos.</div>
      </div>
    </div>


   <!-- === Sección Turnos === -->
<section id="tab-turnos">
  <div class="card" style="margin-bottom:16px">
    <h3>Gestión de turno</h3>
    <form method="post" class="row validate-form" id="form-turno">
      <input type="hidden" name="action" value="update_turno">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="turno_id" id="turno_id">

      <div class="col-6">
        <label>Vehículo</label>
        <input type="text" id="vehiculo" readonly>
      </div>
      <div class="col-6">
        <label>Cliente</label>
        <input type="text" id="cliente" readonly>
      </div>

      <div class="col-6">
        <label>Motivo</label>
        <input type="text" id="motivo" name="motivo" readonly>
      </div>
      <div class="col-6">
        <label>Descripción</label>
        <input type="text" id="descripcion" readonly>
      </div>

      <div class="col-6">
        <label>Fecha</label>
        <input type="date" name="fecha_turno" id="fecha_turno">
      </div>
      <div class="col-6">
        <label>Hora</label>
        <input type="time" name="hora_turno" id="hora_turno">
      </div>

      <div class="col-6">
        <label>Estado</label>
        <select name="estado_id" id="estado_id">
          <?php foreach ($estados as $estado): ?>
            <option value="<?= (int)$estado['id'] ?>">
              <?= h(ucfirst($estado['descripcion'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12"><button class="btn">Guardar cambios</button></div>
    </form>
  </div>

  <div class="card">
    <h3>Listado de turnos</h3>
    <table class="table responsive">
      <thead>
        <tr>
          <th>#</th>
          <th>Cliente</th>
          <th>Vehículo</th>
          <th>Motivo</th>
          <th>Descripción</th>
          <th>Fecha</th>
          <th>Hora</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$turnos): ?>
        <tr><td colspan="9">Sin turnos.</td></tr>
      <?php else: foreach ($turnos as $t): ?>
        <tr>
          <td>T-<?= (int)$t['id'] ?></td>
          <td><?= h(($t['nombre'] ?? '').' '.($t['apellido'] ?? '')) ?></td>
          <td><?= h(($t['marca'] ?? '').' '.($t['modelo'] ?? '').' ('.$t['anio'].')') ?></td>
          <td><?= h($t['motivo'] ?? '') ?></td>
          <td><?= h($t['descripcion'] ?? '') ?></td>
          <td><?= h($t['fecha_turno'] ?? '-') ?></td>
          <td><?= h($t['hora_turno'] ?? '-') ?></td>
          <td><?= h($t['estado']) ?></td>
          <td>
            <button type="button" class="btn ghost btn-editar"
              data-id="<?= (int)$t['id'] ?>"
              data-cliente="<?= h(($t['nombre'] ?? '').' '.($t['apellido'] ?? '')) ?>"
              data-vehiculo="<?= h(($t['marca'] ?? '').' '.$t['modelo'].' ('.$t['anio'].')') ?>"
              data-motivo="<?= h($t['motivo'] ?? '') ?>"
              data-descripcion="<?= h($t['descripcion'] ?? '') ?>"
              data-fecha="<?= h($t['fecha_turno'] ?? '') ?>"
              data-hora="<?= h($t['hora_turno'] ?? '') ?>"
              data-estado="<?= (int)$t['estado_id'] ?>"
            >Editar</button>

            <form method="post" onsubmit="return confirm('¿Eliminar turno?');" style="display:inline">
              <input type="hidden" name="action" value="eliminar_turno">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="turno_id" value="<?= (int)$t['id'] ?>">
              <button class="btn ghost">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>


    </thead>
    <tbody>

    </tbody>
  </table>
</div>

  </section>








  </main>
</div>

<script>
(function(){
  const root=document.documentElement, sidebar=document.getElementById('sidebar'),
        overlay=document.getElementById('overlay'), btnMenu=document.getElementById('btnMenu'),
        tDesk=document.getElementById('themeToggle'), tMob=document.getElementById('themeToggleMobile');

  function setIcon(b){ if(!b) return; b.textContent = root.classList.contains('theme-light') ? '☀️' : '🌙'; }
  const saved = localStorage.getItem('theme') || 'dark';
  if (saved==='light') root.classList.add('theme-light');
  setIcon(tDesk); setIcon(tMob);
  [tDesk,tMob].forEach(b=>b&&b.addEventListener('click',()=>{root.classList.toggle('theme-light');localStorage.setItem('theme',root.classList.contains('theme-light')?'light':'dark');setIcon(tDesk);setIcon(tMob);}))

  function openMenu(){ sidebar.classList.add('open'); overlay.classList.add('show'); btnMenu?.setAttribute('aria-expanded','true'); }
  function closeMenu(){ sidebar.classList.remove('open'); overlay.classList.remove('show'); btnMenu?.setAttribute('aria-expanded','false'); }
  function toggleMenu(){ (sidebar.classList.contains('open')?closeMenu:openMenu)(); }

  btnMenu && btnMenu.addEventListener('click', toggleMenu);
  overlay && overlay.addEventListener('click', closeMenu);
  document.getElementById('nav').addEventListener('click', e=>{
    if(e.target.closest('a') && window.matchMedia('(max-width:1080px)').matches) closeMenu();
  });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape' && sidebar.classList.contains('open')) closeMenu(); });
  window.addEventListener('resize', ()=>{ if(!window.matchMedia('(max-width:1080px)').matches) closeMenu(); });
})();
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const alertBox = document.getElementById("alert");
  if (alertBox) {
    setTimeout(() => {
      alertBox.classList.add("hide");
      setTimeout(() => alertBox.remove(), 600); // quitar del DOM después de la transición
    }, 5000); // 5 segundos
  }
});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("form-turno");
  const inputs = {
    id: document.getElementById("turno_id"),
    cliente: document.getElementById("cliente"),
    vehiculo: document.getElementById("vehiculo"),
    motivo: document.getElementById("motivo"),
    descripcion: document.getElementById("descripcion"),
    fecha: document.getElementById("fecha_turno"),
    hora: document.getElementById("hora_turno"),
    estado: document.getElementById("estado_id"),
  };

  document.querySelectorAll(".btn-editar").forEach(btn => {
    btn.addEventListener("click", () => {
      inputs.id.value = btn.dataset.id;
      inputs.cliente.value = btn.dataset.cliente;
      inputs.vehiculo.value = btn.dataset.vehiculo;
      inputs.motivo.value = btn.dataset.motivo;
      inputs.descripcion.value = btn.dataset.descripcion;
      inputs.fecha.value = btn.dataset.fecha;
      inputs.hora.value = btn.dataset.hora;
      inputs.estado.value = btn.dataset.estado;
      window.scrollTo({ top: form.offsetTop - 50, behavior: "smooth" });
    });
  });
});
</script>

</body>
</html>

