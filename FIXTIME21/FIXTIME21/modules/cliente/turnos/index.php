<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/TurnoRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$repo   = new TurnoRepositorio();
$turnos = $repo->listarPorPersona((int)$_SESSION['uid']);

$ok    = $_GET['ok']    ?? null;
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis turnos</title>
  <link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
  <link rel="stylesheet" href="<?= $base ?>/publico/app.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<main class="page-center">
  <div class="card">
    <h2>Mis turnos</h2>

    <?php if ($ok): ?>
      <div class="msg-ok">Operación exitosa.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="msg-err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="mb-16">
      <a class="btn" href="<?= $base ?>/modules/cliente/turnos/solicitar.php">Solicitar turno</a>
      <a class="btn secundario" href="<?= $base ?>/modules/cliente/">Volver</a>
    </div>

    <?php if (!$turnos): ?>
      <p>No tenés turnos.</p>
    <?php else: ?>
      <div class="tabla">
        <div class="tabla-row tabla-head">
          <div>Vehículo</div>
          <div>Motivo</div>
          <div>Descripción</div>
          <div>Estado</div>
          <div>Acciones</div>
        </div>

        <?php foreach ($turnos as $t): ?>
          <div class="tabla-row">
            <div><?= htmlspecialchars(($t['marca'] ?? '').' '.($t['modelo'] ?? '').' '.($t['anio'] ?? '')) ?></div>
            <div><?= htmlspecialchars($t['motivo'] ?? '') ?></div>
            <div><?= htmlspecialchars($t['descripcion'] ?? '') ?></div>
            <div><?= htmlspecialchars($t['estado'] ?? '') ?></div>
            <div>
              <form action="cancelar.php" method="post" style="display:inline">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn danger" onclick="return confirm('¿Cancelar turno?')">Cancelar</button>
              </form>
              
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
