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

$nombre   = trim($_POST['nombre']   ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$dni      = trim($_POST['dni']      ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$pass     = $_POST['password'] ?? '';
$cargoId  = (int)($_POST['cargo_id'] ?? 0);

if (!$nombre || !$apellido || !$dni || !$email || !$pass || !$cargoId) {
    header("Location: $base/modules/admin/empleados.php?err=Faltan datos");
    exit;
}
if ($repoE->emailExiste($email)) {
    header("Location: $base/modules/admin/empleados.php?err=Ese email ya está registrado");
    exit;
}

try {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $repoE->crearEmpleado($nombre, $apellido, $dni, $email, $hash, $cargoId);
    header("Location: $base/modules/admin/empleados.php?ok=Empleado creado");
} catch (Throwable $e) {
    header("Location: $base/modules/admin/empleados.php?err=" . urlencode("Error: ".$e->getMessage()));
}
