<?php
declare(strict_types=1);
require_once __DIR__ . '/../clases/Sesion.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');

require_once __DIR__ . '/../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();
if (!$repoA->esAdmin((int)($_SESSION['uid'] ?? 0))) { header('Location: '.$base.'/modules/login/'); exit; }

require_once __DIR__ . '/../clases/EmpleadoRepositorio.php';
$repoE = new EmpleadoRepositorio();

$id = (int)($_GET['id'] ?? 0);
if(!$id){ header("Location: $base/modules/admin/empleados.php?err=ID inválido"); exit; }

try {
    $repoE->desactivar($id);
    header("Location: $base/modules/admin/empleados.php?ok=Empleado dado de baja");
} catch (Throwable $e) {
    header("Location: $base/modules/admin/empleados.php?err=" . urlencode("Error: ".$e->getMessage()));
}
