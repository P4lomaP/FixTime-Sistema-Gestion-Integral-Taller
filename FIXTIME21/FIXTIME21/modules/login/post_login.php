<?php
declare(strict_types=1);

require_once __DIR__ . '/../../clases/Sesion.php';
require_once __DIR__ . '/../../clases/PersonaRepositorio.php';

Sesion::iniciar();
$app = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

// Aceptamos solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

// Validación de inputs
$usuario = trim($_POST['usuario'] ?? '');
$pass    = (string)($_POST['contrasenia'] ?? '');
if ($usuario === '' || $pass === '') {
  header('Location: ' . $base . '/modules/login/?error=' . urlencode('Completá usuario y contraseña'));
  exit;
}

// Buscar persona por DNI o Email y verificar contraseña
$repo    = new PersonaRepositorio();
$persona = $repo->buscarPorIdentificador($usuario); // DNI o Email

if (!$persona || !password_verify($pass, (string)$persona['contrasenia'])) {
  header('Location: ' . $base . '/modules/login/?error=' . urlencode('Credenciales inválidas'));
  exit;
}

// Autenticar: esta función debe setear $_SESSION['uid'], ['nombre'], ['apellido'], etc.
Sesion::autenticar($persona);

// Si tu flujo no carga roles acá, dejamos un piso "cliente" para no romper nada.
// (Si ya venís cargando roles en Sesion::autenticar, podés borrar este bloque.)
if (!isset($_SESSION['roles'])) {
  $_SESSION['roles'] = ['cliente'];
} elseif (!is_array($_SESSION['roles'])) {
  $_SESSION['roles'] = [$_SESSION['roles']];
}

// 🔁 Nueva lógica de redirección centralizada:
//   - Si es admin o tiene más de un rol => va SIEMPRE al selector
//   - Si tiene un solo rol => va directo a su panel
require __DIR__ . '/redirect_post_login.php';

// (No debería llegar acá)
header('Location: ' . $base . '/modules/selector/');
exit;
