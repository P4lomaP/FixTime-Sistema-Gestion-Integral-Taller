<?php
declare(strict_types=1);
require_once __DIR__ . '/../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../clases/Sesion.php';

$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'] ?? '/', '/');
$formUrl = $base . '/modules/login/registro.php';

function redirect_clean(string $url, array $params = []): void {
  $qs = $params ? ('?' . http_build_query($params)) : '';
  header('Location: ' . $url . $qs); exit;
}
function p(string ...$keys): string {
  foreach ($keys as $k) if (isset($_POST[$k])) return trim((string)$_POST[$k]);
  return '';
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') redirect_clean($formUrl);

// === NUEVO: tipo de registro
$tipoRegistro = p('tipo_registro'); // solo_personal | personal_y_empresarial

$nombre  = p('nombre'); $apellido = p('apellido'); $dni_raw=p('dni');
$email   = p('email','correo','mail');
$pass    = p('contrasenia','contrasena','password','pass','clave');
$dni     = preg_replace('/\D+/','',$dni_raw);
$pais    = p('pais'); $provincia=p('provincia'); $localidad=p('localidad'); $barrio=p('barrio');
$calle   = p('calle'); $altura=p('altura'); $piso=p('piso'); $dto=p('departamento');
$telefonosPost=$_POST['telefonos']??[]; if(!is_array($telefonosPost))$telefonosPost=[$telefonosPost];

// === NUEVO: datos empresa
$empresaRazon = p('empresa_razon_social');
$empresaCUIT  = p('empresa_cuit');
$empresaEmail = p('empresa_email');
$empresaTel   = p('empresa_tel');

// Validaciones mínimas (tus reglas)
if(!$nombre||!$apellido||!$dni||!$email||!$pass) redirect_clean($formUrl,['error'=>'Completá todos los campos obligatorios.']);
if(!preg_match('/^\d{7,8}$/',$dni)) redirect_clean($formUrl,['error'=>'DNI inválido']);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) redirect_clean($formUrl,['error'=>'Correo inválido']);
if(!preg_match('/(?=.*[A-Za-z])(?=.*\d).{6,}/',$pass)) redirect_clean($formUrl,['error'=>'Contraseña inválida']);
if(!$pais||!$provincia||!$localidad||!$barrio) redirect_clean($formUrl,['error'=>'Ubicación incompleta']);
if(!$calle||!$altura) redirect_clean($formUrl,['error'=>'Completá calle y altura']);

$telefonos=[];
foreach($telefonosPost as $t){
  $t=trim((string)$t); if($t==='')continue;
  $norm=preg_replace('/(?!^\+)\D+/','',$t);
  if($norm[0]==='+') $norm='+' . preg_replace('/\D+/','',substr($norm,1));
  else $norm=preg_replace('/\D+/','',$norm);
  if($norm==='')continue;
  $digits=ltrim($norm,'+');
  if(strlen($digits)<7||strlen($digits)>20) redirect_clean($formUrl,['error'=>'Teléfono inválido']);
  $telefonos[]=$norm;
}
$telefonos=array_values(array_unique($telefonos));
if(!count($telefonos)) redirect_clean($formUrl,['error'=>'Ingresá al menos un teléfono']);

$repo=new PersonaRepositorio();
if($repo->existeEmail($email)) redirect_clean($formUrl,['error'=>'El email ya está registrado.']);
if($repo->existeDni($dni)) redirect_clean($formUrl,['error'=>'El DNI ya está registrado.']);

$hash=password_hash($pass,PASSWORD_DEFAULT);

try{
  // === Alta persona + domicilio + contactos (tal como ya hacías)
  $paisId=$repo->getOrCreatePais($pais);
  $provId=$repo->getOrCreateProvincia($provincia,$paisId);
  $locId=$repo->getOrCreateLocalidad($localidad,$provId);
  $barId=$repo->getOrCreateBarrio($barrio,$locId);

  $personaId=$repo->crearPersona($nombre,$apellido,$dni,$hash);
  $domId=$repo->crearDomicilio($barId,$calle,$altura,$piso?:null,$dto?:null);
  $repo->vincularPersonaDomicilio($personaId,$domId);
  $repo->guardarEmail($personaId,$email);
  $repo->guardarTelefonos($personaId,$telefonos);
  $repo->marcarComoCliente($personaId);

  // Bandera solo en sesión para mostrar sección Empresa en el panel
  Sesion::iniciar();
  // Tras crear el usuario y hacer Sesion::autenticar($persona):
  $_SESSION['es_empresarial'] = isset($_POST['es_empresarial']) && (int)$_POST['es_empresarial'] === 1 ? 1 : 0;

  }catch(Throwable $e){
  error_log('ERROR REGISTRO:'.$e->getMessage());
  $msg=(stripos($e->getMessage(),'uq_tipo_valor')!==false)?'Email o teléfono ya registrado.':'Error inesperado.';
  redirect_clean($formUrl,['error'=>$msg]);
}

redirect_clean($formUrl,['ok'=>'1']);
