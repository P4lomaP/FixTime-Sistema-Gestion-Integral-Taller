<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/Conexion.php';
require_once __DIR__ . '/../../../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../../../clases/DomicilioRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

function redirect_with_flash(string $msg, string $type='success', string $tab='perfil'): void {
  $_SESSION['flash_msg']  = $msg;
  $_SESSION['flash_type'] = $type;
  header('Location: ' . $GLOBALS['base'] . '/modules/cliente/index.php?tab=' . urlencode($tab));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_with_flash('Método inválido.','error');
}

if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
  redirect_with_flash('Sesión expirada. Probá de nuevo.','error');
}

$uid = (int)($_SESSION['uid'] ?? 0);
if ($uid <= 0) {
  redirect_with_flash('Sesión inválida.','error');
}

$nombre   = trim((string)($_POST['nombre']   ?? ''));
$apellido = trim((string)($_POST['apellido'] ?? ''));
$dni      = trim((string)($_POST['dni']      ?? ''));
$email    = trim((string)($_POST['email']    ?? ''));

$telefonos = $_POST['telefonos'] ?? [];
if (!is_array($telefonos)) { $telefonos = []; }
// normalizo teléfonos: quito vacíos y espacios
$telefonos = array_values(array_filter(array_map(fn($t)=>trim((string)$t), $telefonos), fn($t)=>$t !== ''));

// domicilio (por texto; DomicilioRepositorio se encarga de upsert)
$domicilio = [
  'pais'         => trim((string)($_POST['pais'] ?? '')),
  'provincia'    => trim((string)($_POST['provincia'] ?? '')),
  'localidad'    => trim((string)($_POST['localidad'] ?? '')),
  'barrio'       => trim((string)($_POST['barrio'] ?? '')),
  'calle'        => trim((string)($_POST['calle'] ?? '')),
  'altura'       => trim((string)($_POST['altura'] ?? '')),
  'piso'         => trim((string)($_POST['piso'] ?? '')),
  'departamento' => trim((string)($_POST['departamento'] ?? '')),
];

try {
  $repo = new PersonaRepositorio();

  // 1) Actualizar datos básicos (NO toca Personas.email; maneja email en Contacto_Persona)
  $repo->actualizarPerfil($uid, [
    'nombre'   => $nombre,
    'apellido' => $apellido,
    'dni'      => $dni,
    'email'    => $email, // puede ir vacío; el repo lo quita/crea en Contacto_Persona (tipo Email)
  ]);

  // 2) Reemplazar teléfonos (tabla Contacto_Persona, tipo Teléfono)
  $repo->reemplazarTelefonos($uid, $telefonos);

  // 3) Guardar domicilio (pasa por Personas_Domicilios y tablas relacionadas)
  $repo->guardarDomicilio($uid, $domicilio);

  // 4) Refrescar nombre en sesión para el saludo
  $_SESSION['nombre']   = $nombre;
  $_SESSION['apellido'] = $apellido;

  redirect_with_flash('Cambios guardados.','success','perfil');

} catch (Throwable $e) {
  // Mensajes más amigables
  $msg = $e->getMessage();
  if (str_contains($msg, 'DNI') && str_contains($msg, 'registrado')) {
    $msg = 'El DNI ya está registrado por otro usuario.';
  } elseif (str_contains($msg, 'email') && str_contains($msg, 'registrado')) {
    $msg = 'El email ya está registrado por otro usuario.';
  } elseif ($msg === 'Email inválido') {
    // tal cual el repositorio
  } else {
    // Si viene desde PDO con SQLSTATE…
    $msg = 'No se pudieron guardar los cambios: ' . $msg;
  }
  redirect_with_flash($msg,'error','perfil');
}
