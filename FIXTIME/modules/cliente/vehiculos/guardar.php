<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$marcaId = (int)($_POST['marca_id'] ?? 0);
$modeloTexto = trim($_POST['modelo_texto'] ?? '');
$anio = trim($_POST['anio'] ?? '');
$color = trim($_POST['color'] ?? '');
$km = trim($_POST['km'] ?? '');

if (!$marcaId || $modeloTexto === '' || $anio === '' || $color === '' || $km === '') {
  header('Location: ' . $base . '/modules/cliente/vehiculos/index.php?error=' . urlencode('Completá todos los campos.'));
  exit;
}

$repo = new VehiculoRepositorio();
try {
  $repo->crearParaPersona(
    (int)$_SESSION['uid'], $marcaId, $modeloTexto, $anio, $km, $color
  );
} catch (Throwable $e) {
  header('Location: ' . $base . '/modules/cliente/vehiculos/index.php?error=' . urlencode('No se pudo guardar.'));
  exit;
}
header('Location: ' . $base . '/modules/cliente/vehiculos/index.php?ok=1');
exit;
