<?php
declare(strict_types=1);
require_once __DIR__ . '/../../clases/TurnoRepositorio.php';

$repoT = new TurnoRepositorio();
$turnos = $repoT->listarTodosLosTurnos();

// Offset para navegar semanas
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$hoy    = new DateTimeImmutable();
$lunes  = $hoy->modify("monday this week")->modify("$offset week");

$diasSemana = ['Lunes','Martes','Miércoles','Jueves','Viernes'];
$semana = [];
for ($i=0; $i<5; $i++) {
  $semana[] = $lunes->modify("+$i day");
}

// Agrupar turnos por fecha
$turnosSemana = [];
foreach ($turnos as $t) {
  if (!empty($t['fecha_turno'])) {
    $turnosSemana[$t['fecha_turno']][] = $t;
  }
}

require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

// Seguridad: solo administradores
require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();
$uid   = (int)($_SESSION['uid'] ?? 0);
if (!$repoA->esAdmin($uid)) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

// Helper h() si no existiera
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Datos mínimos para saludo/KPIs
$nom = $_SESSION['nombre']  ?? 'Admin';
$ape = $_SESSION['apellido']?? '';
$kpi_empleados = '—';
$kpi_bloqueos  = '—';
$kpi_vehiculos = '—';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fixtime — Panel de Administrador</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
/* Layout general */
.app{display:grid;grid-template-columns:320px 1fr;min-height:100vh}
.sidebar{padding:22px;background:linear-gradient(180deg,var(--panel),var(--bg));border-right:1px solid rgba(157,176,208,.15);position: sticky; top:0; height:100vh; z-index:40;}
.brand{display:flex;gap:12px;align-items:center;margin-bottom:22px}
.brand-badge{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(40px 30px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2));box-shadow:0 12px 30px var(--ring), inset 0 1px 0 rgba(255,255,255,.25)}
.brand-badge img{width:32px;height:32px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.brand-name{font-weight:800;letter-spacing:.35px;font-size:22px}
.brand-sub{opacity:.8;font-size:12px}
.theme-btn{margin-left:auto;appearance:none;border:1px solid rgba(157,176,208,.28);background:rgba(255,255,255,.06);color:var(--text);border-radius:12px;padding:10px 12px;cursor:pointer;font-size:16px;box-shadow:0 6px 16px rgba(0,0,0,.2)}
.nav{display:flex;flex-direction:column;gap:12px;margin-top:10px}
.nav a, .nav button{display:flex;gap:12px;align-items:center;justify-content:flex-start;padding:14px 16px;border-radius:14px;border:1px solid rgba(157,176,208,.18);background:rgba(255,255,255,.03);color:var(--text);cursor:pointer;font-size:16px;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.25);text-decoration:none}
.nav a.active, .nav button.active{background:linear-gradient(135deg, rgba(59,130,246,.20), rgba(37,99,235,.20));border-color:rgba(59,130,246,.55);box-shadow:0 10px 28px var(--ring)}
.topbar-salir{display:block;margin-top:14px;text-align:center;text-decoration:none}
.main{padding:26px 32px;display:flex;flex-direction:column;min-height:100vh}
.hero{display:flex;align-items:center;gap:16px;background:linear-gradient(135deg, rgba(59,130,246,.22), rgba(37,99,235,.18));border:1px solid rgba(59,130,246,.40);border-radius:var(--radius);padding:18px;box-shadow:0 14px 32px var(--ring);margin-bottom:16px}
.hero .avatar{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(24px 16px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2))}
.hero .avatar img{width:38px;height:38px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.hero .greet{font-weight:800;font-size:22px}
.kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:20px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.03));border:1px solid rgba(157,176,208,.16);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.btn{cursor:pointer;border:0;border-radius:12px;padding:12px 16px;background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#0b1220;font-weight:800;box-shadow:0 12px 28px var(--ring)}
.btn.ghost{background:transparent;color:var(--text);border:1px solid rgba(157,176,208,.30);box-shadow:none}
.muted{color:var(--muted)}
/* Header móvil y sidebar móvil */
.header-mobile{display:none;align-items:center;gap:10px;position:sticky;top:0;z-index:1100;padding:12px 16px;background:rgba(12,23,48,.75);backdrop-filter:blur(8px);border-bottom:1px solid rgba(255,255,255,.06)}
.burger{appearance:none;background:transparent;border:0;color:var(--text);font-size:24px;cursor:pointer}
.sidebar__overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000}
.sidebar__overlay.show{display:block}
@media (max-width:1080px){
  .app{grid-template-columns:1fr}
  .sidebar{position:fixed; inset:0 auto 0 0; width:84%; max-width:320px; height:100vh; transform:translateX(-105%); transition:transform .22s ease; z-index:1001; box-shadow:var(--shadow)}
  .sidebar.open{transform:translateX(0)}
  .header-mobile{display:flex}
  .kpis{grid-template-columns:1fr}
}
/* Tema claro */
.theme-light{
  --bg:#f3f6fc;--panel:#ffffff;--panel-2:#f7f9ff;--card:#ffffff;--muted:#5b6b85;--text:#0b1220;--ring:rgba(59,130,246,.28);--shadow:0 8px 26px rgba(15,23,42,.08)
}
.theme-light body{
  background:radial-gradient(1000px 500px at 80% -10%, rgba(59,130,246,.12), transparent 70%),radial-gradient(700px 380px at 10% 110%, rgba(37,99,235,.10), transparent 60%),var(--bg)
}
.theme-light .sidebar{background:linear-gradient(180deg,var(--panel),#eaf0ff);border-right:1px solid rgba(15,23,42,.06)}
.theme-light .nav a, .theme-light .nav button{background:#fff;border-color:rgba(15,23,42,.08)}
.theme-light .card{background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:var(--shadow)}
.theme-light .theme-btn{background:#fff;border-color:rgba(15,23,42,.08);color:#0b1220}

.nav .btn.topbar-salir{
  display:block !important;
  text-align:center !important;
  font-weight:800 !important;  /* más específico que .nav a */
}

</style>
</head>
<body>

<!-- Header móvil con hamburguesa -->
<div class="header-mobile">
  <button class="burger" id="btnMenu" aria-label="Abrir menú" aria-expanded="false">☰</button>
  <div style="display:flex;align-items:center;gap:10px">
    <div class="brand-badge" style="width:36px;height:36px;border-radius:10px"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
    <strong>Fixtime</strong>
  </div>
  <button id="themeToggleMobile" class="theme-btn" title="Cambiar tema">🌙</button>
</div>

<div class="app">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-badge"><img src="<?= $base ?>/publico/widoo.png" alt="Fixtime"></div>
      <div style="flex:1">
        <div class="brand-name">Fixtime</div>
        <div class="brand-sub">Panel de Administrador</div>
      </div>
      <button id="themeToggle" class="theme-btn" title="Cambiar tema">🌙</button>
    </div>

    <nav class="nav" id="nav">
      <!-- Orden idéntico al cliente: principales arriba, luego los de salida -->
      <a class="active" href="<?= $base ?>/modules/admin/">🏠 Inicio</a>
      <a href="<?= $base ?>/modules/admin/empleados.php">👥 Empleados</a>
      <a href="<?= $base ?>/modules/admin/calendario.php">🗓️ Calendario</a>
      <a href="<?= $base ?>/modules/admin/vehiculos.php">🚗 Listar vehículos</a>
      <a href="<?= $base ?>/modules/admin/perfil.php">👤 Mi perfil</a>

      <!-- Al final, igual que cliente -->
      <a href="<?= $base ?>/modules/selector/index.php" class="btn ghost topbar-salir">⬅️ Volver al selector</a>
      <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">Cerrar sesión</a>
    </nav>
  </aside>
  <div class="sidebar__overlay" id="overlay"></div>

  <!-- Contenido principal -->
  <main class="main">
    <div class="hero">
      <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
      <div style="flex:1">
        <div class="greet">¡Hola, <?= h($nom.' '.$ape) ?>!</div>
        <div class="hint">Gestioná empleados, calendario y vehículos desde un solo lugar.</div>
      </div>
    </div>

    <!-- Accesos rápidos -->
    <section class="card">
      <h3 style="margin:0 0 10px">Accesos rápidos</h3>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn" href="<?= $base ?>/modules/admin/empleados.php">🧑🏻‍💼 Nuevo Empleado</a>
        <a class="btn" href="<?= $base ?>/modules/admin/calendario.php">🗓️ Configurar Calendario</a>
        <a class="btn" href="<?= $base ?>/modules/admin/vehiculos.php">🙋🏻 Vehículos de Clientes</a>
      </div>

      <!-- Mini calendario semanal -->
      <section class="card" style="margin-top:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <a href="?offset=<?= $offset-1 ?>" class="btn ghost">⬅ Semana anterior</a>
          <h3 style="margin:0">Turnos de la semana (<?= $lunes->format('d/m/Y') ?>)</h3>
          <a href="?offset=<?= $offset+1 ?>" class="btn ghost">Semana siguiente ➡</a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px">
          <?php foreach ($semana as $i=>$dia):
            $fecha = $dia->format('Y-m-d');
            $turnosDia = $turnosSemana[$fecha] ?? [];
          ?>
            <div style="background:var(--panel-2);border:1px solid rgba(157,176,208,.18);border-radius:12px;padding:12px;min-height:220px;display:flex;flex-direction:column">
              <strong style="margin-bottom:10px;font-size:15px;color:var(--text)">
                <?= $diasSemana[$i] ?> <?= $dia->format('d/m') ?>
              </strong>
              <?php if (!$turnosDia): ?>
                <div style="font-size:13px;color:var(--muted);margin-top:auto">Sin turnos</div>
              <?php else: foreach ($turnosDia as $t): ?>
                <div style="background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.35);border-radius:8px;padding:6px 10px;font-size:13px;margin-bottom:8px">
                  <div><b style="color:var(--brand)"><?= h(substr($t['hora_turno'],0,5)) ?></b> — <?= h($t['marca'].' '.$t['modelo']) ?></div>
                  <small style="display:block;color:var(--muted);margin-top:2px">
                    <?= h($t['nombre'].' '.$t['apellido']) ?> — <?= h($t['estado']) ?>
                  </small>
                </div>
              <?php endforeach; endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </section>

    <footer class="card" style="margin-top:auto;text-align:center;font-size:13px;color:var(--muted)">
      © <?= date('Y') ?> Fixtime — Administrador • Todos los derechos reservados
      <div style="margin-top:8px;display:flex;justify-content:center;gap:14px">
        <a href="mailto:contacto@fixtime.com" title="Email">📧</a>
        <a href="" target="_blank" title="WhatsApp">💬</a>
        <a href="" target="_blank" title="Facebook">📘</a>
        <a href="" target="_blank" title="Instagram">📷</a>
      </div>
    </footer>
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

  // ====== Tema persistente ======
  function setIcon(btn){ if(!btn) return; btn.textContent = root.classList.contains('theme-light') ? '☀️' : '🌙'; }
  const saved = localStorage.getItem('theme') || 'dark';
  if (saved === 'light') root.classList.add('theme-light');
  setIcon(tDesk); setIcon(tMob);
  [tDesk, tMob].forEach(b => b && b.addEventListener('click', () => {
    root.classList.toggle('theme-light');
    localStorage.setItem('theme', root.classList.contains('theme-light') ? 'light' : 'dark');
    setIcon(tDesk); setIcon(tMob);
  }));

  // ====== Menú lateral (hamburguesa) ======
  function openMenu(){
    sidebar.classList.add('open');
    overlay.classList.add('show');
    btnMenu?.setAttribute('aria-expanded','true');
  }
  function closeMenu(){
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
    btnMenu?.setAttribute('aria-expanded','false');
  }
  function toggleMenu(){
    (sidebar.classList.contains('open') ? closeMenu : openMenu)();
  }

  btnMenu && btnMenu.addEventListener('click', toggleMenu);
  overlay && overlay.addEventListener('click', closeMenu);

  // Cerrar al navegar desde el menú en móvil
  const nav = document.getElementById('nav');
  nav && nav.addEventListener('click', (e) => {
    if (e.target.closest('a,button') && window.matchMedia('(max-width:1080px)').matches) {
      closeMenu();
    }
  });

  // Cerrar con tecla Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) closeMenu();
  });

  // Si se agranda la pantalla, aseguramos menú cerrado en desktop
  window.addEventListener('resize', () => {
    if (!window.matchMedia('(max-width:1080px)').matches) closeMenu();
  });
})();
</script>

</body>
</html>
