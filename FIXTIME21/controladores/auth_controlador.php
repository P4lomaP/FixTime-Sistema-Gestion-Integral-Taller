<?php
require_once __DIR__ . '/../clases/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');      
$pass    = trim($_POST['contrasenia'] ?? '');

$auth = new Auth();
$res  = $auth->login($usuario, $pass);

if ($res['ok']) {
    header('Location: ../vistas/panel.php');
    exit;
} else {
    $err = urlencode($res['mensaje']);
    header("Location: ../index.php?error={$err}");
    exit;
}
