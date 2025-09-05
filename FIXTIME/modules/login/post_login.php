<?php
require_once __DIR__ . '/../../clases/Sesion.php';
require_once __DIR__ . '/../../clases/PersonaRepositorio.php';

Sesion::iniciar();
$app = require __DIR__ . '/../../config/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . rtrim($app['base_url'],'/') . '/modules/login/');
  exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$pass    = (string)($_POST['contrasenia'] ?? '');
if ($usuario === '' || $pass === '') {
  header('Location: ' . rtrim($app['base_url'],'/') . '/modules/login/?error=' . urlencode('Completá usuario y contraseña'));
  exit;
}

$repo = new PersonaRepositorio();
$persona = $repo->buscarPorIdentificador($usuario); // DNI o Email

if (!$persona || !password_verify($pass, $persona['contrasenia'])) {
  header('Location: ' . rtrim($app['base_url'],'/') . '/modules/login/?error=' . urlencode('Credenciales inválidas'));
  exit;
}

Sesion::autenticar($persona);
header('Location: ' . rtrim($app['base_url'],'/') . '/modules/cliente/');
exit;
