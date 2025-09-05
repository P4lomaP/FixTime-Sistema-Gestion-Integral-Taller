<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$repoV = new VehiculoRepositorio();
$vehiculos = $repoV->listarPorPersona((int)$_SESSION['uid']);
$ok = $_GET['ok'] ?? null;
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis vehículos</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="stylesheet" href="<?= $base ?>/publico/app.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<main class="page-center">
  <div class="card">
    <h2>Mis vehículos</h2>
    <?php if ($ok): ?><div class="msg-ok">Guardado con éxito.</div><?php endif; ?>
    <?php if ($error): ?><div class="msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="mb-16">
      <a class="btn" href="<?= $base ?>/modules/cliente/vehiculos/nuevo.php">Registrar vehículo</a>
      <a class="btn secundario" href="<?= $base ?>/modules/cliente/">Volver</a>
    </div>
    <?php if (!$vehiculos): ?>
      <p>No tenés vehículos cargados.</p>
    <?php else: ?>
      <div class="tabla">
        <div class="tabla-row tabla-head">
          <div>Marca</div><div>Modelo</div><div>Año</div><div>Color</div><div>KM</div>
        </div>
        <?php foreach ($vehiculos as $v): ?>
          <div class="tabla-row">
            <div><?= htmlspecialchars($v['marca']) ?></div>
            <div><?= htmlspecialchars($v['modelo']) ?></div>
            <div><?= htmlspecialchars($v['anio']) ?></div>
            <div><?= htmlspecialchars($v['color']) ?></div>
            <div><?= htmlspecialchars($v['km']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
