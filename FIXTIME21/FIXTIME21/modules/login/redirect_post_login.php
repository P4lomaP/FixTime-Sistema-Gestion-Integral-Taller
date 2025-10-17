<?php
declare(strict_types=1);

require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';
require_once __DIR__ . '/../../clases/RecepcionistaRepositorio.php';

$uid   = (int)($_SESSION['uid'] ?? 0);
$roles = $_SESSION['roles'] ?? [];
if (!is_array($roles)) $roles = [$roles];

// Admin
$repoA   = new AdministradorRepositorio();
$esAdmin = $uid > 0 ? $repoA->esAdmin($uid) : false;
if ($esAdmin && !in_array('admin', $roles, true)) $roles[] = 'admin';

// Recepcionista (cargo específico)
$repoR        = new RecepcionistaRepositorio();
$esRecep      = $uid > 0 ? $repoR->esRecepcionista($uid) : false;

// Normalizo roles:
// - Si es recepcionista, me aseguro de tener 'recepcionista' y
//   quito 'empleado' para que no cuente doble el mismo perfil.
if ($esRecep) {
  if (!in_array('recepcionista', $roles, true)) $roles[] = 'recepcionista';
  // Si tu app mete 'empleado' por ser parte de Empleados, lo removemos aquí:
  $roles = array_values(array_diff($roles, ['empleado']));
}

// Persisto roles
$_SESSION['roles']    = array_values(array_unique($roles));
$_SESSION['es_admin'] = $esAdmin;

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

// Regla:
// - Si es admin o tiene más de 1 rol => SIEMPRE al selector
// - Si tiene 1 sólo rol => directo a su panel
if ($esAdmin || count($_SESSION['roles']) > 1) {
  header('Location: ' . $base . '/modules/selector/');
  exit;
}

// Un solo rol:
if (in_array('cliente', $_SESSION['roles'], true)) {
  header('Location: ' . $base . '/modules/cliente/');
  exit;
}
if (in_array('recepcionista', $_SESSION['roles'], true)) {
  // nuevo panel específico de recepción
  header('Location: ' . $base . '/recepcionista/');
  exit;
}
if (in_array('empleado', $_SESSION['roles'], true)) {
  // empleado genérico (mecánico u otros cargos no mapeados)
  header('Location: ' . $base . '/modules/empleado/');
  exit;
}

// Sin roles: también al selector (mostrará aviso)
header('Location: ' . $base . '/modules/selector/');
exit;
