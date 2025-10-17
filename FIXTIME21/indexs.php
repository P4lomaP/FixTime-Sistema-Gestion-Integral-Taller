<?php
require_once __DIR__ . '/clases/Sesion.php';
Sesion::iniciar();

$app  = require __DIR__ . '/config/app.php';
$base = rtrim($app['base_url'], '/');

// si hay sesión → módulo cliente
if (!empty($_SESSION['uid'])) {
    header('Location: ' . $base . '/modules/cliente/');
    exit;
}

// si no hay sesión → módulo login
header('Location: ' . $base . '/modules/login/');
exit;
