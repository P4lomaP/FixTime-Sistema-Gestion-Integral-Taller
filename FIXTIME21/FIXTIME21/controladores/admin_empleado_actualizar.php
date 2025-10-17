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

$id      = (int)($_POST['id'] ?? 0);
$nombre  = trim($_POST['nombre'] ?? '');
$apellido= trim($_POST['apellido'] ?? '');
$dni     = trim($_POST['dni'] ?? '');
$email   = trim($_POST['email'] ?? '');
$telefono= trim($_POST['telefono'] ?? '');
$pais    = trim($_POST['pais'] ?? '');
$provincia= trim($_POST['provincia'] ?? '');
$localidad= trim($_POST['localidad'] ?? '');
$barrio  = trim($_POST['barrio'] ?? '');
$calle   = trim($_POST['calle'] ?? '');
$altura  = trim($_POST['altura'] ?? '');
$piso    = trim($_POST['piso'] ?? '');
$departamento = trim($_POST['departamento'] ?? '');

$cargoId = (int)($_POST['cargo_id'] ?? 0);

if(!$id || !$nombre || !$apellido || !$dni || !$cargoId){
    header("Location: $base/modules/admin/empleados.php?err=Datos inválidos");
    exit;
}

try {
    $repoE->actualizar($id, $nombre, $apellido, $dni, $cargoId);
    // Actualizar contactos y domicilio
    require_once __DIR__ . '/../clases/PersonaRepositorio.php';
    $pr = new PersonaRepositorio();
    // Persona del empleado
    $dbp = Conexion::obtener();
    $stp = $dbp->prepare('SELECT Persona_id FROM Empleados WHERE id=:id');
    $stp->execute([':id'=>$id]);
    $pid = (int)$stp->fetchColumn();
    if ($pid) {
        if ($email) $pr->reemplazarEmail($pid, $email);
        if ($telefono) $pr->reemplazarTelefonos($pid, [$telefono]);
        if ($pais || $provincia || $localidad || $barrio || $calle || $altura || $piso || $departamento) {
            try { $pr->actualizarArbolDomicilioExistente($pid, $pais, $provincia, $localidad, $barrio, $calle, $altura, $piso, $departamento); } catch (Throwable $e) {}
        }
    }
    header("Location: $base/modules/admin/empleados.php?ok=Empleado actualizado");
} catch (Throwable $e) {
    header("Location: $base/modules/admin/empleados.php?err=" . urlencode("Error: ".$e->getMessage()));
}
