<?php
$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');
$q = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: ' . $base . '/modules/login/restablecer.php' . $q);
exit;
