<?php
// SOLO PARA PRUEBA LOCAL. ELIMINAR EN PRODUCCIÓN.
declare(strict_types=1);
require_once __DIR__ . '/../clases/Sesion.php';
Sesion::requiereLogin();
$_SESSION['es_empresarial'] = 1;
$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');
header('Location: ' . $base . '/modules/cliente/index.php?tab=empresa');
