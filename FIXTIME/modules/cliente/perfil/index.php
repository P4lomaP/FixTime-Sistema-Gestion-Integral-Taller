<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/PersonaRepositorio.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$repo = new PersonaRepositorio();
$persona = $repo->buscarPorId((int)$_SESSION['uid']);
$ok = $_GET['ok'] ?? null; $error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mi perfil</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="stylesheet" href="<?= $base ?>/publico/app.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<main class="page-center">
  <form class="card card-narrow" action="guardar.php" method="post">
    <h2>Mis datos</h2>
    <?php if ($ok): ?><div class="msg-ok">Actualizado.</div><?php endif; ?>
    <?php if ($error): ?><div class="msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <label>Nombre</label>
    <input name="nombre" value="<?= htmlspecialchars($persona['nombre'] ?? '') ?>" required>

    <label>Apellido</label>
    <input name="apellido" value="<?= htmlspecialchars($persona['apellido'] ?? '') ?>" required>

    <label>DNI</label>
    <input name="dni" value="<?= htmlspecialchars($persona['dni'] ?? '') ?>" required>

    <label>Email</label>
    <input name="email" type="email" value="<?= htmlspecialchars($repo->emailPrincipal((int)$_SESSION['uid']) ?? '') ?>" required>

    <button class="btn" type="submit">Guardar</button>
    <div class="links"><a href="<?= $base ?>/modules/cliente/">Volver</a></div>
  </form>
</main>
</body>
</html>
