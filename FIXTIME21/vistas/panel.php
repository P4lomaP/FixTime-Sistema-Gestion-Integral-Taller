<?php
require_once __DIR__ . '/../clases/Sesion.php';
Sesion::requiereLogin();
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$apellido = $_SESSION['apellido'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fixtime — Panel</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../publico/app.css">
</head>
<body>
  <div class="envoltorio panel-contenido">
    <h1>Hola, <?= htmlspecialchars($nombre.' '.$apellido, ENT_QUOTES,'UTF-8') ?></h1>
    <p>Sesión iniciada correctamente.</p>
    <p><a href="../salir.php">Cerrar sesión</a></p>
  </div>
</body>
</html>
