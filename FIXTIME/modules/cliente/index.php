<?php
require_once __DIR__ . '/../../clases/Sesion.php';
require_once __DIR__ . '/../../clases/VehiculoRepositorio.php';
require_once __DIR__ . '/../../clases/TurnoRepositorio.php';
require_once __DIR__ . '/../../clases/PersonaRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);
$nom = $_SESSION['nombre']  ?? '';
$ape = $_SESSION['apellido']?? '';

$repoV = new VehiculoRepositorio();
$repoT = new TurnoRepositorio();
$repoP = new PersonaRepositorio();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$flash = '';
$flashType = 'info';

// ====== ACCIONES ======
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['action'] ?? '';

  try {
    if ($act==='add_vehiculo') {
      $marcaId = (int)($_POST['marca_id'] ?? 0);
      $modelo  = trim($_POST['modelo'] ?? '');
      $anio    = trim($_POST['anio'] ?? '');
      $color   = trim($_POST['color'] ?? '');
      $km      = trim($_POST['km'] ?? '0');

      if (!$marcaId || $modelo==='' || $anio==='') {
        $flash = 'Completá marca, modelo y año.'; $flashType='error';
      } else {
        $repoV->crearParaPersona($uid, $marcaId, $modelo, $anio, $km, $color);
        $flash = 'Vehículo registrado.'; $flashType='success';
      }
    }

    if ($act==='add_turno') {
      $autoId = (int)($_POST['auto_id'] ?? 0);
      $fecha  = trim($_POST['fecha'] ?? '');
      $hora   = trim($_POST['hora'] ?? '');
      if (!$autoId || $fecha==='' || $hora==='') {
        $flash = 'Elegí vehículo, fecha y hora.'; $flashType='error';
      } else {
        if (!$repoV->perteneceAPersona($uid, $autoId)) {
          $flash = 'El vehículo no te pertenece.'; $flashType='error';
        } elseif (!$repoT->disponible($fecha,$hora)) {
          $flash = 'No hay disponibilidad para esa fecha/hora.'; $flashType='warning';
        } else {
          $repoT->crear($uid,$autoId,$fecha,$hora);
          $flash = 'Turno solicitado (Pendiente).'; $flashType='success';
        }
      }
    }

    if ($act==='reprogramar_turno') {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      $autoId  = (int)($_POST['auto_id']  ?? 0);
      $fecha   = trim($_POST['fecha'] ?? '');
      $hora    = trim($_POST['hora']  ?? '');
      if ($turnoId && $autoId && $fecha && $hora) {
        if (!$repoV->perteneceAPersona($uid,$autoId)) {
          $flash='El vehículo no te pertenece.'; $flashType='error';
        } elseif (!$repoT->disponible($fecha,$hora)) {
          $flash='No hay disponibilidad para esa fecha/hora.'; $flashType='warning';
        } else {
          $repoT->reprogramar($uid,$turnoId,$fecha,$hora,$autoId);
          $flash='Turno reprogramado.'; $flashType='success';
        }
      } else { $flash='Completá los campos.'; $flashType='error'; }
    }

    if ($act==='cancelar_turno') {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      if ($turnoId) { 
        $repoT->cancelar($uid,$turnoId); 
        $flash='Turno cancelado.'; $flashType='success';
      }
    }

    if ($act==='update_perfil') {
      $n   = trim($_POST['n']   ?? '');
      $a   = trim($_POST['a']   ?? '');
      $dni = trim($_POST['dni'] ?? '');
      $em  = trim($_POST['email'] ?? '');

      if ($n==='' || $a==='' || $dni==='') {
        $flash = 'Completá nombre, apellido y DNI.'; $flashType='error';
      } elseif ($em!=='' && !filter_var($em,FILTER_VALIDATE_EMAIL)) {
        $flash = 'Email inválido.'; $flashType='error';
      } else {
        $repoP->actualizarPerfil($uid,$n,$a,$dni,$em);
        $_SESSION['nombre']=$n; $_SESSION['apellido']=$a;
        $nom=$n; $ape=$a;
        $dniAct=$dni; $emailAct=$em;
        $flash='Perfil actualizado.'; $flashType='success';
      }
    }
  } catch (Throwable $e) {
    $flash = 'Ocurrió un error. Intentá nuevamente.'; $flashType='error';
  }
}

// ====== DATOS ======
$marcas    = $repoV->listarMarcas();
$vehiculos = $repoV->listarPorPersona($uid);
$turnos    = $repoT->listarPorPersona($uid);
$historial = $repoT->historialServicios($uid);
$emailAct  = $repoP->emailPrincipal($uid) ?? '';
$per       = $repoP->buscarPorId($uid);
$dniAct    = $dniAct ?? ($per['dni'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fixtime — Panel del Cliente</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ================== VARIABLES ================== */
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

/* ================== SIDEBAR ================== */
.sidebar{position:sticky;top:0;height:100vh;z-index:40;padding:22px;background:linear-gradient(180deg,var(--panel),var(--bg));border-right:1px solid rgba(157,176,208,.15)}
.brand{display:flex;gap:12px;align-items:center;margin-bottom:22px}
.brand-badge{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(40px 30px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2));box-shadow:0 12px 30px var(--ring), inset 0 1px 0 rgba(255,255,255,.25)}
.brand-badge img{width:32px;height:32px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.brand-name{font-weight:800;letter-spacing:.35px;font-size:22px}
.brand-sub{opacity:.8;font-size:12px}
.theme-btn{margin-left:auto;appearance:none;border:1px solid rgba(157,176,208,.28);background:rgba(255,255,255,.06);color:var(--text);border-radius:12px;padding:10px 12px;cursor:pointer;font-size:16px;box-shadow:0 6px 16px rgba(0,0,0,.2)}
.nav{display:flex;flex-direction:column;gap:12px;margin-top:10px}
.nav button{display:flex;gap:12px;align-items:center;justify-content:flex-start;padding:14px 16px;border-radius:14px;border:1px solid rgba(157,176,208,.18);background:rgba(255,255,255,.03);color:var(--text);cursor:pointer;font-size:16px;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.25)}
.nav button.active{background:linear-gradient(135deg, rgba(59,130,246,.20), rgba(37,99,235,.20));border-color:rgba(59,130,246,.55);box-shadow:0 10px 28px var(--ring)}
.topbar-salir{display:block;margin-top:14px;text-align:center;text-decoration:none}

/* ================== MAIN ================== */
.main{padding:26px 32px;display:flex;flex-direction:column;min-height:100vh}
.hero{display:flex;align-items:center;gap:16px;background:linear-gradient(135deg, rgba(59,130,246,.22), rgba(37,99,235,.18));border:1px solid rgba(59,130,246,.40);border-radius:var(--radius);padding:18px;box-shadow:0 14px 32px var(--ring);margin-bottom:16px}
.hero .avatar{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(24px 16px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2))}
.hero .avatar img{width:38px;height:38px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.hero .greet{font-weight:800;font-size:22px}
.kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:20px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.03));border:1px solid rgba(157,176,208,.16);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.table{width:100%;border-collapse:collapse;font-size:14px}
.table th,.table td{padding:12px 10px;border-bottom:1px solid rgba(157,176,208,.14)}
.row{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
.col-12{grid-column:span 12}.col-6{grid-column:span 6}.col-4{grid-column:span 4}.col-3{grid-column:span 3}
label{font-size:12px;color:var(--muted)}
input,select{width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px}
.btn{cursor:pointer;border:0;border-radius:12px;padding:12px 16px;background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#0b1220;font-weight:800;box-shadow:0 12px 28px var(--ring)}
.btn.ghost{background:transparent;color:var(--text);border:1px solid rgba(157,176,208,.30);box-shadow:none}
.pill{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800}
.ok{background:rgba(16,185,129,.12);color:#34d399}.warn{background:rgba(245,158,11,.12);color:#fbbf24}.bad{background:rgba(239,68,68,.12);color:#f87171}

/* ================== FOOTER ================== */
.footer{margin-top:auto;padding:20px;text-align:center;font-size:13px;color:var(--muted);background:var(--panel-2);border-top:1px solid rgba(157,176,208,.15)}
.footer .social{margin-top:10px;display:flex;justify-content:center;gap:14px}
.footer .social a{color:var(--muted);font-size:20px;text-decoration:none;transition:.2s}
.footer .social a:hover{color:var(--brand)}

/* ================== TEMA CLARO ================== */
.theme-light{--bg:#f3f6fc;--panel:#ffffff;--panel-2:#f7f9ff;--card:#ffffff;--muted:#5b6b85;--text:#0b1220;--ring:rgba(59,130,246,.28);--shadow:0 8px 26px rgba(15,23,42,.08)}
.theme-light body{background:radial-gradient(1000px 500px at 80% -10%, rgba(59,130,246,.12), transparent 70%),radial-gradient(700px 380px at 10% 110%, rgba(37,99,235,.10), transparent 60%),var(--bg)}
.theme-light .sidebar{background:linear-gradient(180deg,var(--panel),#eaf0ff);border-right:1px solid rgba(15,23,42,.06)}
.theme-light .nav button{background:#fff;border-color:rgba(15,23,42,.08)}
.theme-light .card{background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:var(--shadow)}
.theme-light .table th,.theme-light .table td{border-bottom:1px solid rgba(15,23,42,.08)}
.theme-light .theme-btn{background:#fff;border-color:rgba(15,23,42,.08);color:#0b1220}

/* ================== RESPONSIVE ================== */
.header-mobile{display:none;align-items:center;gap:10px;position:sticky;top:0;z-index:45;padding:12px 16px;background:rgba(12,23,48,.75);backdrop-filter:blur(8px);border-bottom:1px solid rgba(255,255,255,.06)}
.burger{appearance:none;background:transparent;border:0;color:var(--text);font-size:24px;cursor:pointer}
.sidebar__overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:39}
@media (max-width:1080px){
  .app{grid-template-columns:1fr}
  .sidebar{position:fixed;inset:0 auto 0 0;width:84%;max-width:320px;transform:translateX(-105%);transition:transform .2s ease;box-shadow:var(--shadow)}
  .sidebar.open{transform:translateX(0)}
  .sidebar__overlay.show{display:block}
  .header-mobile{display:flex}
  .kpis{grid-template-columns:1fr}
  .row{grid-template-columns:repeat(6,1fr)} .col-6{grid-column:span 6}.col-4{grid-column:span 6}.col-3{grid-column:span 3}
}
</style>
</head>
<body>

<!-- Header móvil -->
<div class="header-mobile">
  <button class="burger" id="btnMenu" aria-label="Abrir menú">☰</button>
  <div style="display:flex;align-items:center;gap:10px">
    <div class="brand-badge" style="width:36px;height:36px;border-radius:10px"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
    <strong>Fixtime</strong>
  </div>
  <button id="themeToggleMobile" class="theme-btn" title="Cambiar tema">🌙</button>
</div>

<div class="app">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-badge"><img src="<?= $base ?>/publico/widoo.png" alt="Fixtime"></div>
      <div style="flex:1">
        <div class="brand-name">Fixtime</div>
        <div class="brand-sub">Panel de Cliente</div>
      </div>
      <button id="themeToggle" class="theme-btn" title="Cambiar tema">🌙</button>
    </div>

    <nav class="nav" id="nav">
      <button class="active" data-tab="home">🏠 Inicio</button>
      <button data-tab="vehiculos">🚗 Mis vehículos</button>
      <button data-tab="turnos">🗓️ Turnos</button>
      <button data-tab="historial">🧾 Historial</button>
      <button data-tab="perfil">👤 Mi perfil</button>
      <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">Cerrar sesión</a>
    </nav>
  </aside>
    <div class="sidebar__overlay" id="overlay"></div>

  <!-- MAIN -->
  <main class="main">
    <!-- Hero -->
    <div class="hero">
      <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
      <div style="flex:1">
        <div class="greet">¡Hola, <?= h($nom.' '.$ape) ?>!</div>
        <div class="hint">Gestioná tus vehículos, turnos y datos desde un solo lugar.</div>
      </div>
    </div>

    <!-- KPIs -->
    <section class="kpis" id="tab-home">
      <div class="card"><div style="font-size:13px;color:#9db0d0">Hola</div><div style="font-size:24px;font-weight:800"><?= h($nom.' '.$ape) ?></div><div style="opacity:.85;margin-top:6px">Gestioná tus autos, turnos y datos.</div></div>
      <div class="card"><div style="font-size:13px;color:#9db0d0">Mis vehículos</div><div style="font-size:24px;font-weight:800"><?= count($vehiculos) ?></div><div class="hint">Podés agregar los que faltan.</div></div>
      <div class="card"><div style="font-size:13px;color:#9db0d0">Turnos totales</div><div style="font-size:24px;font-weight:800"><?= count($turnos) ?></div><div class="hint">Pendientes, confirmados y cancelados.</div></div>
    </section>

    <!-- Vehículos -->
    <section id="tab-vehiculos" style="display:none">
      <div class="card" style="margin-bottom:16px">
        <h3>Registrar vehículo</h3>
        <form method="post" class="row validate-form">
          <input type="hidden" name="action" value="add_vehiculo">
          <div class="col-4"><label>Marca</label><select name="marca_id" required><option value="">Seleccioná…</option><?php foreach ($marcas as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['descripcion']) ?></option><?php endforeach; ?></select></div>
          <div class="col-4"><label>Modelo</label><input name="modelo" required placeholder="Corolla / Punto"></div>
          <div class="col-4"><label>Año</label><input name="anio" required placeholder="2018"></div>
          <div class="col-6"><label>Color</label><input name="color" placeholder="Rojo"></div>
          <div class="col-6"><label>Kilometraje</label><input name="km" placeholder="0"></div>
          <div class="col-12" style="display:flex;gap:10px"><button class="btn">Guardar</button><button type="reset" class="btn ghost">Limpiar</button></div>
        </form>
      </div>

      <div class="card">
        <h3>Mis vehículos</h3>
        <table class="table">
          <thead><tr><th>Marca</th><th>Modelo</th><th>Año</th><th>Color</th><th>KM</th></tr></thead>
          <tbody>
            <?php if (!$vehiculos): ?><tr><td colspan="5">Aún no registraste vehículos.</td></tr>
            <?php else: foreach ($vehiculos as $v): ?><tr><td><?= h($v['marca']) ?></td><td><?= h($v['modelo']) ?></td><td><?= h($v['anio']) ?></td><td><?= h($v['color']) ?></td><td><?= h($v['km']) ?></td></tr><?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Turnos -->
    <section id="tab-turnos" style="display:none">
      <div class="card" style="margin-bottom:16px">
        <h3>Agendar turno</h3>
        <form method="post" class="row validate-form">
          <input type="hidden" name="action" value="add_turno">
          <div class="col-6"><label>Vehículo</label><select name="auto_id" required><option value="">Seleccionar…</option><?php foreach ($vehiculos as $v): ?><option value="<?= (int)$v['id_auto'] ?>"><?= h($v['marca'].' '.$v['modelo'].' ('.$v['anio'].')') ?></option><?php endforeach; ?></select></div>
          <div class="col-3"><label>Fecha</label><input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required></div>
          <div class="col-3"><label>Hora</label><input type="time" name="hora" required></div>
          <div class="col-12"><button class="btn">Solicitar</button></div>
        </form>
      </div>

      <div class="card">
        <h3>Mis turnos</h3>
        <table class="table">
          <thead><tr><th>#</th><th>Vehículo</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php if (!$turnos): ?><tr><td colspan="6">Sin turnos.</td></tr>
          <?php else: foreach ($turnos as $t): ?>
            <tr>
              <td>T-<?= (int)$t['id'] ?></td>
              <td><?= h($t['marca'].' '.$t['modelo'].' '.$t['anio']) ?></td>
              <td><?= h($t['fecha_turno']) ?></td>
              <td><?= h(substr($t['hora_turno'],0,5)) ?></td>
              <td><?php $e=strtolower($t['estado']); $cl=$e==='pendiente'?'warn':($e==='cancelado'?'bad':'ok'); ?><span class="pill <?= $cl ?>"><?= h($t['estado']) ?></span></td>
              <td style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <form method="post" class="validate-form" style="display:flex;gap:6px;align-items:center">
                  <input type="hidden" name="action" value="reprogramar_turno">
                  <input type="hidden" name="turno_id" value="<?= (int)$t['id'] ?>">
                  <select name="auto_id"><?php foreach ($vehiculos as $v): ?><option value="<?= (int)$v['id_auto'] ?>"><?= h($v['marca'].' '.$v['modelo']) ?></option><?php endforeach; ?></select>
                  <input type="date" name="fecha">
                  <input type="time" name="hora">
                  <button class="btn ghost">Reprogramar</button>
                </form>
                <form method="post" class="form-cancelar">
                  <input type="hidden" name="action" value="cancelar_turno">
                  <input type="hidden" name="turno_id" value="<?= (int)$t['id'] ?>">
                  <button class="btn ghost">Cancelar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Historial -->
    <section id="tab-historial" style="display:none">
      <div class="card">
        <h3>Historial de servicios</h3>
        <table class="table">
          <thead><tr><th>#</th><th>Fecha ingreso</th><th>Trabajo</th><th>Estado</th><th>Vehículo</th></tr></thead>
          <tbody>
          <?php if (!$historial): ?><tr><td colspan="5">Aún no hay trabajos registrados.</td></tr>
          <?php else: foreach ($historial as $hrow): ?><tr><td>OR-<?= (int)$hrow['id'] ?></td><td><?= h($hrow['fecha_ingreso']) ?></td><td><?= h($hrow['trabajo']) ?></td><td><?= h($hrow['estado']) ?></td><td><?= h($hrow['marca'].' '.$hrow['modelo'].' '.$hrow['anio']) ?></td></tr><?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Perfil -->
    <section id="tab-perfil" style="display:none">
      <div class="card">
        <h3>Mis datos</h3>
        <form method="post" class="row validate-form">
          <input type="hidden" name="action" value="update_perfil">
          <div class="col-6"><label>Nombre</label><input name="n" value="<?= h($nom) ?>" required></div>
          <div class="col-6"><label>Apellido</label><input name="a" value="<?= h($ape) ?>" required></div>
          <div class="col-6"><label>DNI</label><input name="dni" value="<?= h($dniAct) ?>" required></div>
          <div class="col-6"><label>Email</label><input type="email" name="email" value="<?= h($emailAct) ?>"></div>
          <div class="col-12"><button class="btn">Guardar cambios</button></div>
        </form>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
      © <?= date('Y') ?> Fixtime — Todos los derechos reservados
      <div class="social">
        <a href="mailto:contacto@fixtime.com" title="Email">📧</a>
        <a href="https://wa.me/5491123456789" target="_blank" title="WhatsApp">💬</a>
        <a href="https://facebook.com/fixtime" target="_blank" title="Facebook">📘</a>
        <a href="https://instagram.com/fixtime" target="_blank" title="Instagram">📷</a>
      </div>
    </footer>
  </main>
</div>

<script>
// ===== Tabs
const nav=document.getElementById('nav');
const sections={home:document.getElementById('tab-home'),vehiculos:document.getElementById('tab-vehiculos'),turnos:document.getElementById('tab-turnos'),historial:document.getElementById('tab-historial'),perfil:document.getElementById('tab-perfil')};
nav.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;[...nav.querySelectorAll('button')].forEach(x=>x.classList.remove('active'));b.classList.add('active');const tab=b.dataset.tab;Object.values(sections).forEach(s=>s.style.display='none');sections[tab].style.display='block';window.scrollTo({top:0,behavior:'smooth'});});

// ===== Sidebar móvil
const sidebar=document.getElementById('sidebar');const overlay=document.getElementById('overlay');const btnMenu=document.getElementById('btnMenu');
function openSidebar(){sidebar.classList.add('open');overlay.classList.add('show');}
function closeSidebar(){sidebar.classList.remove('open');overlay.classList.remove('show');}
btnMenu&&btnMenu.addEventListener('click',openSidebar);overlay&&overlay.addEventListener('click',closeSidebar);
nav.addEventListener('click',()=>{if(window.matchMedia('(max-width:1080px)').matches)closeSidebar();});

// ===== Tema claro/oscuro
const root=document.documentElement;const btnTheme=document.getElementById('themeToggle');const btnThemeMobile=document.getElementById('themeToggleMobile');
function applyTheme(t){if(t==='light'){root.classList.add('theme-light');setIcon('☀️');}else{root.classList.remove('theme-light');setIcon('🌙');}localStorage.setItem('fixtime_theme',t);}
function setIcon(i){if(btnTheme)btnTheme.textContent=i;if(btnThemeMobile)btnThemeMobile.textContent=i;}
(function initTheme(){applyTheme(localStorage.getItem('fixtime_theme')||'dark');})();
function toggleTheme(){const isLight=root.classList.contains('theme-light');applyTheme(isLight?'dark':'light');}
btnTheme&&btnTheme.addEventListener('click',toggleTheme);btnThemeMobile&&btnThemeMobile.addEventListener('click',toggleTheme);

// ===== SweetAlert flash
<?php if ($flash): ?>
Swal.fire({toast:true,position:'top-end',icon:'<?= $flashType ?>',title:'<?= h($flash) ?>',showConfirmButton:false,timer:3000,timerProgressBar:true});
<?php endif; ?>

// ===== Validaciones
document.querySelectorAll('.validate-form').forEach(form=>{
  form.addEventListener('submit',e=>{
    const inputs=form.querySelectorAll('input[required], select[required]');
    for(let inp of inputs){
      if(!inp.value.trim()){
        e.preventDefault();
        Swal.fire({icon:'error',title:'Faltan datos',text:'Por favor completá todos los campos obligatorios'});
        inp.focus();return;
      }
    }
  });
});

// ===== Confirmar cancelar turno
document.querySelectorAll('.form-cancelar').forEach(f=>{
  f.addEventListener('submit',e=>{
    e.preventDefault();
    Swal.fire({
      title:"¿Cancelar turno?",
      text:"Esta acción no se puede deshacer",
      icon:"warning",
      showCancelButton:true,
      confirmButtonText:"Sí, cancelar",
      cancelButtonText:"No",
      confirmButtonColor:"#ef4444"
    }).then((r)=>{if(r.isConfirmed)f.submit();});
  });
});
</script>
</body>
</html>

