<?php
require_once __DIR__ . '/../../clases/Sesion.php';
require_once __DIR__ . '/../../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../../clases/Conexion.php';

Sesion::iniciar();
$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$pass    = (string)($_POST['contrasenia'] ?? '');
if ($usuario === '' || $pass === '') {
  header('Location: ' . $base . '/modules/login/?error=' . urlencode('Completá usuario y contraseña'));
  exit;
}

$repo = new PersonaRepositorio();
$persona = $repo->buscarPorIdentificador($usuario); // Puede ser DNI o Email

if (!$persona || !password_verify($pass, $persona['contrasenia'])) {
  header('Location: ' . $base . '/modules/login/?error=' . urlencode('Credenciales inválidas'));
  exit;
}

// Autenticamos y seteamos la sesión
Sesion::autenticar($persona);

// ========= NUEVO: ROLES Y REDIRECCIÓN =========
$pdo = Conexion::obtener();
$uid = (int)($_SESSION['uid'] ?? 0);

$roles = [];

// ¿Cliente?
$stmt = $pdo->prepare("SELECT 1 FROM Clientes WHERE Persona_id = :id LIMIT 1");
$stmt->execute([':id' => $uid]);
if ($stmt->fetchColumn()) {
    $roles[] = 'cliente';
}

// ¿Empleado?
$stmt = $pdo->prepare("SELECT 1 FROM Empleados WHERE Persona_id = :id LIMIT 1");
$stmt->execute([':id' => $uid]);
if ($stmt->fetchColumn()) {
    $roles[] = 'empleado';
}

// ¿Administrador?
$stmt = $pdo->prepare("SELECT 1 FROM Administradores WHERE Persona_id = :id LIMIT 1");
$stmt->execute([':id' => $uid]);
if ($stmt->fetchColumn()) {
    $roles[] = 'admin';
}

// Si no está en ninguna tabla de roles, por defecto lo tratamos como cliente
if (!$roles) {
    $roles = ['cliente'];
}

$_SESSION['roles'] = array_values(array_unique($roles));

// Si tiene más de un rol, enviamos al selector
if (count($_SESSION['roles']) > 1) {
    header('Location: ' . $base . '/modules/selector/');
    exit;
}

// Si solo tiene uno, lo mandamos directo a su panel
$rol = $_SESSION['roles'][0];
switch ($rol) {
    case 'admin':
        header('Location: ' . $base . '/modules/admin/');
        break;
    case 'empleado':
        // Por ahora, si es solo empleado lo enviamos al panel de cliente
        header('Location: ' . $base . '/modules/cliente/');
        break;
    default:
        header('Location: ' . $base . '/modules/cliente/');
        break;
}
exit;
// ========= FIN NUEVO BLOQUE =========

