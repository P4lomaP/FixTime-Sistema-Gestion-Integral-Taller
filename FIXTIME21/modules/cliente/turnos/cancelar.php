<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/TurnoRepositorio.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
  header('Location: ' . $base . '/modules/cliente/turnos/?error=' . urlencode('Turno inválido.'));
  exit;
}

$repo = new TurnoRepositorio();
$repo->cancelar((int)$_SESSION['uid'], $id);

header('Location: ' . $base . '/modules/cliente/turnos/?ok=1');
exit;
