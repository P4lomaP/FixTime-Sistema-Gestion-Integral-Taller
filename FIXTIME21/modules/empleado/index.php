<?php
declare(strict_types=1);
require_once __DIR__ . '/../../clases/Sesion.php';
require_once __DIR__ . '/../../clases/EmpleadoRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);
if ($uid <= 0) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

$repo = new EmpleadoRepositorio();

if (!$repo->esEmpleado($uid)) {
  // No tiene cargo activo como empleado → volver al selector con alerta
  header('Location: ' . $base . '/modules/selector/?error=' . urlencode('No tenés un cargo activo como empleado.'));
  exit;
}

$cargo = $repo->obtenerCargoUnicoActivo($uid); // será Recepcionista o Mecánico
$ruta  = $cargo ? EmpleadoRepositorio::rutaPanelPorCargo($cargo) : null;

if ($ruta) {
  header('Location: ' . $base . $ruta);
  exit;
}

// Fallback: si no hay mapeo, mostrás algo genérico o volvés al selector
header('Location: ' . $base . '/modules/selector/?error=' . urlencode('Tu cargo de empleado no tiene panel asignado.'));
exit;
