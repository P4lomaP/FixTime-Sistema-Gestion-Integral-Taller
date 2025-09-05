<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/PersonaRepositorio.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$dni = trim($_POST['dni'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($nombre === '' || $apellido === '' || $dni === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . $base . '/modules/cliente/perfil/?error=' . urlencode('Datos inválidos.'));
  exit;
}

$repo = new PersonaRepositorio();
try {
  $repo->actualizarPerfil((int)$_SESSION['uid'], $nombre, $apellido, $dni, $email);
  $_SESSION['nombre'] = $nombre; $_SESSION['apellido'] = $apellido;
} catch (Throwable $e) {
  header('Location: ' . $base . '/modules/cliente/perfil/?error=' . urlencode('No se pudo guardar.'));
  exit;
}
header('Location: ' . $base . '/modules/cliente/perfil/?ok=1');
exit;
