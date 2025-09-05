<?php
require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::iniciar();
if (!empty($_SESSION['uid'])) {
  $app = require __DIR__ . '/../../config/app.php';
  header('Location: ' . rtrim($app['base_url'],'/') . '/modules/cliente/');
  exit;
}
$ok = $_GET['ok'] ?? null;
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fixtime — Registro</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../publico/app.css">
</head>
<body>
  <main class="page-center">
    <form class="card card-narrow" action="../../controladores/registro_controlador.php" method="post">
      <h2>Crear cuenta</h2>
      <?php if ($ok): ?><div class="msg-ok">¡Cuenta creada! Ya podés iniciar sesión.</div><?php endif; ?>
      <?php if ($error): ?><div class="msg-err"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

      <label for="nombre">Nombre</label>
      <input id="nombre" name="nombre" required>

      <label for="apellido">Apellido</label>
      <input id="apellido" name="apellido" required>

      <label for="dni">DNI</label>
      <input id="dni" name="dni" required>

      <label for="email">Correo</label>
      <input id="email" name="email" type="email" required>

      <label for="contrasenia">Contraseña</label>
      <input id="contrasenia" name="contrasenia" type="password" minlength="6" required>

      <button class="btn" type="submit">Registrarme</button>
      <div class="links"><a href="index.php">Volver</a></div>
    </form>
  </main>
</body>
</html>
