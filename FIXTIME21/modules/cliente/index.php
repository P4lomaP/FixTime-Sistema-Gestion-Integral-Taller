<?php

declare(strict_types=1);

require_once __DIR__ . '/../../clases/Sesion.php';
require_once __DIR__ . '/../../clases/Conexion.php';
require_once __DIR__ . '/../../clases/VehiculoRepositorio.php';
require_once __DIR__ . '/../../clases/TurnoRepositorio.php';
require_once __DIR__ . '/../../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../../clases/EmpresaRepositorio.php';
require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);
$nom = $_SESSION['nombre']  ?? '';
$ape = $_SESSION['apellido'] ?? '';

function h($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function normalizar_patente(string $p): string
{
  return strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($p)));
}
function normalizar_cuit(string $c): string
{
  return preg_replace('/\D+/', '', $c);
}

$repoV = new VehiculoRepositorio();
$repoT = new TurnoRepositorio();
$repoP = new PersonaRepositorio();
$repoE = new EmpresaRepositorio();

/* =================== FLASH + PRG =================== */
$flash = '';
$flashType = 'info';
function redirect_with_flash(string $msg, string $type = 'success', string $tab = 'home'): void
{
  $_SESSION['flash_msg'] = $msg;
  $_SESSION['flash_type'] = $type;
  $a = require __DIR__ . '/../../config/app.php';
  $b = rtrim($a['base_url'], '/');
  header('Location: ' . $b . '/modules/cliente/index.php?tab=' . urlencode($tab));
  exit;
}
if (!empty($_SESSION['flash_msg'])) {
  $flash = $_SESSION['flash_msg'];
  $flashType = $_SESSION['flash_type'] ?? 'info';
  unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

/* =================== CSRF =================== */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

/* =================== Upload helpers =================== */
function save_img_required(array $file, string $pref): string
{
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('FOTO_REQUERIDA');
  $mime = @mime_content_type($file['tmp_name']);
  $exts = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
  if (!isset($exts[$mime])) throw new RuntimeException('FOTO_FORMATO');
  if (($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('FOTO_TAM');
  $dir = __DIR__ . '/../../publico/uploads/cedulas';
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  $name = $pref . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . $exts[$mime];
  if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('FOTO_MOVE');
  return 'uploads/cedulas/' . $name; // ruta relativa a /publico
}
function save_img_optional(?array $file, string $pref): ?string
{
  if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  return save_img_required($file, $pref);
}

/* =================== Permisos autos =================== */
function usuarioPuedeOperarAuto(int $personaId, int $autoId): bool
{
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // personales
  $st = $db->prepare("SELECT 1 FROM Vehiculos_Personas WHERE Persona_id = :p AND automoviles_id = :a LIMIT 1");
  $st->execute([':p' => $personaId, ':a' => $autoId]);
  if ($st->fetchColumn()) return true;
  // empresariales con contacto compartido
  $sql = "
    SELECT 1
      FROM Vehiculos_Empresas ve
      JOIN Empresas e ON e.id = ve.Empresas_id
     WHERE ve.automoviles_id = :a
       AND EXISTS (
           SELECT 1 FROM Contactos_Empresas ce
            WHERE ce.Empresas_id = e.id
              AND ce.valor IN (SELECT valor FROM Contacto_Persona WHERE Persona_id = :p)
       )
     LIMIT 1
  ";
  $st = $db->prepare($sql);
  $st->execute([':a' => $autoId, ':p' => $personaId]);
  return (bool)$st->fetchColumn();
}

/* =================== Empresa helpers (sin tocar DB) =================== */
function empresa_obtener(int $empresaId): ?array
{
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $st = $db->prepare("SELECT * FROM Empresas WHERE id=? LIMIT 1");
  $st->execute([$empresaId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}
function empresa_listar_contactos(int $empresaId): array
{
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT ce.id, UPPER(t.descripcion) AS tipo, ce.valor
          FROM Contactos_Empresas ce
          JOIN Tipos_Contactos t ON t.id = ce.Tipo_Contacto_id
         WHERE ce.Empresas_id = ?
         ORDER BY t.descripcion, ce.valor";
  $st = $db->prepare($sql);
  $st->execute([$empresaId]);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}
function empresa_eliminar_contacto(int $empresaId, int $contactoId): bool
{
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $st = $db->prepare("DELETE FROM Contactos_Empresas WHERE id=? AND Empresas_id=?");
  return $st->execute([$contactoId, $empresaId]);
}
function empresa_actualizar(int $empresaId, string $razon, string $cuit, ?string $email, ?string $tel): void
{
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $cuit = normalizar_cuit($cuit);
  if ($cuit === '' || strlen($cuit) !== 11) throw new RuntimeException('CUIT_INVALIDO');
  // ¿existe el mismo CUIT en otra empresa?
  $st = $db->prepare("SELECT id FROM Empresas WHERE CUIT=? AND id<>? LIMIT 1");
  $st->execute([$cuit, $empresaId]);
  if ($st->fetchColumn()) throw new RuntimeException('CUIT_DUPLICADO');

  // update empresa
  $st = $db->prepare("UPDATE Empresas SET razon_social=?, CUIT=? WHERE id=?");
  $st->execute([$razon, $cuit, $empresaId]);

  // asegurar tipos de contacto y upsert (no duplicar)
  $tipos = ['CUIT_EMPRESA', 'EMAIL', 'TELEFONO'];
  foreach ($tipos as $t) {
    $st = $db->prepare("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion)=UPPER(?) LIMIT 1");
    $st->execute([$t]);
    if (!$st->fetchColumn()) {
      $i = $db->prepare("INSERT INTO Tipos_Contactos (descripcion) VALUES (?)");
      $i->execute([$t]);
    }
  }
  // Upsert CUIT contacto
  $tipoCuitId = (int)$db->query("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion)='CUIT_EMPRESA'")->fetchColumn();
  $tipoEmailId = (int)$db->query("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion)='EMAIL'")->fetchColumn();
  $tipoTelId  = (int)$db->query("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion)='TELEFONO'")->fetchColumn();

  // CUIT contacto (único por valor)
  $valores = $db->prepare("SELECT id FROM Contactos_Empresas WHERE Empresas_id=? AND Tipo_Contacto_id=? LIMIT 1");
  $valores->execute([$empresaId, $tipoCuitId]);
  if ($row = $valores->fetch(PDO::FETCH_ASSOC)) {
    $upd = $db->prepare("UPDATE Contactos_Empresas SET valor=? WHERE id=?");
    $upd->execute([$cuit, $row['id']]);
  } else {
    // evitar duplicado global de valor/tipo
    $chk = $db->prepare("SELECT 1 FROM Contactos_Empresas WHERE Tipo_Contacto_id=? AND valor=? LIMIT 1");
    $chk->execute([$tipoCuitId, $cuit]);
    if (!$chk->fetchColumn()) {
      $ins = $db->prepare("INSERT INTO Contactos_Empresas (Empresas_id, Tipo_Contacto_id, valor) VALUES (?,?,?)");
      $ins->execute([$empresaId, $tipoCuitId, $cuit]);
    }
  }

  // Email
  if ($email !== null && $email !== '') {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('EMAIL_INVALIDO');
    $valores->execute([$empresaId, $tipoEmailId]);
    if ($row = $valores->fetch(PDO::FETCH_ASSOC)) {
      $upd = $db->prepare("UPDATE Contactos_Empresas SET valor=? WHERE id=?");
      $upd->execute([$email, $row['id']]);
    } else {
      $chk = $db->prepare("SELECT 1 FROM Contactos_Empresas WHERE Tipo_Contacto_id=? AND valor=? LIMIT 1");
      $chk->execute([$tipoEmailId, $email]);
      if (!$chk->fetchColumn()) {
        $ins = $db->prepare("INSERT INTO Contactos_Empresas (Empresas_id, Tipo_Contacto_id, valor) VALUES (?,?,?)");
        $ins->execute([$empresaId, $tipoEmailId, $email]);
      }
    }
  }
  // Teléfono
  if ($tel !== null && $tel !== '') {
    $tel = trim($tel);
    $valores->execute([$empresaId, $tipoTelId]);
    if ($row = $valores->fetch(PDO::FETCH_ASSOC)) {
      $upd = $db->prepare("UPDATE Contactos_Empresas SET valor=? WHERE id=?");
      $upd->execute([$tel, $row['id']]);
    } else {
      $chk = $db->prepare("SELECT 1 FROM Contactos_Empresas WHERE Tipo_Contacto_id=? AND valor=? LIMIT 1");
      $chk->execute([$tipoTelId, $tel]);
      if (!$chk->fetchColumn()) {
        $ins = $db->prepare("INSERT INTO Contactos_Empresas (Empresas_id, Tipo_Contacto_id, valor) VALUES (?,?,?)");
        $ins->execute([$empresaId, $tipoTelId, $tel]);
      }
    }
  }
}

/* Para mostrar nombres amigables */
function friendly_tipo(string $t): string
{
  $t = strtoupper($t);
  if ($t === 'CUIT_EMPRESA' || $t === 'CUIT') return 'CUIT';
  if ($t === 'EMAIL') return 'Email';
  if ($t === 'TELEFONO' || $t === 'TEL' || $t === 'PHONE') return 'Teléfono';
  return ucwords(strtolower($t));
}

/* =================== POST =================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    redirect_with_flash('Sesión expirada. Probá nuevamente.', 'error', 'home');
  }

  $act = $_POST['action'] ?? '';

  try {
    /* Alta de vehículo */
    if ($act === 'add_vehiculo') {
      // Nuevo flujo: el usuario elige Personal o Empresarial siempre
      $tipo   = ($_POST['tipo_vehiculo'] ?? 'personal') === 'empresarial' ? 'empresarial' : 'personal';
      $empSel = (int)($_POST['empresa_id'] ?? 0);

      $marca  = trim($_POST['marca']  ?? '');
      $modelo = trim($_POST['modelo'] ?? '');
      $anio   = (int)($_POST['anio']  ?? 0);
      $color  = trim($_POST['color']  ?? '');
      $km     = (int)($_POST['km']    ?? 0);
      $pat    = normalizar_patente($_POST['patente'] ?? '');
      $dex    = trim($_POST['descripcion_extra'] ?? '');

      if ($marca === '' || $modelo === '' || !$anio || $color === '' || $pat === '' || $km < 0) {
        redirect_with_flash('Completá todos los campos obligatorios.', 'error', 'vehiculos');
      }
      if (!preg_match('/^([A-Z]{3}\d{3}|[A-Z]{2}\d{3}[A-Z]{2})$/', $pat)) {
        redirect_with_flash('Patente inválida (ABC123 o AA123BB).', 'error', 'vehiculos');
      }
      $fr = save_img_required($_FILES['cedula_frente'] ?? [], 'frente');
      $dr = save_img_required($_FILES['cedula_dorso']  ?? [], 'dorso');

      if ($tipo === 'empresarial') {
        if ($empSel <= 0) {
          redirect_with_flash('Elegí una empresa en el selector o creala en el panel Empresa.', 'warning', 'vehiculos');
        }
        try {
          $repoV->crearParaEmpresa($empSel, $uid, $marca, $modelo, $anio, $km, $color, $pat, $dex, $fr, $dr);
        } catch (Throwable $e) {
          $msg = $e->getMessage();
          if ($msg === 'PERMISO_EMPRESA') redirect_with_flash('No podés registrar vehículos para esa empresa.', 'error', 'vehiculos');
          if ($msg === 'VEHICULO_DUPLICADO') redirect_with_flash('Esa empresa ya tiene un vehículo igual.', 'warning', 'vehiculos');
          if ($msg === 'PATENTE_DUPLICADA')  redirect_with_flash('Esa patente ya está registrada.', 'warning', 'vehiculos');
          throw $e;
        }
      } else {
        $repoV->crearParaPersona($uid, $marca, $modelo, $anio, $km, $color, $pat, $dex, $fr, $dr);
      }
      redirect_with_flash('Vehículo registrado.', 'success', 'home');
    }

    /* Actualizar vehículo */
    if ($act === 'update_vehiculo') {
      $autoId = (int)($_POST['id_auto'] ?? 0);
      $marca  = trim($_POST['marca']  ?? '');
      $modelo = trim($_POST['modelo'] ?? '');
      $anio   = (int)($_POST['anio']  ?? 0);
      $color  = trim($_POST['color']  ?? '');
      $km     = (int)($_POST['km']    ?? 0);
      $pat    = normalizar_patente($_POST['patente'] ?? '');
      $dex    = trim($_POST['descripcion_extra'] ?? '');

      if (!$autoId || $marca === '' || $modelo === '' || !$anio || $color === '' || $pat === '' || $km < 0) {
        redirect_with_flash('Completá todos los campos obligatorios.', 'error', 'vehiculos');
      }
      if (!preg_match('/^([A-Z]{3}\d{3}|[A-Z]{2}\d{3}[A-Z]{2})$/', $pat)) {
        redirect_with_flash('Patente inválida (ABC123 o AA123BB).', 'error', 'vehiculos');
      }
      $fr = save_img_optional($_FILES['cedula_frente'] ?? null, 'frente');
      $dr = save_img_optional($_FILES['cedula_dorso']  ?? null, 'dorso');

      try {
        $repoV->actualizar($autoId, $uid, [
          'marca' => $marca,
          'modelo' => $modelo,
          'anio' => $anio,
          'km' => $km,
          'color' => $color,
          'patente' => $pat,
          'descripcion_extra' => $dex,
          'cedula_frente' => $fr,
          'cedula_trasera' => $dr
        ]);
      } catch (Throwable $e) {
        $code = $e->getMessage();
        if ($code === 'PERMISO_DENEGADO') redirect_with_flash('Ese vehículo no te pertenece (titular empresarial).', 'error', 'vehiculos');
        if ($code === 'VEHICULO_DUPLICADO') redirect_with_flash('Ya tenés un vehículo igual.', 'warning', 'vehiculos');
        if ($code === 'PATENTE_DUPLICADA')  redirect_with_flash('Esa patente ya está registrada.', 'warning', 'vehiculos');
        throw $e;
      }
      redirect_with_flash('Vehículo actualizado.', 'success', 'vehiculos');
    }

    /* Eliminar / Desvincular */
    if ($act === 'delete_vehiculo') {
      $id = (int)($_POST['id_auto'] ?? 0);
      if (!$id) redirect_with_flash('No se pudo eliminar.', 'error', 'vehiculos');
      $c = $repoV->eliminar($id, $uid);
      if ($c === 1) redirect_with_flash('Vehículo eliminado de tu cuenta.', 'success', 'vehiculos');
      redirect_with_flash('No se pudo eliminar el vehículo.', 'warning', 'vehiculos');
    }

    /* Turnos */
    if ($act === 'add_turno') {
      $autoId = (int)($_POST['auto_id'] ?? 0);
      $motivo  = trim($_POST['motivo'] ?? '');
      $descripcion   = trim($_POST['descripcion']  ?? '');

      if (!$autoId) {
        redirect_with_flash('Elegí vehículo, fecha y hora.', 'error', 'turnos');
      }
      if (!usuarioPuedeOperarAuto($uid, $autoId)) {
        redirect_with_flash('No tenés permisos para operar ese vehículo.', 'error', 'turnos');
      } else {
        $repoT->crearSolicitud($uid, $autoId, $motivo, $descripcion);
        redirect_with_flash('Turno solicitado (Pendiente).', 'success', 'turnos');
      }
    }

    if ($act === 'reprogramar_turno') {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      $autoId  = (int)($_POST['auto_id']  ?? 0);
      $fecha   = trim($_POST['fecha'] ?? '');
      $hora    = trim($_POST['hora']  ?? '');
      if (!$turnoId || !$autoId) {
        redirect_with_flash('Completá los campos.', 'error', 'turnos');
      }
      if (!usuarioPuedeOperarAuto($uid, $autoId)) {
        redirect_with_flash('No tenés permisos para operar ese vehículo.', 'error', 'turnos');
      }
      //  elseif (!$repoT->disponible($fecha, $hora)) {
      //   redirect_with_flash('No hay disponibilidad para esa fecha/hora.', 'warning', 'turnos');
      // } else {
      //   $repoT->reprogramar($uid, $turnoId, $fecha, $hora, $autoId);
      //   redirect_with_flash('Turno reprogramado.', 'success', 'turnos');
      // }
    }

    if ($act === 'cancelar_turno') {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      if ($turnoId) {
        $repoT->cancelar($uid, $turnoId);
        redirect_with_flash('Turno cancelado.', 'success', 'turnos');
      } else {
        redirect_with_flash('No se pudo cancelar el turno.', 'error', 'turnos');
      }
    }

    /* ===== EMPRESA: upsert, contactos y EDICIÓN ===== */
    if ($act === 'upsert_empresa_vinculo') {
      $razon = trim($_POST['razon_social'] ?? '');
      $cuit  = trim($_POST['cuit'] ?? '');
      $emprE = trim($_POST['email_empresa'] ?? '') ?: null;
      $telE  = trim($_POST['telefono_empresa'] ?? '') ?: null;

      if ($razon === '' || $cuit === '') {
        redirect_with_flash('Completá razón social y CUIT.', 'error', 'empresa');
      }
      try {
        $repoE->upsertEmpresaYVincularPorCUIT($uid, $razon, $cuit, $emprE, $telE);
        redirect_with_flash('Empresa vinculada/actualizada.', 'success', 'empresa');
      } catch (Throwable $e) {
        $m = $e->getMessage();
        if ($m === 'CUIT_INVALIDO') $m = 'CUIT inválido.';
        if ($m === 'CUIT_DUPLICADO') $m = 'Ese CUIT ya está registrado en otra empresa.';
        redirect_with_flash('No se pudo guardar: ' . $m, 'error', 'empresa');
      }
    }

    if ($act === 'editar_empresa') {
      $eid   = (int)($_POST['empresa_id'] ?? 0);
      $razon = trim($_POST['razon_social'] ?? '');
      $cuit  = trim($_POST['cuit'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $tel   = trim($_POST['telefono'] ?? '');
      if (!$eid || $razon === '' || $cuit === '') redirect_with_flash('Completá razón social y CUIT.', 'error', 'empresa');

      // seguridad: solo si el usuario tiene vínculo por contactos
      if (!$repoE->personaPuedeUsarEmpresa($eid, $uid)) {
        redirect_with_flash('No tenés permisos sobre esa empresa.', 'error', 'empresa');
      }

      try {
        empresa_actualizar($eid, $razon, $cuit, $email ?: null, $tel ?: null);
        redirect_with_flash('Datos de empresa actualizados.', 'success', 'empresa');
      } catch (Throwable $e) {
        $m = $e->getMessage();
        if ($m === 'CUIT_INVALIDO') $m = 'CUIT inválido (debe tener 11 dígitos).';
        if ($m === 'CUIT_DUPLICADO') $m = 'Ese CUIT ya existe en otra empresa.';
        if ($m === 'EMAIL_INVALIDO') $m = 'Email de empresa inválido.';
        redirect_with_flash($m, 'error', 'empresa');
      }
    }

    if ($act === 'add_contacto_emp') {
      $eid  = (int)($_POST['empresa_id'] ?? 0);
      $tipo = strtoupper(trim($_POST['tipo'] ?? ''));
      $val  = trim($_POST['valor'] ?? '');
      if (!$eid || $tipo === '' || $val === '') {
        redirect_with_flash('Completá tipo y valor.', 'error', 'empresa');
      }
      if (!$repoE->personaPuedeUsarEmpresa($eid, $uid)) {
        redirect_with_flash('No tenés permisos sobre esa empresa.', 'error', 'empresa');
      }
      // evitar duplicado por tipo/valor
      $db = Conexion::obtener();
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $st = $db->prepare("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion)=UPPER(?) LIMIT 1");
      $st->execute([$tipo]);
      $tipoId = $st->fetchColumn();
      if (!$tipoId) {
        $ins = $db->prepare("INSERT INTO Tipos_Contactos (descripcion) VALUES (?)");
        $ins->execute([$tipo]);
        $tipoId = $db->lastInsertId();
      }
      $dup = $db->prepare("SELECT 1 FROM Contactos_Empresas WHERE Tipo_Contacto_id=? AND valor=? LIMIT 1");
      $dup->execute([$tipoId, $val]);
      if ($dup->fetchColumn()) redirect_with_flash('Ese contacto ya existe.', 'warning', 'empresa');

      $repoE->agregarContacto($eid, $tipo, $val);
      redirect_with_flash('Contacto agregado.', 'success', 'empresa');
    }

    if ($act === 'del_contacto_emp') {
      $eid = (int)($_POST['empresa_id'] ?? 0);
      $cid = (int)($_POST['contacto_id'] ?? 0);
      if (!$eid || !$cid) {
        redirect_with_flash('No se pudo quitar el contacto.', 'error', 'empresa');
      }
      if (!$repoE->personaPuedeUsarEmpresa($eid, $uid)) {
        redirect_with_flash('No tenés permisos sobre esa empresa.', 'error', 'empresa');
      }
      empresa_eliminar_contacto($eid, $cid);
      redirect_with_flash('Contacto eliminado.', 'success', 'empresa');
    }
  } catch (Throwable $eOuter) {
    $msg = $eOuter->getMessage();
    if ($msg === 'VEHICULO_DUPLICADO') $msg = 'Ya existe un vehículo igual (misma marca, modelo, año y color).';
    if ($msg === 'PATENTE_DUPLICADA')  $msg = 'Esa patente ya está registrada.';
    if ($msg === 'FOTO_REQUERIDA')     $msg = 'Cargá frente y dorso de la cédula.';
    if ($msg === 'FOTO_TAM')           $msg = 'Cada imagen debe pesar hasta 5MB.';
    if ($msg === 'FOTO_FORMATO')       $msg = 'Usá JPG/PNG/WEBP.';
    redirect_with_flash('Ocurrió un error: ' . $msg, 'error', 'vehiculos');
  }
}

/* =================== Datos para pintar =================== */
$vehiculos = $repoV->listarParaPanel($uid);
$turnos    = $repoT->listarPorPersona($uid);
$historial = $repoT->historialServicios($uid);
$emailAct  = $repoP->emailPrincipal($uid) ?? '';
$per       = $repoP->buscarPorId($uid) ?? [];
$dniAct    = $per['dni'] ?? '';
$domi = $repoP->obtenerDomicilioActual($uid) ?? [
  'pais' => '',
  'provincia' => '',
  'localidad' => '',
  'barrio' => '',
  'calle' => '',
  'altura' => '',
  'piso' => '',
  'departamento' => ''
];
$tels = $repoP->listarTelefonos($uid);
if (!$tels) $tels = [''];

/* Empresas relacionadas — siempre obtenemos */
$empresasBasicas = $repoE->listarEmpresasPorPersonaContactos($uid);
$empresasFull = [];
foreach ($empresasBasicas as $e) {
  $row = empresa_obtener((int)$e['id']);
  if ($row) {
    $row['contactos'] = empresa_listar_contactos((int)$e['id']);
    $empresasFull[] = $row;
  }
}

/* Empresas para selector en alta de vehículo */
$empresasParaSelector = $repoE->listarEmpresasPorPersonaContactos($uid);

?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Fixtime — Panel del Cliente</title>
  <link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root {
      --bg: #0b1226;
      --panel: #0f1a33;
      --panel-2: #0b162b;
      --card: #0c1730;
      --muted: #9db0d0;
      --text: #e9f0ff;
      --brand: #3b82f6;
      --brand-2: #2563eb;
      --ring: rgba(59, 130, 246, .40);
      --shadow: 0 12px 40px rgba(2, 6, 23, .45);
      --radius: 18px;
    }

    * {
      box-sizing: border-box
    }

    html,
    body {
      height: 100%;
      margin: 0
    }

    body {
      min-height: 100vh;
      background:
        radial-gradient(1200px 600px at 80% -10%, rgba(59, 130, 246, .22), transparent 70%),
        radial-gradient(900px 480px at 10% 110%, rgba(37, 99, 235, .16), transparent 60%),
        var(--bg);
      color: var(--text);
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial;
    }

    .app {
      display: grid;
      grid-template-columns: 320px 1fr;
      min-height: 100vh
    }

    .sidebar {
      padding: 22px;
      background: linear-gradient(180deg, var(--panel), var(--bg));
      border-right: 1px solid rgba(157, 176, 208, .15);
      position: sticky;
      top: 0;
      height: 100vh;
      z-index: 40;
    }

    .brand {
      display: flex;
      gap: 12px;
      align-items: center;
      margin-bottom: 22px
    }

    .brand-badge {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: radial-gradient(40px 30px at 30% 20%, rgba(255, 255, 255, .25), transparent 40%), linear-gradient(135deg, var(--brand), var(--brand-2));
      box-shadow: 0 12px 30px var(--ring), inset 0 1px 0 rgba(255, 255, 255, .25)
    }

    .brand-badge img {
      width: 32px;
      height: 32px;
      object-fit: contain;
      filter: drop-shadow(0 0 14px rgba(255, 255, 255, .6))
    }

    .brand-name {
      font-weight: 800;
      letter-spacing: .35px;
      font-size: 22px
    }

    .brand-sub {
      opacity: .8;
      font-size: 12px
    }

    .theme-btn {
      margin-left: auto;
      appearance: none;
      border: 1px solid rgba(157, 176, 208, .28);
      background: rgba(255, 255, 255, .06);
      color: var(--text);
      border-radius: 12px;
      padding: 10px 12px;
      cursor: pointer;
      font-size: 16px;
      box-shadow: 0 6px 16px rgba(0, 0, 0, .2)
    }

    .nav {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 10px
    }

    .nav button {
      display: flex;
      gap: 12px;
      align-items: center;
      justify-content: flex-start;
      padding: 14px 16px;
      border-radius: 14px;
      border: 1px solid rgba(157, 176, 208, .18);
      background: rgba(255, 255, 255, .03);
      color: var(--text);
      cursor: pointer;
      font-size: 16px;
      font-weight: 700;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .25)
    }

    .nav button.active {
      background: linear-gradient(135deg, rgba(59, 130, 246, .20), rgba(37, 99, 235, .20));
      border-color: rgba(59, 130, 246, .55);
      box-shadow: 0 10px 28px var(--ring)
    }

    .topbar-salir {
      display: block;
      margin-top: 14px;
      text-align: center;
      text-decoration: none
    }

    .main {
      padding: 26px 32px;
      display: flex;
      flex-direction: column;
      min-height: 100vh
    }

    .hero {
      display: flex;
      align-items: center;
      gap: 16px;
      background: linear-gradient(135deg, rgba(59, 130, 246, .22), rgba(37, 99, 235, .18));
      border: 1px solid rgba(59, 130, 246, .40);
      border-radius: var(--radius);
      padding: 18px;
      box-shadow: 0 14px 32px var(--ring);
      margin-bottom: 16px
    }

    .hero .avatar {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: radial-gradient(24px 16px at 30% 20%, rgba(255, 255, 255, .25), transparent 40%), linear-gradient(135deg, var(--brand), var(--brand-2))
    }

    .hero .avatar img {
      width: 38px;
      height: 38px;
      object-fit: contain;
      filter: drop-shadow(0 0 14px rgba(255, 255, 255, .6))
    }

    .hero .greet {
      font-weight: 800;
      font-size: 22px,
    }

    .kpis {
      display: grid !important;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
      margin-bottom: 20px !important
    }

    .card {
      background: linear-gradient(180deg, rgba(255, 255, 255, .05), rgba(255, 255, 255, .03));
      border: 1px solid rgba(157, 176, 208, .16);
      border-radius: var(--radius);
      padding: 18px;
      box-shadow: var(--shadow)
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px
    }

    .table th,
    .table td {
      padding: 12px 10px;
      border-bottom: 1px solid rgba(157, 176, 208, .14)
    }

    .row {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 12px
    }

    .col-12 {
      grid-column: span 12
    }

    .col-6 {
      grid-column: span 6
    }

    .col-4 {
      grid-column: span 4
    }

    .col-3 {
      grid-column: span 3
    }

    .col-8 {
      grid-column: span 8
    }

    .col-5 {
      grid-column: span 5
    }

    .col-7 {
      grid-column: span 7
    }

    .col-9 {
      grid-column: span 9
    }

    .col-2 {
      grid-column: span 2
    }

    .col-10 {
      grid-column: span 10
    }

    .col-11 {
      grid-column: span 11
    }

    label {
      font-size: 12px;
      color: var(--muted)
    }

    input,
    select {
      width: 100%;
      background: var(--panel-2);
      border: 1px solid rgba(157, 176, 208, .2);
      color: var(--text);
      border-radius: 12px;
      padding: 12px 14px
    }

    .btn {
      cursor: pointer;
      border: 0;
      border-radius: 12px;
      padding: 12px 16px;
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      color: #0b1220;
      font-weight: 800;
      box-shadow: 0 12px 28px var(--ring)
    }

    .btn.ghost {
      background: transparent;
      color: var(--text);
      border: 1px solid rgba(157, 176, 208, .30);
      box-shadow: none
    }

    .pill {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800
    }

    .ok {
      background: rgba(16, 185, 129, .12);
      color: #34d399
    }

    .warn {
      background: rgba(245, 158, 11, .12);
      color: #fbbf24
    }

    .bad {
      background: rgba(239, 68, 68, .12);
      color: #f87171
    }

    .small {
      font-size: 12px;
      opacity: .85
    }

    .thumb,
    .thumb-sm {
      display: inline-block;
      width: 52px;
      height: 52px;
      border-radius: 10px;
      background: #0b162b center/cover no-repeat;
      border: 1px solid rgba(157, 176, 208, .25);
      position: relative;
      cursor: zoom-in
    }

    .thumb::after,
    .thumb-sm::after {
      content: "";
      position: absolute;
      left: 50%;
      bottom: 100%;
      transform: translate(-50%, -10px) scale(.95);
      width: 240px;
      height: 180px;
      border-radius: 12px;
      background: inherit;
      background-size: contain;
      background-repeat: no-repeat;
      background-position: center;
      box-shadow: 0 20px 40px rgba(2, 6, 23, .45);
      border: 1px solid rgba(157, 176, 208, .25);
      opacity: 0;
      pointer-events: none;
      transition: opacity .12s ease, transform .12s ease;
      z-index: 20
    }

    .thumb:hover::after,
    .thumb-sm:hover::after {
      opacity: 1;
      transform: translate(-50%, -12px) scale(1)
    }

    .table td {
      overflow: visible
    }

    dialog#vehiculoModal {
      position: fixed;
      inset: 50% auto auto 50%;
      transform: translate(-50%, -50%);
      z-index: 1000;
      border: 0;
      border-radius: 18px;
      padding: 0;
      max-width: 880px;
      width: 95%;
      background: var(--panel);
      box-shadow: 0 30px 80px rgba(2, 6, 23, .60)
    }

    dialog#vehiculoModal::backdrop {
      background: rgba(5, 10, 25, .55);
      backdrop-filter: blur(4px) saturate(120%)
    }

    body.modal-open {
      overflow: hidden
    }

    body.modal-open .thumb::after,
    body.modal-open .thumb-sm::after {
      display: none !important
    }

    .theme-light {
      --bg: #f3f6fc;
      --panel: #ffffff;
      --panel-2: #f7f9ff;
      --card: #ffffff;
      --muted: #5b6b85;
      --text: #0b1220;
      --ring: rgba(59, 130, 246, .28);
      --shadow: 0 8px 26px rgba(15, 23, 42, .08)
    }

    .theme-light body {
      background: radial-gradient(1000px 500px at 80% -10%, rgba(59, 130, 246, .12), transparent 70%), radial-gradient(700px 380px at 10% 110%, rgba(37, 99, 235, .10), transparent 60%), var(--bg)
    }

    .theme-light .sidebar {
      background: linear-gradient(180deg, var(--panel), #eaf0ff);
      border-right: 1px solid rgba(15, 23, 42, .06)
    }

    .theme-light .nav button {
      background: #fff;
      border-color: rgba(15, 23, 42, .08)
    }

    .theme-light .card {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .06);
      box-shadow: var(--shadow)
    }

    .theme-light .table th,
    .theme-light .table td {
      border-bottom: 1px solid rgba(15, 23, 42, .08)
    }

    .theme-light .theme-btn {
      background: #fff;
      border-color: rgba(15, 23, 42, .08);
      color: #0b1220
    }

    .header-mobile {
      display: none;
      align-items: center;
      gap: 10px;
      position: sticky;
      top: 0;
      z-index: 1100;
      padding: 12px 16px;
      background: rgba(12, 23, 48, .75);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid rgba(255, 255, 255, .06)
    }

    .burger {
      appearance: none;
      background: transparent;
      border: 0;
      color: var(--text);
      font-size: 24px;
      cursor: pointer
    }

    .sidebar__overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .45);
      z-index: 1000
    }

    .sidebar__overlay.show {
      display: block
    }

    @media (max-width:1080px) {
      .app {
        grid-template-columns: 1fr
      }

      .sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: 84%;
        max-width: 320px;
        height: 100vh;
        transform: translateX(-105%);
        transition: transform .22s ease;
        z-index: 1001;
        box-shadow: var(--shadow)
      }

      .sidebar.open {
        transform: translateX(0)
      }

      .header-mobile {
        display: flex
      }

      .kpis {
        grid-template-columns: 1fr
      }

      .row {
        grid-template-columns: repeat(6, 1fr)
      }

      .col-6 {
        grid-column: span 6
      }

      .col-4 {
        grid-column: span 6
      }

      .col-3 {
        grid-column: span 3
      }

      .col-5 {
        grid-column: span 6
      }

      .col-7 {
        grid-column: span 6
      }

      .col-9 {
        grid-column: span 6
      }

      .col-10 {
        grid-column: span 6
      }

      .col-11 {
        grid-column: span 6
      }
    }

    .table.responsive {
      width: 100%;
    }

    @media (max-width: 740px) {
      .table.responsive thead {
        display: none;
      }

      .table.responsive tbody tr {
        display: block;
        background: linear-gradient(180deg, rgba(255, 255, 255, .05), rgba(255, 255, 255, .03));
        border: 1px solid rgba(157, 176, 208, .16);
        border-radius: 14px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: 0 10px 24px rgba(2, 6, 23, .25);
      }

      .table.responsive tbody td {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 8px;
        padding: 8px 4px;
        border: 0;
      }

      .table.responsive tbody td::before {
        content: attr(data-label);
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
      }

      .table.responsive .thumb-sm {
        width: 46px;
        height: 46px;
        border-radius: 10px;
      }

      .veh-acciones {
        display: flex !important;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 4px;
      }
    }

    .table.responsive th,
    .table.responsive td {
      vertical-align: middle;
    }

    .input-patente {
      min-width: 220px
    }

    @media (min-width: 740px) {
      #vehiculoModal form .patente-wide {
        grid-column: span 6;
      }
    }

    .banner-empresa {
      background: linear-gradient(135deg, rgba(59, 130, 246, .18), rgba(37, 99, 235, .14));
      border: 1px solid rgba(59, 130, 246, .35);
      padding: 12px 14px;
      border-radius: 12px;
      margin-bottom: 10px;
      font-size: 14px
    }

    /* Toggle estilizado */
    .toggle {
      display: flex;
      border: 1px solid rgba(157, 176, 208, .3);
      border-radius: 12px;
      overflow: hidden;
      width: max-content
    }

    .toggle input {
      display: none
    }

    .toggle label {
      padding: 10px 14px;
      cursor: pointer;
      user-select: none;
      font-weight: 800
    }

    .toggle input:checked+label {
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      color: #0b1220
    }
  </style>
</head>

<body>

  <div class="header-mobile">
    <button class="burger" id="btnMenu" aria-label="Abrir menú">☰</button>
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
        <div style="flex:1">
          <div class="brand-name">Fixtime</div>
          <div class="brand-sub">Panel de Cliente</div>
        </div>
        <button id="themeToggle" class="theme-btn" title="Cambiar tema">🌙</button>
      </div>

      <nav class="nav" id="nav">
        <?php if ($repoA->esAdmin((int)($_SESSION['uid'] ?? 0))): ?>
          <a href="<?= $base ?>/modules/admin/" class="btn ghost" style="margin-left:auto">
            ↔ Cambiar a panel administrador
          </a>
        <?php endif; ?>
        <button class="active" data-tab="home">🏠 Inicio</button>
        <button data-tab="vehiculos">🚗 Mis vehículos</button>
        <button data-tab="turnos">🗓️ Turnos</button>
        <button data-tab="historial">🧾 Historial</button>
        <button data-tab="perfil">👤 Mi perfil</button>
        <button data-tab="empresa">🏢 Empresa</button>
        <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">
          Cerrar sesión
        </a>
      </nav>

    </aside>
    <div class="sidebar__overlay" id="overlay"></div>

    <main class="main">
      <div class="hero">
        <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
        <div style="flex:1">
          <div class="greet">¡Hola, <?= h($nom . ' ' . $ape) ?>!</div>
          <div class="hint">Gestioná tus vehículos, turnos y datos desde un solo lugar.</div>
        </div>
      </div>

      <section class="kpis" id="tab-home">
        <div class="card">
          <div style="font-size:13px;color:#9db0d0">Hola</div>
          <div style="font-size:24px;font-weight:800"><?= h($nom . ' ' . $ape) ?></div>
          <div style="opacity:.85;margin-top:6px">Gestioná tus autos, turnos y datos.</div>
        </div>
        <div class="card">
          <div style="font-size:13px;color:#9db0d0">Mis vehículos</div>
          <div style="font-size:24px;font-weight:800"><?= count($vehiculos) ?></div>
          <div class="hint">Podés agregar los que faltan.</div>
        </div>
        <div class="card">
          <div style="font-size:13px;color:#9db0d0">Turnos totales</div>
          <div style="font-size:24px;font-weight:800"><?= count($turnos) ?></div>
          <div class="hint">Pendientes, confirmados y cancelados.</div>
        </div>
      </section>

      <section id="tab-vehiculos" style="display:none">
        <div class="card" style="margin-bottom:16px">
          <h3>Registrar vehículo</h3>
          <form method="post" enctype="multipart/form-data" class="row validate-form" id="formAlta">
            <input type="hidden" name="action" value="add_vehiculo">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

            <div class="col-12">
              <label>Tipo de vehículo</label>
              <div class="toggle" role="tablist" aria-label="Tipo de vehículo">
                <input type="radio" id="tipo_personal" name="tipo_vehiculo" value="personal" checked>
                <label for="tipo_personal">Personal</label>
                <input type="radio" id="tipo_empresarial" name="tipo_vehiculo" value="empresarial">
                <label for="tipo_empresarial">Empresarial</label>
              </div>
              <div id="empSelWrap" style="margin-top:10px; display:none">
                <label>Empresa</label>
                <select name="empresa_id" id="empresa_id">
                  <option value="">— Elegí una empresa —</option>
                  <?php foreach ($empresasParaSelector as $e): ?>
                    <option value="<?= (int)$e['id'] ?>"><?= h($e['razon_social']) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (empty($empresasParaSelector)): ?>
                  <div class="small" style="margin-top:6px">
                    No tenés empresas vinculadas todavía. Creala en el panel <strong>Empresa</strong>.
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-4"><label>Marca</label><input name="marca" required placeholder="Toyota / Fiat / etc."></div>
            <div class="col-4"><label>Modelo</label><input name="modelo" required placeholder="Corolla / Punto"></div>
            <div class="col-4"><label>Año</label><input name="anio" type="number" min="1900" max="<?= date('Y') + 1 ?>" required placeholder="2018"></div>

            <div class="col-5"><label>Patente</label><input class="input-patente" name="patente" required pattern="^([A-Z]{3}\d{3}|[A-Z]{2}\d{3}[A-Z]{2})$" title="ABC123 o AA123BB"></div>
            <div class="col-7"><label>Descripción (opcional)</label><input name="descripcion_extra" placeholder="Observaciones"></div>

            <div class="col-6"><label>Color</label><input name="color" required placeholder="Rojo"></div>
            <div class="col-6"><label>Kilometraje</label><input name="km" type="number" min="0" required placeholder="0"></div>

            <div class="col-6"><label>Cédula – Frente (jpg/png/webp, máx. 5MB)</label><input type="file" name="cedula_frente" accept=".jpg,.jpeg,.png,.webp" required></div>
            <div class="col-6"><label>Cédula – Dorso (jpg/png/webp, máx. 5MB)</label><input type="file" name="cedula_dorso" accept=".jpg,.jpeg,.png,.webp" required></div>

            <div class="col-12" style="display:flex;gap:10px"><button class="btn">Guardar</button><button type="reset" class="btn ghost">Limpiar</button></div>
          </form>
        </div>

        <div class="card">
          <h3>Mis vehículos</h3>
          <table class="table responsive">
            <thead>
              <tr>
                <th>Marca</th>
                <th>Modelo</th>
                <th>Año</th>
                <th>Patente</th>
                <th>Color</th>
                <th>KM</th>
                <th>Titular</th>
                <th>Frente</th>
                <th>Dorso</th>
                <th style="width:180px">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$vehiculos): ?>
                <tr>
                  <td colspan="10">Aún no registraste vehículos.</td>
                </tr>
                <?php else: foreach ($vehiculos as $v):
                  $fr = !empty($v['cedula_frente']) ? $base . '/publico/' . ltrim($v['cedula_frente'], '/') : '';
                  $dr = !empty($v['cedula_trasera']) ? $base . '/publico/' . ltrim($v['cedula_trasera'], '/') : '';
                ?>
                  <tr>
                    <td data-label="Marca"><?= h($v['marca']) ?></td>
                    <td data-label="Modelo"><?= h($v['modelo']) ?></td>
                    <td data-label="Año"><?= h($v['anio']) ?></td>
                    <td data-label="Patente"><?= h($v['patente']) ?></td>
                    <td data-label="Color"><?= h($v['color']) ?></td>
                    <td data-label="KM"><?= h($v['km']) ?></td>
                    <td data-label="Titular">
                      <?= (isset($v['titular']) && $v['titular'] === 'Empresa')
                        ? 'Empresa: ' . h($v['empresa'] ?? '')
                        : 'Personal' ?>
                    </td>
                    <td data-label="Frente"><?php if ($fr): ?><span class="thumb-sm" style="background-image:url('<?= h($fr) ?>')"></span><?php endif; ?></td>
                    <td data-label="Dorso"><?php if ($dr): ?><span class="thumb-sm" style="background-image:url('<?= h($dr) ?>')"></span><?php endif; ?></td>
                    <td data-label="Acciones">
                      <div class="veh-acciones" style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="btn ghost btn-edit"
                          data-id="<?= (int)$v['id_auto'] ?>"
                          data-marca="<?= h($v['marca']) ?>"
                          data-modelo="<?= h($v['modelo']) ?>"
                          data-anio="<?= h($v['anio']) ?>"
                          data-color="<?= h($v['color']) ?>"
                          data-km="<?= h($v['km']) ?>"
                          data-patente="<?= h($v['patente']) ?>"
                          data-descripcion_extra="<?= h($v['descripcion_extra'] ?? '') ?>"
                          data-foto_fr="<?= h($fr) ?>"
                          data-foto_dr="<?= h($dr) ?>">Editar</button>
                        <form method="post" class="form-delete" style="margin:0">
                          <input type="hidden" name="action" value="delete_vehiculo">
                          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                          <input type="hidden" name="id_auto" value="<?= (int)$v['id_auto'] ?>">
                          <button class="btn ghost">Eliminar</button>
                        </form>
                      </div>
                    </td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>

        <dialog id="vehiculoModal">
          <form method="post" enctype="multipart/form-data" class="row validate-form" style="background:var(--panel);padding:18px;border-radius:18px">
            <input type="hidden" name="action" value="update_vehiculo">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="id_auto" id="m_id">
            <div class="col-6"><label>Marca</label><input name="marca" id="m_marca" required></div>
            <div class="col-6"><label>Modelo</label><input name="modelo" id="m_modelo" required></div>
            <div class="col-3"><label>Año</label><input type="number" min="1900" max="<?= date('Y') + 1 ?>" name="anio" id="m_anio" required></div>
            <div class="col-5"><label>Patente</label><input class="input-patente" name="patente" id="m_patente" required pattern="^([A-Z]{3}\d{3}|[A-Z]{2}\d{3}[A-Z]{2})$" title="ABC123 o AA123BB"></div>
            <div class="col-4 patente-wide">
              <label>Patente</label>
              <input name="patente" id="m_patente_dup" required pattern="^([A-Z]{3}\d{3}|[A-Z]{2}\d{3}[A-Z]{2})$" title="ABC123 o AA123BB">
            </div>
            <div class="col-4"><label>Color</label><input name="color" id="m_color" required></div>
            <div class="col-12"><label>Descripción (opcional)</label><input name="descripcion_extra" id="m_descripcion_extra"></div>
            <div class="col-12"><label>Kilometraje</label><input type="number" min="0" name="km" id="m_km" required></div>
            <div class="col-6">
              <label>Frente (reemplazar)</label>
              <div style="display:flex;gap:10px;align-items:center">
                <span id="m_prev_fr" class="thumb"></span>
                <input type="file" name="cedula_frente" accept=".jpg,.jpeg,.png,.webp">
              </div>
            </div>
            <div class="col-6">
              <label>Dorso (reemplazar)</label>
              <div style="display:flex;gap:10px;align-items:center">
                <span id="m_prev_dr" class="thumb"></span>
                <input type="file" name="cedula_dorso" accept=".jpg,.jpeg,.png,.webp">
              </div>
            </div>
            <div class="col-12" style="display:flex;gap:10px;justify-content:flex-end">
              <button type="button" class="btn ghost" id="m_close">Cancelar</button>
              <button class="btn">Guardar cambios</button>
            </div>
          </form>
        </dialog>
      </section>

      <section id="tab-turnos" style="display:none">
        <div class="card" style="margin-bottom:16px">
          <h3>Agendar turno</h3>
          <form method="post" class="row validate-form">
            <input type="hidden" name="action" value="add_turno">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <div class="col-6"><label>Vehículo</label>
              <select name="auto_id" required>
                <option value="">Seleccionar…</option>
                <?php foreach ($vehiculos as $vv): ?>
                  <option value="<?= (int)$vv['id_auto'] ?>">
                    <?= h($vv['marca'] . ' ' . $vv['modelo'] . ' (' . $vv['anio'] . ')') ?> —
                    <?= (isset($vv['titular']) && $vv['titular'] === 'Empresa') ? 'Empresa: ' . h($vv['empresa'] ?? '') : 'Personal' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6"><label>Motivo</label><input type="text" name="motivo" maxlength="100" required></div>
            <div class="col-12"><label>Descripción (opcional)</label><input type="text" name="descripcion" rows="3" maxlength="500"></input></div>
            <div class="col-12"><button class="btn">Solicitar</button></div>
          </form>
        </div>

        <div class="card">
          <h3>Mis turnos</h3>
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>Vehículo</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Motivo</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$turnos): ?>
                <tr>
                  <td colspan="6">Sin turnos.</td>
                </tr>
                <?php else: foreach ($turnos as $t): ?>
                  <tr>
                    <td>T-<?= (int)$t['id'] ?></td>
                    <td><?= h($t['marca'] . ' ' . $t['modelo'] . ' ' . $t['anio']) ?></td>
                    <td><?= isset($t['fecha_turno']) && $t['fecha_turno'] !== null ? h($t['fecha_turno']) : 'Sin fecha' ?></td>
                    <td><?= isset($t['hora_turno']) && $t['hora_turno'] !== null ? h(substr($t['hora_turno'], 0, 5)) : 'Sin hora' ?></td>
                    <td><?= isset($t['motivo']) ? $t['motivo'] : "sin motivo" ?></td>
                    <td><?= isset($t['descripcion']) ? $t['descripcion'] : "sin descripcion"  ?></td>
                    <td><?php $e = strtolower($t['estado']);
                        $cl = $e === 'pendiente' ? 'warn' : ($e === 'cancelado' ? 'bad' : 'ok'); ?><span class="pill <?= $cl ?>"><?= h($t['estado']) ?></span></td>
                    <td style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                      <form method="post" class="validate-form" style="display:flex;gap:6px;align-items:center">
                        <input type="hidden" name="action" value="reprogramar_turno">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="turno_id" value="<?= (int)$t['id'] ?>">
                        <select name="auto_id">
                          <?php foreach ($vehiculos as $v2): ?>
                            <option value="<?= (int)$v2['id_auto'] ?>">
                              <?= h($v2['marca'] . ' ' . $v2['modelo']) ?> —
                              <?= (isset($v2['titular']) && $v2['titular'] === 'Empresa') ? 'Empresa: ' . h($v2['empresa'] ?? '') : 'Personal' ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <span class="badge">Pendiente de asignación del administrador</span>
                      </form>
                      <form method="post" class="form-cancelar">
                        <input type="hidden" name="action" value="cancelar_turno">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="turno_id" value="<?= (int)$t['id'] ?>">
                        <button class="btn ghost">Cancelar</button>
                      </form>
                    </td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-historial" style="display:none">
        <div class="card">
          <h3>Historial de servicios</h3>
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>Fecha ingreso</th>
                <th>Trabajo</th>
                <th>Estado</th>
                <th>Vehículo</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$historial): ?><tr>
                  <td colspan="5">Aún no hay trabajos registrados.</td>
                </tr>
                <?php else: foreach ($historial as $hrow): ?>
                  <tr>
                    <td>OR-<?= (int)$hrow['id'] ?></td>
                    <td><?= h($hrow['fecha_ingreso']) ?></td>
                    <td><?= h($hrow['trabajo']) ?></td>
                    <td><?= h($hrow['estado']) ?></td>
                    <td><?= h($hrow['marca'] . ' ' . $hrow['modelo'] . ' ' . $hrow['anio']) ?></td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-perfil" style="display:none">
        <div class="card">
          <h3>Mis datos</h3>
          <form method="post" class="row validate-form">
            <input type="hidden" name="action" value="update_perfil">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <div class="col-6"><label>Nombre</label><input name="n" value="<?= h($nom) ?>" required></div>
            <div class="col-6"><label>Apellido</label><input name="a" value="<?= h($ape) ?>" required></div>
            <div class="col-6"><label>DNI</label><input name="dni" value="<?= h($dniAct) ?>" required></div>
            <div class="col-6"><label>Email</label><input type="email" name="email" value="<?= h($emailAct) ?>"></div>
            <div class="col-12"><label>Teléfonos</label>
              <div id="telefonos-wrap" style="display:flex;flex-direction:column;gap:10px">
                <?php foreach ($tels as $tel): ?>
                  <div class="tel-item" style="display:grid;grid-template-columns:1fr auto;gap:10px">
                    <input type="text" name="telefonos[]" value="<?= h($tel) ?>" placeholder="+54 9 11 2345 6789">
                    <button type="button" class="btn ghost btn-del-tel">Quitar</button>
                  </div>
                <?php endforeach; ?>
              </div>
              <div style="margin-top:4px"><button type="button" id="addTel" class="btn ghost">+ Agregar teléfono</button></div>
            </div>
            <div class="col-6"><label>País</label><input name="pais" value="<?= h($domi['pais']) ?>"></div>
            <div class="col-6"><label>Provincia</label><input name="provincia" value="<?= h($domi['provincia']) ?>"></div>
            <div class="col-6"><label>Localidad</label><input name="localidad" value="<?= h($domi['localidad']) ?>"></div>
            <div class="col-6"><label>Barrio</label><input name="barrio" value="<?= h($domi['barrio']) ?>"></div>
            <div class="col-6"><label>Calle</label><input name="calle" value="<?= h($domi['calle']) ?>"></div>
            <div class="col-3"><label>Altura</label><input name="altura" value="<?= h($domi['altura']) ?>"></div>
            <div class="col-3"><label>Piso</label><input name="piso" value="<?= h($domi['piso']) ?>"></div>
            <div class="col-3"><label>Dto.</label><input name="departamento" value="<?= h($domi['departamento']) ?>"></div>
            <div class="col-12" style="margin-top:4px"><button class="btn">Guardar cambios</button></div>
          </form>
        </div>
      </section>

      <section id="tab-empresa" style="display:none">
        <div class="card">
          <h3>Empresa</h3>
          <div class="banner-empresa">
            Este panel está disponible para todos los usuarios. Si querés registrar vehículos <strong>empresariales</strong>,
            primero vinculá tu empresa por <strong>CUIT</strong>. Si no necesitás funciones empresariales, simplemente ignorá esta sección.
          </div>
          <p class="small">Vinculá tu empresa por CUIT. Se normaliza a dígitos para evitar errores.</p>
          <form method="post" class="row validate-form" id="formEmpresa">
            <input type="hidden" name="action" value="upsert_empresa_vinculo">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <div class="col-9"><label>Razón social</label><input name="razon_social" required></div>
            <div class="col-3"><label>CUIT</label><input name="cuit" placeholder="11-22222222-3" required></div>
            <div class="col-9"><label>Email empresa (opcional)</label><input type="email" name="email_empresa" placeholder="contacto@empresa.com"></div>
            <div class="col-3"><label>Teléfono empresa (opcional)</label><input name="telefono_empresa" placeholder="+54 9 11 2345 6789"></div>
            <div class="col-12" style="margin-top:6px"><button class="btn">Vincular / Actualizar</button></div>
          </form>
        </div>

        <div class="card" style="margin-top:16px">
          <h3>Empresas vinculadas</h3>
          <?php if (!$empresasFull): ?>
            <div>No tenés empresas asociadas aún. Usá el formulario de arriba.</div>
            <?php else: foreach ($empresasFull as $e): ?>
              <div style="padding:12px 0;border-bottom:1px solid rgba(157,176,208,.14)">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center">
                  <div>
                    <strong><?= h($e['razon_social']) ?></strong>
                    <div class="small">CUIT: <?= h($e['CUIT']) ?></div>
                  </div>
                </div>

                <?php if (!empty($e['contactos'])): ?>
                  <table class="table" style="margin-top:10px">
                    <thead>
                      <tr>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th style="width:140px">Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($e['contactos'] as $c): ?>
                        <tr>
                          <td><?= h(friendly_tipo($c['tipo'])) ?></td>
                          <td><?= h($c['valor']) ?></td>
                          <td>
                            <form method="post" class="form-del-contacto" style="display:inline">
                              <input type="hidden" name="action" value="del_contacto_emp">
                              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                              <input type="hidden" name="empresa_id" value="<?= (int)$e['id'] ?>">
                              <input type="hidden" name="contacto_id" value="<?= (int)$c['id'] ?>">
                              <button class="btn ghost">Quitar</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>

                <!-- EDITAR DATOS DE EMPRESA -->
                <form method="post" class="row validate-form" style="margin-top:12px">
                  <input type="hidden" name="action" value="editar_empresa">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="empresa_id" value="<?= (int)$e['id'] ?>">
                  <div class="col-5"><label>Razón social</label><input name="razon_social" value="<?= h($e['razon_social']) ?>" required></div>
                  <div class="col-3"><label>CUIT</label><input name="cuit" value="<?= h($e['CUIT']) ?>" required></div>
                  <?php
                  // Buscar email/teléfono actuales (si existen)
                  $emailActual = '';
                  $telActual = '';
                  if (!empty($e['contactos'])) {
                    foreach ($e['contactos'] as $cc) {
                      $t = strtoupper($cc['tipo']);
                      if ($t === 'EMAIL') $emailActual = $cc['valor'];
                      if ($t === 'TELEFONO') $telActual = $cc['valor'];
                    }
                  }
                  ?>
                  <div class="col-3"><label>Email</label><input type="email" name="email" value="<?= h($emailActual) ?>"></div>
                  <div class="col-1" style="display:flex;align-items:end; justify-content:flex-end">
                    &nbsp;
                  </div>
                  <div class="col-3"><label>Teléfono</label><input name="telefono" value="<?= h($telActual) ?>"></div>
                  <div class="col-2" style="display:flex;align-items:end"><button class="btn ghost">Guardar</button></div>
                </form>

                <!-- Agregar contacto puntual -->
                <form method="post" class="row validate-form" style="margin-top:10px">
                  <input type="hidden" name="action" value="add_contacto_emp">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="empresa_id" value="<?= (int)$e['id'] ?>">
                  <div class="col-3">
                    <label>Tipo</label>
                    <select name="tipo" required>
                      <option value="CUIT_EMPRESA">CUIT</option>
                      <option value="EMAIL">Email</option>
                      <option value="TELEFONO">Teléfono</option>
                    </select>
                  </div>
                  <div class="col-7">
                    <label>Valor</label>
                    <input name="valor" required>
                  </div>
                  <div class="col-2" style="display:flex;align-items:end">
                    <button class="btn ghost">Agregar</button>
                  </div>
                </form>
              </div>
          <?php endforeach;
          endif; ?>
        </div>
      </section>

      <footer class="card" style="margin-top:auto;text-align:center;font-size:13px;color:var(--muted)">
        © <?= date('Y') ?> Fixtime — Todos los derechos reservados
        <div style="margin-top:8px;display:flex;justify-content:center;gap:14px">
          <a href="mailto:contacto@fixtime.com" title="Email">📧</a>
          <a href="https://wa.me/5491123456789" target="_blank" title="WhatsApp">💬</a>
          <a href="https://facebook.com/fixtime" target="_blank" title="Facebook">📘</a>
          <a href="https://instagram.com/fixtime" target="_blank" title="Instagram">📷</a>
        </div>
      </footer>
    </main>
  </div>

  <script>
    const nav = document.getElementById('nav');
    const sections = {
      home: document.getElementById('tab-home'),
      vehiculos: document.getElementById('tab-vehiculos'),
      turnos: document.getElementById('tab-turnos'),
      historial: document.getElementById('tab-historial'),
      perfil: document.getElementById('tab-perfil'),
      empresa: document.getElementById('tab-empresa')
    };
    nav.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (!b) return;
      [...nav.querySelectorAll('button')].forEach(x => x.classList.remove('active'));
      b.classList.add('active');
      const tab = b.dataset.tab;
      Object.values(sections).forEach(s => s && (s.style.display = 'none'));
      sections[tab] && (sections[tab].style.display = 'block');
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const btnMenu = document.getElementById('btnMenu');

    function openSidebar() {
      sidebar?.classList.add('open');
      overlay?.classList.add('show');
      if (btnMenu) btnMenu.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
      sidebar?.classList.remove('open');
      overlay?.classList.remove('show');
      if (btnMenu) btnMenu.setAttribute('aria-expanded', 'false');
    }
    btnMenu?.addEventListener('click', () => {
      (sidebar?.classList.contains('open') ? closeSidebar : openSidebar)();
    });
    overlay?.addEventListener('click', closeSidebar);
    // Cerrar con Escape (accesible en PC y móvil)
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeSidebar();
    });
    nav.addEventListener('click', () => {
      if (window.matchMedia('(max-width:1080px)').matches) closeSidebar();
    });
    const root = document.documentElement;
    const btnTheme = document.getElementById('themeToggle');
    const btnThemeMobile = document.getElementById('themeToggleMobile');

    function setIcon(i) {
      if (btnTheme) btnTheme.textContent = i;
      if (btnThemeMobile) btnThemeMobile.textContent = i;
    }

    function applyTheme(t) {
      if (t === 'light') {
        root.classList.add('theme-light');
        setIcon('☀️');
      } else {
        root.classList.remove('theme-light');
        setIcon('🌙');
      }
      localStorage.setItem('fixtime_theme', t);
    }
    (function initTheme() {
      const saved = localStorage.getItem('fixtime_theme') || 'dark';
      applyTheme(saved);
    })();

    function toggleTheme() {
      const isLight = root.classList.contains('theme-light');
      applyTheme(isLight ? 'dark' : 'light');
    }
    btnTheme?.addEventListener('click', toggleTheme);
    btnThemeMobile?.addEventListener('click', toggleTheme);
    <?php if ($flash): ?>
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: '<?= $flashType ?>',
        title: '<?= h($flash) ?>',
        showConfirmButton: false,
        timer: 3200,
        timerProgressBar: true
      });
    <?php endif; ?>
    document.querySelectorAll('.validate-form').forEach(form => {
      form.addEventListener('submit', e => {
        const inputs = form.querySelectorAll('input[required], select[required]');
        for (const inp of inputs) {
          if (!String(inp.value || '').trim()) {
            e.preventDefault();
            Swal.fire({
              icon: 'error',
              title: 'Faltan datos',
              text: 'Completá los campos obligatorios.'
            });
            inp.focus();
            return;
          }
        }
      });
    });
    (function() {
      const f = document.getElementById('formAlta');
      if (!f) return;
      const MAX = 5 * 1024 * 1024,
        ok = ['image/jpeg', 'image/png', 'image/webp'];
      f.addEventListener('submit', e => {
        const tipo = f.querySelector('input[name="tipo_vehiculo"]:checked')?.value || 'personal';
        if (tipo === 'empresarial') {
          const emp = f.querySelector('#empresa_id')?.value || '';
          if (!emp) {
            e.preventDefault();
            Swal.fire({
              icon: 'warning',
              title: 'Elegí una empresa',
              text: 'Vinculá una empresa en el panel Empresa y luego volvé a este formulario.'
            });
            return;
          }
        }
        for (const n of ['cedula_frente', 'cedula_dorso']) {
          const file = f[n].files[0];
          if (!file) {
            e.preventDefault();
            Swal.fire({
              icon: 'error',
              title: 'Faltan las fotos de cédula'
            });
            return;
          }
          if (file.size > MAX) {
            e.preventDefault();
            Swal.fire({
              icon: 'error',
              title: 'Archivo muy grande (máx 5MB)'
            });
            return;
          }
          if (!ok.includes(file.type)) {
            e.preventDefault();
            Swal.fire({
              icon: 'error',
              title: 'Formato inválido (JPG/PNG/WEBP)'
            });
            return;
          }
        }
      });
      const radios = f.querySelectorAll('input[name="tipo_vehiculo"]');
      const wrap = document.getElementById('empSelWrap');
      const toggle = () => {
        const val = f.querySelector('input[name="tipo_vehiculo"]:checked')?.value || 'personal';
        wrap.style.display = (val === 'empresarial') ? 'block' : 'none';
      };
      radios.forEach(r => r.addEventListener('change', toggle));
      toggle();
    })();
    document.querySelectorAll('.form-cancelar').forEach(f => f.addEventListener('submit', e => {
      e.preventDefault();
      Swal.fire({
        title: "¿Cancelar turno?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, cancelar",
        cancelButtonText: "No",
        confirmButtonColor: "#ef4444"
      }).then(r => {
        if (r.isConfirmed) f.submit();
      });
    }));
    document.querySelectorAll('.form-delete').forEach(f => f.addEventListener('submit', e => {
      e.preventDefault();
      Swal.fire({
        title: "¿Eliminar vehículo?",
        text: "Si no tiene vínculos, se borrará definitivamente.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "No",
        confirmButtonColor: "#ef4444"
      }).then(r => {
        if (r.isConfirmed) f.submit();
      });
    }));
    const modal = document.getElementById('vehiculoModal');
    const mClose = document.getElementById('m_close');

    function openVehiculoModal() {
      if (typeof modal.showModal === 'function') modal.showModal();
      else modal.setAttribute('open', '');
      document.body.classList.add('modal-open');
    }

    function closeVehiculoModal() {
      if (typeof modal.close === 'function') modal.close();
      else modal.removeAttribute('open');
      document.body.classList.remove('modal-open');
    }
    document.querySelectorAll('.btn-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        m_id.value = btn.dataset.id;
        m_marca.value = btn.dataset.marca;
        m_modelo.value = btn.dataset.modelo;
        m_anio.value = btn.dataset.anio || '';
        m_color.value = btn.dataset.color || '';
        m_km.value = btn.dataset.km || 0;
        m_patente.value = btn.dataset.patente || '';
        const dup = document.getElementById('m_patente_dup');
        if (dup) dup.value = btn.dataset.patente || '';
        m_descripcion_extra.value = btn.dataset.descripcion_extra || '';
        const fr = btn.dataset.foto_fr || '';
        const dr = btn.dataset.foto_dr || '';
        m_prev_fr.style.backgroundImage = fr ? `url('${fr}')` : 'none';
        m_prev_dr.style.backgroundImage = dr ? `url('${dr}')` : 'none';
        openVehiculoModal();
      });
    });
    mClose?.addEventListener('click', closeVehiculoModal);
    modal.addEventListener('cancel', e => {
      e.preventDefault();
      closeVehiculoModal();
    });
    modal.addEventListener('click', e => {
      const rect = modal.querySelector('form').getBoundingClientRect();
      const inside = (e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom);
      if (!inside) closeVehiculoModal();
    });
    document.getElementById('addTel')?.addEventListener('click', () => {
      const wrap = document.getElementById('telefonos-wrap');
      const div = document.createElement('div');
      div.className = 'tel-item';
      div.style.display = 'grid';
      div.style.gridTemplateColumns = '1fr auto';
      div.style.gap = '10px';
      div.innerHTML = '<input type="text" name="telefonos[]" placeholder="+54 9 11 2345 6789"><button type="button" class="btn ghost btn-del-tel">Quitar</button>';
      wrap.appendChild(div);
    });
    document.getElementById('telefonos-wrap')?.addEventListener('click', e => {
      const b = e.target.closest('.btn-del-tel');
      if (!b) return;
      const item = b.closest('.tel-item');
      if (!item) return;
      const total = item.parentElement.querySelectorAll('.tel-item').length;
      if (total > 1) item.remove();
      else item.querySelector('input').value = '';
    });
    (function() {
      const p = new URLSearchParams(location.search);
      const t = p.get('tab') || 'home';
      const b = document.querySelector(`.nav button[data-tab="${t}"]`);
      if (b) b.click();
    })();
  </script>
</body>

</html>