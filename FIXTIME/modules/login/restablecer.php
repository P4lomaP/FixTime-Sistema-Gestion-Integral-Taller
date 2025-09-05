<?php
require_once __DIR__ . '/../../clases/PasswordResetRepositorio.php';

$token = $_GET['token'] ?? '';
$repo  = new PasswordResetRepositorio();
$personaId = $token ? $repo->validarToken($token) : null;
$invalido = !$personaId;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fixtime — Restablecer contraseña</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../publico/app.css">
</head>
<body>
  <main class="page-center">
    <form class="card card-narrow" action="../../controladores/procesar_reset_controlador.php" method="post">
      <h2>Restablecer contraseña</h2>

      <?php if ($invalido): ?>
        <div class="msg-err">El enlace es inválido o ha expirado.</div>
        <div class="links"><a href="olvide.php">Solicitar otro</a></div>
      <?php else: ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token,ENT_QUOTES,'UTF-8') ?>">

        <label for="contrasenia">Nueva contraseña</label>
        <input id="contrasenia" name="contrasenia" type="password" minlength="6" required autocomplete="new-password">

        <label for="contrasenia2">Repetir</label>
        <input id="contrasenia2" name="contrasenia2" type="password" minlength="6" required autocomplete="new-password">

        <button class="btn" type="submit">Guardar</button>
        <div class="links"><a href="index.php">Volver</a></div>
      <?php endif; ?>
    </form>
  </main>
</body>
</html>
