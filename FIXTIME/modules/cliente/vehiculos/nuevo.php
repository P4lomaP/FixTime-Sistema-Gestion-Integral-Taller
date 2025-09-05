<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$repoV = new VehiculoRepositorio();
$marcas = $repoV->listarMarcas();
$modelos = []; // se cargan por marca via POST simple o todo junto; dejamos simple
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registrar vehículo</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<link rel="stylesheet" href="<?= $base ?>/publico/app.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<main class="page-center">
  <form class="card card-narrow" action="guardar.php" method="post">
    <h2>Registrar vehículo</h2>

    <label>Marca</label>
    <select name="marca_id" required>
      <option value="">Seleccioná</option>
      <?php foreach ($marcas as $m): ?>
        <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['descripcion']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Modelo</label>
    <input name="modelo_texto" placeholder="Ej: Corolla / Punto" required>

    <label>Año</label>
    <input name="anio" required>

    <label>Color</label>
    <input name="color" required>

    <label>Kilometraje</label>
    <input name="km" required>

    <button class="btn" type="submit">Guardar</button>
    <div class="links"><a href="<?= $base ?>/modules/cliente/vehiculos/">Volver</a></div>
  </form>
</main>
</body>
</html>
