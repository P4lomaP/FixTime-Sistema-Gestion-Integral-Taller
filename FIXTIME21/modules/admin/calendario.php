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
        $turnoId  = (int)($_POST['turno_id'] ?? 0);
        $fecha    = $_POST['fecha_turno'] !== '' ? $_POST['fecha_turno'] : null;
        $hora     = $_POST['hora_turno']  !== '' ? $_POST['hora_turno']  : null;
        $estadoId = (int)($_POST['estado_id'] ?? 1);

        if ($turnoId > 0 && $estadoId > 0 && $repo->actualizarTurno($turnoId, $fecha, $hora, $estadoId)) {
            
            // 🔹 Después de actualizar el turno, buscar datos del cliente
            $datos = $repo->obtenerEmailClientePorTurno($turnoId);

            if ($datos && filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                $to      = $datos['email'];
                $subject = "Turno asignado en Fixtime";
                $message = "
                    <h2>Hola {$datos['nombre']} {$datos['apellido']},</h2>
                    <p>Se te asignó un turno con los siguientes datos:</p>
                    <ul>
                        <li><b>Fecha:</b> {$datos['fecha_turno']}</li>
                        <li><b>Hora:</b> {$datos['hora_turno']}</li>
                    </ul>
                    <p>Si necesitás reprogramar, podés hacerlo desde tu panel de cliente.</p>
                    <br>
                    <p>Saludos,<br>Equipo Fixtime</p>
                ";

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: Fixtime <no-reply@fixtimear.site>\r\n";

                @mail($to, $subject, $message, $headers);
            }

            header('Location: '.$base.'/modules/admin/calendario.php?ok=1');
            exit;
        } else {
            header('Location: '.$base.'/modules/admin/calendario.php?error=1');
            exit;
        }
    }

    if ($action === 'eliminar_turno') {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        if ($turnoId > 0 && $repo->eliminarTurno($turnoId)) {
            header('Location: '.$base.'/modules/admin/calendario.php?ok=1'); 
            exit;
        } else {
            header('Location: '.$base.'/modules/admin/calendario.php?error=1'); 
            exit;
        }
    }



    if ($action === 'cancelar_turno') {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        if ($turnoId > 0) {
            // Buscar el ID de "cancelado" en la tabla Estados_Turnos
            $canceladoId = $repoE->obtenerIdPorDescripcion('cancelado');
            if ($canceladoId && $repo->actualizarTurno($turnoId, null, null, $canceladoId)) {
                header('Location: '.$base.'/modules/admin/calendario.php?ok=1'); exit;
            } else {
                header('Location: '.$base.'/modules/admin/calendario.php?error=1'); exit;
            }
        }
    }
}

$ok    = $_GET['ok']    ?? null;
$error = $_GET['error'] ?? null;

// Datos para el header
$nom = $_SESSION['nombre']  ?? 'Admin';
$ape = $_SESSION['apellido']?? '';
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

/* Estilos específicos para calendario */
.row {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}

.col-12 { grid-column: span 12; }
.col-6 { grid-column: span 6; }
.col-4 { grid-column: span 4; }
.col-3 { grid-column: span 3; }

label {
  font-size: 12px;
  color: var(--muted);
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
}

input, select {
  width: 100%;
  background: var(--panel-2);
  border: 1px solid rgba(157, 176, 208, .2);
  color: var(--text);
  border-radius: 12px;
  padding: 12px 14px;
  font-family: inherit;
  font-size: 14px;
}

input:read-only {
  background: rgba(157, 176, 208, .08);
  color: var(--muted);
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  margin-top: 16px;
}

.table th, .table td {
  padding: 12px 10px;
  border-bottom: 1px solid rgba(157, 176, 208, .14);
  text-align: left;
}

.table th {
  font-weight: 700;
  color: var(--muted);
  font-size: 13px;
}

.pill {
  display: inline-block;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
}

.ok { background: rgba(16, 185, 129, .12); color: #34d399; }
.warn { background: rgba(245, 158, 11, .12); color: #fbbf24; }
.bad { background: rgba(239, 68, 68, .12); color: #f87171; }

.alert {
  padding: 14px 18px;
  border-radius: 12px;
  font-weight: 700;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
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

/* Responsive para tabla */
@media (max-width: 740px) {
  .table.responsive thead { display: none; }
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
}

.nav .btn.topbar-salir{
  display:block !important;
  text-align:center !important;
  font-weight:800 !important;
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
      <!-- Mismo orden que el primer archivo -->
      <a href="<?= $base ?>/modules/admin/">🏠 Inicio</a>
      <a href="<?= $base ?>/modules/admin/empleados.php">👥 Empleados</a>
      <a class="active" href="<?= $base ?>/modules/admin/calendario.php">🗓️ Calendario</a>
      <a href="<?= $base ?>/modules/admin/vehiculos.php">🚗 Listar vehículos</a>
      <a href="<?= $base ?>/modules/admin/perfil.php">👤 Mi perfil</a>

      <!-- Al final, igual que el primer archivo -->
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

    <!-- Alertas -->
    <?php if ($ok): ?>
      <div class="alert success">✅ La accion se realizo correctamente</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error">❌ Error al procesar el turno</div>
    <?php endif; ?>

    <!-- Sección Turnos -->
    <section id="tab-turnos">
      <div class="card" style="margin-bottom:16px">
        <h3 style="margin:0 0 16px 0">Gestión de turno</h3>
        <form method="post" class="row validate-form" id="form-turno">
          <input type="hidden" name="action" value="update_turno">
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

          <div class="col-12">
            <button class="btn">Guardar cambios</button>
          </div>
        </form>
      </div>

      <div class="card">
        <h3 style="margin:0 0 16px 0">Listado de turnos</h3>
        <table class="table responsive">
          <thead>
            <tr>
              <th>#</th>
              <th>Cliente</th>
              <th>Vehículo</th>
              <th>Motivo</th>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$turnos): ?>
            <tr><td colspan="8" style="text-align:center;padding:20px;color:var(--muted)">No hay turnos programados</td></tr>
          <?php else: foreach ($turnos as $t): ?>
            <tr>
              <td data-label="ID">T-<?= (int)$t['id'] ?></td>
              <td data-label="Cliente"><?= h(($t['nombre'] ?? '').' '.($t['apellido'] ?? '')) ?></td>
              <td data-label="Vehículo"><?= h(($t['marca'] ?? '').' '.($t['modelo'] ?? '').' ('.$t['anio'].')') ?></td>
              <td data-label="Motivo"><?= h($t['motivo'] ?? '') ?></td>
              <td data-label="Fecha"><?= h($t['fecha_turno'] ?? '-') ?></td>
              <td data-label="Hora"><?= h($t['hora_turno'] ?? '-') ?></td>
              <td data-label="Estado">
                <span class="pill <?= 
                  $t['estado'] === 'confirmado' ? 'ok' : 
                  ($t['estado'] === 'pendiente' ? 'warn' : 'bad')
                ?>">
                  <?= h(ucfirst($t['estado'])) ?>
                </span>
              </td>
              <td data-label="Acciones">
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

                <!-- Botón Eliminar -->
                <form method="post" class="form-eliminar" style="display:inline">
                  <input type="hidden" name="action" value="eliminar_turno">
                  <input type="hidden" name="turno_id" value="<?= (int)$t['id'] ?>">
                  <button type="button" class="btn ghost btn-eliminar">🗑️ Eliminar</button>
                </form>

              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
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

<script>
document.addEventListener("DOMContentLoaded", () => {
  // Auto-ocultar alertas después de 5 segundos
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity 0.6s ease';
      setTimeout(() => alert.remove(), 600);
    }, 5000);
  });

  // Funcionalidad de edición de turnos
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
      
      // Scroll suave al formulario
      window.scrollTo({ top: form.offsetTop - 50, behavior: "smooth" });
    });
  });
});
</script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".btn-eliminar").forEach(boton => {
    boton.addEventListener("click", () => {
      const form = boton.closest("form");
      Swal.fire({
        title: '¿Estás seguro?',
        text: "No podrás recuperar este turno después.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });
});
</script>

</body>
</html>