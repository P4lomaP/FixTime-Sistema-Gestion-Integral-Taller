<?php
declare(strict_types=1);

require_once __DIR__ . '/../../clases/Sesion.php';
require_once __DIR__ . '/../../clases/Conexion.php';
require_once __DIR__ . '/../../clases/VehiculoRepositorio.php';
require_once __DIR__ . '/../../clases/TurnoRepositorio.php';
require_once __DIR__ . '/../../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../../clases/EmpresaRepositorio.php';

Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);
$nom = $_SESSION['nombre']  ?? '';
$ape = $_SESSION['apellido']?? '';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function normalizar_patente(string $p): string { return strtoupper(preg_replace('/[^A-Z0-9]/','',trim($p))); }
function normalizar_cuit(string $c): string { return preg_replace('/\D+/', '', $c); }

$repoV = new VehiculoRepositorio();
$repoT = new TurnoRepositorio();
$repoP = new PersonaRepositorio();
$repoE = new EmpresaRepositorio();

/* =================== FLASH + PRG =================== */
$flash=''; $flashType='info';
function redirect_with_flash(string $msg, string $type='success', string $tab='home'): void {
  $_SESSION['flash_msg']=$msg; $_SESSION['flash_type']=$type;
  $a=require __DIR__.'/../../config/app.php'; $b=rtrim($a['base_url'],'/');
  header('Location: '.$b.'/modules/cliente/index.php?tab='.urlencode($tab)); exit;
}
if (!empty($_SESSION['flash_msg'])) {
  $flash=$_SESSION['flash_msg']; $flashType=$_SESSION['flash_type']??'info';
  unset($_SESSION['flash_msg'],$_SESSION['flash_type']);
}

/* =================== CSRF =================== */
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf'];

/* =================== Upload helpers =================== */
function save_img_required(array $file, string $pref): string {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('FOTO_REQUERIDA');
  $mime = @mime_content_type($file['tmp_name']);
  $exts = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp'];
  if (!isset($exts[$mime])) throw new RuntimeException('FOTO_FORMATO');
  if (($file['size'] ?? 0) > 5*1024*1024) throw new RuntimeException('FOTO_TAM');
  $dir = __DIR__.'/../../publico/uploads/cedulas';
  if (!is_dir($dir)) mkdir($dir,0775,true);
  $name = $pref.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).$exts[$mime];
  if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('FOTO_MOVE');
  return 'uploads/cedulas/'.$name; // ruta relativa a /publico
}
function save_img_optional(?array $file, string $pref): ?string {
  if (!$file || ($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
  return save_img_required($file,$pref);
}

/* =================== Permisos autos =================== */
function usuarioPuedeOperarAuto(int $personaId, int $autoId): bool {
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $st = $db->prepare("SELECT 1 FROM Vehiculos_Personas WHERE Persona_id = :p AND automoviles_id = :a LIMIT 1");
  $st->execute([':p'=>$personaId, ':a'=>$autoId]);
  if ($st->fetchColumn()) return true;
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
  $st->execute([':a'=>$autoId, ':p'=>$personaId]);
  return (bool)$st->fetchColumn();
}

/* =================== Empresa helpers =================== */
function empresa_obtener(int $empresaId): ?array {
  $db=Conexion::obtener(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $st=$db->prepare("SELECT * FROM Empresas WHERE id=? LIMIT 1"); $st->execute([$empresaId]);
  $row=$st->fetch(PDO::FETCH_ASSOC); return $row ?: null;
}
function empresa_listar_contactos(int $empresaId): array {
  $db=Conexion::obtener(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql="SELECT ce.id, UPPER(t.descripcion) AS tipo, ce.valor
          FROM Contactos_Empresas ce
          JOIN Tipos_Contactos t ON t.id = ce.Tipo_Contacto_id
         WHERE ce.Empresas_id = ?
         ORDER BY t.descripcion, ce.valor";
  $st=$db->prepare($sql); $st->execute([$empresaId]);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}
function empresa_eliminar_contacto(int $empresaId, int $contactoId): bool {
  $db=Conexion::obtener(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $st=$db->prepare("DELETE FROM Contactos_Empresas WHERE id=? AND Empresas_id=?");
  return $st->execute([$contactoId,$empresaId]);
}

/* =================== POST =================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    redirect_with_flash('Sesión expirada. Probá nuevamente.','error','home');
  }
  $act = $_POST['action'] ?? '';
  try {

    /* Turnos */
    if ($act === 'add_turno') {
      $autoId      = (int)($_POST['auto_id'] ?? 0);
      $motivo      = trim($_POST['motivo'] ?? '');
      $descripcion = trim($_POST['descripcion'] ?? '');
      if (!$autoId || $motivo === '') {
        redirect_with_flash('Elegí el vehículo y escribí el motivo.','error','turnos');
      }
      if (!usuarioPuedeOperarAuto($uid,$autoId)) {
        redirect_with_flash('No tenés permisos para operar ese vehículo.','error','turnos');
      }
      $repoT->crearSolicitud($uid,$autoId,$motivo,$descripcion);
      redirect_with_flash('Solicitud enviada. Pendiente de asignación.','success','turnos');
    }

    if ($act === 'reprogramar_turno') {
      redirect_with_flash('La reprogramación la hará el administrador.','warning','turnos');
    }

    if ($act === 'cancelar_turno') {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      if ($turnoId) {
        $repoT->cancelar($uid,$turnoId);
        redirect_with_flash('Turno cancelado.','success','turnos');
      } else {
        redirect_with_flash('No se pudo cancelar el turno.','error','turnos');
      }
    }

    /* Empresa contactos */
    if ($act === 'add_contacto_emp') {
      $eid  = (int)($_POST['empresa_id'] ?? 0);
      $tipo = strtoupper(trim($_POST['tipo'] ?? ''));
      $val  = trim($_POST['valor'] ?? '');
      if (!$eid || $tipo === '' || $val === '') {
        redirect_with_flash('Completá tipo y valor.','error','empresa');
      }
      $db=Conexion::obtener(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $st=$db->prepare("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion)=UPPER(?) LIMIT 1");
      $st->execute([$tipo]); $tipoId=$st->fetchColumn();
      if(!$tipoId){ $ins=$db->prepare("INSERT INTO Tipos_Contactos (descripcion) VALUES (?)"); $ins->execute([$tipo]); $tipoId=$db->lastInsertId(); }
      $dup=$db->prepare("SELECT 1 FROM Contactos_Empresas WHERE Tipo_Contacto_id=? AND valor=? LIMIT 1");
      $dup->execute([$tipoId,$val]);
      if($dup->fetchColumn()) redirect_with_flash('Ese contacto ya existe.','warning','empresa');
      $repoE->agregarContacto($eid, $tipo, $val);
      redirect_with_flash('Contacto agregado.','success','empresa');
    }

  } catch (Throwable $eOuter) {
    redirect_with_flash('Ocurrió un error: '.$eOuter->getMessage(),'error','home');
  }
}

/* =================== Datos =================== */
$vehiculos = $repoV->listarParaPanel($uid);
$turnos    = $repoT->listarPorPersona($uid);
$historial = $repoT->historialServicios($uid);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Fixtime — Panel del Cliente</title>
  <link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
  <link rel="stylesheet" href="<?= $base ?>/publico/app.css">
</head>
<body>
<main class="main">
  <!-- === Sección Turnos === -->
  <section id="tab-turnos">
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
                <?= h($vv['marca'].' '.$vv['modelo'].' ('.$vv['anio'].')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6"><label>Motivo</label><input type="text" name="motivo" maxlength="100" required></div>
        <div class="col-12"><label>Descripción (opcional)</label><textarea name="descripcion" rows="3" maxlength="500"></textarea></div>
        <div class="col-12"><button class="btn">Solicitar</button></div>
      </form>
    </div>

    <div class="card">
      <h3>Mis turnos</h3>
      <table class="table">
        <thead>
          <tr><th>#</th><th>Vehículo</th><th>Motivo</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php if (!$turnos): ?>
          <tr><td colspan="6">Sin turnos.</td></tr>
        <?php else: foreach ($turnos as $t): ?>
          <tr>
            <td>T-<?= (int)$t['id'] ?></td>
            <td><?= h(($t['marca'] ?? '').' '.($t['modelo'] ?? '').' '.($t['anio'] ?? '')) ?></td>
            <td><?= h($t['motivo'] ?? '') ?></td>
            <td><?= h($t['descripcion'] ?? '') ?></td>
            <td>
              <?php $e=strtolower($t['estado'] ?? ''); $cl=$e==='pendiente'?'warn':($e==='cancelado'?'bad':'ok'); ?>
              <span class="pill <?= $cl ?>"><?= h($t['estado'] ?? '') ?></span>
            </td>
            <td>
              <form method="post" class="form-cancelar">
                <input type="hidden" name="action" value="cancelar_turno">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="turno_id" value="<?= (int)$t['id'] ?>">
                <button class="btn ghost">Cancelar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
