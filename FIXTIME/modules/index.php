<?php
// modules/index.php
$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');

// enviá al módulo que quieras como “entrada”
header('Location: ' . $base . '/modules/login/');
exit;
