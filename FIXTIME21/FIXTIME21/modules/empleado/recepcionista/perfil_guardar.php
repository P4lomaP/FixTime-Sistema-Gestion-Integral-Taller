<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 3);
require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';
Sesion::requiereLogin();

$app  = require $ROOT . '/config/app.php';
$base = rtrim($app['base_url'], '/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: '.$base.'/modules/empleado/recepcionista/?tab=perfil&error='.urlencode('Método inválido'));
  exit;
}

$uid  = (int)($_SESSION['uid'] ?? 0);
$db   = Conexion::obtener();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Resolver persona/empleado igual que en index */
function _resolverPersonaEmpleadoDesdeUid(PDO $db, int $uid): array {
  $st=$db->prepare("SELECT id FROM Personas WHERE id=? LIMIT 1");
  $st->execute([$uid]); if ($st->fetchColumn()) return ['persona_id'=>$uid,'empleado_id'=>0];

  $st=$db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                      FROM Empleados e JOIN Personas p ON p.id=e.Persona_id
                     WHERE e.id=? LIMIT 1");
  $st->execute([$uid]); if($r=$st->fetch(PDO::FETCH_ASSOC)) return ['persona_id'=>(int)$r['persona_id'],'empleado_id'=>(int)$r['empleado_id']];

  try{
    $st=$db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                        FROM recepcionistas r
                        JOIN Empleados e ON e.id=r.Empleado_id
                        JOIN Personas  p ON p.id=e.Persona_id
                       WHERE r.id=? LIMIT 1");
    $st->execute([$uid]); if($r=$st->fetch(PDO::FETCH_ASSOC)) return ['persona_id'=>(int)$r['persona_id'],'empleado_id'=>(int)$r['empleado_id']];
  }catch(Throwable $e){}

  try{
    $st=$db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                        FROM recepcionistas r
                        JOIN Empleados e ON e.id=r.Empleado_id
                        JOIN Personas  p ON p.id=e.Persona_id
                       WHERE r.Empleado_id=? LIMIT 1");
    $st->execute([$uid]); if($r=$st->fetch(PDO::FETCH_ASSOC)) return ['persona_id'=>(int)$r['persona_id'],'empleado_id'=>(int)$r['empleado_id']];
  }catch(Throwable $e){}

  try{
    $cols=$db->query("SHOW COLUMNS FROM Empleados")->fetchAll(PDO::FETCH_COLUMN,0);
    if(in_array('Usuario_id',$cols,true)){
      $st=$db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                          FROM Empleados e JOIN Personas p ON p.id=e.Persona_id
                         WHERE e.Usuario_id=? LIMIT 1");
      $st->execute([$uid]); if($r=$st->fetch(PDO::FETCH_ASSOC)) return ['persona_id'=>(int)$r['persona_id'],'empleado_id'=>(int)$r['empleado_id']];
    }
  }catch(Throwable $e){}

  return ['persona_id'=>0,'empleado_id'=>0];
}

/* Tomar ids del POST o resolverlos */
$personaId  = (int)($_POST['persona_id']  ?? 0);
$empleadoId = (int)($_POST['empleado_id'] ?? 0);
if ($personaId<=0 || $empleadoId<=0){
  $ids=_resolverPersonaEmpleadoDesdeUid($db,$uid);
  if($personaId<=0)  $personaId=(int)$ids['persona_id'];
  if($empleadoId<=0) $empleadoId=(int)$ids['empleado_id'];
}
if ($personaId<=0){
  header('Location: '.$base.'/modules/empleado/recepcionista/?tab=perfil&error='.urlencode('No se encontró a la persona.'));
  exit;
}

/* Datos */
$nombre   = trim((string)($_POST['nombre']   ?? ''));
$apellido = trim((string)($_POST['apellido'] ?? ''));
$dni      = preg_replace('/\D+/','',(string)($_POST['dni'] ?? ''));
$email    = trim((string)($_POST['email']    ?? ''));

$telefonos = $_POST['telefono'] ?? $_POST['telefonos'] ?? [];
if(!is_array($telefonos)) $telefonos = [$telefonos];
$telefonos = array_values(array_unique(array_filter(array_map('trim',$telefonos),fn($t)=>$t!=='')));

$pais      = trim((string)($_POST['pais']      ?? ''));
$provincia = trim((string)($_POST['provincia'] ?? ''));
$localidad = trim((string)($_POST['localidad'] ?? ''));
$barrio    = trim((string)($_POST['barrio']    ?? ''));
$calle     = trim((string)($_POST['calle']     ?? ''));
$altura    = trim((string)($_POST['altura']    ?? ''));
$piso      = trim((string)($_POST['piso']      ?? ''));
$depto     = trim((string)($_POST['departamento'] ?? $_POST['depto'] ?? ''));

try{
  $db->beginTransaction();

  /* Persona */
  $st=$db->prepare("UPDATE Personas SET nombre=?, apellido=?, dni=? WHERE id=? LIMIT 1");
  $st->execute([$nombre,$apellido,$dni!==''?$dni:null,$personaId]);

  /* Tipos contacto */
  $tipoEmail = (int)($db->query("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion)='EMAIL' LIMIT 1")->fetchColumn() ?: 0);
  if(!$tipoEmail){ $db->exec("INSERT INTO Tipos_Contactos(descripcion) VALUES ('Email')"); $tipoEmail=(int)$db->lastInsertId(); }
  $tipoTel   = (int)($db->query("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion) IN ('TELEFONO','TEL','PHONE') LIMIT 1")->fetchColumn() ?: 0);
  if(!$tipoTel){ $db->exec("INSERT INTO Tipos_Contactos(descripcion) VALUES ('Teléfono')"); $tipoTel=(int)$db->lastInsertId(); }

  /* Email (upsert) */
  $st=$db->prepare("SELECT id FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=? LIMIT 1");
  $st->execute([$personaId,$tipoEmail]); $cid=(int)($st->fetchColumn() ?: 0);
  if ($email==='') {
    if($cid) $db->prepare("DELETE FROM Contacto_Persona WHERE id=?")->execute([$cid]);
  } else {
    if($cid) $db->prepare("UPDATE Contacto_Persona SET valor=? WHERE id=?")->execute([$email,$cid]);
    else     $db->prepare("INSERT INTO Contacto_Persona (Persona_id,Tipo_Contacto_id,valor) VALUES (?,?,?)")->execute([$personaId,$tipoEmail,$email]);
  }

  /* Teléfonos (reemplazo) */
  $db->prepare("DELETE FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=?")->execute([$personaId,$tipoTel]);
  if ($telefonos){
    $ins=$db->prepare("INSERT INTO Contacto_Persona (Persona_id,Tipo_Contacto_id,valor) VALUES (?,?,?)");
    foreach($telefonos as $t) $ins->execute([$personaId,$tipoTel,$t]);
  }

  /* Domicilio: si tenés modelo con tablas, lo podés agregar; como fallback, guardamos directo en Personas si existen esas columnas */
  $colsP = $db->query("SHOW COLUMNS FROM Personas")->fetchAll(PDO::FETCH_COLUMN,0);
  $set=[]; $vals=[];
  $dom = ['pais'=>$pais,'provincia'=>$provincia,'localidad'=>$localidad,'barrio'=>$barrio,'calle'=>$calle,'altura'=>$altura,'piso'=>$piso,'departamento'=>$depto];
  foreach($dom as $k=>$v){ if(in_array($k,$colsP,true)){ $set[]="$k=?"; $vals[] = ($v!==''?$v:null); } }
  if ($set){
    $vals[]=$personaId;
    $db->prepare("UPDATE Personas SET ".implode(',',$set)." WHERE id=? LIMIT 1")->execute($vals);
  }

  $db->commit();
  $_SESSION['nombre']=$nombre; $_SESSION['apellido']=$apellido;
  header('Location: '.$base.'/modules/empleado/recepcionista/?tab=perfil&ok='.urlencode('Perfil actualizado'));
  exit;

} catch(Throwable $e){
  if($db->inTransaction()) $db->rollBack();
  header('Location: '.$base.'/modules/empleado/recepcionista/?tab=perfil&error='.urlencode('No se pudo guardar: '.$e->getMessage()));
  exit;
}
