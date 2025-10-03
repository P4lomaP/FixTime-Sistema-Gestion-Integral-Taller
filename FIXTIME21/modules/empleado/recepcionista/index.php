<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 3); // .../FIXTIME21

require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';
require_once $ROOT . '/clases/RecepcionistaRepositorio.php';
require_once $ROOT . '/clases/TurnoRepositorio.php';
require_once $ROOT . '/clases/EmpleadoRepositorio.php';

Sesion::requiereLogin();

$app  = require $ROOT . '/config/app.php';
$base = rtrim($app['base_url'], '/');

$uid = (int)($_SESSION['uid'] ?? 0);
$nom = (string)($_SESSION['nombre'] ?? '');
$ape = (string)($_SESSION['apellido'] ?? '');

$repoR = new RecepcionistaRepositorio();
if (!$repoR->esRecepcionista($uid)) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

/* ===== Datos ===== */
$repoT = new TurnoRepositorio();
/*
  listarTodosLosTurnos() debe traer al menos:
  id, fecha_turno, hora_turno, Estado_Turno_id, estado,
  Automovil_id, patente, anio, color, modelo, marca, motivo, descripcion
*/
$turnos = $repoT->listarTodosLosTurnos();
$turnosPorFecha = [];
foreach ($turnos as $t) {
  $f = $t['fecha_turno'] ?? null;
  if ($f) $turnosPorFecha[$f][] = $t;
}

/* ===== Semana para mini-calendario (Inicio) ===== */
$offset = (int)($_GET['offset'] ?? 0);
$hoy    = new DateTimeImmutable();
$lunes  = $hoy->modify('monday this week')->modify("$offset week");

$diasSemana = ['Lunes','Martes','Miércoles','Jueves','Viernes'];
$semana = [];
for ($i=0; $i<5; $i++) { $semana[] = $lunes->modify("+$i day"); }

$repoE = new EmpleadoRepositorio();
$mecanicos = $repoE->listarMecanicosActivos();

/* Catálogos */
$db = Conexion::obtener();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


// === Resolver persona/empleado a partir del UID de sesión ===
function fixtime_resolverIds(PDO $db, int $uid): array {
  // ¿UID es Persona?
  $st=$db->prepare("SELECT id FROM Personas WHERE id=?");
  $st->execute([$uid]);
  $pid=(int)$st->fetchColumn();
  if($pid>0){
    $st=$db->prepare("SELECT id FROM Empleados WHERE Persona_id=? LIMIT 1");
    $st->execute([$pid]); $eid=(int)($st->fetchColumn() ?: 0);
    return ['persona_id'=>$pid,'empleado_id'=>$eid];
  }
  // ¿UID es Empleado?
  $st=$db->prepare("SELECT Persona_id FROM Empleados WHERE id=?");
  $st->execute([$uid]); $pid=(int)($st->fetchColumn() ?: 0);
  return ['persona_id'=>$pid,'empleado_id'=>$uid];
}

// === Cargar perfil completo con tolerancia de esquemas ===
function fixtime_cargarPerfil(PDO $db, int $personaId, int $empleadoId): array {
  $out = [
    'persona_id'=>$personaId,'empleado_id'=>$empleadoId,
    'nombre'=>'','apellido'=>'','dni'=>'','email'=>'','telefonos'=>[],
    'pais'=>'','provincia'=>'','localidad'=>'','barrio'=>'','calle'=>'','altura'=>'','piso'=>'','departamento'=>''
  ];
  if ($personaId<=0) return $out;

  // Persona
  $st=$db->prepare("SELECT * FROM Personas WHERE id=? LIMIT 1");
  $st->execute([$personaId]);
  $p=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  if($p){
    $out['nombre']   = (string)($p['nombre']   ?? $p['Nombre']   ?? '');
    $out['apellido'] = (string)($p['apellido'] ?? $p['Apellido'] ?? '');
    // DNI: admite variantes
    foreach($p as $k=>$v){
      $lk=strtolower($k);
      if($lk==='dni' || $lk==='documento' || $lk==='nro_documento' || $lk==='num_documento' || preg_match('/\bdni\b/',$lk)){
        $out['dni']=trim((string)$v); if($out['dni']!=='') break;
      }
    }
  }

  // Contactos (email / teléfonos)
  try{
    $st=$db->prepare("SELECT LOWER(tc.descripcion) AS tipo, cp.valor
                        FROM Contacto_Persona cp
                        JOIN Tipos_Contactos tc ON tc.id=cp.Tipo_Contacto_id
                       WHERE cp.Persona_id=?");
    $st->execute([$personaId]);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $tipo=(string)($r['tipo']??''); $val=trim((string)($r['valor']??''));
      if($val==='') continue;
      if(strpos($tipo,'mail')!==false) $out['email']=$val;
      if(strpos($tipo,'tel')!==false || strpos($tipo,'phone')!==false) $out['telefonos'][]=$val;
    }
  }catch(Throwable $e){}

  // Domicilio relacional
  $tieneDom=false;
  try{
    $sql="SELECT d.calle,d.altura,d.piso,d.departamento,
                 b.descripcion AS barrio, l.descripcion AS localidad,
                 pr.descripcion AS provincia, pa.descripcion AS pais
            FROM Personas_Domicilios pd
            JOIN Domicilios d   ON d.id=pd.Domicilio_id
            LEFT JOIN Barrios b ON b.id=d.Barrio_id
            LEFT JOIN Localidades l ON l.id=b.Localidad_id
            LEFT JOIN Provincias pr ON pr.id=l.Provincia_id
            LEFT JOIN Paises pa ON pa.id=pr.Pais_id
           WHERE pd.Persona_id=? LIMIT 1";
    $st=$db->prepare($sql); $st->execute([$personaId]);
    if($d=$st->fetch(PDO::FETCH_ASSOC)){
      foreach(['pais','provincia','localidad','barrio','calle','altura','piso','departamento'] as $k)
        $out[$k]=(string)($d[$k]??'');
      $tieneDom=true;
    }
  }catch(Throwable $e){}

  // Domicilio en columnas directas de Personas (si tu esquema lo usa)
  if(!$tieneDom){
    try{
      $cols=$db->query("SHOW COLUMNS FROM Personas")->fetchAll(PDO::FETCH_COLUMN,0);
      $pick=array_intersect(['pais','provincia','localidad','barrio','calle','altura','piso','departamento'],$cols);
      if($pick){
        $st=$db->prepare("SELECT ".implode(',',$pick)." FROM Personas WHERE id=? LIMIT 1");
        $st->execute([$personaId]); if($d=$st->fetch(PDO::FETCH_ASSOC))
          foreach($pick as $k) $out[$k]=(string)($d[$k]??'');
      }
    }catch(Throwable $e){}
  }

  // Fallback en Empleados (por si DNI/teléfonos/domicilio viven ahí)
  if($empleadoId>0){
    try{
      $st=$db->prepare("SELECT * FROM Empleados WHERE id=? LIMIT 1");
      $st->execute([$empleadoId]); if($emp=$st->fetch(PDO::FETCH_ASSOC)){
        if($out['dni']===''){
          foreach($emp as $k=>$v){
            $lk=strtolower($k);
            if($lk==='dni' || $lk==='documento' || $lk==='nro_documento' || preg_match('/\bdni\b/',$lk)){
              $out['dni']=trim((string)$v); if($out['dni']!=='') break;
            }
          }
        }
        foreach(['telefono','celular','tel'] as $tk){
          if(isset($emp[$tk]) && trim((string)$emp[$tk])!=='') $out['telefonos'][]=trim((string)$emp[$tk]);
        }
        foreach(['pais','provincia','localidad','barrio','calle','altura','piso','departamento'] as $k){
          if($out[$k]==='' && isset($emp[$k]) && trim((string)$emp[$k])!=='') $out[$k]=trim((string)$emp[$k]);
        }
      }
    }catch(Throwable $e){}
  }

  if($out['telefonos']) $out['telefonos']=array_values(array_unique(array_map('trim',$out['telefonos'])));
  return $out;
}


/* ===== Resolver persona/empleado a partir del UID (robusto) ===== */
function _resolverPersonaEmpleadoDesdeUid(PDO $db, int $uid): array {
  // 0) ¿El uid ya es una persona?
  $st=$db->prepare("SELECT id FROM Personas WHERE id=? LIMIT 1");
  $st->execute([$uid]); if ($st->fetchColumn()) return ['persona_id'=>$uid,'empleado_id'=>0];

  // 1) ¿El uid es empleado?
  $st=$db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                      FROM Empleados e JOIN Personas p ON p.id=e.Persona_id
                     WHERE e.id=? LIMIT 1");
  $st->execute([$uid]); if ($r=$st->fetch(PDO::FETCH_ASSOC)) return ['persona_id'=>(int)$r['persona_id'],'empleado_id'=>(int)$r['empleado_id']];

  // 2) ¿El uid es recepcionista?
  try {
    $st=$db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                        FROM recepcionistas r
                        JOIN Empleados e ON e.id=r.Empleado_id
                        JOIN Personas  p ON p.id=e.Persona_id
                       WHERE r.id=? LIMIT 1");
    $st->execute([$uid]); if ($r=$st->fetch(PDO::FETCH_ASSOC)) return ['persona_id'=>(int)$r['persona_id'],'empleado_id'=>(int)$r['empleado_id']];
  } catch(Throwable $e){}

  // 3) ¿El uid es el Empleado_id del recepcionista?
  try {
    $st=$db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                        FROM recepcionistas r
                        JOIN Empleados e ON e.id=r.Empleado_id
                        JOIN Personas  p ON p.id=e.Persona_id
                       WHERE r.Empleado_id=? LIMIT 1");
    $st->execute([$uid]); if ($r=$st->fetch(PDO::FETCH_ASSOC)) return ['persona_id'=>(int)$r['persona_id'],'empleado_id'=>(int)$r['empleado_id']];
  } catch(Throwable $e){}

  // 4) ¿El uid es Usuario_id del empleado? (si existe esa columna)
  try {
    $cols = $db->query("SHOW COLUMNS FROM Empleados")->fetchAll(PDO::FETCH_COLUMN,0);
    if (in_array('Usuario_id',$cols,true)) {
      $st=$db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                          FROM Empleados e JOIN Personas p ON p.id=e.Persona_id
                         WHERE e.Usuario_id=? LIMIT 1");
      $st->execute([$uid]); if ($r=$st->fetch(PDO::FETCH_ASSOC)) return ['persona_id'=>(int)$r['persona_id'],'empleado_id'=>(int)$r['empleado_id']];
    }
  } catch(Throwable $e){}

  return ['persona_id'=>0,'empleado_id'=>0];
}

/* ===== Cargar perfil (igual filosofía que admin) ===== */
/* ===== Cargar perfil (robusto; busca en Persona, Contactos, Domicilio y Empleado) ===== */
function _cargarPerfilRecep(PDO $db, int $uid): array {
  $ids = _resolverPersonaEmpleadoDesdeUid($db,$uid);
  $pid = (int)$ids['persona_id'];
  $eid = (int)$ids['empleado_id'];

  $out = [
    'persona_id'=>$pid,'empleado_id'=>$eid,
    'nombre'=>'','apellido'=>'','dni'=>'','email'=>'','telefonos'=>[],
    'pais'=>'','provincia'=>'','localidad'=>'','barrio'=>'','calle'=>'','altura'=>'','piso'=>'','departamento'=>''
  ];
  if ($pid<=0) return $out;

  /* Persona (traemos todo para detectar nombres alternativos de columnas) */
  $st=$db->prepare("SELECT * FROM Personas WHERE id=? LIMIT 1");
  $st->execute([$pid]);
  $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  if ($p){
    // Nombre / Apellido (nombres habituales)
    $out['nombre']   = (string)($p['nombre']   ?? $p['Nombre']   ?? '');
    $out['apellido'] = (string)($p['apellido'] ?? $p['Apellido'] ?? '');

    // DNI — tolera variantes (dni, documento, doc, nro_documento, etc.)
    $dni = '';
    foreach ($p as $k=>$v){
      $lk = strtolower($k);
      if ($lk==='dni' || $lk==='documento' || $lk==='nro_documento' || $lk==='num_documento' || preg_match('/\bdni\b/',$lk)) {
        $dni = trim((string)$v); if ($dni!=='') break;
      }
    }
    $out['dni'] = $dni;
  }

  /* Contactos (email / teléfonos) desde Contacto_Persona + Tipos_Contactos */
  try{
    $st=$db->prepare("SELECT LOWER(tc.descripcion) AS tipo, cp.valor
                        FROM Contacto_Persona cp
                        JOIN Tipos_Contactos tc ON tc.id=cp.Tipo_Contacto_id
                       WHERE cp.Persona_id=?");
    $st->execute([$pid]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $tipo = (string)($r['tipo'] ?? '');
      $val  = trim((string)($r['valor'] ?? ''));
      if ($val==='') continue;
      if (strpos($tipo,'mail')!==false)            $out['email'] = $val;
      if (strpos($tipo,'tel')!==false || strpos($tipo,'phone')!==false) $out['telefonos'][] = $val;
    }
  } catch (Throwable $e){/* ignora */}

  /* Domicilio por modelo relacional Personas_Domicilios (si existe) */
  $hadDom = false;
  try{
    $sql="SELECT d.calle,d.altura,d.piso,d.departamento,
                 b.descripcion AS barrio, l.descripcion AS localidad,
                 pr.descripcion AS provincia, pa.descripcion AS pais
            FROM Personas_Domicilios pd
            JOIN Domicilios d   ON d.id=pd.Domicilio_id
            LEFT JOIN Barrios b ON b.id=d.Barrio_id
            LEFT JOIN Localidades l ON l.id=b.Localidad_id
            LEFT JOIN Provincias pr ON pr.id=l.Provincia_id
            LEFT JOIN Paises pa ON pa.id=pr.Pais_id
           WHERE pd.Persona_id=? LIMIT 1";
    $st=$db->prepare($sql); $st->execute([$pid]);
    if($d=$st->fetch(PDO::FETCH_ASSOC)){
      foreach(['pais','provincia','localidad','barrio','calle','altura','piso','departamento'] as $k)
        $out[$k]=(string)($d[$k]??'');
      $hadDom = true;
    }
  } catch(Throwable $e){/* ignora */}

  /* Si no hubo domicilio, probamos columnas directas en Personas (si existen) */
  if(!$hadDom){
    try{
      $cols=$db->query("SHOW COLUMNS FROM Personas")->fetchAll(PDO::FETCH_COLUMN,0);
      $pick = array_intersect(['pais','provincia','localidad','barrio','calle','altura','piso','departamento'],$cols);
      if($pick){
        $st=$db->prepare("SELECT ".implode(',',$pick)." FROM Personas WHERE id=? LIMIT 1");
        $st->execute([$pid]); if($d=$st->fetch(PDO::FETCH_ASSOC))
          foreach($pick as $k) $out[$k]=(string)($d[$k]??'');
      }
    }catch(Throwable $e){/* ignora */}
  }

  /* ===== Fallback en Empleados (por si DNI/teléfonos/domicilio están ahí) ===== */
  if ($eid>0){
    try{
      $st=$db->prepare("SELECT * FROM Empleados WHERE id=? LIMIT 1");
      $st->execute([$eid]); if($emp=$st->fetch(PDO::FETCH_ASSOC)){
        // DNI alternativo
        if ($out['dni']===''){
          foreach($emp as $k=>$v){
            $lk=strtolower($k);
            if ($lk==='dni' || $lk==='documento' || $lk==='nro_documento' || preg_match('/\bdni\b/',$lk)){
              $out['dni']=trim((string)$v); if($out['dni']!=='') break;
            }
          }
        }
        // Teléfonos alternativos (telefono / celular / tel)
        foreach(['telefono','celular','tel'] as $tk){
          if (isset($emp[$tk]) && trim((string)$emp[$tk])!==''){
            $out['telefonos'][] = trim((string)$emp[$tk]);
          }
        }
        // Domicilio alternativo (si Personas no lo tenía y Empleados sí)
        $domKeys=['pais','provincia','localidad','barrio','calle','altura','piso','departamento'];
        foreach($domKeys as $k){
          if ($out[$k]==='' && isset($emp[$k]) && trim((string)$emp[$k])!==''){
            $out[$k]=trim((string)$emp[$k]);
          }
        }
      }
    }catch(Throwable $e){/* ignora */}
  }

  /* Quitar duplicados de teléfonos */
  if ($out['telefonos']) {
    $out['telefonos'] = array_values(array_unique(array_map('trim', $out['telefonos'])));
  }

  return $out;
}

/* ——— construir $perfil para la pestaña “Mi perfil” ——— */
$ids    = fixtime_resolverIds($db, (int)($_SESSION['uid'] ?? 0));
$perfil = fixtime_cargarPerfil($db, (int)$ids['persona_id'], (int)$ids['empleado_id']);















/* ===== Helper: resolver ids de empleado/persona a partir del uid ===== */
function resolverPersonaEmpleado(PDO $db, int $uid): array {
  // 1) Empleados.id = uid
  $st = $db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                        FROM Empleados e
                        JOIN Personas p ON p.id = e.Persona_id
                       WHERE e.id = ? LIMIT 1");
  $st->execute([$uid]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    return ['empleado_id'=>(int)$row['empleado_id'], 'persona_id'=>(int)$row['persona_id']];
  }

  // 2) Recepcionistas existe -> probar r.id = uid
  try {
    if ($db->query("SHOW TABLES LIKE 'Recepcionistas'")->fetchColumn()) {
      $st = $db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                            FROM Recepcionistas r
                            JOIN Empleados e ON e.id = r.Empleado_id
                            JOIN Personas  p ON p.id = e.Persona_id
                           WHERE r.id = ? LIMIT 1");
      $st->execute([$uid]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        return ['empleado_id'=>(int)$row['empleado_id'], 'persona_id'=>(int)$row['persona_id']];
      }
      // 3) Recepcionistas.Empleado_id = uid
      $st = $db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                            FROM Recepcionistas r
                            JOIN Empleados e ON e.id = r.Empleado_id
                            JOIN Personas  p ON p.id = e.Persona_id
                           WHERE r.Empleado_id = ? LIMIT 1");
      $st->execute([$uid]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        return ['empleado_id'=>(int)$row['empleado_id'], 'persona_id'=>(int)$row['persona_id']];
      }
    }
  } catch (Throwable $e) {}

  // 4) Empleados.Usuario_id = uid (por si el uid es el usuario)
  try {
    $cols = $db->query("SHOW COLUMNS FROM Empleados")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (in_array('Usuario_id', $cols, true)) {
      $st = $db->prepare("SELECT e.id AS empleado_id, p.id AS persona_id
                            FROM Empleados e
                            JOIN Personas p ON p.id = e.Persona_id
                           WHERE e.Usuario_id = ? LIMIT 1");
      $st->execute([$uid]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        return ['empleado_id'=>(int)$row['empleado_id'], 'persona_id'=>(int)$row['persona_id']];
      }
    }
  } catch (Throwable $e) {}

  return ['empleado_id'=>0,'persona_id'=>0];
}

/* ===== Mi Perfil (empleado logueado) ===== */
$perfil = [
  'empleado_id'=>0,'persona_id'=>0,
  'nombre'=>$nom,'apellido'=>$ape,'dni'=>'',
  'email'=>'','telefonos'=>[],
  'pais'=>'','provincia'=>'','localidad'=>'','barrio'=>'','calle'=>'','altura'=>'','depto'=>'','piso'=>''
];

try {
  // Resolver ids reales
  $ids = resolverPersonaEmpleado($db, $uid);
  $perfil['empleado_id'] = $ids['empleado_id'];
  $perfil['persona_id']  = $ids['persona_id'];

  if ($perfil['persona_id'] > 0) {
    // Traer nombre/apellido/dni por si difiere de la sesión
    $st = $db->prepare("SELECT nombre, apellido, dni FROM Personas WHERE id=?");
    $st->execute([$perfil['persona_id']]);
    if ($p = $st->fetch(PDO::FETCH_ASSOC)) {
      $perfil['nombre']   = (string)$p['nombre'];
      $perfil['apellido'] = (string)$p['apellido'];
      $perfil['dni']      = (string)($p['dni'] ?? '');
    }

    // Tipos de contacto
    $tidEmail = (int)($db->query("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion)='EMAIL' LIMIT 1")->fetchColumn() ?: 0);
    $tidTel   = (int)($db->query("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion) IN ('TELEFONO','TEL','PHONE') LIMIT 1")->fetchColumn() ?: 0);

    // Email
    if ($tidEmail) {
      $st = $db->prepare("SELECT valor FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=? LIMIT 1");
      $st->execute([$perfil['persona_id'], $tidEmail]);
      $perfil['email'] = (string)($st->fetchColumn() ?: '');
    }

    // Teléfonos
    if ($tidTel) {
      $st = $db->prepare("SELECT valor FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=? ORDER BY id");
      $st->execute([$perfil['persona_id'], $tidTel]);
      $perfil['telefonos'] = array_values(array_filter(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN))));
    }

    // Domicilio (tabla o columnas en Personas)
    $tablaDom = null;
    foreach (['Domicilios_Personas','Domicilio_Persona','Direcciones_Personas','domicilios_personas'] as $tbl) {
      $q=$db->prepare("SHOW TABLES LIKE ?"); $q->execute([$tbl]);
      if ($q->fetchColumn()) { $tablaDom=$tbl; break; }
    }
    if ($tablaDom) {
      $colsDom = $db->query("SHOW COLUMNS FROM {$tablaDom}")->fetchAll(PDO::FETCH_COLUMN, 0);
      $colPid  = in_array('Persona_id',$colsDom,true)?'Persona_id' : (in_array('persona_id',$colsDom,true)?'persona_id':null);
      if ($colPid) {
        $st = $db->prepare("SELECT * FROM {$tablaDom} WHERE {$colPid}=? ORDER BY id DESC LIMIT 1");
        $st->execute([$perfil['persona_id']]);
        if ($d = $st->fetch(PDO::FETCH_ASSOC)) {
          foreach (['pais','provincia','localidad','barrio','calle','altura','departamento','piso'] as $k) {
            if (array_key_exists($k,$d)) $perfil[$k==='departamento'?'depto':$k] = (string)($d[$k] ?? '');
          }
        }
      }
    } else {
      $colsP = $db->query("SHOW COLUMNS FROM Personas")->fetchAll(PDO::FETCH_COLUMN, 0);
      $sel   = [];
      foreach (['pais','provincia','localidad','barrio','calle','altura','departamento','piso'] as $k) {
        if (in_array($k,$colsP,true)) $sel[]=$k;
      }
      if ($sel) {
        $st = $db->prepare("SELECT ".implode(',', $sel)." FROM Personas WHERE id=?");
        $st->execute([$perfil['persona_id']]);
        if ($d = $st->fetch(PDO::FETCH_ASSOC)) {
          foreach ($sel as $k) $perfil[$k==='departamento'?'depto':$k] = (string)($d[$k] ?? '');
        }
      }
    }
  }
} catch (Throwable $e) { /* noop */ }






/* ===== Última OR por Turno (auto-detect FK y PK) =====
   Nota: DEJAMOS UN SOLO BLOQUE para no sobreescribir el mapa sin querer */
$ultimaOrdenPorTurno = [];
try {
  // Columnas reales
  $cols = $db->query("SHOW COLUMNS FROM ordenes_reparaciones")
             ->fetchAll(PDO::FETCH_COLUMN, 0);

  // Candidatos típicos para la FK al turno
  $candFk = ['Turno_id','turno_id','id_turno','Turnos_id','turnos_id','TurnoId'];
  $colTurno = null;
  foreach ($candFk as $c) if (in_array($c, $cols, true)) { $colTurno = $c; break; }
  if (!$colTurno) {
    foreach ($cols as $c) { // heurística si tiene "turn" e "id"
      $lc = strtolower($c);
      if (strpos($lc,'turn') !== false && strpos($lc,'id') !== false) { $colTurno = $c; break; }
    }
  }

  // Detectar PK (por si no se llama "id")
  $pk = 'id';
  try {
    $pkRow = $db->query("SHOW KEYS FROM ordenes_reparaciones WHERE Key_name='PRIMARY'")
                ->fetch(PDO::FETCH_ASSOC);
    if (!empty($pkRow['Column_name'])) $pk = $pkRow['Column_name'];
  } catch (Throwable $e) { /* opcional */ }

  if ($colTurno) {
    // Traer última OR por turno (por PK mayor)
    $sql = "SELECT `$colTurno` AS turno_id, MAX(`$pk`) AS orden_id
              FROM ordenes_reparaciones
             GROUP BY `$colTurno`";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $tid = (int)($r['turno_id'] ?? 0);
      $oid = (int)($r['orden_id'] ?? 0);
      if ($tid > 0 && $oid > 0) $ultimaOrdenPorTurno[$tid] = $oid;
    }
  }
} catch (Throwable $e) {
  // Si algo falla dejamos el mapa vacío y en pantalla aparecerá “—”
}

/* Vehículos para selector */
$vehiculosTodos = $db->query("
  SELECT a.id,
         ma.descripcion AS marca,
         mo.descripcion AS modelo,
         a.anio, a.patente
    FROM Automoviles a
    JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
    JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
   ORDER BY ma.descripcion, mo.descripcion, a.anio DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* === Mapa auto_id => cliente para autocompletar el campo Cliente === */
$clientesPorAuto = [];
try {
  $rows = $db->query("
    SELECT vp.automoviles_id AS auto_id, CONCAT(p.nombre,' ',p.apellido) AS cliente
      FROM Vehiculos_Personas vp
      JOIN Personas p ON p.id = vp.Persona_id
  ")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) { $clientesPorAuto[(int)$r['auto_id']] = (string)$r['cliente']; }
} catch (Throwable $e) { /* si no existe la tabla, seguimos */ }

try {
  $rows = $db->query("
    SELECT ve.automoviles_id AS auto_id, e.razon_social AS cliente
      FROM Vehiculos_Empresas ve
      JOIN Empresas e ON e.id = ve.Empresas_id
  ")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) {
    $aid = (int)$r['auto_id'];
    if (!isset($clientesPorAuto[$aid])) $clientesPorAuto[$aid] = (string)$r['cliente'];
  }
} catch (Throwable $e) { /* opcional */ }

/* Estados de turnos */
$estadosTurnos = $db->query("SELECT id, descripcion FROM Estados_Turnos ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

/* Detectar ids de 'cancelado' y 'terminado' y armar colecciones ACTIVAS */
$ID_CANCELADO = null;
$ID_TERMINADO = null;
foreach ($estadosTurnos as $e) {
  $desc = strtolower(trim((string)$e['descripcion']));
  if ($desc === 'cancelado') $ID_CANCELADO = (int)$e['id'];
  if ($desc === 'terminado') $ID_TERMINADO = (int)$e['id'];
}
$turnosActivos = array_values(array_filter($turnos, function($t) use ($ID_CANCELADO, $ID_TERMINADO){
  $eid = (int)($t['Estado_Turno_id'] ?? 0);
  if ($ID_CANCELADO !== null && $eid === $ID_CANCELADO) return false;
  if ($ID_TERMINADO !== null && $eid === $ID_TERMINADO) return false;
  return true;
}));

/* Agrupar SOLO los activos por fecha (para Asignar Mecánico) */
$turnosActivosPorFecha = [];
foreach ($turnosActivos as $t) {
  $f = $t['fecha_turno'] ?? null;
  if ($f) $turnosActivosPorFecha[$f][] = $t;
}

/* Flash por query */
$flash = (string)($_GET['ok']    ?? '');
$err   = (string)($_GET['error'] ?? '');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fixtime — Panel de Recepcionista</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{
  --bg:#0b1226; --panel:#0f1a33; --panel-2:#0b162b; --card:#0c1730;
  --muted:#9db0d0; --text:#e9f0ff; --brand:#3b82f6; --brand-2:#2563eb;
  --ring:rgba(59,130,246,.40); --shadow:0 12px 40px rgba(2,6,23,.45); --radius:18px;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0}
body{
  min-height:100vh;
  background:
    radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.22), transparent 70%),
    radial-gradient(900px 480px at 10% 110%, rgba(37,99,235,.16), transparent 60%),
    var(--bg);
  color:var(--text);
  font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;
}
.app{display:grid;grid-template-columns:320px 1fr;min-height:100vh}
.sidebar{padding:22px;background:linear-gradient(180deg,var(--panel),var(--bg));border-right:1px solid rgba(157,176,208,.15);position: sticky; top:0; height:100vh; z-index:40;}
.brand{display:flex;gap:12px;align-items:center;margin-bottom:22px}
.brand-badge{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(40px 30px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2));box-shadow:0 12px 30px var(--ring), inset 0 1px 0 rgba(255,255,255,.25)}
.brand-badge img{width:32px;height:32px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.brand-name{font-weight:800;letter-spacing:.35px;font-size:22px}
.brand-sub{opacity:.8;font-size:12px}
.theme-btn{margin-left:auto;appearance:none;border:1px solid rgba(157,176,208,.28);background:rgba(255,255,255,.06);color:var(--text);border-radius:12px;padding:10px 12px;cursor:pointer;font-size:16px;box-shadow:0 6px 16px rgba(0,0,0,.2)}
.nav{display:flex;flex-direction:column;gap:12px;margin-top:10px}
.nav button, .nav a{display:flex;gap:12px;align-items:center;justify-content:flex-start;padding:14px 16px;border-radius:14px;border:1px solid rgba(157,176,208,.18);background:rgba(255,255,255,.03);color:var(--text);cursor:pointer;font-size:16px;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.25); text-decoration:none}
.nav .active{background:linear-gradient(135deg, rgba(59,130,246,.20), rgba(37,99,235,.20));border-color:rgba(59,130,246,.55);box-shadow:0 10px 28px var(--ring)}
.topbar-salir{display:block;margin-top:14px;text-align:center;text-decoration:none}
.main{padding:26px 32px;display:flex;flex-direction:column;min-height:100vh}
.hero{display:flex;align-items:center;gap:16px;background:linear-gradient(135deg, rgba(59,130,246,.22), rgba(37,99,235,.18));border:1px solid rgba(59,130,246,.40);border-radius:var(--radius);padding:18px;box-shadow:0 14px 32px var(--ring);margin-bottom:16px}
.hero .avatar{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(24px 16px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2))}
.hero .avatar img{width:38px;height:38px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.hero .greet{font-weight:800;font-size:22px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.03));border:1px solid rgba(157,176,208,.16);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse;font-size:14px;table-layout:fixed}
.table th,.table td{padding:12px 10px;border-bottom:1px solid rgba(157,176,208,.14);word-break:break-word;background:transparent}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800;border:1px solid rgba(157,176,208,.22)}
.btn{cursor:pointer;border:0;border-radius:12px;padding:12px 16px;background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#0b1220;font-weight:800;box-shadow:0 12px 28px var(--ring)}
.btn.ghost{background:transparent;color:var(--text);border:1px solid rgba(157,176,208,.30);box-shadow:none}
.small{font-size:12px;opacity:.85}
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
.theme-light body{background:radial-gradient(1000px 500px at 80% -10%, rgba(59,130,246,.12), transparent 70%),radial-gradient(700px 380px at 10% 110%, rgba(37,99,235,.10), transparent 60%),var(--bg)}
.theme-light .sidebar{background:linear-gradient(180deg,var(--panel),#eaf0ff);border-right:1px solid rgba(15,23,42,.06)}
.theme-light .nav button, .theme-light .nav a{background:#fff;border-color:rgba(15,23,42,.08);color:#0b1220}
.theme-light .card{background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:var(--shadow)}
.theme-light .table th,.theme-light .table td{border-bottom:1px solid rgba(15,23,42,.08)}
.theme-light .theme-btn{background:#fff;border-color:rgba(15,23,42,.08);color:#0b1220}

/* Evitar “franja” detrás de botones/combos dentro de la tabla */
.table td form{ background:transparent !important; border:0 !important; padding:0 !important; }
/* Evitar que "Aplicar" se parta */
.table td .btn { white-space: nowrap; word-break: normal; }
/* Que el form no rompa a dos filas dentro de la celda */
.table td .form-estado { flex-wrap: nowrap; }

@media (max-width: 1100px){
  .card + section .card > div[style*="grid-template-columns:repeat(5"]{
    grid-template-columns: repeat(2, 1fr) !important;
  }
}
@media (max-width: 640px){
  .card + section .card > div[style*="grid-template-columns:repeat(5"]{
    grid-template-columns: 1fr !important;
  }
}
</style>
</head>
<body>

<!-- Header móvil -->
<div class="header-mobile">
  <button class="burger" id="btnMenu" aria-label="Abrir menú">☰</button>
  <div style="display:flex;align-items:center;gap:10px">
    <div class="brand-badge" style="width:36px;height:36px;border-radius:10px"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
    <strong>Fixtime</strong>
  </div>
  <button id="themeToggleMobile" class="theme-btn" title="Cambiar tema">🌙</button>
</div>

<div class="app">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-badge"><img src="<?= $base ?>/publico/widoo.png" alt="Fixtime"></div>
      <div style="flex:1">
        <div class="brand-name">Fixtime</div>
        <div class="brand-sub">Panel de Recepcionista</div>
      </div>
      <button id="themeToggle" class="theme-btn" title="Cambiar tema">🌙</button>
    </div>

    <nav class="nav" id="nav">
      <button class="active" data-tab="home">🏠 Inicio</button>
      <button data-tab="agenda"> 🧰 Asignar Mecanico </button>
      <button data-tab="turnos"> 🗓️ Agenda Turnos </button>
      <button data-tab="historial">🧾 Todos los Turnos </button>
      <button data-tab="perfil">👤 Mi perfil</button>


      <a href="<?= $base ?>/modules/selector/index.php" class="btn ghost topbar-salir">⬅️ Volver al selector</a>
      <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">Cerrar sesión</a>
    </nav>
  </aside>
  <div class="sidebar__overlay" id="overlay"></div>

  <!-- Main -->
  <main class="main">
    <div class="hero">
      <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
      <div style="flex:1">
        <div class="greet">¡Hola, <?= h($nom.' '.$ape) ?>!</div>
        <div class="hint">Gestioná turnos, asigná mecánicos y generá Órdenes.</div>
      </div>
    </div>

    <!-- KPIs / Home -->
    <section id="tab-home" style="display:block">
      <!-- KPIs -->
      <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:20px">
        <div class="card">
          <div style="font-size:13px;color:#9db0d0">Turnos hoy</div>
          <div style="font-size:24px;font-weight:800">
            <?php $hoy=date('Y-m-d'); $c=isset($turnosPorFecha[$hoy])?count($turnosPorFecha[$hoy]):0; echo (int)$c; ?>
          </div>
          <div class="hint">Pendientes y asignados.</div>
        </div>
        <div class="card">
          <div style="font-size:13px;color:#9db0d0">Mecánicos activos</div>
          <div style="font-size:24px;font-weight:800"><?= count($mecanicos) ?></div>
          <div class="hint">Disponibles para asignar.</div>
        </div>
        <div class="card">
          <div style="font-size:13px;color:#9db0d0">Turnos totales</div>
          <div style="font-size:24px;font-weight:800"><?= count($turnos) ?></div>
          <div class="hint">Histórico cargado.</div>
        </div>
      </div>

      <!-- Mini-calendario semanal -->
      <section class="card" style="margin-top:4px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <a class="btn ghost" href="?tab=home&offset=<?= $offset-1 ?>">⬅ Semana anterior</a>
          <h3 style="margin:0">Turnos de la semana (<?= $lunes->format('d/m/Y') ?>)</h3>
          <a class="btn ghost" href="?tab=home&offset=<?= $offset+1 ?>">Semana siguiente ➡</a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px">
          <?php foreach ($semana as $i=>$dia):
            $fecha = $dia->format('Y-m-d');
            $turnosDia = $turnosPorFecha[$fecha] ?? [];
          ?>
            <div style="background:var(--panel-2);border:1px solid rgba(157,176,208,.18);border-radius:12px;padding:12px;min-height:220px;display:flex;flex-direction:column">
              <strong style="margin-bottom:10px;font-size:15px;color:var(--text)">
                <?= $diasSemana[$i] ?> <?= $dia->format('d/m') ?>
              </strong>

              <?php if (!$turnosDia): ?>
                <div style="font-size:13px;color:var(--muted);margin-top:auto">Sin turnos</div>
              <?php else: foreach ($turnosDia as $t): ?>
                <div style="background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.35);border-radius:8px;padding:6px 10px;font-size:13px;margin-bottom:8px">
                  <div>
                    <b style="color:var(--brand)"><?= h(substr((string)($t['hora_turno'] ?? ''),0,5)) ?></b>
                    — <?= h(trim(($t['marca'] ?? '').' '.($t['modelo'] ?? ''))) ?>
                  </div>
                  <small style="display:block;color:var(--muted);margin-top:2px">
                    <?php
                      $cliente = trim( (($t['nombre'] ?? '') . ' ' . ($t['apellido'] ?? '')) );
                      echo $cliente !== '' ? h($cliente).' — ' : '';
                    ?>
                    <?= h(ucfirst((string)($t['estado'] ?? ''))) ?>
                  </small>
                </div>
              <?php endforeach; endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </section>

    <!-- Agenda (asignar mecánico + imprimir OR) -->
    <section id="tab-agenda" style="display:none">
      <div class="card">
        <h3 style="margin:0 0 8px 0">Agenda</h3>
        <div class="small" style="margin-bottom:10px">Asigná un mecánico para generar la Orden e imprimir.</div>

        <div class="table-wrap">
          <table class="table" aria-label="Turnos">
            <thead>
              <tr>
                <th>#</th><th>Fecha</th><th>Hora</th><th>Estado</th>
                <th>Vehículo</th><th>Motivo</th><th style="width:360px">Asignar mecánico</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$turnosActivosPorFecha): ?>
                <tr><td colspan="7">Sin turnos activos.</td></tr>
              <?php endif; ?>
              <?php foreach ($turnosActivosPorFecha as $fecha => $items): ?>
                <?php foreach ($items as $t): ?>
  <?php if (strtolower($t['estado'] ?? '') === 'terminado') continue; ?>
  <tr>
    <td>T-<?= (int)($t['id'] ?? 0) ?></td>
    <td><?= h((string)($t['fecha_turno'] ?? '')) ?></td>
    <td><?= h(substr((string)($t['hora_turno'] ?? ''),0,5)) ?></td>
    <td><span class="badge"><?= h((string)($t['estado'] ?? '')) ?></span></td>
    <td>
      <?php
        $veh = trim(($t['marca'] ?? '').' '.($t['modelo'] ?? '').' '.($t['anio'] ?? ''));
        $pat = trim((string)($t['patente'] ?? ''));
        echo h($veh) . ($pat!=='' ? ' — '.h($pat) : '');
      ?>
    </td>
    <td><?= h((string)($t['motivo'] ?? '')) ?></td>
    <td>
      <form method="post"
            action="<?= $base ?>/modules/empleado/recepcionista/asignar.php"
            class="validate-form form-imprimir"
            style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0">
        <input type="hidden" name="turno_id" value="<?= (int)($t['id'] ?? 0) ?>">
        <select required name="mecanico_id" style="min-width:220px;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          <option value="">— Elegí mecánico —</option>
          <?php foreach ($mecanicos as $m): ?>
            <option value="<?= (int)$m['empleado_id'] ?>"><?= h((string)($m['apellido'] ?? '').' '.(string)($m['nombre'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
                        <button class="btn">Crear OR + Imprimir</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- TURNOS CRUD -->
    <section id="tab-turnos" style="display:none">
      <div class="card" style="margin-bottom:16px">
        <h3 style="margin:0 0 10px">Nuevo turno</h3>
        <form method="post" action="<?= $base ?>/modules/empleado/recepcionista/turnos_guardar.php" class="validate-form" style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px">
          <input type="hidden" name="id" value="">
          <div style="grid-column:span 3">
            <label class="small">Vehículo</label>
            <select id="sel_vehiculo" name="automovil_id" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
              <option value="">— Elegí —</option>
              <?php foreach ($vehiculosTodos as $v):
                $cliente = $clientesPorAuto[(int)$v['id']] ?? '';
              ?>
                <option value="<?= (int)$v['id'] ?>" data-cliente="<?= h($cliente) ?>">
                  <?= h((string)$v['marca'].' '.(string)$v['modelo'].' '.(string)$v['anio'].' — '.(string)($v['patente'] ?? '')) ?>
                  <?= $cliente ? ' — '.h($cliente) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Cliente autocompletado -->
          <div style="grid-column:span 3">
            <label class="small">Cliente</label>
            <input id="inp_cliente" type="text" readonly placeholder="Se completa al elegir vehículo" style="width:100%;background:rgba(157,176,208,.08);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          </div>

          <div>
            <label class="small">Fecha</label>
            <input type="date" name="fecha" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          </div>
          <div>
            <label class="small">Hora</label>
            <input type="time" name="hora" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          </div>
          <div>
            <label class="small">Estado</label>
            <select name="estado_id" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
              <?php foreach ($estadosTurnos as $e): ?>
                <option value="<?= (int)$e['id'] ?>"><?= h((string)$e['descripcion']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="grid-column:span 3">
            <label class="small">Motivo</label>
            <input name="motivo" maxlength="100" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          </div>
          <div style="grid-column:span 3">
            <label class="small">Descripción (opcional)</label>
            <input name="descripcion" maxlength="500" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          </div>
          <div style="grid-column:span 6;display:flex;gap:10px;justify-content:flex-end">
            <button class="btn">Guardar</button>
            <button type="reset" class="btn ghost">Limpiar</button>
          </div>
        </form>
      </div>

<div class="card">
  <h3 style="margin:0 0 10px">Todos los turnos</h3>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Vehículo</th><th>Motivo</th><th style="width:360px">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$turnosActivos): ?>
        <tr><td colspan="7">No hay turnos activos.</td></tr>
      <?php else: foreach ($turnosActivos as $t): ?>
        <?php if (strtolower($t['estado'] ?? '') === 'terminado') continue; ?>
        <tr>
          <td>T-<?= (int)($t['id'] ?? 0) ?></td>
          <td><?= h((string)($t['fecha_turno'] ?? '')) ?></td>
          <td><?= h(substr((string)($t['hora_turno'] ?? ''),0,5)) ?></td>
          <td><span class="badge"><?= h((string)($t['estado'] ?? '')) ?></span></td>
          <td>
            <?php
              $veh = trim(($t['marca'] ?? '').' '.($t['modelo'] ?? '').' '.($t['anio'] ?? ''));
              $pat = trim((string)($t['patente'] ?? ''));
              echo h($veh) . ($pat!=='' ? ' — '.h($pat) : '');
            ?>
          </td>
          <td><?= h((string)($t['motivo'] ?? '')) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <!-- Editar (modal) -->
            <button class="btn ghost btn-edit"
              data-id="<?= (int)($t['id'] ?? 0) ?>"
              data-auto="<?= (int)($t['Automovil_id'] ?? 0) ?>"
              data-fecha="<?= h((string)($t['fecha_turno'] ?? '')) ?>"
              data-hora="<?= h(substr((string)($t['hora_turno'] ?? ''),0,5)) ?>"
              data-estado="<?= h((string)($t['Estado_Turno_id'] ?? '')) ?>"
              data-motivo="<?= h((string)($t['motivo'] ?? '')) ?>"
              data-descripcion="<?= h((string)($t['descripcion'] ?? '')) ?>"
            >Editar</button>

                  <!-- Cambiar estado (select + aplicar) -->
                  <form method="post"
                        action="<?= $base ?>/modules/empleado/recepcionista/turnos_estado.php"
                        class="validate-form form-estado"
                        style="display:flex;gap:6px;align-items:center;margin:0">
                    <input type="hidden" name="id" value="<?= (int)($t['id'] ?? 0) ?>">
                    <select name="estado_id" required style="background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:10px;padding:8px 10px">
                      <?php foreach ($estadosTurnos as $e): ?>
                        <option value="<?= (int)$e['id'] ?>" <?= ((int)($t['Estado_Turno_id'] ?? 0) === (int)$e['id'] ? 'selected' : '') ?>>
                          <?= h((string)$e['descripcion']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn ghost">Aplicar</button>
                  </form>

                  <!-- Acciones rápidas: Terminar / Cancelar -->
                  <?php if ($ID_TERMINADO !== null): ?>
                  <form method="post"
                        action="<?= $base ?>/modules/empleado/recepcionista/turnos_estado.php"
                        class="validate-form form-estado" style="margin:0">
                    <input type="hidden" name="id" value="<?= (int)($t['id'] ?? 0) ?>">
                    <input type="hidden" name="estado_id" value="<?= (int)$ID_TERMINADO ?>">
                    <button class="btn ghost">Terminar</button>
                  </form>
                  <?php endif; ?>

                  <?php if ($ID_CANCELADO !== null): ?>
                  <form method="post"
                        action="<?= $base ?>/modules/empleado/recepcionista/turnos_estado.php"
                        class="validate-form form-estado" style="margin:0">
                    <input type="hidden" name="id" value="<?= (int)($t['id'] ?? 0) ?>">
                    <input type="hidden" name="estado_id" value="<?= (int)$ID_CANCELADO ?>">
                    <button class="btn ghost">Cancelar</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal editar -->
      <dialog id="turnoModal">
        <form method="post" action="<?= $base ?>/modules/empleado/recepcionista/turnos_guardar.php" style="background:var(--panel);padding:18px;border-radius:18px;min-width:640px">
          <input type="hidden" name="id" id="m_id">
          <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px">
            <div style="grid-column:span 3">
              <label class="small">Vehículo</label>
              <select name="automovil_id" id="m_auto" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
                <option value="">— Elegí —</option>
                <?php foreach ($vehiculosTodos as $v): ?>
                  <option value="<?= (int)$v['id'] ?>">
                    <?= h((string)$v['marca'].' '.(string)$v['modelo'].' '.(string)$v['anio'].' — '.(string)($v['patente'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="small">Fecha</label>
              <input type="date" name="fecha" id="m_fecha" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
            </div>
            <div>
              <label class="small">Hora</label>
              <input type="time" name="hora" id="m_hora" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
            </div>
            <div>
              <label class="small">Estado</label>
              <select name="estado_id" id="m_estado" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
                <?php foreach ($estadosTurnos as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= h((string)$e['descripcion']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="grid-column:span 3">
              <label class="small">Motivo</label>
              <input name="motivo" id="m_motivo" maxlength="100" required style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
            </div>
            <div style="grid-column:span 3">
              <label class="small">Descripción</label>
              <input name="descripcion" id="m_desc" maxlength="500" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
            </div>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
            <button type="button" class="btn ghost" id="m_close">Cancelar</button>
            <button class="btn">Guardar cambios</button>
          </div>
        </form>
      </dialog>
    </section>

    <!-- HISTORIAL -->
    <section id="tab-historial" style="display:none">
      <div class="card" style="margin-bottom:12px">
        <h3 style="margin:0 0 10px">Historial de turnos</h3>

        <!-- Filtros locales -->
        <div class="row" style="display:grid;grid-template-columns:repeat(12,1fr);gap:12px;margin-bottom:10px">
          <div style="grid-column:span 4">
            <label class="small">Buscar (cliente, vehículo, patente, motivo)</label>
            <input id="hist_q" placeholder="Ej.: Juan, Corolla, ABC123"
                   style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          </div>
          <div style="grid-column:span 3">
            <label class="small">Desde</label>
            <input id="hist_d1" type="date"
                   style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          </div>
          <div style="grid-column:span 3">
            <label class="small">Hasta</label>
            <input id="hist_d2" type="date"
                   style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
          </div>
          <div style="grid-column:span 2">
            <label class="small">Estado</label>
            <select id="hist_estado"
                    style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
              <option value="">Todos</option>
              <?php foreach ($estadosTurnos as $e): ?>
                <option value="<?= h(strtolower((string)$e['descripcion'])) ?>"><?= h((string)$e['descripcion']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end">
          <span class="small" style="align-self:center;color:var(--muted)">Mostrando <b id="hist_count">0</b> items</span>
          <button id="hist_clear" class="btn ghost">Limpiar filtros</button>
        </div>
      </div>

      <div class="card">
        <div class="table-wrap">
          <table class="table" id="hist_table">
            <thead>
              <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Estado</th>
                <th>Cliente</th>
                <th>Vehículo</th>
                <th>Patente</th>
                <th>Motivo</th>
                <th>Descripción</th>
                <th>Orden</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$turnos): ?>
                <tr><td colspan="10">No hay registros.</td></tr>
              <?php else: foreach ($turnos as $t):
                  $cliente = trim((string)($t['nombre'] ?? '').' '.(string)($t['apellido'] ?? ''));
                  $veh     = trim((string)($t['marca'] ?? '').' '.(string)($t['modelo'] ?? '').' '.(string)($t['anio'] ?? ''));
                  $fecha   = (string)($t['fecha_turno'] ?? '');
                  $hora    = substr((string)($t['hora_turno'] ?? ''),0,5);
                  $estado  = strtolower((string)($t['estado'] ?? ''));
                  $pat     = (string)($t['patente'] ?? '');
                  $motivo  = (string)($t['motivo'] ?? '');
                  $desc    = (string)($t['descripcion'] ?? '');
                  $busca   = trim($cliente.' '.$veh.' '.$pat.' '.$motivo.' '.$desc);
                  $tid     = (int)($t['id'] ?? 0);
                  $oid     = $ultimaOrdenPorTurno[$tid] ?? 0;
              ?>
              <tr
                data-fecha="<?= h($fecha) ?>"
                data-estado="<?= h($estado) ?>"
                data-busca="<?= h($busca) ?>"
              >
                <td>T-<?= (int)($t['id'] ?? 0) ?></td>
                <td><?= h($fecha) ?></td>
                <td><?= h($hora) ?></td>
                <td><span class="badge"><?= h((string)($t['estado'] ?? '')) ?></span></td>
                <td><?= $cliente !== '' ? h($cliente) : '—' ?></td>
                <td><?= $veh !== '' ? h($veh) : '—' ?></td>
                <td><?= $pat !== '' ? h($pat) : '—' ?></td>
                <td><?= $motivo !== '' ? h($motivo) : '—' ?></td>
                <td><?= $desc !== '' ? h($desc) : '—' ?></td>
                <td>
                  <?php if ($oid): ?>
                    <a class="btn ghost btn-reprint" target="_blank"
                       href="<?= $base ?>/modules/empleado/recepcionista/imprimir.php?orden_id=<?= $oid ?>">
                      Reimprimir
                    </a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
<!-- MI PERFIL -->
<section id="tab-perfil" style="display:none">
  <div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 10px">Mi perfil</h3>
    <div class="small" style="margin-bottom:10px">
      Actualizá tus datos personales, teléfonos y domicilio.
    </div>

    <form method="post" action="<?= $base ?>/modules/empleado/recepcionista/perfil_guardar.php" class="validate-form" style="display:grid;grid-template-columns:repeat(12,1fr);gap:12px">
      <input type="hidden" name="empleado_id" value="<?= (int)$perfil['empleado_id'] ?>">
      <input type="hidden" name="persona_id"  value="<?= (int)$perfil['persona_id'] ?>">

      <div style="grid-column:span 6">
        <label class="small">Nombre</label>
        <input name="nombre" required value="<?= h($perfil['nombre']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 6">
        <label class="small">Apellido</label>
        <input name="apellido" required value="<?= h($perfil['apellido']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>

      <div style="grid-column:span 6">
        <label class="small">DNI</label>
        <input name="dni" value="<?= h($perfil['dni']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 6">
        <label class="small">Email</label>
        <input type="email" name="email" value="<?= h($perfil['email']) ?>" placeholder="tucorreo@dominio.com" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>

      <!-- Teléfonos -->
      <div style="grid-column:span 12">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <label class="small" style="margin:0">Teléfonos</label>
          <button type="button" id="btnAddTel" class="btn ghost">+ Agregar teléfono</button>
        </div>
        <div id="tel_list" style="display:flex;flex-direction:column;gap:8px">
          <?php $tels = $perfil['telefonos']; if(!$tels) $tels = ['']; ?>
          <?php foreach ($tels as $t): ?>
          <div class="tel-item" style="display:flex;gap:8px;align-items:center">
            <input name="telefonos[]" value="<?= h($t) ?>" placeholder="+54 9 11 2345 6789" 
                   style="flex:1;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
            <button type="button" class="btn ghost btn-del-tel">Quitar</button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Domicilio -->
      <div style="grid-column:span 6">
        <label class="small">País</label>
        <input name="pais" value="<?= h($perfil['pais']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 6">
        <label class="small">Provincia</label>
        <input name="provincia" value="<?= h($perfil['provincia']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 6">
        <label class="small">Localidad</label>
        <input name="localidad" value="<?= h($perfil['localidad']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 6">
        <label class="small">Barrio</label>
        <input name="barrio" value="<?= h($perfil['barrio']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 6">
        <label class="small">Calle</label>
        <input name="calle" value="<?= h($perfil['calle']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 2">
        <label class="small">Altura</label>
        <input name="altura" value="<?= h($perfil['altura']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 2">
        <label class="small">Piso</label>
        <input name="piso" value="<?= h($perfil['piso']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>
      <div style="grid-column:span 2">
        <label class="small">Depto</label>
        <input name="depto" value="<?= h($perfil['depto']) ?>" style="width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px">
      </div>

      <div style="grid-column:span 12;display:flex;gap:10px;justify-content:flex-end">
        <button class="btn">Guardar cambios</button>
        <button type="reset" class="btn ghost">Revertir</button>
      </div>
    </form>
  </div>
</section>


    <footer class="card" style="margin-top:auto;text-align:center;font-size:13px;color:var(--muted)">
      © <?= date('Y') ?> Fixtime — Todos los derechos reservados
    </footer>
  </main>
</div>

<script>
/* Tabs */
const nav=document.getElementById('nav');
const sections={
  home:document.getElementById('tab-home'),
  agenda:document.getElementById('tab-agenda'),
  turnos:document.getElementById('tab-turnos'),
  historial:document.getElementById('tab-historial'),
  perfil:document.getElementById('tab-perfil')
};
nav.addEventListener('click',e=>{
  const b=e.target.closest('button'); if(!b) return;
  [...nav.querySelectorAll('button')].forEach(x=>x.classList.remove('active'));
  b.classList.add('active');
  const tab=b.dataset.tab;
  Object.values(sections).forEach(s=>s&&(s.style.display='none'));
  sections[tab]&&(sections[tab].style.display='block');
  window.scrollTo({top:0,behavior:'smooth'});
});

/* Sidebar móvil */
const sidebar=document.getElementById('sidebar');
const overlay=document.getElementById('overlay');
const btnMenu=document.getElementById('btnMenu');
function openSidebar(){sidebar?.classList.add('open');overlay?.classList.add('show'); if(btnMenu) btnMenu.setAttribute('aria-expanded','true');}
function closeSidebar(){sidebar?.classList.remove('open');overlay?.classList.remove('show'); if(btnMenu) btnMenu.setAttribute('aria-expanded','false');}
btnMenu?.addEventListener('click',()=>{ (sidebar?.classList.contains('open')?closeSidebar:openSidebar)(); });
overlay?.addEventListener('click',closeSidebar);
window.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeSidebar(); });
nav.addEventListener('click',()=>{ if (window.matchMedia('(max-width:1080px)').matches) closeSidebar(); });

/* Tema */
const root=document.documentElement;
const btnTheme=document.getElementById('themeToggle');
const btnThemeMobile=document.getElementById('themeToggleMobile');
function setIcon(i){ if(btnTheme)btnTheme.textContent=i; if(btnThemeMobile)btnThemeMobile.textContent=i; }
function applyTheme(t){ if(t==='light'){ root.classList.add('theme-light'); setIcon('☀️'); } else{ root.classList.remove('theme-light'); setIcon('🌙'); } localStorage.setItem('fixtime_theme',t); }
(function initTheme(){ const saved = localStorage.getItem('fixtime_theme') || 'dark'; applyTheme(saved); })();
function toggleTheme(){ const isLight=root.classList.contains('theme-light'); applyTheme(isLight?'dark':'light'); }
btnTheme?.addEventListener('click',toggleTheme);
btnThemeMobile?.addEventListener('click',toggleTheme);

/* Validaciones mínimas */
document.querySelectorAll('.validate-form').forEach(f=>{
  f.addEventListener('submit',e=>{
    const req=f.querySelectorAll('select[required], input[required]');
    for(const inp of req){
      if(!String(inp.value||'').trim()){
        e.preventDefault();
        Swal.fire({icon:'error',title:'Faltan datos',text:'Completá los campos obligatorios.'});
        inp.focus(); return;
      }
    }
  });
});

/* Modal editar turno */
const modal=document.getElementById('turnoModal'); const mClose=document.getElementById('m_close');
function openModal(){ if(typeof modal.showModal==='function') modal.showModal(); else modal.setAttribute('open',''); }
function closeModal(){ if(typeof modal.close==='function') modal.close(); else modal.removeAttribute('open'); }
mClose?.addEventListener('click',closeModal);
modal?.addEventListener('cancel',e=>{e.preventDefault();closeModal();});

document.querySelectorAll('.btn-edit').forEach(b=>{
  b.addEventListener('click',()=>{
    m_id.value      = b.dataset.id || '';
    m_auto.value    = b.dataset.auto || '';
    m_fecha.value   = b.dataset.fecha || '';
    m_hora.value    = b.dataset.hora || '';
    m_estado.value  = b.dataset.estado || '';
    m_motivo.value  = b.dataset.motivo || '';
    m_desc.value    = b.dataset.descripcion || '';
    openModal();
  });
});

/* ===== SweetAlert: toasts y confirmaciones ===== */
const toast = (title, icon='success') =>
  Swal.fire({toast:true, position:'top-end', icon, title, showConfirmButton:false, timer:3200, timerProgressBar:true});

const swalConfirm = (opts={}) =>
  Swal.fire(Object.assign({
    icon:'question',
    title:'¿Confirmás la acción?',
    showCancelButton:true,
    confirmButtonText:'Sí, continuar',
    cancelButtonText:'Cancelar',
    confirmButtonColor:'#3b82f6'
  }, opts));

/* Mostrar ok/error desde la query y limpiar la URL */
(function showFlashFromQuery(){
  const usp = new URLSearchParams(location.search);
  const ok   = usp.get('ok');
  const err  = usp.get('error');
  if (ok)  toast(ok,  'success');
  if (err) toast(err, 'error');
  if (ok || err) {
    usp.delete('ok'); usp.delete('error');
    const newUrl = location.pathname + (usp.toString() ? ('?'+usp.toString()) : '');
    history.replaceState({}, '', newUrl);
  }
})();

/* Confirmar Crear OR + Imprimir */
document.querySelectorAll('.form-imprimir').forEach(f=>{
  f.addEventListener('submit', (e)=>{
    e.preventDefault();
    swalConfirm({
      title: '¿Crear Orden y abrir impresión?',
      text: 'Se generará la OR y se abrirá la vista de impresión.',
    }).then(r=>{ if(r.isConfirmed) f.submit(); });
  });
});

/* Confirmar Cambiar estado */
document.querySelectorAll('.form-estado').forEach(f=>{
  f.addEventListener('submit', (e)=>{
    e.preventDefault();
    swalConfirm({
      title: '¿Aplicar nuevo estado?',
      confirmButtonText:'Aplicar'
    }).then(r=>{ if(r.isConfirmed) f.submit(); });
  });
});

/* Autocompletar “Cliente” al elegir vehículo */
(function(){
  const sel = document.getElementById('sel_vehiculo');
  const out = document.getElementById('inp_cliente');
  if (!sel || !out) return;
  const setCli = () => {
    const opt = sel.selectedOptions[0];
    out.value = opt ? (opt.dataset.cliente || '') : '';
  };
  sel.addEventListener('change', setCli);
  setCli();
})();

/* ====== Filtros de Historial (client-side) ====== */
(function(){
  const q   = document.getElementById('hist_q');
  const d1  = document.getElementById('hist_d1');
  const d2  = document.getElementById('hist_d2');
  const est = document.getElementById('hist_estado');
  const clr = document.getElementById('hist_clear');
  const rows= document.querySelectorAll('#hist_table tbody tr');
  const out = document.getElementById('hist_count');

  if(!rows.length) return;

  function apply(){
    const qv  = (q?.value || '').trim().toLowerCase();
    const f1  = (d1?.value || '0000-01-01');
    const f2  = (d2?.value || '9999-12-31');
    const ev  = (est?.value || '').toLowerCase();

    let visible = 0;
    rows.forEach(tr=>{
      const tf = tr.dataset.fecha || '';
      const te = (tr.dataset.estado || '').toLowerCase();
      const tk = (tr.dataset.busca || '').toLowerCase();
      const okDate   = !tf || (tf >= f1 && tf <= f2);
      const okEstado = !ev || te === ev;
      const okText   = !qv || tk.includes(qv);
      const show = okDate && okEstado && okText;
      tr.style.display = show ? '' : 'none';
      if(show) visible++;
    });
    if(out) out.textContent = String(visible);
  }

  [q,d1,d2,est].forEach(el=> el && el.addEventListener('input', apply));
  clr?.addEventListener('click', ()=>{ if(q) q.value=''; if(d1) d1.value=''; if(d2) d2.value=''; if(est) est.value=''; apply(); });
  apply();
})();

/* Abrir tab por query (?tab=turnos|agenda|home|historial); default: agenda */
(function(){
  const q=new URLSearchParams(location.search);
  const t=q.get('tab')||'agenda';
  const b=document.querySelector(`.nav button[data-tab="${t}"]`) || document.querySelector('.nav button');
  b?.click();
})();

/* Reimpresión con confirmación */
document.querySelectorAll('.btn-reprint').forEach(a => {
  a.addEventListener('click', (e) => {
    e.preventDefault();
    const url = a.getAttribute('href');
    Swal.fire({
      icon: 'question',
      title: '¿Reimprimir la orden?',
      text: 'Se abrirá la OR en una nueva pestaña lista para imprimir.',
      showCancelButton: true,
      confirmButtonText: 'Sí, reimprimir',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#3b82f6'
    }).then(r => {
      if (r.isConfirmed) window.open(url, '_blank', 'noopener');
    });
  });
});
(function(){
  const list = document.getElementById('tel_list');
  const add  = document.getElementById('btnAddTel');
  if(!list || !add) return;

  const makeItem = (val='')=>{
    const wrap = document.createElement('div');
    wrap.className = 'tel-item';
    wrap.style.cssText = 'display:flex;gap:8px;align-items:center';

    const inp = document.createElement('input');
    inp.name  = 'telefonos[]';
    inp.placeholder = '+54 9 11 2345 6789';
    inp.value = val;
    inp.style.cssText = 'flex:1;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn ghost btn-del-tel';
    btn.textContent = 'Quitar';
    btn.addEventListener('click',()=> wrap.remove());

    wrap.appendChild(inp); wrap.appendChild(btn);
    return wrap;
  };

  add.addEventListener('click', ()=> list.appendChild(makeItem('')));
  list.querySelectorAll('.btn-del-tel').forEach(b=> b.addEventListener('click', e=> e.currentTarget.closest('.tel-item').remove()));
})();


</script>
</body>
</html>
