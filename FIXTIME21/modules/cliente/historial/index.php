<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/TurnoRepositorio.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$repo = new TurnoRepositorio();
$items = $repo->historialServicios((int)$_SESSION['uid']); 
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Historial de servicios</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="stylesheet" href="<?= $base ?>/publico/app.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<main class="page-center">
  <div class="card">
    <h2>Historial de servicios</h2>
    <div class="mb-16"><a class="btn secundario" href="<?= $base ?>/modules/cliente/">Volver</a></div>
    <?php if (!$items): ?>
      <p>Sin registros.</p>
    <?php else: ?>
      <div class="tabla">
        <div class="tabla-row tabla-head">
          <div>Fecha ingreso</div><div>Trabajo</div><div>Estado</div><div>Vehículo</div>
        </div>
        <?php foreach ($items as $r): ?>
          <div class="tabla-row">
            <div><?= htmlspecialchars($r['fecha_ingreso']) ?></div>
            <div><?= htmlspecialchars($r['trabajo']) ?></div>
            <div><?= htmlspecialchars($r['estado']) ?></div>
            <div><?= htmlspecialchars($r['marca'].' '.$r['modelo'].' '.$r['anio']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
