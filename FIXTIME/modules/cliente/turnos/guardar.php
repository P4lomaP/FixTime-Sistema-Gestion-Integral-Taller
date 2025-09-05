<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/TurnoRepositorio.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$autoId = (int)($_POST['automovil_id'] ?? 0);
$fecha  = trim($_POST['fecha'] ?? '');
$hora   = trim($_POST['hora'] ?? '');
$reprogramarId = (int)($_POST['reprogramar_id'] ?? 0);

if (!$autoId || $fecha === '' || $hora === '') {
  header('Location: ' . $base . '/modules/cliente/turnos/solicitar.php?error=' . urlencode('Completá todo.'));
  exit;
}

$repo = new TurnoRepositorio();
if (!$repo->disponible($fecha, $hora)) {
  header('Location: ' . $base . '/modules/cliente/turnos/solicitar.php?error=' . urlencode('No hay disponibilidad para esa fecha/hora.'));
  exit;
}

try {
  if ($reprogramarId) {
    $repo->reprogramar((int)$_SESSION['uid'], $reprogramarId, $fecha, $hora, $autoId);
  } else {
    $repo->crear((int)$_SESSION['uid'], $autoId, $fecha, $hora);
  }
} catch (Throwable $e) {
  header('Location: ' . $base . '/modules/cliente/turnos/solicitar.php?error=' . urlencode('No se pudo guardar.'));
  exit;
}

header('Location: ' . $base . '/modules/cliente/turnos/index.php?ok=1');
exit;
