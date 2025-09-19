<?php
declare(strict_types=1);
require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

$roles = $_SESSION['roles'] ?? ['cliente']; // 'cliente','empleado','admin'
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Elegir panel</title>
<link rel="stylesheet" href="<?= $base ?>/publico/app.css">
<style>.grid{display:grid;gap:16px;grid-template-columns:repeat(3,1fr)}@media(max-width:740px){.grid{grid-template-columns:1fr}}</style>
</head>
<body>
<main class="page-center">
  <div class="card card-narrow panel-selector">
    <h1>Elegí el panel</h1>
    <div class="grid">
      <?php if(in_array('cliente', $roles)): ?>
        <a class="btn" href="<?= $base ?>/modules/clientes/">Panel cliente</a>
      <?php endif; ?>
      <?php if(in_array('empleado', $roles)): ?>
        <a class="btn" href="<?= $base ?>/modules/empleado/">Panel empleado</a>
      <?php endif; ?>
      <?php if(in_array('admin', $roles)): ?>
        <a class="btn" href="<?= $base ?>/modules/admin/">Panel administrador</a>
      <?php endif; ?>
    </div>
  </div>
</main>

<style>
.page-center {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh; /* ocupa toda la pantalla */
}

.panel-selector {
  text-align: center;
  padding: 30px;
}

.panel-selector h1 {
  margin-bottom: 20px;
}

.panel-selector .grid {
  display: flex;
  flex-direction: column;
  gap: 15px;
  align-items: center;
}
</style>

</body>
</html>
