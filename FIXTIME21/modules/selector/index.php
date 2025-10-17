<?php
declare(strict_types=1);

require_once __DIR__ . '/../../clases/Sesion.php';
require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);
$nom = (string)($_SESSION['nombre']   ?? '');
$ape = (string)($_SESSION['apellido'] ?? '');

$roles = $_SESSION['roles'] ?? ['cliente'];
if (!is_array($roles)) { $roles = [$roles]; }

if ($uid > 0 && !in_array('admin', $roles, true)) {
  $repoA = new AdministradorRepositorio();
  if ($repoA->esAdmin($uid)) {
    $roles[] = 'admin';
    $_SESSION['roles'] = array_values(array_unique($roles));
    $_SESSION['es_admin'] = true;
  }
}
$roles = array_values(array_unique(array_map('strval', $roles)));
$esAdmin = false;
$esEmpleado = false;
$cargoEmpleado = null;

try {
    $db = Conexion::obtener();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 🔹 Verificar si es administrador
    $st = $db->prepare("SELECT 1 FROM Administradores WHERE Persona_id=? AND (fecha_baja IS NULL OR fecha_baja > CURDATE()) LIMIT 1");
    $st->execute([$uid]);
    $esAdmin = (bool)$st->fetchColumn();

    // 🔹 Verificar si es empleado y obtener cargo
    $st = $db->prepare("
        SELECT c.descripcion 
        FROM Empleados e
        JOIN Cargos c ON c.id = e.Cargo_id
        WHERE e.Persona_id=? AND (e.fecha_baja IS NULL OR e.fecha_baja > CURDATE())
        LIMIT 1
    ");
    $st->execute([$uid]);
    $cargoEmpleado = $st->fetchColumn();

    if ($cargoEmpleado) {
        $esEmpleado = true;
        // Normalizo a minúsculas para comparar
        $cargoEmpleado = mb_strtolower($cargoEmpleado, 'UTF-8');
    }

} catch (Throwable $e) {
    $esAdmin = false;
    $esEmpleado = false;
    $cargoEmpleado = null;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Fixtime — Selector de Panel</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="stylesheet" href="<?= $base ?>/publico/app.css">
<style>
  body{
    min-height:100vh;margin:0;display:flex;justify-content:center;align-items:center;
    background:
      radial-gradient(1000px 500px at 80% -10%, rgba(59,130,246,.22), transparent 70%),
      radial-gradient(800px 400px at 10% 110%, rgba(37,99,235,.16), transparent 60%),
      var(--bg);
    font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial;
    padding:24px;
  }
  .card.selector{
    display:flex;flex-direction:column;gap:18px;
    text-align:center;padding:40px 32px;border-radius:20px;background:var(--card);
    border:1px solid var(--border);box-shadow:0 12px 40px rgba(0,0,0,.35);
    width:min(680px, 96%);
  }
  .logo-wrap{
    width:124px;height:124px;margin:0 auto 6px;display:grid;place-items:center;border-radius:26px;
    background: radial-gradient(60% 60% at 50% 40%, rgba(255,255,255,.25), transparent 62%),
                linear-gradient(135deg, rgba(59,130,246,.45), rgba(37,99,235,.35));
    box-shadow:0 0 90px rgba(59,130,246,.45), inset 0 1px 0 rgba(255,255,255,.35);
    border:1px solid rgba(59,130,246,.5);
  }
  .logo-wrap img{width:80px;height:80px;object-fit:contain;filter:drop-shadow(0 10px 16px rgba(0,0,0,.35));}
  h1{margin:8px 0 0;font-size:1.9rem;font-weight:900;}
  .sub{color:var(--muted);margin-bottom:8px}
  .grid{display:flex;gap:18px;justify-content:center;flex-wrap:wrap}
  .btn{
    display:block;min-width:200px;text-decoration:none;text-align:center;
    padding:14px 18px;border-radius:14px;font-weight:800;
    background:linear-gradient(135deg,var(--primary), var(--primary-2, #2563eb));
    color:#0b1220;border:1px solid rgba(59,130,246,.55);
    box-shadow:0 10px 26px rgba(59,130,246,.35);
    transition:transform .15s ease;
  }
  .btn:hover{transform:translateY(-2px)}
  .btn.ghost{
    display:block;width:100%;text-align:center;
    background:transparent;
    color:var(--text);
    border:1px solid rgba(157,176,208,.30);
    border-radius:14px;
    padding:14px 18px;
    font-weight:800;
    text-decoration:none; 
    box-shadow:none;
    transition:all .15s ease;
  }
  .btn.ghost:hover{
    background:rgba(255,255,255,0.05);
    transform:translateY(-2px);
  }
  .footer-actions{margin-top:auto;padding-top:20px}
  .muted{color:var(--muted)}
</style>
</head>
<body>
  <div class="card selector">
    <div class="logo-wrap" aria-hidden="true">
      <img src="<?= $base ?>/publico/widoo.png" alt="Logo FixTime">
    </div>

    <div>
      <h1>Hola, <?= h(trim("$nom $ape")) ?> 👋</h1>
      <div class="sub">¿A qué panel te gustaría acceder?</div>
    </div>

    <div class="grid" role="navigation" aria-label="Selector de paneles">
  <?php if (in_array('cliente', $roles, true)): ?>
    <a class="btn" href="<?= $base ?>/modules/cliente/">Panel de Cliente</a>
  <?php endif; ?>

  <?php if ($esEmpleado): ?>
    <?php if ($cargoEmpleado === 'recepcionista'): ?>
      <a class="btn" href="<?= $base ?>/modules/empleado/recepcionista/">Panel de Recepcionista</a>
    <?php elseif ($cargoEmpleado === 'mecánico' || $cargoEmpleado === 'mecanico'): ?>
      <a class="btn" href="<?= $base ?>/modules/empleado/mecanico/">Panel de Mecánico</a>
    <?php else: ?>
      <a class="btn" href="<?= $base ?>/modules/empleado/">Panel de Empleado</a>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($esAdmin): ?>
    <a class="btn" href="<?= $base ?>/modules/admin/">Panel de Administrador</a>
  <?php endif; ?>

  <?php if (!array_intersect(['cliente','empleado','admin'], $roles)): ?>
    <div class="muted" style="width:100%">No tenés roles asignados. Pedile a un administrador que te habilite.</div>
  <?php endif; ?>
</div>


    <!-- Footer: botón cerrar sesión -->
    <div class="footer-actions">
      <a class="btn ghost" href="<?= $base ?>/modules/login/logout.php">Cerrar sesión</a>
    </div>
  </div>
</body>
</html>
