<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Sesion.php';
require_once __DIR__ . '/../clases/EmpresaRepositorio.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');

// CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $base . '/modules/cliente/index.php'); exit;
}
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  die('Token inválido.');
}

$razon  = trim((string)($_POST['razon_social'] ?? ''));
$cuit   = trim((string)($_POST['cuit'] ?? ''));

// Validaciones mínimas
if ($razon === '') {
  header('Location: ' . $base . '/modules/cliente/index.php?e=' . urlencode('La razón social es obligatoria')); exit;
}

try {
  $repo = new EmpresaRepositorio();

  // crear/actualizar empresa simple (tabla Empresas)
  $empresaId = $repo->crearOActualizarBasico([
    'razon_social' => $razon,
    'CUIT'         => $cuit === '' ? null : $cuit,
  ]);

  // Guardar contactos/domicilios si mandás esos campos (opcional)
  // ...

  // Dejar empresa en sesión para vincular vehículos empresariales
  $_SESSION['empresa_id'] = (int)$empresaId;

  header('Location: ' . $base . '/modules/cliente/index.php?ok=' . urlencode('Datos de empresa guardados.'));
  exit;

} catch (\Throwable $e) {
  header('Location: ' . $base . '/modules/cliente/index.php?e=' . urlencode($e->getMessage()));
  exit;
}
