<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
require_once __DIR__ . '/../../../clases/TurnoRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$repoV     = new VehiculoRepositorio();
$vehiculos = $repoV->listarParaPanel((int)$_SESSION['uid']);

$ok    = $_GET['ok']    ?? null;
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitar turno</title>
  <link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
  <link rel="stylesheet" href="<?= $base ?>/publico/app.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<main class="page-center">
  <form class="card card-narrow" action="guardar.php" method="post">
    <h2>Solicitar turno</h2>

    <?php if ($ok): ?>
      <div class="msg-ok">Guardado.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="msg-err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$vehiculos): ?>
      <p>Primero cargá un vehículo.</p>
      <div class="links">
        <a href="<?= $base ?>/modules/cliente/vehiculos/nuevo.php">Registrar vehículo</a>
      </div>
    <?php else: ?>

      <label>Vehículo</label>
      <select name="automovil_id" required>
        <option value="">Seleccioná…</option>
        <?php foreach ($vehiculos as $v): ?>
          <option value="<?= (int)($v['id_auto'] ?? $v['id']) ?>">
            <?= htmlspecialchars(($v['marca'] ?? '').' '.($v['modelo'] ?? '').' '.($v['anio'] ?? '').($v['color'] ? ' ('.$v['color'].')' : '')) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Motivo del turno</label>
      <input type="text" name="motivo" maxlength="100" required placeholder="Ej.: Cambio de aceite, ruido en frenos…">

      <label>Descripción (opcional)</label>
      <input type="text" name="descripcion" rows="4" maxlength="500" placeholder="Contanos más detalles (ruidos, síntomas, cuándo ocurre, etc.)">

      <button class="btn" type="submit">Enviar solicitud</button>
      <div class="links"><a href="<?= $base ?>/modules/cliente/turnos/">Volver</a></div>

    <?php endif; ?>
  </form>
</main>
</body>
</html>
