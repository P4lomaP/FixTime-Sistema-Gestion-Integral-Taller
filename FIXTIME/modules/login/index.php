<?php
require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::iniciar();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

if (!empty($_SESSION['uid'])) {
  header('Location: ' . $base . '/modules/cliente/');
  exit;
}

$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fixtime — Iniciar sesión</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../publico/app.css">
</head>
<body>
  <main class="envoltorio">
    <div class="grid">
      <!-- Izquierda (hero) -->
      <section class="panel izq">
        <img class="logo glow" src="../../publico/widoo.png" alt="Fixtime">
        <h1 class="titulo">Fixtime</h1>
        <p class="sub">Organizá tu taller con precisión</p>

        <div class="features">
          <div class="feature"><b>📅 Turnos inteligentes</b>Programá y confirmá citas fácilmente.</div>
          <div class="feature"><b>🛠️ Órdenes claras</b>Seguimiento de cada reparación y estado.</div>
          <div class="feature"><b>💸 Facturación simple</b>Emití y consultá facturas al instante.</div>
          <div class="feature"><b>📦 Repuestos & stock</b>Control de inventario y precios de repuestos.</div>
        </div>

        <p class="foot">Todo lo que tu taller necesita, sin vueltas.</p>
      </section>

      <!-- Derecha (login) -->
      <section class="panel der">
        <form class="card card-narrow" action="post_login.php" method="post">
          <h2>Iniciar sesión</h2>
          <p class="desc">Entrá con tu DNI o correo</p>

          <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div>
          <?php endif; ?>

          <label for="usuario">DNI o correo</label>
          <input id="usuario" name="usuario" type="text" required autocomplete="username">

          <label for="contrasenia">Contraseña</label>
          <input id="contrasenia" name="contrasenia" type="password" required autocomplete="current-password">

          <button class="btn" type="submit">Entrar</button>

          <div class="links">
           <a href="<?= $base ?>/modules/login/olvide.php">¿Olvidaste tu contraseña?</a>
<a href="<?= $base ?>/modules/login/registro.php">¿No tenés cuenta? Registrate</a>

          </div>
        </form>
      </section>
    </div>
  </main>
</body>
</html>
