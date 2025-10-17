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
<title>Fixtime — Recuperar Contraseña</title>
<link rel="icon" href="<?= $base ?>/publico/widoo.png?v=1" type="image/png">
<link rel="shortcut icon" href="<?= $base ?>/publico/widoo.png?v=1" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../publico/app.css">
</head>
<body>
  <main class="page-center">
    <form class="card card-narrow" action="../../controladores/solicitar_reset_controlador.php" method="post">
      <h2>Recuperar contraseña</h2>
      <p class="desc">Ingresá tu correo electrónico</p>

      <?php if ($ok): ?><div class="msg-ok">Si el correo existe, recibirás un email con instrucciones.</div><?php endif; ?>
      <?php if ($error): ?><div class="msg-err"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

      <label for="email">Correo</label>
      <input id="email" name="email" type="email" required autocomplete="email">

      <button class="btn" type="submit">Enviar Enlace</button>
      <div class="links"><a href="index.php">Volver</a></div>
    </form>
  </main>
</body>
</html>
