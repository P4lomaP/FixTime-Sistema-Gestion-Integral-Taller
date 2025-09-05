<?php
require_once __DIR__ . '/../clases/PersonaRepositorio.php';

$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $base . '/modules/login/registro.php');
  exit;
}

$nombre    = trim($_POST['nombre'] ?? '');
$apellido  = trim($_POST['apellido'] ?? '');
$dni       = trim($_POST['dni'] ?? '');
$email     = trim($_POST['email'] ?? '');
$pass      = (string)($_POST['contrasenia'] ?? '');

if ($nombre === '' || $apellido === '' || $dni === '' || $email === '' || $pass === '') {
  header('Location: ' . $base . '/modules/login/registro.php?error=' . urlencode('Completá todos los campos.'));
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . $base . '/modules/login/registro.php?error=' . urlencode('Correo inválido.'));
  exit;
}

$repo = new PersonaRepositorio();
if ($repo->existeDni($dni)) {
  header('Location: ' . $base . '/modules/login/registro.php?error=' . urlencode('El DNI ya está registrado.'));
  exit;
}
if ($repo->existeEmail($email)) {
  header('Location: ' . $base . '/modules/login/registro.php?error=' . urlencode('El email ya está registrado.'));
  exit;
}

$hash = password_hash($pass, PASSWORD_DEFAULT);

try {
  $personaId = $repo->crearPersona($nombre, $apellido, $dni, $hash);
  $repo->marcarComoCliente($personaId);
  $repo->guardarEmail($personaId, $email);
} catch (Throwable $e) {
  header('Location: ' . $base . '/modules/login/registro.php?error=' . urlencode('No se pudo registrar. Intentá más tarde.'));
  exit;
}

header('Location: ' . $base . '/modules/login/registro.php?ok=1');
exit;
