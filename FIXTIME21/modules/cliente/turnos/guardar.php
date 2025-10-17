<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/TurnoRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$autoId      = (int)($_POST['automovil_id'] ?? 0);
$motivo      = trim($_POST['motivo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

if (!$autoId || $motivo === '') {
  header('Location: ' . $base . '/modules/cliente/turnos/solicitar.php?error=' . urlencode('Elegí el vehículo y escribí el motivo.'));
  exit;
}

try {
  $repo = new TurnoRepositorio();
  // Crea solicitud SIN fecha ni hora
  $repo->crearSolicitud((int)$_SESSION['uid'], $autoId, $motivo, $descripcion);
} catch (Throwable $e) {
  header('Location: ' . $base . '/modules/cliente/turnos/solicitar.php?error=' . urlencode('No se pudo guardar la solicitud.'));
  exit;
}

header('Location: ' . $base . '/modules/cliente/turnos/index.php?ok=1');
exit;
