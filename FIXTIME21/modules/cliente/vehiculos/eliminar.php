<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: $base/modules/cliente/vehiculos/"); exit; }
if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
  header("Location: $base/modules/cliente/vehiculos/?m=csrf"); exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { header("Location: $base/modules/cliente/vehiculos/?m=badid"); exit; }

$repo = new VehiculoRepositorio();
$repo->eliminar($id, $uid);
header("Location: $base/modules/cliente/vehiculos/?m=deleted"); exit;
