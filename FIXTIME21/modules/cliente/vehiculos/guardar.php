<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);

// CSRF
if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
  header("Location: $base/modules/cliente/vehiculos/?m=csrf"); exit;
}

// Entrada
$id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$titular = $_POST['titular'] ?? ('persona:' . $uid); // compat
$marca   = trim($_POST['marca'] ?? '');
$modelo  = trim($_POST['modelo'] ?? '');
$anio    = (int)($_POST['anio'] ?? 0);
$km      = (int)($_POST['km'] ?? 0);
$color   = trim($_POST['color'] ?? '');

$repo = new VehiculoRepositorio();

try {
  if ($id > 0) {
    // Update (solo personales, como ya tenías). Si querés, después extendemos para empresa.
    $repo->actualizar($id, $uid, compact('marca','modelo','anio','km','color'));
    $m='updated';
  } else {
    // Create
    if (strpos($titular, 'empresa:') === 0) {
      $empresaId = (int)substr($titular, 8);
      $repo->crearParaEmpresa($empresaId, $uid, $marca, $modelo, $anio, $km, $color);
    } else {
      $repo->crearParaPersona($uid, $marca, $modelo, $anio, $km, $color);
    }
    $m='created';
  }

  header("Location: $base/modules/cliente/vehiculos/?m=$m"); exit;

} catch (Throwable $e) {
  $code = $e->getMessage();
  if ($code === 'VEHICULO_DUPLICADO' || $code === 'PATENTE_DUPLICADA') {
    header("Location: $base/modules/cliente/vehiculos/?m=dup"); exit;
  }
  if ($code === 'PERMISO_EMPRESA' || $code === 'PERMISO_DENEGADO') {
    header("Location: $base/modules/cliente/vehiculos/?m=perm"); exit;
  }
  header("Location: $base/modules/cliente/vehiculos/?m=error"); exit;
}
