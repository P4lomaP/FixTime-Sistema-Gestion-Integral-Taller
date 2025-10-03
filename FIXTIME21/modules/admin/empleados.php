<?php
declare(strict_types=1);

require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();
if (!$repoA->esAdmin((int)($_SESSION['uid'] ?? 0))) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

require_once __DIR__ . '/../../clases/Conexion.php';
require_once __DIR__ . '/../../clases/EmpleadoRepositorio.php';
$repoE = new EmpleadoRepositorio();

/* ------------------------- helpers ------------------------- */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
/** @return PDO */
function db(){ return Conexion::obtener(); }

/* ====== CONTACTOS ====== */
/** Obtiene id del tipo de contacto por descripción. */
function getTipoContactoId(string $descripcion): ?int {
  $st = db()->prepare("SELECT id FROM Tipos_Contactos WHERE descripcion = ? LIMIT 1");
  $st->execute([$descripcion]);
  $id = $st->fetchColumn();
  return $id ? (int)$id : null;
}
/** Upsert del contacto (por persona+tipo). Si valor vacío, elimina el existente de esa persona/tipo. */
function upsertContacto(int $personaId, string $tipo, ?string $valor): void {
  $tipoId = getTipoContactoId($tipo);
  if (!$tipoId) return;
  $st = db()->prepare("SELECT id FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=? LIMIT 1");
  $st->execute([$personaId, $tipoId]);
  $id = $st->fetchColumn();

  if ($valor === null || trim($valor)==='') {
    if ($id) db()->prepare("DELETE FROM Contacto_Persona WHERE id=? LIMIT 1")->execute([$id]);
    return;
  }
  if ($id) db()->prepare("UPDATE Contacto_Persona SET valor=? WHERE id=? LIMIT 1")->execute([$valor, $id]);
  else     db()->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)")->execute([$personaId, $tipoId, $valor]);
}

/* ====== EMAIL DUPLICADO (edición) ====== */
/** Verifica si el email está en uso por otra persona (edición). */
function emailUsadoPorOtro(string $email, int $personaId): bool {
  if ($email === '') return false;
  $sql = "SELECT 1
          FROM Contacto_Persona cp
          JOIN Tipos_Contactos t ON t.id = cp.Tipo_Contacto_id AND t.descripcion='Email'
          WHERE LOWER(cp.valor)=LOWER(?) AND cp.Persona_id <> ?
          LIMIT 1";
  $st = db()->prepare($sql);
  $st->execute([$email, $personaId]);
  return (bool)$st->fetchColumn();
}

/* ====== GEO / DOMICILIO ====== */
function getOrCreatePais(string $desc): ?int {
  if ($desc==='') return null;
  $st = db()->prepare("SELECT id FROM Paises WHERE descripcion=? LIMIT 1");
  $st->execute([$desc]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  db()->prepare("INSERT INTO Paises (descripcion) VALUES (?)")->execute([$desc]);
  return (int)db()->lastInsertId();
}
function getOrCreateProvincia(string $desc, ?int $paisId): ?int {
  if ($desc==='') return null;
  $st = db()->prepare("SELECT id FROM Provincias WHERE descripcion=? AND Pais_id <=> ? LIMIT 1");
  $st->execute([$desc, $paisId]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  db()->prepare("INSERT INTO Provincias (descripcion, Pais_id) VALUES (?,?)")->execute([$desc, $paisId]);
  return (int)db()->lastInsertId();
}
function getOrCreateLocalidad(string $desc, ?int $provId): ?int {
  if ($desc==='') return null;
  $st = db()->prepare("SELECT id FROM Localidades WHERE descripcion=? AND Provincia_id <=> ? LIMIT 1");
  $st->execute([$desc, $provId]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  db()->prepare("INSERT INTO Localidades (descripcion, Provincia_id) VALUES (?,?)")->execute([$desc, $provId]);
  return (int)db()->lastInsertId();
}
function getOrCreateBarrio(string $desc, ?int $locId): ?int {
  if ($desc==='') return null;
  $st = db()->prepare("SELECT id FROM Barrios WHERE descripcion=? AND Localidad_id <=> ? LIMIT 1");
  $st->execute([$desc, $locId]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;
  db()->prepare("INSERT INTO Barrios (descripcion, Localidad_id) VALUES (?,?)")->execute([$desc, $locId]);
  return (int)db()->lastInsertId();
}
/** Busca/crea domicilio idéntico y retorna id (o null si todo vacío). */
function getOrCreateDomicilio(?int $barrioId, string $calle, string $altura, string $piso, string $depto): ?int {
  if (!$barrioId && $calle==='' && $altura==='' && $piso==='' && $depto==='') return null;
  $st = db()->prepare("SELECT id FROM Domicilios WHERE Barrio_id <=> ? AND calle <=> ? AND altura <=> ? AND piso <=> ? AND departamento <=> ? LIMIT 1");
  $st->execute([$barrioId, $calle!==''?$calle:null, $altura!==''?$altura:null, $piso!==''?$piso:null, $depto!==''?$depto:null]);
  $id = $st->fetchColumn();
  if ($id) return (int)$id;

  db()->prepare("INSERT INTO Domicilios (Barrio_id, calle, altura, piso, departamento) VALUES (?,?,?,?,?)")
     ->execute([$barrioId, $calle!==''?$calle:null, $altura!==''?$altura:null, $piso!==''?$piso:null, $depto!==''?$depto:null]);
  return (int)db()->lastInsertId();
}
/** Vincula/actualiza Personas_Domicilios a un Domicilio_id. */
function vincularDomicilioPersona(int $personaId, ?int $domId): void {
  if (!$domId) return;
  $st = db()->prepare("SELECT id FROM Personas_Domicilios WHERE Persona_id=? LIMIT 1");
  $st->execute([$personaId]);
  $id = $st->fetchColumn();
  if ($id) db()->prepare("UPDATE Personas_Domicilios SET Domicilio_id=? WHERE id=? LIMIT 1")->execute([$domId, $id]);
  else     db()->prepare("INSERT INTO Personas_Domicilios (Persona_id, Domicilio_id) VALUES (?,?)")->execute([$personaId, $domId]);
}

/* ====== ADMIN ROL (no cambiamos DB, sólo sincronizamos si existe el cargo Administrador) ====== */
/** Devuelve el id de cargo "Administrador" si existe. */
function cargoAdminId(): ?int {
  $st = db()->query("SELECT id FROM Cargos WHERE descripcion = 'Administrador' LIMIT 1");
  $id = $st->fetchColumn();
  return $id ? (int)$id : null;
}
/** Sincroniza tabla Administradores según el cargo elegido (si existe). */
function toggleAdministradorParaPersona(int $personaId, bool $esAdmin): void {
  // Esta función es NO destructiva y asume la tabla Administradores(Persona_id,...)
  // No usa fecha_baja para no tocar el modelo de datos del usuario.
  $pdo = db();
  if ($esAdmin) {
    // Inserta si no existe
    $pdo->prepare("INSERT INTO Administradores (Persona_id)
                   SELECT ? WHERE NOT EXISTS(SELECT 1 FROM Administradores WHERE Persona_id=?)")
        ->execute([$personaId, $personaId]);
  } else {
    // Quitar fila de Administradores si existe
    $pdo->prepare("DELETE FROM Administradores WHERE Persona_id=?")->execute([$personaId]);
  }
}

/* ====== FETCH COMPLETO PARA EL MODAL ====== */
function fetchEmpleadoCompleto(int $empleadoId): ?array {
  $sql = "
    SELECT 
      e.id            AS empleado_id,
      e.Persona_id    AS persona_id,
      e.Cargo_id      AS cargo_id,
      p.nombre, p.apellido, p.dni,
      c.descripcion   AS cargo_desc,
      (SELECT cp.valor FROM Contacto_Persona cp 
         JOIN Tipos_Contactos tc ON tc.id=cp.Tipo_Contacto_id AND tc.descripcion='Email'
        WHERE cp.Persona_id=p.id LIMIT 1) AS email,
      (SELECT cp.valor FROM Contacto_Persona cp 
         JOIN Tipos_Contactos tc ON tc.id=cp.Tipo_Contacto_id AND tc.descripcion='Teléfono'
        WHERE cp.Persona_id=p.id LIMIT 1) AS telefono,
      d.calle, d.altura, d.piso, d.departamento,
      b.descripcion AS barrio, 
      l.descripcion AS localidad,
      pr.descripcion AS provincia,
      pa.descripcion AS pais
    FROM Empleados e
    JOIN Personas  p  ON p.id = e.Persona_id
    LEFT JOIN Cargos c ON c.id = e.Cargo_id
    LEFT JOIN Personas_Domicilios pd ON pd.Persona_id = p.id
    LEFT JOIN Domicilios d ON d.id = pd.Domicilio_id
    LEFT JOIN Barrios b ON b.id = d.Barrio_id
    LEFT JOIN Localidades l ON l.id = b.Localidad_id
    LEFT JOIN Provincias pr ON pr.id = l.Provincia_id
    LEFT JOIN Paises pa ON pa.id = pr.Pais_id
    WHERE e.id = ?
    LIMIT 1";
  $st = db()->prepare($sql);
  $st->execute([$empleadoId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/* ====== UPDATE COMPLETO ====== */
function actualizarEmpleado(array $data): bool {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    if ($data['nombre']==='' || $data['apellido']==='' || $data['dni']==='' || !$data['cargo_id']) {
      throw new RuntimeException('Completá nombre, apellido, DNI y cargo.');
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('El email no es válido.');
    }
    if ($data['email'] !== '' && emailUsadoPorOtro($data['email'], (int)$data['persona_id'])) {
      throw new RuntimeException('Ya existe un usuario con ese email.');
    }

    $pdo->prepare("UPDATE Personas SET nombre=?, apellido=?, dni=? WHERE id=? LIMIT 1")
        ->execute([$data['nombre'], $data['apellido'], $data['dni'], (int)$data['persona_id']]);

    $pdo->prepare("UPDATE Empleados SET Cargo_id=? WHERE id=? LIMIT 1")
        ->execute([(int)$data['cargo_id'], (int)$data['empleado_id']]);

    upsertContacto((int)$data['persona_id'], 'Email',    $data['email']    !== '' ? $data['email']    : null);
    upsertContacto((int)$data['persona_id'], 'Teléfono', $data['telefono'] !== '' ? $data['telefono'] : null);

    $paisId   = getOrCreatePais($data['pais']);
    $provId   = getOrCreateProvincia($data['provincia'], $paisId);
    $locId    = getOrCreateLocalidad($data['localidad'], $provId);
    $barrioId = getOrCreateBarrio($data['barrio'], $locId);
    $domId    = getOrCreateDomicilio($barrioId, $data['calle'], $data['altura'], $data['piso'], $data['departamento']);
    if ($domId) vincularDomicilioPersona((int)$data['persona_id'], $domId);

    // Sincronizar rol Administrador según cargo seleccionado (si existe el cargo)
    $adminCargoId = cargoAdminId();
    if ($adminCargoId) {
      $esAdminCargo = ((int)$data['cargo_id'] === (int)$adminCargoId);
      toggleAdministradorParaPersona((int)$data['persona_id'], $esAdminCargo);
    }

    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/* --------------------- acciones (POST) --------------------- */
$ok  = $_GET['ok']  ?? '';
$err = $_GET['err'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'update_empleado') {
  $payload = [
    'empleado_id' => (int)($_POST['empleado_id'] ?? 0),
    'persona_id'  => (int)($_POST['persona_id']  ?? 0),
    'cargo_id'    => (int)($_POST['cargo_id']    ?? 0),
    'nombre'      => trim((string)($_POST['nombre']   ?? '')),
    'apellido'    => trim((string)($_POST['apellido'] ?? '')),
    'dni'         => preg_replace('/\D+/', '', (string)($_POST['dni'] ?? '')),
    'email'       => trim((string)($_POST['email'] ?? '')),
    'telefono'    => trim((string)($_POST['telefono'] ?? '')),
    'pais'        => trim((string)($_POST['pais'] ?? '')),
    'provincia'   => trim((string)($_POST['provincia'] ?? '')),
    'localidad'   => trim((string)($_POST['localidad'] ?? '')),
    'barrio'      => trim((string)($_POST['barrio'] ?? '')),
    'calle'       => trim((string)($_POST['calle'] ?? '')),
    'altura'      => trim((string)($_POST['altura'] ?? '')),
    'piso'        => trim((string)($_POST['piso'] ?? '')),
    'departamento'=> trim((string)($_POST['departamento'] ?? '')),
  ];

  try {
    if (actualizarEmpleado($payload)) {
      header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?ok=' . urlencode('Cambios guardados.') . '#list');
      exit;
    }
  } catch (Throwable $e) {
    $err = 'No se pudieron guardar los cambios: ' . $e->getMessage();
  }
}

/* ------------------ datos de pantalla (GET) ------------------ */
$cargoFilter = isset($_GET['cargo_filter']) && $_GET['cargo_filter'] !== '' ? (int)$_GET['cargo_filter'] : null;
$empleados   = $repoE->listar($cargoFilter);

$nombre   = $_SESSION['nombre']  ?? 'Admin';
$apellido = $_SESSION['apellido']?? '';
$msj = $ok ?: $err;

/* precarga modal (solo si hay ?edit=) */
$editId  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$empEdit = null;
if ($editId > 0) {
  $empEdit = fetchEmpleadoCompleto($editId);
  if (!$empEdit) {
    // si viene un edit inválido, mostrar toast de error vía query
    header('Location: ' . strtok($_SERVER['REQUEST_URI'],'?') . '?err=' . urlencode('No se encontró el empleado a editar.') . '#list');
    exit;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fixtime — Panel de Administrador</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<style>
/* =====================================================================
   THEME + LAYOUT
   ===================================================================== */
:root{ --bg:#0b1226; --panel:#0f1a33; --panel-2:#0b162b; --card:#0c1730;
  --muted:#9db0d0; --text:#e9f0ff; --brand:#3b82f6; --brand-2:#2563eb;
  --ring:rgba(59,130,246,.40); --shadow:0 12px 40px rgba(2,6,23,.45); --radius:18px;}
*{box-sizing:border-box} html,body{height:100%;margin:0}
body{min-height:100vh;background:
  radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.22), transparent 70%),
  radial-gradient(900px 480px at 10% 110%, rgba(37,99,235,.16), transparent 60%),
  var(--bg); color:var(--text); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;}
.app{display:grid;grid-template-columns:320px 1fr;min-height:100vh}
.sidebar{padding:22px;background:linear-gradient(180deg,var(--panel),var(--bg));border-right:1px solid rgba(157,176,208,.15);position: sticky; top:0; height:100vh; z-index:40;}
.brand{display:flex;gap:12px;align-items:center;margin-bottom:22px}
.brand-badge{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(40px 30px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2));box-shadow:0 12px 30px var(--ring), inset 0 1px 0 rgba(255,255,255,.25)}
.brand-badge img{width:32px;height:32px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.brand-name{font-weight:800;letter-spacing:.35px;font-size:22px}
.brand-sub{opacity:.8;font-size:12px}
.theme-btn{margin-left:auto;appearance:none;border:1px solid rgba(157,176,208,.28);background:rgba(255,255,255,.06);color:var(--text);border-radius:12px;padding:10px 12px;cursor:pointer;font-size:16px;box-shadow:0 6px 16px rgba(0,0,0,.2)}
.nav{display:flex;flex-direction:column;gap:12px;margin-top:10px}
.nav a{display:flex;gap:12px;align-items:center;justify-content:flex-start;padding:14px 16px;border-radius:14px;border:1px solid rgba(157,176,208,.18);background:rgba(255,255,255,.03);color:var(--text);text-decoration:none;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.25)}
.nav a.active{background:linear-gradient(135deg, rgba(59,130,246,.20), rgba(37,99,235,.20));border-color:rgba(59,130,246,.55); box-shadow:0 10px 28px var(--ring)}
.nav .btn.topbar-salir{display:block !important;text-align:center !important;font-weight:800 !important;}
.topbar-salir{display:block;margin-top:14px;text-align:center;text-decoration:none}
.main{padding:26px 32px;display:flex;flex-direction:column;min-height:100vh}
.hero{display:flex;align-items:center;gap:16px;background:linear-gradient(135deg, rgba(59,130,246,.22), rgba(37,99,235,.18));border:1px solid rgba(59,130,246,.40);border-radius:var(--radius);padding:18px;box-shadow:var(--ring);margin-bottom:16px}
.hero .avatar{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(24px 16px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2))}
.hero .avatar img{width:38px;height:38px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.greet{font-weight:800;font-size:22px}
.hint{color:rgba(233,240,255,0.9)}
.card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.03));border:1px solid rgba(157,176,208,.16);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
@media(max-width:700px){ .row{grid-template-columns:1fr} }
label{font-size:12px;color:var(--muted)}
input,select{width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px}
.btn{cursor:pointer;border:0;border-radius:12px;padding:12px 16px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-weight:800;box-shadow:0 12px 28px rgba(59,130,246,.32);transition:filter .2s ease,transform .12s ease}
.btn.ghost{background:transparent;color:#e9f0ff;border:1px solid rgba(157,176,208,.35);box-shadow:none}
.btn.danger{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;box-shadow:0 12px 28px rgba(239,68,68,.35)}
.btn.danger:hover{filter:brightness(.98)}
.table{width:100%;border-collapse:collapse;font-size:14px}
.table th,.table td{padding:12px 10px;border-bottom:1px solid rgba(157,176,208,.14);text-align:left}
.header-mobile{display:none;align-items:center;gap:10px;position:sticky;top:0;z-index:1100;padding:12px 16px;background:rgba(12,23,48,.75);backdrop-filter:blur(8px);border-bottom:1px solid rgba(255,255,255,.06)}
.burger{appearance:none;background:transparent;border:0;color:var(--text);font-size:24px;cursor:pointer}
.sidebar__overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000}
.sidebar__overlay.show{display:block}
@media (max-width:1080px){
  .app{grid-template-columns:1fr}
  .sidebar{position:fixed; inset:0 auto 0 0; width:84%; max-width:320px; height:100vh; transform:translateX(-105%); transition:transform .22s ease; z-index:1001; box-shadow:var(--shadow)}
  .sidebar.open{transform:translateX(0)}
  .header-mobile{display:flex}
}
.theme-light{--bg:#f3f6fc;--panel:#ffffff;--panel-2:#f7f9ff;--card:#ffffff;--muted:#5b6b85;--text:#0b1220;--ring:rgba(59,130,246,.28);--shadow:0 8px 26px rgba(15,23,42,.08)}
.theme-light .sidebar{background:linear-gradient(180deg,var(--panel),#eaf0ff);border-right:1px solid rgba(15,23,42,.06)}
.theme-light .nav a{background:#fff;border-color:rgba(15,23,42,.08)}
.theme-light .card{background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:var(--shadow)}
.theme-light .theme-btn{background:#fff;border-color:rgba(15,23,42,.08);color:#0b1220}
.theme-light .hint { color:#475569; }  /* legible en claro */

/* =====================================================================
   TOASTS BLANCOS (estética de las capturas): fondo blanco, barrita animada,
   textos gris, ícono ✓/X, animación in/out
   ===================================================================== */
.toasts{position:fixed; top:18px; right:18px; z-index:1300; display:flex; flex-direction:column; gap:10px}
.toast{
  width:min(440px, 92vw);
  background:#ffffff; color:#111827;
  border:1px solid rgba(15,23,42,.08);
  border-radius:14px;
  box-shadow:0 20px 40px rgba(15,23,42,.12);
  padding:12px 14px 10px; display:flex; gap:10px; align-items:flex-start;
  animation:toastIn .22s ease-out both;
}
.toast .icon{width:28px; height:28px; flex:0 0 28px; display:grid; place-items:center}
.toast .icon svg{display:block}
.toast .body{flex:1; font-weight:700; color:#374151}
.toast .close{appearance:none;background:transparent;border:0;color:#6b7280;cursor:pointer;font-size:18px}
.toast .close:hover{color:#111827}
.toast .bar{height:3px; margin-top:8px; border-radius:3px; background:#e5e7eb; overflow:hidden}
.toast .bar>i{ display:block; height:100%; width:0% }
.toast.success .bar>i{ background:#22c55e }
.toast.error   .bar>i{ background:#ef4444 }
@keyframes toastIn{from{transform:translateY(-6px); opacity:0} to{transform:none; opacity:1}}
@keyframes toastOut{to{transform:translateY(-6px); opacity:0}}

/* =====================================================================
   SEGMENTED CONTROL (Empleado / Administrador)
   ===================================================================== */
.segmented{
  display:inline-flex; gap:8px; padding:6px;
  border-radius:999px;
  background:#fff; border:1px solid rgba(15,23,42,.10);
  box-shadow:0 8px 18px rgba(15,23,42,.10), inset 0 1px 0 rgba(15,23,42,.04);
  margin:4px 0 12px;
}
.segmented__btn{
  position:relative;
  display:inline-flex; align-items:center; gap:8px;
  padding:10px 14px; border-radius:999px;
  background:transparent; color:var(--muted);
  border:0; cursor:pointer; font-weight:800;
  transition: transform .12s ease, background .2s ease, box-shadow .2s ease, color .2s ease;
}
.segmented__icon{ font-size:16px; line-height:0 }
.segmented__btn:hover{ background:rgba(15,23,42,.04) }
.segmented__btn:active{ transform:translateY(1px) }
.segmented__btn:focus-visible{ outline:none; box-shadow:0 0 0 3px var(--ring); }
.segmented__btn.active{
  color:#0b1220;
  background:linear-gradient(135deg,var(--brand),var(--brand-2));
  box-shadow:0 10px 24px rgba(59,130,246,.25);
}

/* Bloquear select cuando es admin (sin disabled para que envíe el valor) */
.locked{ pointer-events:none; opacity:.75; }

/* =====================================================================
   MODAL CONFIRM BAJA (look blanco con círculo "!")
   ===================================================================== */
.confirm-backdrop{
  position:fixed; inset:0; z-index:1400;
  background:rgba(0,0,0,.55); backdrop-filter:blur(2px);
  display:none; align-items:center; justify-content:center;
}
.confirm-backdrop.show{ display:flex; }
.confirm-modal{
  width:min(560px,96vw);
  border-radius:16px;
  padding:22px;
  background:#ffffff; color:#0b1220;
  border:1px solid rgba(15,23,42,.08);
  box-shadow:0 24px 60px rgba(15,23,42,.18);
  text-align:center;
  animation:toastIn .22s ease-out both;
}
.confirm-icon{
  width:110px; height:110px; border-radius:999px;
  margin:0 auto 12px;
  display:grid; place-items:center;
  border:6px solid #f59e0b20; color:#f59e0b; font-size:56px; font-weight:700;
}
.confirm-title{ font-size:28px; font-weight:800; margin:6px 0 6px }
.confirm-sub{ color:#6b7280; margin-bottom:18px }
.confirm-actions{ display:flex; gap:12px; justify-content:center; margin-top:4px }

/* ================== FIXES: modal edición + botones modo oscuro ================== */
.modal-backdrop{
  position:fixed; inset:0; z-index:1400;
  background:rgba(0,0,0,.55); backdrop-filter:blur(2px);
  display:none; align-items:center; justify-content:center;
}
.modal-backdrop.show{ display:flex; }
.modal {
  width: min(980px, 96vw);
  max-height: none;   /* eliminamos el límite de altura */
  height: auto;       /* que crezca según el contenido */
  overflow: visible;  /* sin scroll interno */
  border-radius: 16px;
  background: var(--panel);
  color: var(--text);
  border: 1px solid rgba(157,176,208,18);
  box-shadow: 0 24px 60px rgba(0,0,0,35);
  padding: 22px;
}
.modal h3{font-size:20px;font-weight:800;margin:0 0 12px}
.modal .row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
@media(max-width:700px){ .modal .row{grid-template-columns:1fr} }
.modal label{font-size:12px;color:var(--muted)}
.modal input,.modal select{width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px}
.modal .footer{margin-top:14px;display:flex;justify-content:flex-end;gap:10px}

input,select{width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px}
.btn{cursor:pointer;border:0;border-radius:12px;padding:12px 16px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-weight:800;box-shadow:0 12px 28px rgba(59,130,246,.32);transition:filter .2s ease,transform .12s ease}
.btn:hover{filter:brightness(1.06)}
.btn.ghost{background:transparent;color:#e9f0ff;border:1px solid rgba(157,176,208,.35);box-shadow:none}
.btn.danger{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;box-shadow:0 12px 28px rgba(239,68,68,.35)}
.header-mobile{display:none;align-items:center;gap:10px;position:sticky;top:0;background:rgba(11,18,32,.6);backdrop-filter:blur(8px);border-bottom:1px solid rgba(255,255,255,.06)}
.sidebar{padding:22px;background:linear-gradient(180deg,var(--panel),rgba(59,130,246,.06));border-right:1px solid rgba(157,176,208,.15);position: sticky; top:0; height:100vh; z-index:40;}
@media (max-width:1080px){
  .app{grid-template-columns:1fr}
  .sidebar{position:fixed; inset:0 auto 0 0; width:84%; max-width:360px; transform:translateX(-100%); transition:transform .22s ease; z-index:1001; box-shadow:var(--shadow)}
  .sidebar.open{transform:translateX(0)}
  .header-mobile{display:flex}
}
.theme-light{--bg:#f3f6fc;--panel:#ffffff;--panel-2:#f7f9ff;--card:#ffffff;--brand:#3b82f6;--brand-2:#2563eb;--muted:#5b6b85;--text:#0b1220;--ring:rgba(59,130,246,.28);--shadow:0 8px 26px rgba(15,23,42,.08)}

.segmented__btn{ position:relative; display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; background:transparent; color:var(--muted); border:0; cursor:pointer; font-weight:800; transition: transform .12s ease, background .2s ease, box-shadow .2s ease, color .2s ease; }
.segmented__btn.active{ background:linear-gradient(135deg,var(--brand),var(--brand-2)); color:#fff; box-shadow:0 12px 28px var(--ring); }
.segmented__btn:hover{ background:rgba(15,23,42,.10) }

.toggle-group {
  display: flex;
  gap: 8px;
  background: var(--card);
  border-radius: 50px;
  padding: 4px;
}

.toggle-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-weight: 600;
  border-radius: 50px;
  padding: 8px 14px;
  cursor: pointer;
  transition: all 0.25s ease;
  border: none;
}

.toggle-btn.active {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 0 12px rgba(0, 102, 255, 0.5);
}

.toggle-btn:not(.active) {
  background: transparent;
  color: var(--text-muted); /* gris clarito para que se vea */
}

.btn-danger {
  background: #e63946;
  color: #fff;
  font-weight: 600;
  padding: 10px 18px;
  border-radius: 12px;
  border: none;
  cursor: pointer;
  transition: background 0.25s ease, transform 0.15s ease;
  box-shadow: 0 4px 12px rgba(230, 57, 70, 0.4);
}
.btn-danger:hover {
  background: #d62828;
  transform: translateY(-2px);
}

.btn-outline {
  background: transparent;
  color: var(--text);
  border: 2px solid rgba(157,176,208,0.3);
  padding: 10px 18px;
  border-radius: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.25s ease;
}
.btn-outline:hover {
  border-color: var(--primary);
  color: var(--primary);
}

</style>
</head>
<body>

<div class="header-mobile">
  <button class="burger" id="btnMenu" aria-label="Abrir menú" aria-expanded="false">☰</button>
  <div style="display:flex;align-items:center;gap:10px">
    <div class="brand-badge" style="width:36px;height:36px;border-radius:10px"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
    <strong>Fixtime</strong>
  </div>
  <button id="themeToggleMobile" class="theme-btn" title="Cambiar tema">🌙</button>
</div>

<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-badge"><img src="<?= $base ?>/publico/widoo.png" alt="Fixtime"></div>
      <div style="flex:1"><div class="brand-name">Fixtime</div><div class="brand-sub">Panel de Administrador</div></div>
      <button id="themeToggle" class="theme-btn" title="Cambiar tema">🌙</button>
    </div>

    <nav class="nav" id="nav">
      <a href="<?= $base ?>/modules/admin/">🏠 Inicio</a>
      <a class="active" href="<?= $base ?>/modules/admin/empleados.php">👥 Empleados</a>
      <a href="<?= $base ?>/modules/admin/calendario.php">🗓️ Calendario</a>
      <a href="<?= $base ?>/modules/admin/vehiculos.php">🚗 Listar vehículos</a>
      <a href="<?= $base ?>/modules/admin/perfil.php">👤 Mi perfil</a>
      <a href="<?= $base ?>/modules/selector/index.php" class="btn ghost topbar-salir">⬅️ Volver al selector</a>
      <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">Cerrar sesión</a>
    </nav>
  </aside>
  <div class="sidebar__overlay" id="overlay"></div>

  <main class="main">
    <div class="hero">
      <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
      <div style="flex:1">
        <div class="greet">¡Hola, <?= h($nombre.' '.$apellido) ?>!</div>
        <div class="hint">Gestioná empleados, calendario y vehículos desde un solo lugar.</div>
      </div>
    </div>

    <!-- ===== Filtro por cargo ===== -->
    <section class="card" style="margin:16px 0">
      <form method="get" action="" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
        <strong>Filtrar por cargo:</strong>
        <select name="cargo_filter">
          <option value="">-- Todos --</option>
          <?php foreach(db()->query("SELECT id, descripcion FROM Cargos ORDER BY descripcion") as $c): 
                $sel = ($cargoFilter !== null && $cargoFilter === (int)$c['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= htmlspecialchars($c['descripcion']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Aplicar</button>
        <?php if($cargoFilter !== null): ?>
          <a class="btn ghost" href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>">Quitar filtro</a>
        <?php endif; ?>
      </form>
    </section>

    <!-- Toasts blancos -->
    <div class="toasts" id="toasts"></div>

    <!-- ===== Listado ===== -->
    <section class="card" id="list" style="margin-top:0">
      <h3 style="margin:0 0 10px">Listado</h3>
      <div style="overflow:auto">
        <table class="table">
          <thead><tr><th>Nombre</th><th>DNI</th><th>Cargo</th><th style="width:240px">Acciones</th></tr></thead>
          <tbody>
          <?php if(!empty($empleados)): foreach($empleados as $e): ?>
            <tr>
              <td><?= h($e['apellido'].', '.$e['nombre']) ?></td>
              <td><?= h($e['dni']) ?></td>
              <td><?= h($e['cargo']) ?></td>
              <td>
                <a class="btn ghost" href="<?= $base ?>/modules/admin/empleados.php?edit=<?= (int)$e['id'] ?>#edit">Editar</a>
                <button type="button" class="btn danger btn-baja"
                        data-href="<?= $base ?>/controladores/admin_empleado_baja.php?id=<?= (int)$e['id'] ?>"
                        data-name="<?= h($e['nombre'].' '.$e['apellido']) ?>">Eliminar</button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="4" style="color:var(--muted)">No hay empleados para el filtro seleccionado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ===== Alta Empleado / Administrador ===== -->
    <section class="card" style="margin:16px 0">
      <?php
        $accion = $base . "/controladores/admin_empleado_crear.php";
        $titulo = "Alta rápida de Empleado / Administrador";

        ob_start(); ?>
          <div class="segmented" id="altaMode" role="tablist" aria-label="Modo de alta">
            <button type="button" class="segmented__btn active" data-mode="empleado" role="tab" aria-selected="true">
              <span class="segmented__icon"></span><span>Empleado</span>
            </button>
            <button type="button" class="segmented__btn" data-mode="admin" role="tab" aria-selected="false">
              <span class="segmented__icon"></span><span>Administrador</span>
            </button>
          </div>

          <label>Seleccionar Cargo
            <select name="cargo_id" required id="cargoSelect">
              <option value="">-- Seleccionar --</option>
              <?php foreach(db()->query("SELECT id, descripcion FROM Cargos ORDER BY descripcion") as $c): 
                $isAdmin = (strcasecmp((string)$c['descripcion'],'Administrador')===0); ?>
                <option value="<?= (int)$c['id'] ?>" <?= $isAdmin?'data-admin="1"':'' ?>>
                  <?= htmlspecialchars($c['descripcion']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php
        $extraCampos = ob_get_clean();
        $submitLabel = "Crear";
        include __DIR__ . "/../../plantillas/form_persona_domicilio.php";
      ?>
    </section>
  </main>
</div>

<!-- ======================= MODAL EDICIÓN ======================= -->
<?php if ($empEdit): ?>
<div class="modal-backdrop show" id="modalEditBackdrop">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="editTitle">
    <h3 id="editTitle">Editar empleado</h3>

    <form method="post" action="<?= h($_SERVER['REQUEST_URI']) ?>">
      <input type="hidden" name="accion" value="update_empleado">
      <input type="hidden" name="empleado_id" value="<?= (int)$empEdit['empleado_id'] ?>">
      <input type="hidden" name="persona_id"  value="<?= (int)$empEdit['persona_id'] ?>">

      <div class="row" style="margin-bottom:8px">
        <label>Nombre
          <input name="nombre" required minlength="2" value="<?= h($empEdit['nombre']) ?>">
        </label>
        <label>Apellido
          <input name="apellido" required minlength="2" value="<?= h($empEdit['apellido']) ?>">
        </label>
        <label>DNI
          <input name="dni" required inputmode="numeric" value="<?= h($empEdit['dni']) ?>">
        </label>
        <label>Cargo
          <select name="cargo_id" required>
            <option value="">-- Seleccionar --</option>
            <?php foreach(db()->query("SELECT id, descripcion FROM Cargos ORDER BY descripcion") as $c): 
              $sel = ((int)$c['id'] === (int)$empEdit['cargo_id']) ? 'selected' : ''; ?>
              <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= h($c['descripcion']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Email
          <input name="email" type="email" placeholder="Opcional" value="<?= h($empEdit['email'] ?? '') ?>">
        </label>
        <label>Teléfono
          <input name="telefono" placeholder="Opcional" value="<?= h($empEdit['telefono'] ?? '') ?>">
        </label>
      </div>

      <fieldset style="border:1px solid rgba(157,176,208,.16);border-radius:14px;padding:12px;margin-top:4px">
        <legend style="font-size:12px;color:var(--muted);padding:0 6px">Domicilio</legend>
        <div class="row">
          <label>País
            <input name="pais" value="<?= h($empEdit['pais'] ?? '') ?>">
          </label>
          <label>Provincia
            <input name="provincia" value="<?= h($empEdit['provincia'] ?? '') ?>">
          </label>
          <label>Localidad
            <input name="localidad" value="<?= h($empEdit['localidad'] ?? '') ?>">
          </label>
          <label>Barrio
            <input name="barrio" value="<?= h($empEdit['barrio'] ?? '') ?>">
          </label>
          <label>Calle
            <input name="calle" value="<?= h($empEdit['calle'] ?? '') ?>">
          </label>
          <label>Altura
            <input name="altura" value="<?= h($empEdit['altura'] ?? '') ?>">
          </label>
          <label>Piso
            <input name="piso" value="<?= h($empEdit['piso'] ?? '') ?>">
          </label>
          <label>Departamento
            <input name="departamento" value="<?= h($empEdit['departamento'] ?? '') ?>">
          </label>
        </div>
      </fieldset>

      <div class="footer">
        <a class="btn ghost" href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>">Cancelar</a>
        <button class="btn" type="submit">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ===== Modal de confirmación de baja ===== -->
<div class="confirm-backdrop" id="confirmBaja">
  <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmBajaTitle">
    <div class="confirm-icon">!</div>
    <div id="confirmBajaTitle" class="confirm-title">¿Eliminar empleado?</div>
    <div class="confirm-sub" id="confirmBajaSub">Se eliminará al empleado. Podrás reingresarlo más adelante.</div>
    <div class="confirm-actions">
      <button class="btn danger" id="confirmBajaYes">Sí, eliminar</button>
      <button class="btn ghost" id="confirmBajaNo">No</button>
    </div>
  </div>
</div>

<script>
(function () {
  const root    = document.documentElement;
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const btnMenu = document.getElementById('btnMenu');
  const tDesk   = document.getElementById('themeToggle');
  const tMob    = document.getElementById('themeToggleMobile');

  function setIcon(btn){ if(!btn) return; btn.textContent = root.classList.contains('theme-light') ? '☀️' : '🌙'; }
  const saved = localStorage.getItem('theme') || 'dark';
  if (saved === 'light') root.classList.add('theme-light');
  setIcon(tDesk); setIcon(tMob);
  [tDesk, tMob].forEach(b => b && b.addEventListener('click', () => {
    root.classList.toggle('theme-light');
    localStorage.setItem('theme', root.classList.contains('theme-light') ? 'light' : 'dark');
    setIcon(tDesk); setIcon(tMob);
  }));

  function openMenu(){ sidebar.classList.add('open'); overlay.classList.add('show'); btnMenu?.setAttribute('aria-expanded','true'); }
  function closeMenu(){ sidebar.classList.remove('open'); overlay.classList.remove('show'); btnMenu?.setAttribute('aria-expanded','false'); }
  function toggleMenu(){ (sidebar.classList.contains('open') ? closeMenu : openMenu)(); }
  btnMenu && btnMenu.addEventListener('click', toggleMenu);
  overlay && overlay.addEventListener('click', closeMenu);

  const nav = document.getElementById('nav');
  nav && nav.addEventListener('click', (e) => {
    if (e.target.closest('a') && window.matchMedia('(max-width:1080px)').matches) closeMenu();
  });

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && sidebar.classList.contains('open')) closeMenu(); });

  // Cerrar modal de edición con ESC
  const modalBackdrop = document.getElementById('modalEditBackdrop');
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalBackdrop && modalBackdrop.classList.contains('show')) {
      window.location.href = '<?= strtok($_SERVER['REQUEST_URI'],'?') ?>';
    }
  });
  modalBackdrop && modalBackdrop.addEventListener('click', (e) => {
    if (e.target === modalBackdrop) window.location.href = '<?= strtok($_SERVER['REQUEST_URI'],'?') ?>';
  });

  // === TOASTS blancos + scroll a tabla ===
  (function(){
    function svgIcon(type){
      if (type==='error') {
        return `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="#ef4444" stroke-width="2" fill="none"/>
          <path d="M9 9l6 6M15 9l-6 6" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/>
        </svg>`;
      }
      return `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="10" stroke="#22c55e" stroke-width="2" fill="none"/>
        <path d="M7 12.5l3.2 3.2L17 9.9" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`;
    }
    function toast(msg, type='success', ms=4200){
      const box = document.getElementById('toasts'); if(!box || !msg) return;
      const el = document.createElement('div');
      el.className = 'toast ' + (type==='error'?'error':'success');
      el.innerHTML = `
        <div class="icon">${svgIcon(type)}</div>
        <div class="body">${msg}</div>
        <button class="close" aria-label="Cerrar">×</button>
        <div class="bar"><i style="width:0%"></i></div>
      `;
      box.appendChild(el);
      const bar = el.querySelector('.bar>i');
      let t0 = performance.now();
      function frame(now){ let p = Math.min(1, (now - t0)/ms); bar.style.width = (100*p) + '%'; if (p < 1) req = requestAnimationFrame(frame); }
      let req = requestAnimationFrame(frame);
      const close = () => { cancelAnimationFrame(req); el.style.animation = 'toastOut .18s ease-in both'; setTimeout(()=> el.remove(), 180); };
      el.querySelector('.close').addEventListener('click', close);
      setTimeout(close, ms);
    }
    const params = new URLSearchParams(location.search);
    const ok  = params.get('ok');
    const err = params.get('err');
    if (ok)  { toast(ok, 'success'); document.getElementById('list')?.scrollIntoView({behavior:'smooth'}); }
    if (err) { toast(err, 'error', 5200); }
  })();

  // === Modo Alta: Empleado / Administrador (segmented) ===
  (function(){
    const group = document.getElementById('altaMode');
    const cargoSel = document.getElementById('cargoSelect');
    if (!group || !cargoSel) return;
    const adminOpt = cargoSel.querySelector('option[data-admin="1"]');
    function lockSelect(lock){
      if (lock) { cargoSel.classList.add('locked'); cargoSel.setAttribute('aria-readonly','true'); }
      else { cargoSel.classList.remove('locked'); cargoSel.removeAttribute('aria-readonly'); }
    }
    function setMode(mode){
      [...group.querySelectorAll('.segmented__btn')].forEach(b=>{
        const active = b.dataset.mode===mode;
        b.classList.toggle('active', active);
        b.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      if (mode === 'admin') {
        if (!adminOpt) { alert('No existe el cargo "Administrador" en Cargos. Agregalo y recargá.'); return; }
        adminOpt.disabled = false;
        cargoSel.value = adminOpt.value;
        lockSelect(true);
      } else {
        if (adminOpt) {
          if (cargoSel.value === adminOpt.value) cargoSel.value = '';
          adminOpt.disabled = true;
        }
        lockSelect(false);
      }
    }
    group.addEventListener('click', (e)=>{
      const btn = e.target.closest('.segmented__btn'); if (!btn) return;
      setMode(btn.dataset.mode);
    });
    setMode('empleado');
  })();

  // ==== Confirmación de baja (modal) ====
  (function(){
    const backdrop = document.getElementById('confirmBaja');
    const btnYes   = document.getElementById('confirmBajaYes');
    const btnNo    = document.getElementById('confirmBajaNo');
    const subTxt   = document.getElementById('confirmBajaSub');
    const title    = document.getElementById('confirmBajaTitle');

    let targetHref = null;
    let lastFocus  = null;

    function open(href, nombre){
      targetHref = href;
      lastFocus = document.activeElement;
      title.textContent = '¿Eliminar empleado?';
      subTxt.textContent = nombre
        ? `Se eliminará a ${nombre}. Podrás reingresarlo más adelante.`
        : 'Se eliminará al empleado. Podrás reingresarlo más adelante.';
      backdrop.classList.add('show');
      btnYes.focus();
      document.addEventListener('keydown', onKey, { once:false });
    }
    function close(){
      backdrop.classList.remove('show');
      document.removeEventListener('keydown', onKey);
      if (lastFocus) lastFocus.focus();
      targetHref = null;
    }
    function onKey(e){
      if (e.key === 'Escape') close();
      if (e.key === 'Enter' && backdrop.classList.contains('show')) doYes();
    }
    function doYes(){
      if (!targetHref) return;
      window.location.href = targetHref;
    }
    document.addEventListener('click', (e)=>{
      const btn = e.target.closest('.btn-baja');
      if (!btn) return;
      e.preventDefault();
      open(btn.dataset.href, btn.dataset.name || '');
    });
    btnNo?.addEventListener('click', close);
    backdrop?.addEventListener('click', (e)=>{ if (e.target === backdrop) close(); });
    btnYes?.addEventListener('click', doYes);
  })();

  // Al cambiar ancho, si salimos de móvil, cierro menú
  window.addEventListener('resize', () => { if (!window.matchMedia('(max-width:1080px)').matches) sidebar.classList.remove('open'); });
})();
</script>

<!-- Accessibility overrides injected by ChatGPT -->
<style>
/* === Accesibilidad (contraste AA) === */

/* 1) Segmentado Empleado / Administrador: texto claro sobre gradiente */
.segmented__btn.active{
  color:#ffffff !important; /* antes #0b1220 quedaba ilegible en gradiente azul */
}

/* 2) Botón "ghost" en MODO CLARO: que se vea sobre fondo blanco */
.theme-light .btn.ghost{
  background:#ffffff !important;
  color:#0b1220 !important;
  border:1px solid rgba(15,23,42,.22) !important;
  box-shadow:0 8px 18px rgba(15,23,42,.08) !important;
}

/* 3) Botón "No" en el MODAL y cualquier ghost dentro de modales */
.confirm-actions .btn.ghost,
.modal .btn.ghost{
  background:var(--panel-2) !important;
  color:var(--text) !important;
  border:1px solid rgba(157,176,208,.35) !important;
}

/* 4) Aro de enfoque visible para teclado */
:where(button,a,input,select,textarea):focus-visible{
  outline:3px solid var(--ring) !important;
  outline-offset:2px;
}

/* 5) Fondo del overlay del confirm y modal suficiente opaco en claro */
.theme-light .confirm-backdrop,
.theme-light .modal-backdrop{
  background:rgba(0,0,0,.55) !important;
}
</style>

</body>
</html>
<style>
.btn.ghost:hover{border-color:rgba(157,176,208,.55);color:#fff}
.btn.danger:hover{filter:brightness(1.06)}
</style>
