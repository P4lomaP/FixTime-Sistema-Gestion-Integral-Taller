<?php
require_once __DIR__ . '/clases/Sesion.php';
Sesion::cerrar();
header('Location: index.php');
exit;
