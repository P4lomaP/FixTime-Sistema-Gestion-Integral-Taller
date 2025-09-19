<?php
require_once __DIR__ . '/../clases/PasswordResetRepositorio.php';
require_once __DIR__ . '/../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../clases/Sesion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$token = $_POST['token'] ?? '';
$pass1 = $_POST['contrasenia'] ?? '';
$pass2 = $_POST['contrasenia2'] ?? '';

$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');


if ($token === '' || $pass1 === '' || $pass2 === '') {
    header('Location: ' . $base . '/modules/login/restablecer.php?token=' . urlencode($token) . '&error=' . urlencode('Completá todos los campos.'));
    exit;
}
if ($pass1 !== $pass2) {
    header('Location: ' . $base . '/modules/login/restablecer.php?token=' . urlencode($token) . '&error=' . urlencode('Las contraseñas no coinciden.'));
    exit;
}


$repoReset   = new PasswordResetRepositorio();
$repoPersona = new PersonaRepositorio();


$personaId = $repoReset->validarToken($token);
if (!$personaId) {
    header('Location: ' . $base . '/modules/login/restablecer.php?error=' . urlencode('El enlace es inválido o ha expirado.'));
    exit;
}


$hash = password_hash($pass1, PASSWORD_DEFAULT);
$repoPersona->actualizarPassword($personaId, $hash);


$repoReset->marcarUsado($token);


Sesion::cerrar();


header('Location: ' . $base . '/modules/login/reset_ok.php');
exit;
