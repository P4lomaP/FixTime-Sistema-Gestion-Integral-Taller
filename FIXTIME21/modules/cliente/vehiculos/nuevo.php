<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/VehiculoRepositorio.php';
require_once __DIR__ . '/../../../clases/EmpresaRepositorio.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);

// ===== CSRF =====
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

// ===== Helpers =====
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ===== POST handler (mismo archivo) =====
$errores = [];
$okMsg   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
      throw new RuntimeException('Token inválido. Recargá la página.');
    }

    $tipo = ($_POST['tipo_vehiculo'] ?? '') === 'empresarial' ? 'empresarial' : 'personal';

    $marca  = trim((string)($_POST['marca'] ?? ''));
    $modelo = trim((string)($_POST['modelo'] ?? ''));
    $anio   = (string)($_POST['anio'] ?? '');
    $color  = trim((string)($_POST['color'] ?? ''));
    $km     = (string)($_POST['km'] ?? '0');

    $patente           = trim((string)($_POST['patente'] ?? ''));
    $descripcion_extra = trim((string)($_POST['descripcion_extra'] ?? ''));

    // Validaciones mínimas
    if ($marca === '' || $modelo === '') {
      throw new InvalidArgumentException('Marca y modelo son obligatorios.');
    }
    if ($anio !== '' && (!ctype_digit($anio) || (int)$anio < 1900 || (int)$anio > ((int)date('Y') + 1))) {
      throw new InvalidArgumentException('Año inválido.');
    }
    if (!ctype_digit($km) || (int)$km < 0) {
      throw new InvalidArgumentException('Kilometraje inválido.');
    }

    // Si es empresarial, necesitamos empresa en sesión
    if ($tipo === 'empresarial') {
      $empresaId = (int)($_SESSION['empresa_id'] ?? 0);
      if ($empresaId <= 0) {
        throw new RuntimeException('Para registrar un vehículo empresarial primero completá los datos de empresa en el panel "Empresa".');
      }
    }

    // Persistencia
    $vehRepo = new VehiculoRepositorio();

    // Asegurar Marca/Modelo (helpers del repositorio)
    $marcaId  = $vehRepo->asegurarMarca($marca);
    $modeloId = $vehRepo->asegurarModelo($modeloId = null, $modelo, $marcaId); // crea modelo bajo la marca

    $autoId = $vehRepo->crearAutomovil([
      'descripcion'         => $marca . ' ' . $modelo,
      'anio'                => $anio === '' ? null : $anio,
      'km'                  => (int)$km,
      'color'               => $color === '' ? null : $color,
      'patente'             => $patente === '' ? null : $patente,
      'descripcion_extra'   => $descripcion_extra === '' ? null : $descripcion_extra,
      'foto_Cedula_Frente'  => null, // si después agregás upload, acá va la ruta
      'foto_Cedula_Trasera' => null,
      'Modelo_Automovil_id' => $modeloId,
    ]);

    if ($tipo === 'personal') {
      $vehRepo->vincularAutomovilAPersona($uid, $autoId);
    } else {
      $empresaId = (int)$_SESSION['empresa_id'];
      $vehRepo->vincularAutomovilAEmpresa($empresaId, $autoId);
    }

    $okMsg = 'Vehículo creado correctamente.';
    // Regenero CSRF para evitar re-envíos
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    header('Location: ' . $base . '/modules/cliente/vehiculos/index.php?ok=' . urlencode($okMsg));
    exit;

  } catch (\Throwable $e) {
    $errores[] = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Nuevo vehículo</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Inter,Arial; background:#0b1020; color:#e5edf9; margin:0;}
    .wrap{max-width:900px; margin:24px auto; padding:16px;}
    .card{background:#0f1b34cc; border-radius:16px; padding:20px; box-shadow:0 6px 24px rgba(0,0,0,.35)}
    h1{margin:0 0 12px}
    .alert{padding:12px 14px; border-radius:12px; background:#15254d; color:#d9e4ff; margin:0 0 12px}
    .error{background:#3a1733; color:#ffd8e4}
    form{display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px}
    label{font-weight:600; font-size:14px; opacity:.95}
    input,select,textarea{padding:10px 12px; border-radius:10px; border:1px solid #2b3d6b; background:#0e162e; color:#e5edf9}
    .row{display:flex; gap:12px; align-items:center}
    .muted{opacity:.85; font-size:13px}
    .actions{margin-top:12px; display:flex; gap:10px}
    button,.btn{background:#3c7cff; border:none; color:white; padding:10px 14px; border-radius:10px; cursor:pointer}
    a.btn{display:inline-block; text-decoration:none; text-align:center}
    .radio{display:flex; gap:14px; align-items:center}
    .radio label{font-weight:500}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Registrar vehículo</h1>
      <p class="muted">Podés registrar vehículos <strong>personales</strong> o <strong>empresariales</strong>. Si elegís empresarial, usaremos la empresa que cargaste en el panel <em>Empresa</em>.</p>

      <?php if ($errores): ?>
        <div class="alert error">
          <?php foreach ($errores as $e): ?>
            <div>• <?= h($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <div class="row radio" style="grid-column:1/-1">
          <label><input type="radio" name="tipo_vehiculo" value="personal" checked> Personal</label>
          <label><input type="radio" name="tipo_vehiculo" value="empresarial"> Empresarial</label>
        </div>

        <div>
          <label for="marca">Marca</label>
          <input id="marca" name="marca" required placeholder="Toyota / Fiat">
        </div>

        <div>
          <label for="modelo">Modelo</label>
          <input id="modelo" name="modelo" required placeholder="Corolla / Punto">
        </div>

        <div>
          <label for="anio">Año</label>
          <input id="anio" name="anio" type="number" min="1900" max="<?= (int)date('Y') + 1 ?>">
        </div>

        <div>
          <label for="color">Color</label>
          <input id="color" name="color" placeholder="Rojo / Negro / ...">
        </div>

        <div>
          <label for="km">KM</label>
          <input id="km" name="km" type="number" min="0" value="0">
        </div>

        <div>
          <label for="patente">Patente (opcional)</label>
          <input id="patente" name="patente" placeholder="ABC123 / AA123BB">
        </div>

        <div style="grid-column:1/-1">
          <label for="descripcion_extra">Descripción extra (opcional)</label>
          <textarea id="descripcion_extra" name="descripcion_extra" rows="2" placeholder="Observaciones, variante del modelo, etc."></textarea>
        </div>

        <div class="actions" style="grid-column:1/-1">
          <a class="btn" href="<?= h($base) ?>/modules/cliente/vehiculos/index.php">Volver</a>
          <button type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
