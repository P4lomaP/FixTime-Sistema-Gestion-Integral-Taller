<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Sesion.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');

require_once __DIR__ . '/../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();
if (!$repoA->esAdmin((int)($_SESSION['uid'] ?? 0))) { header('Location: '.$base.'/modules/login/'); exit; }

require_once __DIR__ . '/../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../clases/Conexion.php';

$nombre   = trim($_POST['nombre']   ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$dni      = trim($_POST['dni']      ?? '');
$email    = trim($_POST['email']    ?? '');
$password = (string)($_POST['password'] ?? ($_POST['contrasenia'] ?? ''));
$telefono = trim($_POST['telefono'] ?? '');
$cargoId  = isset($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : 0;
$pais = trim($_POST['pais'] ?? '');
$provincia = trim($_POST['provincia'] ?? '');
$localidad = trim($_POST['localidad'] ?? '');
$barrio = trim($_POST['barrio'] ?? '');
$calle = trim($_POST['calle'] ?? '');
$altura = trim($_POST['altura'] ?? '');
$piso = trim($_POST['piso'] ?? '');
$departamento = trim($_POST['departamento'] ?? '');

if (!$nombre || !$apellido || !$email || !$password) {
    header("Location: $base/modules/admin/empleados.php?err=" . urlencode("Completá nombre, apellido, correo y contraseña."));
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: $base/modules/admin/empleados.php?err=" . urlencode("El correo es inválido."));
    exit;
}

$repo = new PersonaRepositorio();
$db   = Conexion::obtener();

try {
    $db->beginTransaction();

    // Hash de contraseña
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>11]);

    // Si no viene DNI, usar un placeholder único (no ideal, pero evita romper FK única si aplica)
    if ($dni === '') {
        $dni = 'EMP-' . substr(hash('sha256', $email . microtime(true)), 0, 10);
    }

    // Crear persona base
    $personaId = $repo->crearPersona($nombre, $apellido, $dni, $hash);

    // Contactos
    $repo->guardarEmail($personaId, $email);
    if ($telefono !== '') {
        $repo->guardarTelefono($personaId, $telefono);
    }

    // Domicilio (si se envió algo)
    if ($pais || $provincia || $localidad || $barrio || $calle || $altura || $piso || $departamento) {
        try {
            $repo->actualizarArbolDomicilioExistente($personaId, $pais, $provincia, $localidad, $barrio, $calle, $altura, $piso, $departamento);
        } catch (Throwable $e) { /* continuar */ }
    }

    // Insertar en Empleados
    if ($cargoId > 0) {
        $st = $db->prepare("INSERT INTO Empleados (Persona_id, Cargo_id) VALUES (:pid, :cid)");
        $st->execute([':pid'=>$personaId, ':cid'=>$cargoId]);
    } else {
        $st = $db->prepare("INSERT INTO Empleados (Persona_id) VALUES (:pid)");
        $st->execute([':pid'=>$personaId]);
    }

    $db->commit();
    header("Location: $base/modules/admin/empleados.php?ok=" . urlencode("Empleado creado correctamente"));
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("ADMIN_EMPLEADO_CREAR: " . $e->getMessage());
    $msg = (stripos($e->getMessage(), 'uq_tipo_valor') !== false)
            ? "Email o teléfono ya registrado."
            : "Error al crear empleado.";
    header("Location: $base/modules/admin/empleados.php?err=" . urlencode($msg));
}
