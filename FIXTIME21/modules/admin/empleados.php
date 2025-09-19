<?php
declare(strict_types=1);

require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();
if (!$repoA->esAdmin((int)($_SESSION['uid'] ?? 0))) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

require_once __DIR__ . '/../../clases/Conexion.php';
require_once __DIR__ . '/../../clases/EmpleadoRepositorio.php';
$repoE = new EmpleadoRepositorio();
$empleados = $repoE->listar();

$nombre   = $_SESSION['nombre']  ?? 'Admin';
$apellido = $_SESSION['apellido']?? '';
$msj = $_GET['ok'] ?? ($_GET['err'] ?? '');

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fixtime — Empleados (Admin)</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
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
.kgrid{display:grid;grid-template-columns:1.25fr 1fr;gap:16px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.03));border:1px solid rgba(157,176,208,.16);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
@media(max-width:700px){ .row{grid-template-columns:1fr} }
label{font-size:12px;color:var(--muted)}
input,select{width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px}
.btn{cursor:pointer;border:0;border-radius:12px;padding:12px 16px;background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#0b1220;font-weight:800;box-shadow:0 12px 28px var(--ring)}
.btn.ghost{background:transparent;color:var(--text);border:1px solid rgba(157,176,208,.30);box-shadow:none}
.table{width:100%;border-collapse:collapse;font-size:14px}
.table th,.table td{padding:12px 10px;border-bottom:1px solid rgba(157,176,208,.14);text-align:left}
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
</style>
</head>
<body>

<div class="header-mobile">
  <button class="burger" id="btnMenu" aria-label="Abrir menú" aria-expanded="false">☰</button>
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
      <a class="active" href="<?= $base ?>/modules/admin/empleados.php">👥 Empleados</a>
      <a href="<?= $base ?>/modules/admin/calendario.php">🗓️ Calendario</a>
      <a href="<?= $base ?>/modules/admin/vehiculos.php">🚗 Listar vehículos</a>
      <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">Cerrar sesión</a>
    </nav>
  </aside>
  <div class="sidebar__overlay" id="overlay"></div>

  <main class="main">
    <div class="hero">
      <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
      <div><div style="font-weight:800;font-size:22px">Empleados</div><div style="color:var(--muted)">Alta, edición y bajas de empleados.</div></div>
    </div>

    <section class="card" style="margin:16px 0">
      <form method="get" action="" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
        <strong>Filtrar por cargo:</strong>
        <select name="cargo_filter">
          <option value="">-- Todos --</option>
          <?php foreach(Conexion::obtener()->query("SELECT id, descripcion FROM Cargos ORDER BY descripcion") as $c): 
                $sel = (isset($_GET['cargo_filter']) && $_GET['cargo_filter']!=='' && (int)$_GET['cargo_filter']===(int)$c['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= htmlspecialchars($c['descripcion']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Aplicar</button>
        <?php if(isset($_GET['cargo_filter']) && $_GET['cargo_filter']!==''): ?>
          <a class="btn ghost" href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>">Quitar filtro</a>
        <?php endif; ?>
      </form>
    </section>
    

    <div class="card" style="margin:16px 0;display:flex;align-items:center;gap:12px">
  <form method="post" action="<?= $base ?>/controladores/rol_admin_a_cliente.php" class="row" style="display:flex;gap:8px;align-items:center">
    <strong>🔁 Cambiar rol Admin → Cliente</strong>
    <input name="persona_id" placeholder="ID Persona admin" style="width:160px">
    <button class="btn" type="submit">Convertir</button>
  </form>
  <small style="color:var(--muted)">No modifica contraseñas ni contactos. Solo mueve la relación de <em>Administradores</em> a <em>Clientes</em>.</small>
</div>


    <?php if($msj): ?>
      <div class="card" style="margin-bottom:16px"><?= h($msj) ?></div>
    <?php endif; ?>

    <section class="kgrid">
      <div class="card">
        <h3 style="margin:0 0 10px">Alta rápida</h3>
        <?php
        // Alta empleado con el mismo formulario completo que registro (Persona + Domicilio + Contactos)
        require_once __DIR__ . '/../../clases/Conexion.php';
        $accion = $base . "/controladores/admin_empleado_crear.php";
        $titulo = "Alta de empleado";
        $val = [];
        // Campo de cargo como extra
        ob_start(); ?>
          <label>Cargo
            <select name="cargo_id" required>
              <option value="">-- Seleccionar --</option>
              <?php foreach(Conexion::obtener()->query("SELECT id, descripcion FROM Cargos ORDER BY descripcion") as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['descripcion']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php $extraCampos = ob_get_clean();
        $submitLabel = "Crear empleado";
        include __DIR__ . "/../../plantillas/form_persona_domicilio.php";
        ?>
      </div>

      <div class="card">
        <h3 style="margin:0 0 10px">Notas</h3>
        <ul style="margin:0 0 8px; padding-left:18px; color:var(--muted)">
          <li>El email no se puede repetir (índice único).</li>
          <li>La baja quita la relación de <b>Empleado</b> pero no borra la <b>Persona</b>.</li>
        </ul>
      </div>
    </section>

    <section class="card" style="margin-top:16px">
      <h3 style="margin:0 0 10px">Listado</h3>
      <div style="overflow:auto">
        <table class="table">
          <thead><tr><th>Nombre</th><th>DNI</th><th>Cargo</th><th style="width:220px">Acciones</th></tr></thead>
          <tbody>
          <?php foreach($empleados as $e): ?>
            <tr>
              <td><?= h($e['apellido'].', '.$e['nombre']) ?></td>
              <td><?= h($e['dni']) ?></td>
              <td><?= h($e['cargo']) ?></td>
              <td>
                <a class="btn ghost" href="<?= $base ?>/modules/admin/empleados_editar.php?id=<?= (int)$e['id'] ?>">Editar</a>
                <a class="btn ghost" href="<?= $base ?>/controladores/admin_empleado_baja.php?id=<?= (int)$e['id'] ?>" onclick="return confirm('¿Dar de baja este empleado?');">Baja</a>
              </td>
            </tr>
          <?php endforeach; if(empty($empleados)): ?>
            <tr><td colspan="4" style="color:var(--muted)">No hay empleados cargados.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<script>
(function () {
  const root    = document.documentElement;
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const btnMenu = document.getElementById('btnMenu');
  const tDesk   = document.getElementById('themeToggle');
  const tMob    = document.getElementById('themeToggleMobile');

  function setIcon(btn){ if(!btn) return; btn.textContent = root.classList.contains('theme-light') ? '☀️' : '🌙'; }
  const saved = localStorage.getItem('theme') || 'dark';
  if (saved === 'light') root.classList.add('theme-light');
  setIcon(tDesk); setIcon(tMob);
  [tDesk, tMob].forEach(b => b && b.addEventListener('click', () => {
    root.classList.toggle('theme-light');
    localStorage.setItem('theme', root.classList.contains('theme-light') ? 'light' : 'dark');
    setIcon(tDesk); setIcon(tMob);
  }));

  function openMenu(){ sidebar.classList.add('open'); overlay.classList.add('show'); btnMenu?.setAttribute('aria-expanded','true'); }
  function closeMenu(){ sidebar.classList.remove('open'); overlay.classList.remove('show'); btnMenu?.setAttribute('aria-expanded','false'); }
  function toggleMenu(){ (sidebar.classList.contains('open') ? closeMenu : openMenu)(); }
  btnMenu && btnMenu.addEventListener('click', toggleMenu);
  overlay && overlay.addEventListener('click', closeMenu);

  const nav = document.getElementById('nav');
  nav && nav.addEventListener('click', (e) => {
    if (e.target.closest('a') && window.matchMedia('(max-width:1080px)').matches) closeMenu();
  });

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && sidebar.classList.contains('open')) closeMenu(); });
  window.addEventListener('resize', () => { if (!window.matchMedia('(max-width:1080px)').matches) closeMenu(); });
})();
</script>
</body>
</html>

