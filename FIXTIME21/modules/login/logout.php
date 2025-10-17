<?php
require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::cerrar();
$app = require __DIR__ . '/../../config/app.php';
header('Location: ' . rtrim($app['base_url'],'/') . '/modules/login/');
exit;
