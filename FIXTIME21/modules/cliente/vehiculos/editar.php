<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$repo = new VehiculoRepositorio();
$veh  = $repo->obtenerDePersona($id, $uid);
if (!$veh) { header("Location: $base/modules/cliente/vehiculos/?m=notfound"); exit; }

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<h2>Editar vehículo</h2>
<form action="<?= $base ?>/modules/cliente/vehiculos/guardar.php" method="post" style="display:grid;gap:8px;max-width:520px">
  <input type="hidden" name="id" value="<?= (int)$veh['id'] ?>">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">

  <label>Marca</label>
  <input name="marca" value="<?= h($veh['marca']) ?>" required>

  <label>Modelo</label>
  <input name="modelo" value="<?= h($veh['modelo']) ?>" required>

  <label>Año</label>
  <input name="anio" type="number" min="1900" max="<?= date('Y')+1 ?>" value="<?= (int)$veh['anio'] ?>" required>

  <label>Color</label>
  <input name="color" value="<?= h($veh['color']) ?>">

  <label>KM</label>
  <input name="km" type="number" min="0" value="<?= (int)$veh['km'] ?>">

  <div style="display:flex;gap:8px">
    <button type="submit">Guardar cambios</button>
    <a href="<?= $base ?>/modules/cliente/vehiculos/">Cancelar</a>
  </div>
</form>
