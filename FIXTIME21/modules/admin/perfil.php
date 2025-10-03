<?php
declare(strict_types=1);

require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::requiereLogin();

$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();
$uid   = (int)($_SESSION['uid'] ?? 0);
if (!$uid || !$repoA->esAdmin($uid)) {
  header('Location: ' . $base . '/modules/login/');
  exit;
}

require_once __DIR__ . '/../../clases/Conexion.php';
require_once __DIR__ . '/../../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../../clases/DomicilioRepositorio.php';

$personas   = new PersonaRepositorio();
$domicilios = new DomicilioRepositorio();

/* ===== Helpers ===== */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
function db(){ return Conexion::obtener(); }
function callFirst(object $repo, array $candidates, array $args = [], &$used = null) {
  foreach ($candidates as $m) if (method_exists($repo, $m)) { $used=$m; return $repo->$m(...$args); }
  $used = null; return null;
}

/* ===== Duplicados ===== */
function emailOcupadoPorOtro(string $email, int $personaId): bool {
  if ($email==='') return false;
  $pdo = db();
  $tipoId = $pdo->query("SELECT id FROM Tipos_Contactos WHERE descripcion='Email' LIMIT 1")->fetchColumn();
  if (!$tipoId) return false;
  $st = $pdo->prepare("SELECT Persona_id FROM Contacto_Persona WHERE Tipo_Contacto_id=? AND valor=? AND Persona_id<>? LIMIT 1");
  $st->execute([(int)$tipoId,$email,$personaId]);
  return (bool)$st->fetchColumn();
}
function dniOcupadoPorOtro(string $dni, int $personaId): bool {
  if ($dni==='') return false;
  $st = db()->prepare("SELECT id FROM Personas WHERE dni=? AND id<>? LIMIT 1");
  $st->execute([$dni,$personaId]);
  return (bool)$st->fetchColumn();
}

/* ===== Fetch perfil completo ===== */
function cargarPerfilCompleto(int $personaId, PersonaRepositorio $personas, DomicilioRepositorio $domicilios): array {
  // Persona
  $p = callFirst($personas, ['obtenerPorId','getById','findById','traerPorId','buscarPorId','obtener'], [$personaId]);
  if (!$p) {
    $st = db()->prepare("SELECT id,nombre,apellido,dni FROM Personas WHERE id=? LIMIT 1");
    $st->execute([$personaId]);
    $p = $st->fetch(PDO::FETCH_ASSOC) ?: ['id'=>$personaId,'nombre'=>'','apellido'=>'','dni'=>''];
  }

  // Contactos
  $email=''; $telefonos=[];
  $contactos = callFirst($personas, ['obtenerContactos','getContactos','listarContactos','contactosDe'], [$personaId]);
  if (is_array($contactos)) {
    foreach ($contactos as $row) {
      $tipo = $row['tipo'] ?? $row['descripcion'] ?? $row['Tipo_Contacto'] ?? '';
      $val  = trim((string)($row['valor'] ?? ''));
      if ($tipo==='Email'    && $val!=='') $email=$val;
      if ($tipo==='Teléfono' && $val!=='') $telefonos[]=$val;
    }
    if (isset($contactos['Email']) && $contactos['Email']) $email=(string)$contactos['Email'];
    if (isset($contactos['Teléfono'])) {
      $tel=$contactos['Teléfono'];
      if (is_string($tel) && $tel!=='') $telefonos[]=$tel;
      if (is_array($tel)) foreach($tel as $t) if(trim((string)$t)!=='') $telefonos[]=trim((string)$t);
    }
  } else {
    $st = db()->prepare("SELECT tc.descripcion AS tipo, cp.valor
                           FROM Contacto_Persona cp
                           JOIN Tipos_Contactos tc ON tc.id=cp.Tipo_Contacto_id
                          WHERE cp.Persona_id=?");
    $st->execute([$personaId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      if ($r['tipo']==='Email') $email=$r['valor'];
      if ($r['tipo']==='Teléfono') $telefonos[]=$r['valor'];
    }
  }

  // Domicilio
  $d = callFirst($domicilios, ['obtenerPorPersonaId','obtenerPorPersona','getByPersonaId','getDePersona','traerPorPersonaId'], [$personaId]);
  $dom = ['pais'=>'','provincia'=>'','localidad'=>'','barrio'=>'','calle'=>'','altura'=>'','piso'=>'','departamento'=>''];
  if (is_array($d) && !empty($d)) {
    $dom['pais']      = (string)($d['pais'] ?? $d['pais_desc'] ?? '');
    $dom['provincia'] = (string)($d['provincia'] ?? $d['provincia_desc'] ?? '');
    $dom['localidad'] = (string)($d['localidad'] ?? $d['localidad_desc'] ?? '');
    $dom['barrio']    = (string)($d['barrio'] ?? $d['barrio_desc'] ?? '');
    $dom['calle']     = (string)($d['calle'] ?? '');
    $dom['altura']    = (string)($d['altura'] ?? '');
    $dom['piso']      = (string)($d['piso'] ?? '');
    $dom['departamento'] = (string)($d['departamento'] ?? '');
  } else {
    $sql = "SELECT d.calle,d.altura,d.piso,d.departamento,
                   b.descripcion AS barrio, l.descripcion AS localidad,
                   pr.descripcion AS provincia, pa.descripcion AS pais
              FROM Personas_Domicilios pd
              JOIN Domicilios d   ON d.id=pd.Domicilio_id
              LEFT JOIN Barrios b ON b.id=d.Barrio_id
              LEFT JOIN Localidades l ON l.id=b.Localidad_id
              LEFT JOIN Provincias pr ON pr.id=l.Provincia_id
              LEFT JOIN Paises pa ON pa.id=pr.Pais_id
             WHERE pd.Persona_id=? LIMIT 1";
    $st=db()->prepare($sql); $st->execute([$personaId]);
    if ($r=$st->fetch(PDO::FETCH_ASSOC)) {
      $dom = array_merge($dom, [
        'pais'=>(string)($r['pais'] ?? ''), 'provincia'=>(string)($r['provincia'] ?? ''),
        'localidad'=>(string)($r['localidad'] ?? ''), 'barrio'=>(string)($r['barrio'] ?? ''),
        'calle'=>(string)($r['calle'] ?? ''), 'altura'=>(string)($r['altura'] ?? ''),
        'piso'=>(string)($r['piso'] ?? ''), 'departamento'=>(string)($r['departamento'] ?? '')
      ]);
    }
  }

  return [
    'persona_id'=>(int)($p['id'] ?? $personaId),
    'nombre'=>(string)($p['nombre'] ?? ''),
    'apellido'=>(string)($p['apellido'] ?? ''),
    'dni'=>(string)($p['dni'] ?? ''),
    'email'=>$email,
    'telefonos'=>$telefonos,
    ...$dom,
  ];
}

/* ===== Guardar perfil ===== */
function guardarPerfilCompleto(array $in, PersonaRepositorio $personas, DomicilioRepositorio $domicilios): void {
  // Persona
  $did = callFirst($personas, ['actualizar','update','guardar','save'], [(int)$in['persona_id'], [
    'nombre'=>$in['nombre'],'apellido'=>$in['apellido'],'dni'=>$in['dni'],
  ]]);
  if ($did === null) {
    $st = db()->prepare("UPDATE Personas SET nombre=?, apellido=?, dni=? WHERE id=? LIMIT 1");
    $st->execute([$in['nombre'],$in['apellido'],$in['dni'],(int)$in['persona_id']]);
  }

  // Email único
  $haveUpsert = method_exists($personas,'upsertContacto') || method_exists($personas,'upsertContactoPorTipo');
  if ($haveUpsert) {
    if (method_exists($personas,'upsertContacto')) $personas->upsertContacto((int)$in['persona_id'],'Email',$in['email'] ?: null);
    else $personas->upsertContactoPorTipo((int)$in['persona_id'],'Email',$in['email'] ?: null);
  } else {
    $db = db();
    $tipoId = $db->query("SELECT id FROM Tipos_Contactos WHERE descripcion='Email' LIMIT 1")->fetchColumn();
    if ($tipoId) {
      $st = $db->prepare("SELECT id FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=? LIMIT 1");
      $st->execute([(int)$in['persona_id'],(int)$tipoId]); $id=$st->fetchColumn();
      $val = $in['email'] ?: null;
      if ($val===null) { if($id) $db->prepare("DELETE FROM Contacto_Persona WHERE id=?")->execute([$id]); }
      else { if($id) $db->prepare("UPDATE Contacto_Persona SET valor=? WHERE id=?")->execute([$val,$id]);
             else $db->prepare("INSERT INTO Contacto_Persona (Persona_id,Tipo_Contacto_id,valor) VALUES (?,?,?)")->execute([(int)$in['persona_id'],(int)$tipoId,$val]); }
    }
  }

  // Teléfonos (recrear)
  $db = db();
  $telTipo = $db->query("SELECT id FROM Tipos_Contactos WHERE descripcion='Teléfono' LIMIT 1")->fetchColumn();
  if ($telTipo) {
    $db->prepare("DELETE FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=?")->execute([(int)$in['persona_id'],(int)$telTipo]);
    $ins = $db->prepare("INSERT INTO Contacto_Persona (Persona_id,Tipo_Contacto_id,valor) VALUES (?,?,?)");
    foreach ($in['telefonos'] as $t) { $t=trim($t); if($t!=='') $ins->execute([(int)$in['persona_id'],(int)$telTipo,$t]); }
  }

  // Domicilio (repo o fallback)
  $okDom = callFirst($domicilios, ['upsertDomicilioPersona','guardarParaPersona','guardarDePersona','saveForPersona'],
    [(int)$in['persona_id'],[
      'pais'=>$in['pais'],'provincia'=>$in['provincia'],'localidad'=>$in['localidad'],'barrio'=>$in['barrio'],
      'calle'=>$in['calle'],'altura'=>$in['altura'],'piso'=>$in['piso'],'departamento'=>$in['departamento'],
    ]]
  );
  if ($okDom===null) {
    $db->beginTransaction();
    try{
      $scalar=function($sql,$p=[] ) use($db){$st=$db->prepare($sql);$st->execute($p);return $st->fetchColumn();};
      $exec  =function($sql,$p=[] ) use($db){$st=$db->prepare($sql);$st->execute($p);};

      $paisId = $in['pais']!=='' ? ($scalar("SELECT id FROM Paises WHERE descripcion=? LIMIT 1",[$in['pais']]) ?: ($exec("INSERT INTO Paises(descripcion) VALUES (?)",[$in['pais']]) ?? $db->lastInsertId())) : null;
      if (!is_numeric($paisId) && $in['pais']!=='') $paisId = (int)$db->lastInsertId();

      $provId = $in['provincia']!=='' ? ($scalar("SELECT id FROM Provincias WHERE descripcion=? AND Pais_id <=> ? LIMIT 1",[$in['provincia'],$paisId]) ?: ($exec("INSERT INTO Provincias(descripcion,Pais_id) VALUES (?,?)",[$in['provincia'],$paisId]) ?? $db->lastInsertId())) : null;
      if (!is_numeric($provId) && $in['provincia']!=='') $provId = (int)$db->lastInsertId();

      $locId  = $in['localidad']!=='' ? ($scalar("SELECT id FROM Localidades WHERE descripcion=? AND Provincia_id <=> ? LIMIT 1",[$in['localidad'],$provId]) ?: ($exec("INSERT INTO Localidades(descripcion,Provincia_id) VALUES (?,?)",[$in['localidad'],$provId]) ?? $db->lastInsertId())) : null;
      if (!is_numeric($locId) && $in['localidad']!=='') $locId = (int)$db->lastInsertId();

      $barId  = $in['barrio']!=='' ? ($scalar("SELECT id FROM Barrios WHERE descripcion=? AND Localidad_id <=> ? LIMIT 1",[$in['barrio'],$locId]) ?: ($exec("INSERT INTO Barrios(descripcion,Localidad_id) VALUES (?,?)",[$in['barrio'],$locId]) ?? $db->lastInsertId())) : null;
      if (!is_numeric($barId) && $in['barrio']!=='') $barId = (int)$db->lastInsertId();

      $st=$db->prepare("SELECT id FROM Domicilios WHERE Barrio_id <=> ? AND calle <=> ? AND altura <=> ? AND piso <=> ? AND departamento <=> ? LIMIT 1");
      $st->execute([$barId,$in['calle']?:null,$in['altura']?:null,$in['piso']?:null,$in['departamento']?:null]);
      $domId=$st->fetchColumn();
      if(!$domId){
        $db->prepare("INSERT INTO Domicilios (Barrio_id,calle,altura,piso,departamento) VALUES (?,?,?,?,?)")
           ->execute([$barId,$in['calle']?:null,$in['altura']?:null,$in['piso']?:null,$in['departamento']?:null]);
        $domId=(int)$db->lastInsertId();
      }

      $st=$db->prepare("SELECT id FROM Personas_Domicilios WHERE Persona_id=? LIMIT 1");
      $st->execute([(int)$in['persona_id']]); $pdId=$st->fetchColumn();
      if ($pdId) $db->prepare("UPDATE Personas_Domicilios SET Domicilio_id=? WHERE id=? LIMIT 1")->execute([$domId,$pdId]);
      else $db->prepare("INSERT INTO Personas_Domicilios (Persona_id,Domicilio_id) VALUES (?,?)")->execute([(int)$in['persona_id'],$domId]);

      $db->commit();
    }catch(Throwable $e){ $db->rollBack(); throw $e; }
  }
}

/* ===== POST ===== */
$ok=$_GET['ok']??''; $err=$_GET['err']??'';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $telefonos = $_POST['telefono'] ?? [];
  if(!is_array($telefonos)) $telefonos = [$telefonos];
  $telefonos = array_values(array_unique(array_filter(array_map('trim',$telefonos),fn($t)=>$t!=='')));

  $payload=[
    'persona_id'=>$uid,
    'nombre'=>trim((string)($_POST['nombre']??'')),
    'apellido'=>trim((string)($_POST['apellido']??'')),
    'dni'=>preg_replace('/\D+/','',(string)($_POST['dni']??'')),
    'email'=>trim((string)($_POST['email']??'')),
    'telefonos'=>$telefonos,
    'pais'=>trim((string)($_POST['pais']??'')),
    'provincia'=>trim((string)($_POST['provincia']??'')),
    'localidad'=>trim((string)($_POST['localidad']??'')),
    'barrio'=>trim((string)($_POST['barrio']??'')),
    'calle'=>trim((string)($_POST['calle']??'')),
    'altura'=>trim((string)($_POST['altura']??'')),
    'piso'=>trim((string)($_POST['piso']??'')),
    'departamento'=>trim((string)($_POST['departamento']??'')),
  ];

  if($payload['nombre']==='' || $payload['apellido']==='' || $payload['dni']===''){
    $err='Completá nombre, apellido y DNI.';
  } elseif ($payload['email']!=='' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
    $err='El email no es válido.';
  } elseif (dniOcupadoPorOtro($payload['dni'], $uid)) {
    $err='El DNI ingresado ya está asociado a otra persona.';
  } elseif ($payload['email']!=='' && emailOcupadoPorOtro($payload['email'], $uid)) {
    $err='Ese email ya está en uso por otra persona.';
  } else {
    try{
      guardarPerfilCompleto($payload, $personas, $domicilios);
      $_SESSION['nombre']=$payload['nombre'];
      $_SESSION['apellido']=$payload['apellido'];
      header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?ok=' . urlencode('Cambios guardados.'));
      exit;
    }catch(Throwable $e){
      $err='No se pudieron guardar los cambios: '.$e->getMessage();
    }
  }
}

/* ===== GET ===== */
try{ $perfil=cargarPerfilCompleto($uid,$personas,$domicilios); }
catch(Throwable $e){
  $perfil=['persona_id'=>$uid,'nombre'=>'','apellido'=>'','dni'=>'','email'=>'','telefonos'=>[],
    'pais'=>'','provincia'=>'','localidad'=>'','barrio'=>'','calle'=>'','altura'=>'','piso'=>'','departamento'=>''];
  $err = $err ?: 'No se pudieron leer los datos: '.$e->getMessage();
}

$nombreSesion=$_SESSION['nombre']??'Admin';
$apellidoSesion=$_SESSION['apellido']??'';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fixtime — Panel de Administrador</title>
<link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
<style>
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
.hero{display:flex;align-items:center;gap:16px;background:linear-gradient(135deg, rgba(59,130,246,.22), rgba(37,99,235,.18));border:1px solid rgba(59,130,246,.40);border-radius:var(--radius);padding:18px;box-shadow:0 14px 32px var(--ring);margin-bottom:16px}
.hero .avatar{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;background:radial-gradient(24px 16px at 30% 20%, rgba(255,255,255,.25), transparent 40%),linear-gradient(135deg,var(--brand),var(--brand-2))}
.hero .avatar img{width:38px;height:38px;object-fit:contain;filter:drop-shadow(0 0 14px rgba(255,255,255,.6))}
.greet{font-weight:800;font-size:22px}
.hint{color:rgba(233,240,255,0.9)}
.card{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.03));border:1px solid rgba(157,176,208,.16);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
@media(max-width:700px){ .row{grid-template-columns:1fr} }
label{font-size:12px;color:var(--muted)}
input{width:100%;background:var(--panel-2);border:1px solid rgba(157,176,208,.2);color:var(--text);border-radius:12px;padding:12px 14px}
.btn{cursor:pointer;border-radius:12px;padding:12px 16px;font-weight:700;background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#0b1220;border:0;transition:.2s ease}
.btn:hover{opacity:.9}
.btn.ghost{background:transparent;color:var(--text);border:1px solid rgba(157,176,208,.30);box-shadow:none}
.btn.ghost:hover{background:rgba(255,255,255,.05)}

/* ===== Toasts sólidos estilo cliente ===== */
.toast-container{
  position:fixed; top:20px; right:20px;
  display:flex; flex-direction:column; gap:10px;
  z-index:2000;
}
.toast{
  display:flex; align-items:center; gap:12px;
  min-width:260px; max-width:380px;
  background:#fff;
  color:#1f2937; /* texto gris oscuro */
  border-radius:12px;
  padding:14px 16px;
  box-shadow:0 6px 16px rgba(0,0,0,.12);
  font-family:Inter,system-ui,Arial;
  position:relative; overflow:hidden;
  animation:slideIn .35s ease, fadeOut .5s ease forwards;
  animation-delay:0s, var(--dur,4s);
}
.toast .icon{
  width:28px; height:28px; border-radius:999px;
  display:grid; place-items:center; flex-shrink:0;
  background:#e6f8ee; /* success bg */
  border:1px solid rgba(15,23,42,.06);
  color:#16a34a;      /* verde por defecto */
}
.toast.error .icon{ background:#fde7e7; color:#ef4444; }
.toast .msg{ flex:1; font-size:14px; font-weight:600; color:#1f2937; }

/* Barra inferior con color de estado */
.toast .bar{
  position:absolute; left:0; right:0; bottom:0;
  height:4px; border-radius:0 0 12px 12px; overflow:hidden;
  background:#e5e7eb; /* track gris claro */
}
.toast.success{ --bar-color:#16a34a; }
.toast.error{   --bar-color:#ef4444; }
.toast .bar::after{
  content:""; display:block; height:100%; width:0%;
  background:var(--bar-color);
  animation:progress var(--dur,4s) linear forwards;
}

/* ===== Tema CLARO ===== */
:root.theme-light{
  --bg:#f6f8ff;
  --panel:#ffffff;
  --panel-2:#f2f6ff;
  --card:#ffffff;
  --muted:#475569;
  --text:#0b1220;
  --brand:#3b82f6;
  --brand-2:#2563eb;
  --ring:rgba(59,130,246,.20);
  --shadow:0 12px 40px rgba(2,6,23,.08);
}

/* Ajustes de contraste sobre componentes en claro */
:root.theme-light body{
  background:
    radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.10), transparent 70%),
    radial-gradient(900px 480px at 10% 110%, rgba(37,99,235,.08), transparent 60%),
    var(--bg);
  color:var(--text);
}

:root.theme-light .sidebar{
  background:linear-gradient(180deg,var(--panel),var(--bg));
  border-right:1px solid rgba(2,6,23,.08);
}




:root.theme-light input{
  background:#ffffff;
  border:1px solid rgba(2,6,23,.12);
  color:var(--text);
}

:root.theme-light .card{
  background:linear-gradient(180deg,rgba(0,0,0,.02),rgba(0,0,0,.015));
  border-color:rgba(2,6,23,.10);
}

:root.theme-light .hero{
  background:linear-gradient(135deg, rgba(59,130,246,.12), rgba(37,99,235,.10));
  border-color:rgba(59,130,246,.25);
  box-shadow:0 10px 26px var(--ring);
}

:root.theme-light .theme-btn{
  background:#ffffff;
  color:var(--text);
  border:1px solid rgba(2,6,23,.12);
  box-shadow:0 4px 12px rgba(2,6,23,.06);
}

:root.theme-light .btn.ghost{
  color:var(--text);
  border-color:rgba(2,6,23,.12);
}
:root.theme-light .btn.ghost:hover{
  background:rgba(0,0,0,.03);
}

/* ===== Sombras en modo claro ===== */
:root.theme-light .card,
:root.theme-light .sidebar,
:root.theme-light .hero,
:root.theme-light input,
:root.theme-light .btn,
:root.theme-light .theme-btn {
  box-shadow: 0 4px 12px rgba(0,0,0,.08), 0 2px 6px rgba(0,0,0,.04);
}

/* Botones principales */
:root.theme-light .btn{
  background:linear-gradient(135deg,var(--brand),var(--brand-2));
  color:#fff;
  border:0;
  box-shadow:0 6px 16px rgba(0,0,0,.12);
}

/* Botones ghost */
:root.theme-light .btn.ghost{
  background:#fff;
  border:1px solid rgba(0,0,0,.08);
  color:var(--text);
  box-shadow:0 4px 10px rgba(0,0,0,.05);
}
:root.theme-light .btn.ghost:hover{
  background:rgba(0,0,0,.03);
}

/* Inputs con sombra sutil */
:root.theme-light input{
  background:#fff;
  border:1px solid rgba(0,0,0,.10);
  color:var(--text);
  box-shadow:inset 0 1px 2px rgba(0,0,0,.06);
}

/* Texto de subtítulo (hint) en modo claro */
:root.theme-light .hint {
  color: rgba(0, 0, 0, 0.75); /* gris oscuro legible */
}

/* Animaciones */
@keyframes slideIn{ from{transform:translateX(120%); opacity:0;} to{transform:translateX(0); opacity:1;} }
@keyframes fadeOut{ to{opacity:0; transform:translateX(120%);} }
@keyframes progress{ to{width:100%;} }
</style>

<style>
/* Match vehiculos: light mode cards look white with soft gray border and no blue outline */
.theme-light .nav a, .theme-light .nav button{background:#fff;border-color:rgba(15,23,42,.08)}
</style>
</head>
<body>

<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-badge"><img src="<?= $base ?>/publico/widoo.png" alt="Fixtime"></div>
      <div style="flex:1"><div class="brand-name">Fixtime</div><div class="brand-sub">Panel de Administrador</div></div>
      <button id="themeToggle" class="theme-btn" title="Cambiar tema">🌙</button>
    </div>
    <nav class="nav" id="nav">
      <a href="<?= $base ?>/modules/admin/">🏠 Inicio</a>
      <a href="<?= $base ?>/modules/admin/empleados.php">👥 Empleados</a>
      <a href="<?= $base ?>/modules/admin/calendario.php">🗓️ Calendario</a>
      <a href="<?= $base ?>/modules/admin/vehiculos.php">🚗 Listar vehículos</a>
      <a class="active" href="<?= $base ?>/modules/admin/perfil.php">👤 Mi perfil</a>
      <a href="<?= $base ?>/modules/selector/index.php" class="btn ghost topbar-salir">⬅️ Volver al selector</a>
      <a href="<?= $base ?>/modules/login/logout.php" class="btn ghost topbar-salir">Cerrar sesión</a>
    </nav>
  </aside>

  <main class="main">
    <div class="hero">
      <div class="avatar"><img src="<?= $base ?>/publico/widoo.png" alt=""></div>
      <div style="flex:1">
        <div class="greet">¡Hola, <?= h($nombreSesion.' '.$apellidoSesion) ?>!</div>
        <div class="hint">Gestioná empleados, calendario y vehículos desde un solo lugar.</div>
      </div>
    </div>

    <section class="card">
      <form method="post" class="row" id="perfilForm">
        <label>Nombre
          <input name="nombre" required value="<?= h($perfil['nombre'] ?? '') ?>">
        </label>
        <label>Apellido
          <input name="apellido" required value="<?= h($perfil['apellido'] ?? '') ?>">
        </label>
        <label>DNI
          <input name="dni" required inputmode="numeric" value="<?= h($perfil['dni'] ?? '') ?>">
        </label>
        <label>Email
          <input name="email" type="email" placeholder="Opcional" value="<?= h($perfil['email'] ?? '') ?>">
        </label>

        <div style="grid-column:1/-1">
          <div style="display:flex;align-items:center;justify-content:space-between;margin:6px 0 8px">
            <strong>Teléfonos</strong>
            <button type="button" class="btn ghost" id="addPhone">+ Agregar teléfono</button>
          </div>
          <div id="phonesWrap" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
            <?php $tels = $perfil['telefonos'] ?? []; if(!$tels) $tels=['']; foreach($tels as $t): ?>
              <div style="display:flex;gap:8px">
                <input name="telefono[]" placeholder="+54 9 11 2345 6789" value="<?= h($t) ?>" style="flex:1">
                <button type="button" class="btn ghost rmPhone">Quitar</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div style="grid-column:1/-1; height:8px"></div>

        <label>País
          <input name="pais" value="<?= h($perfil['pais'] ?? '') ?>">
        </label>
        <label>Provincia
          <input name="provincia" value="<?= h($perfil['provincia'] ?? '') ?>">
        </label>
        <label>Localidad
          <input name="localidad" value="<?= h($perfil['localidad'] ?? '') ?>">
        </label>
        <label>Barrio
          <input name="barrio" value="<?= h($perfil['barrio'] ?? '') ?>">
        </label>
        <label>Calle
          <input name="calle" value="<?= h($perfil['calle'] ?? '') ?>">
        </label>
        <label>Altura
          <input name="altura" value="<?= h($perfil['altura'] ?? '') ?>">
        </label>
        <label>Piso
          <input name="piso" value="<?= h($perfil['piso'] ?? '') ?>">
        </label>
        <label>Departamento
          <input name="departamento" value="<?= h($perfil['departamento'] ?? '') ?>">
        </label>

        <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
          <button class="btn" type="submit">Guardar cambios</button>
        </div>
      </form>
    </section>
  </main>
</div>

<!-- Toasts -->
<div class="toast-container" id="toastContainer"></div>

<script>
(function(){
  const root=document.documentElement, t=document.getElementById('themeToggle');
  const saved=localStorage.getItem('theme')||'dark';
  if(saved==='light') root.classList.add('theme-light');
  const setIcon=()=>{ t.textContent=root.classList.contains('theme-light')?'☀️':'🌙'; };
  setIcon();
  t.addEventListener('click',()=>{ root.classList.toggle('theme-light'); localStorage.setItem('theme',root.classList.contains('theme-light')?'light':'dark'); setIcon(); });

  // Teléfonos dinámicos
  const wrap=document.getElementById('phonesWrap');
  document.getElementById('addPhone').addEventListener('click', ()=>{
    const row=document.createElement('div');
    row.style.display='flex'; row.style.gap='8px';
    row.innerHTML = `<input name="telefono[]" placeholder="+54 9 11 2345 6789" style="flex:1">
                     <button type="button" class="btn ghost rmPhone">Quitar</button>`;
    wrap.appendChild(row);
  });
  wrap.addEventListener('click', (e)=>{
    if(e.target.classList.contains('rmPhone')){
      const row=e.target.closest('div');
      if (wrap.querySelectorAll('input[name="telefono[]"]').length>1) row.remove();
      else row.querySelector('input').value='';
    }
  });

  // Toast sólido estilo cliente
  function showToast(type, msg, dur=4000){
    const box=document.createElement("div");
    box.className="toast "+type;
    box.style.setProperty("--dur",dur+"ms");
    const icon = (type==="success")
      ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>'
      : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    box.innerHTML = `
      <div class="icon">${icon}</div>
      <div class="msg">${msg}</div>
      <div class="bar"></div>`;
    document.getElementById("toastContainer").appendChild(box);
    setTimeout(()=>box.remove(),dur+600);
  }

  // Mostrar si viene ?ok o ?err
  <?php if(!empty($ok)): ?>
    showToast("success", <?= json_encode($ok) ?>, 3800);
  <?php elseif(!empty($err)): ?>
    showToast("error",   <?= json_encode($err) ?>, 5200);
  <?php endif; ?>
})();
</script>
</body>
</html>
