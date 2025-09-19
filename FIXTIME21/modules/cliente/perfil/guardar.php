<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../clases/Sesion.php';
require_once __DIR__ . '/../../../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../../../clases/EmpresaRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid   = (int)($_SESSION['uid'] ?? 0);
$repoP = new PersonaRepositorio();
$repoE = new EmpresaRepositorio();

$norm = function (?string $v): string {
  $v = trim((string)$v);
  return preg_replace('/\s+/', ' ', $v);
};
$title = function (?string $v) use ($norm): string {
  return mb_convert_case($norm($v), MB_CASE_TITLE, "UTF-8");
};

// Persona
$nombre   = $title($_POST['nombre']   ?? '');
$apellido = $title($_POST['apellido'] ?? '');
$dni      = $norm($_POST['dni'] ?? '');
$email    = mb_strtolower($norm($_POST['email'] ?? ''));

// Domicilio
$pais      = $title($_POST['pais'] ?? '');
$provincia = $title($_POST['provincia'] ?? '');
$localidad = $title($_POST['localidad'] ?? '');
$barrio    = $title($_POST['barrio'] ?? '');
$calle     = $title($_POST['calle'] ?? '');
$altura    = $norm($_POST['altura'] ?? '');
$piso      = $norm($_POST['piso'] ?? '');
$depto     = $norm($_POST['departamento'] ?? '');

// Empresa (opcionales salvo razon+CUIT si los completa)
$empresaRazon = $title($_POST['empresa_razon'] ?? '');
$empresaCUIT  = $norm($_POST['empresa_cuit']  ?? '');
$empresaEmail = mb_strtolower($norm($_POST['empresa_email'] ?? ''));
$empresaTel   = $norm($_POST['empresa_tel'] ?? '');

$errores = [];
if ($nombre === '' || $apellido === '') $errores[] = 'Nombre y apellido son obligatorios.';
if ($dni === '') $errores[] = 'DNI es obligatorio.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Email inválido.';
if ($empresaRazon !== '' || $empresaCUIT !== '') {
  if ($empresaRazon === '' || $empresaCUIT === '') {
    $errores[] = 'Para vincular una empresa completá Razón social y CUIT.';
  }
}

if ($errores) {
  $_SESSION['flash_error'] = implode(' ', $errores);
  header("Location: $base/modules/cliente/perfil/");
  exit;
}

try {
  $repoP->begin();

  // Evitar duplicados lógicos en otros usuarios
  if ($repoP->dniPerteneceAOtro($dni, $uid)) {
    throw new RuntimeException('El DNI ya pertenece a otra persona.');
  }
  if ($repoP->emailPerteneceAOtro($email, $uid)) {
    throw new RuntimeException('El email ya pertenece a otra persona.');
  }

  // Upsert de persona + email
  $repoP->actualizarPerfil($uid, $nombre, $apellido, $dni, $email);

  // Árbol de ubicación (reuso, sin duplicar)
  $paisId = $repoP->getOrCreatePais($pais !== '' ? $pais : 'Argentina');
  $provId = $repoP->getOrCreateProvincia($provincia ?: '—', $paisId);
  $locId  = $repoP->getOrCreateLocalidad($localidad ?: '—', $provId);
  $barId  = $repoP->getOrCreateBarrio($barrio ?: '—', $locId);

  // Domicilio: reusar si existe idéntico, si no crear
  $domId  = $repoP->findOrCreateDomicilioReusando(
    $barId, $calle, $altura, $piso !== '' ? $piso : null, $depto !== '' ? $depto : null
  );

  // Vincular persona ↔ domicilio (sin duplicar relación)
  $repoP->vincularPersonaDomicilio($uid, $domId);

  // Empresa (opcional): sin tocar la base -> vínculo por contacto compartido (CUIT_EMPRESA)
  if ($empresaRazon !== '' && $empresaCUIT !== '') {
    $repoE->upsertEmpresaYVincularPorCUIT($uid, $empresaRazon, $empresaCUIT, $empresaEmail ?: null, $empresaTel ?: null);
  }

  $repoP->commit();

  $_SESSION['flash_ok'] = 'Datos actualizados correctamente.';
  header("Location: $base/modules/cliente/perfil/");
  exit;

} catch (Throwable $e) {
  $repoP->rollBack();
  $msg = $e->getMessage();
  if ($msg === 'CUIT_INVALIDO') $msg = 'CUIT inválido. Usá sólo números (con o sin guiones).';
  $_SESSION['flash_error'] = 'No se pudo guardar: ' . $msg;
  header("Location: $base/modules/cliente/perfil/");
  exit;
}
