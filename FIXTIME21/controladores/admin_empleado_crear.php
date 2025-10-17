<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Sesion.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');

require_once __DIR__ . '/../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();
if (!$repoA->esAdmin((int)($_SESSION['uid'] ?? 0))) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

require_once __DIR__ . '/../clases/Conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $base . '/modules/admin/empleados.php?err=' . urlencode('Método inválido'));
  exit;
}

function db(): PDO { return Conexion::obtener(); }
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

/* ===== Helpers ===== */
function getTipoContactoId(PDO $pdo, string $descripcion): ?int {
  $st = $pdo->prepare("SELECT id FROM Tipos_Contactos WHERE descripcion=? LIMIT 1");
  $st->execute([$descripcion]);
  $id = $st->fetchColumn();
  return $id ? (int)$id : null;
}
function upsertContacto(PDO $pdo, int $personaId, string $tipo, ?string $valor): void {
  $tipoId = getTipoContactoId($pdo, $tipo);
  if (!$tipoId) return;
  $st = $pdo->prepare("SELECT id FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=? LIMIT 1");
  $st->execute([$personaId, $tipoId]);
  $id = $st->fetchColumn();

  if ($valor === null || trim($valor)==='') {
    if ($id) $pdo->prepare("DELETE FROM Contacto_Persona WHERE id=? LIMIT 1")->execute([$id]);
    return;
  }

  if ($id) {
    $pdo->prepare("UPDATE Contacto_Persona SET valor=? WHERE id=? LIMIT 1")->execute([$valor, $id]);
  } else {
    $pdo->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)")
        ->execute([$personaId, $tipoId, $valor]);
  }
}

function getOrCreatePais(PDO $pdo, string $desc): ?int {
  if ($desc==='') return null;
  $st = $pdo->prepare("SELECT id FROM Paises WHERE descripcion=? LIMIT 1");
  $st->execute([$desc]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  $pdo->prepare("INSERT INTO Paises (descripcion) VALUES (?)")->execute([$desc]);
  return (int)$pdo->lastInsertId();
}
function getOrCreateProvincia(PDO $pdo, string $desc, ?int $paisId): ?int {
  if ($desc==='') return null;
  $st = $pdo->prepare("SELECT id FROM Provincias WHERE descripcion=? AND Pais_id <=> ? LIMIT 1");
  $st->execute([$desc, $paisId]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  $pdo->prepare("INSERT INTO Provincias (descripcion, Pais_id) VALUES (?,?)")->execute([$desc, $paisId]);
  return (int)$pdo->lastInsertId();
}
function getOrCreateLocalidad(PDO $pdo, string $desc, ?int $provId): ?int {
  if ($desc==='') return null;
  $st = $pdo->prepare("SELECT id FROM Localidades WHERE descripcion=? AND Provincia_id <=> ? LIMIT 1");
  $st->execute([$desc, $provId]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  $pdo->prepare("INSERT INTO Localidades (descripcion, Provincia_id) VALUES (?,?)")->execute([$desc, $provId]);
  return (int)$pdo->lastInsertId();
}
function getOrCreateBarrio(PDO $pdo, string $desc, ?int $locId): ?int {
  if ($desc==='') return null;
  $st = $pdo->prepare("SELECT id FROM Barrios WHERE descripcion=? AND Localidad_id <=> ? LIMIT 1");
  $st->execute([$desc, $locId]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  $pdo->prepare("INSERT INTO Barrios (descripcion, Localidad_id) VALUES (?,?)")->execute([$desc, $locId]);
  return (int)$pdo->lastInsertId();
}
function getOrCreateDomicilio(PDO $pdo, ?int $barrioId, string $calle, string $altura, string $piso, string $depto): ?int {
  if (!$barrioId && $calle==='' && $altura==='' && $piso==='' && $depto==='') return null;
  $st = $pdo->prepare("SELECT id FROM Domicilios WHERE Barrio_id <=> ? AND calle <=> ? AND altura <=> ? AND piso <=> ? AND departamento <=> ? LIMIT 1");
  $st->execute([
    $barrioId, $calle!==''?$calle:null, $altura!==''?$altura:null, $piso!==''?$piso:null, $depto!==''?$depto:null
  ]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;

  $pdo->prepare("INSERT INTO Domicilios (Barrio_id, calle, altura, piso, departamento) VALUES (?,?,?,?,?)")
      ->execute([$barrioId, $calle!==''?$calle:null, $altura!==''?$altura:null, $piso!==''?$piso:null, $depto!==''?$depto:null]);
  return (int)$pdo->lastInsertId();
}
function vincularDomicilioPersona(PDO $pdo, int $personaId, ?int $domId): void {
  if (!$domId) return;
  $st = $pdo->prepare("SELECT id FROM Personas_Domicilios WHERE Persona_id=? LIMIT 1");
  $st->execute([$personaId]);
  $id = $st->fetchColumn();
  if ($id) {
    $pdo->prepare("UPDATE Personas_Domicilios SET Domicilio_id=? WHERE id=? LIMIT 1")->execute([$domId, $id]);
  } else {
    $pdo->prepare("INSERT INTO Personas_Domicilios (Persona_id, Domicilio_id) VALUES (?,?)")->execute([$personaId, $domId]);
  }
}

/* ===== Normalización inputs ===== */
$nombre      = trim((string)($_POST['nombre']      ?? ''));
$apellido    = trim((string)($_POST['apellido']    ?? ''));
$dni         = preg_replace('/\D+/', '', (string)($_POST['dni'] ?? ''));
$email       = trim((string)($_POST['email']       ?? ''));
$telefono    = trim((string)($_POST['telefono']    ?? ''));
$password    = (string)($_POST['password'] ?? '');
$cargoId     = (int)($_POST['cargo_id'] ?? 0);

// Domicilio
$pais        = trim((string)($_POST['pais'] ?? ''));
$provincia   = trim((string)($_POST['provincia'] ?? ''));
$localidad   = trim((string)($_POST['localidad'] ?? ''));
$barrio      = trim((string)($_POST['barrio'] ?? ''));
$calle       = trim((string)($_POST['calle'] ?? ''));
$altura      = trim((string)($_POST['altura'] ?? ''));
$piso        = trim((string)($_POST['piso'] ?? ''));
$departamento= trim((string)($_POST['departamento'] ?? ''));

// Honeypot
if (!empty($_POST['hp'] ?? '')) {
  header('Location: ' . $base . '/modules/admin/empleados.php?err=' . urlencode('Solicitud inválida.'));
  exit;
}

/* ===== Validaciones ===== */
try {
  if ($nombre==='' || mb_strlen($nombre) < 2)       throw new RuntimeException('Nombre inválido.');
  if ($apellido==='' || mb_strlen($apellido) < 2)   throw new RuntimeException('Apellido inválido.');
  if ($dni==='' || strlen($dni) < 7 || strlen($dni) > 10) throw new RuntimeException('DNI inválido.');
  if ($email!=='' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Email inválido.');
  if ($password==='' || strlen($password) < 6)      throw new RuntimeException('La contraseña debe tener 6+ caracteres.');
  if (!$cargoId)                                    throw new RuntimeException('Seleccioná un cargo.');
} catch (Throwable $e) {
  header('Location: ' . $base . '/modules/admin/empleados.php?err=' . urlencode($e->getMessage()));
  exit;
}

/* ===== Alta ===== */
$pdo = db();
$pdo->beginTransaction();

try {
  // ¿Existe persona por DNI?
  $st = $pdo->prepare("SELECT id FROM Personas WHERE dni = ? LIMIT 1");
  $st->execute([$dni]);
  $personaId = (int)($st->fetchColumn() ?: 0);

  if ($personaId) {
    // ¿Ya tiene empleado activo?
    $chk = $pdo->prepare("SELECT 1 FROM Empleados WHERE Persona_id=? AND fecha_baja IS NULL LIMIT 1");
    $chk->execute([$personaId]);
    if ($chk->fetchColumn()) throw new RuntimeException('Ya existe un empleado activo con ese DNI.');

    // Actualizo datos básicos por si cambiaron, y (re)defino password si viene
    $pdo->prepare("UPDATE Personas SET nombre=?, apellido=?, dni=?, contrasenia=? WHERE id=? LIMIT 1")
        ->execute([$nombre, $apellido, $dni, password_hash($password, PASSWORD_DEFAULT), $personaId]);
  } else {
    // Creo persona nueva
    $pdo->prepare("INSERT INTO Personas (nombre, apellido, dni, contrasenia) VALUES (?,?,?,?)")
        ->execute([$nombre, $apellido, $dni, password_hash($password, PASSWORD_DEFAULT)]);
    $personaId = (int)$pdo->lastInsertId();
  }

  // Contactos
  upsertContacto($pdo, $personaId, 'Email',    $email !== '' ? $email : null);
  upsertContacto($pdo, $personaId, 'Teléfono', $telefono !== '' ? $telefono : null);

  // Domicilio
  $paisId   = getOrCreatePais($pdo, $pais);
  $provId   = getOrCreateProvincia($pdo, $provincia, $paisId);
  $locId    = getOrCreateLocalidad($pdo, $localidad, $provId);
  $barrioId = getOrCreateBarrio($pdo, $barrio, $locId);
  $domId    = getOrCreateDomicilio($pdo, $barrioId, $calle, $altura, $piso, $departamento);
  if ($domId) vincularDomicilioPersona($pdo, $personaId, $domId);

  // Empleado (fecha_baja NULL por defecto)
  $pdo->prepare("INSERT INTO Empleados (Persona_id, Cargo_id) VALUES (?, ?)")
      ->execute([$personaId, $cargoId]);

  // Si el cargo es "Administrador" => activar/crear en Administradores
  $adminCargoId = (int)($pdo->query("SELECT id FROM Cargos WHERE descripcion='Administrador' LIMIT 1")->fetchColumn() ?: 0);
  if ($adminCargoId && $cargoId === $adminCargoId) {
    // Reactivo si existe, sino creo
    $upd = $pdo->prepare("UPDATE Administradores SET fecha_baja=NULL WHERE Persona_id=?");
    $upd->execute([$personaId]);

    $pdo->prepare("INSERT INTO Administradores (Persona_id)
                   SELECT ? WHERE NOT EXISTS (SELECT 1 FROM Administradores WHERE Persona_id=?)")
        ->execute([$personaId, $personaId]);
  }

  $pdo->commit();

  // Redirigir a la tabla filtrada por el cargo elegido y con ancla a #list
  $dest = $base . '/modules/admin/empleados.php?cargo_filter=' . (int)$cargoId . '&ok=' . urlencode('Empleado creado') . '#list';
  header('Location: ' . $dest);
  exit;

} catch (Throwable $e) {
  $pdo->rollBack();
  header('Location: ' . $base . '/modules/admin/empleados.php?err=' . urlencode($e->getMessage()));
  exit;
}
