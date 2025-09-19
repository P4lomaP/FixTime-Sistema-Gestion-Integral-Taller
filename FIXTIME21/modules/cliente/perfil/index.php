<?php
require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../../../clases/EmpresaRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid   = (int)($_SESSION['uid'] ?? 0);
$repoP = new PersonaRepositorio();
$repoE = new EmpresaRepositorio();

$persona = $repoP->buscarPorId($uid) ?? [];
$email   = $repoP->emailPrincipal($uid) ?? '';
$domi    = $repoP->obtenerDomicilioActual($uid) ?? [
  'pais' => '', 'provincia' => '', 'localidad' => '', 'barrio' => '',
  'calle' => '', 'altura' => '', 'piso' => '', 'departamento' => ''
];

// Intento prellenar empresa “principal” si ya hay contacto compartido
$emp = $repoE->obtenerEmpresaPrincipalDePersona($uid);
$emp_razon = $emp['razon_social'] ?? '';
$emp_cuit  = $emp['CUIT'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mi perfil</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/estilos.css">
  <style>
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width: 820px){.grid-2{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="container p-6">
  <div class="card">
    <div class="card-header">
      <h2>¡Hola, <?= htmlspecialchars($persona['nombre'] ?? '') ?> <?= htmlspecialchars($persona['apellido'] ?? '') ?>!</h2>
      <p>Gestioná tus vehículos, turnos y datos desde un solo lugar.</p>
    </div>

    <?php if (!empty($_SESSION['flash_ok'])): ?>
      <div class="alert success"><?= $_SESSION['flash_ok']; unset($_SESSION['flash_ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert error"><?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <form method="post" action="guardar.php" autocomplete="off" novalidate>
      <h3 class="mt-4">Mis datos</h3>
      <div class="grid-2">
        <label>Nombre
          <input type="text" name="nombre" required value="<?= htmlspecialchars($persona['nombre'] ?? '') ?>">
        </label>
        <label>Apellido
          <input type="text" name="apellido" required value="<?= htmlspecialchars($persona['apellido'] ?? '') ?>">
        </label>
        <label>DNI
          <input type="text" name="dni" required value="<?= htmlspecialchars($persona['dni'] ?? '') ?>">
        </label>
        <label>Email
          <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">
        </label>
      </div>

      <h3 class="mt-4">Domicilio</h3>
      <div class="grid-2">
        <label>País
          <input type="text" name="pais" value="<?= htmlspecialchars($domi['pais']) ?>">
        </label>
        <label>Provincia
          <input type="text" name="provincia" value="<?= htmlspecialchars($domi['provincia']) ?>">
        </label>
        <label>Localidad
          <input type="text" name="localidad" value="<?= htmlspecialchars($domi['localidad']) ?>">
        </label>
        <label>Barrio
          <input type="text" name="barrio" value="<?= htmlspecialchars($domi['barrio']) ?>">
        </label>
        <label>Calle
          <input type="text" name="calle" value="<?= htmlspecialchars($domi['calle']) ?>">
        </label>
        <label>Altura
          <input type="text" name="altura" value="<?= htmlspecialchars($domi['altura']) ?>">
        </label>
        <label>Piso
          <input type="text" name="piso" value="<?= htmlspecialchars($domi['piso']) ?>">
        </label>
        <label>Departamento
          <input type="text" name="departamento" value="<?= htmlspecialchars($domi['departamento']) ?>">
        </label>
      </div>

      <h3 class="mt-4">Datos de la empresa</h3>
      <p class="muted">Si trabajás con una empresa, cargá al menos Razón social y CUIT. Con eso ya quedás vinculad@. Los contactos de empresa son opcionales.</p>
      <div class="grid-2">
        <label>Razón social
          <input type="text" name="empresa_razon" value="<?= htmlspecialchars($emp_razon) ?>">
        </label>
        <label>CUIT
          <input type="text" name="empresa_cuit" placeholder="30-XXXXXXXX-X" value="<?= htmlspecialchars($emp_cuit) ?>">
        </label>
        <label>Email empresa (opcional)
          <input type="email" name="empresa_email" value="">
        </label>
        <label>Teléfono empresa (opcional)
          <input type="text" name="empresa_tel" value="">
        </label>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
