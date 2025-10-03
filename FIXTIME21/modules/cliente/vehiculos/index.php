<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
require_once __DIR__ . '/../../../clases/EmpresaRepositorio.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$empresaId = (int)($_SESSION['empresa_id'] ?? 0);
$repo = new VehiculoRepositorio();
$vehiculos = $repo->listarParaUsuario($uid, $empresaId > 0 ? $empresaId : null);

$ok = isset($_GET['ok']) ? (string)$_GET['ok'] : null;
$e  = isset($_GET['e'])  ? (string)$_GET['e']  : null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mis vehículos</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Inter,Arial; background:#0b1020; color:#e5edf9; margin:0;}
    .wrap{max-width:1100px; margin:24px auto; padding:16px;}
    .top{display:flex; justify-content:space-between; align-items:center; margin-bottom:14px}
    .card{background:#0f1b34cc; border-radius:16px; padding:20px; box-shadow:0 6px 24px rgba(0,0,0,.35)}
    .alert{padding:12px 14px; border-radius:12px; background:#15254d; color:#d9e4ff; margin:0 0 12px}
    .error{background:#3a1733; color:#ffd8e4}
    table{width:100%; border-collapse:collapse}
    th,td{padding:10px 12px; border-bottom:1px solid #233563; text-align:left}
    .btn{background:#3c7cff; border:none; color:white; padding:8px 12px; border-radius:8px; text-decoration:none}
    .muted{opacity:.85; font-size:13px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <h1 style="margin:0">Mis vehículos</h1>
      <a class="btn" href="<?= h($base) ?>/modules/cliente/vehiculos/nuevo.php">Nuevo</a>
    </div>

    <div class="card">
      <p class="muted">Acá verás tanto tus vehículos <strong>personales</strong> como los <strong>empresariales</strong> asociados a la empresa que guardaste en el panel <em>Empresa</em>.</p>

      <?php if ($ok): ?><div class="alert"><?= h($ok) ?></div><?php endif; ?>
      <?php if ($e):  ?><div class="alert error"><?= h($e) ?></div><?php endif; ?>

      <table>
        <thead>
          <tr>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Año</th>
            <th>Color</th>
            <th>KM</th>
            <th>Titular</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$vehiculos): ?>
          <tr><td colspan="6" class="muted">Sin vehículos.</td></tr>
        <?php else: foreach ($vehiculos as $v): ?>
          <tr>
            <td><?= h($v['marca']) ?></td>
            <td><?= h($v['modelo']) ?></td>
            <td><?= h((string)$v['anio']) ?></td>
            <td><?= h((string)$v['color']) ?></td>
            <td><?= h((string)$v['km']) ?></td>
            <td><?= $v['titular']==='Empresa' ? 'Empresa: '.h((string)$v['empresa']) : 'Personal' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
