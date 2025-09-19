<?php
declare(strict_types=1);
require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::requiereLogin();
$app  = require __DIR__ . '/../../config/app.php';
$base = rtrim($app['base_url'], '/');

require_once __DIR__ . '/../../clases/AdministradorRepositorio.php';
$repoA = new AdministradorRepositorio();
if (!$repoA->esAdmin((int)($_SESSION['uid'] ?? 0))) { header('Location: '.$base.'/modules/login/'); exit; }

require_once __DIR__ . '/../../clases/EmpleadoRepositorio.php';
require_once __DIR__ . '/../../clases/Conexion.php';

$id = (int)($_GET['id'] ?? 0);
$db = Conexion::obtener();
$st = $db->prepare("SELECT e.id, e.Persona_id, e.Cargo_id, p.nombre, p.apellido, p.dni
                    FROM Empleados e JOIN Personas p ON p.id=e.Persona_id WHERE e.id=:id");
$st->execute([':id'=>$id]);
$emp = $st->fetch();
if(!$emp){ header("Location: $base/modules/admin/empleados.php?err=Empleado no encontrado"); exit; }
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Editar empleado</title>
<link rel="stylesheet" href="<?= $base ?>/publico/app.css">
</head>
<body>
<main class="envoltorio">
  <h1 class="titulo" style="text-align:left">Editar empleado</h1>
  <?php
$val = [
  'id' => $emp['id'] ?? null,
  'nombre' => $emp['nombre'] ?? '',
  'apellido' => $emp['apellido'] ?? '',
  'dni' => $emp['dni'] ?? '',
  'email' => $email ?? '',
  'telefono' => $tel ?? '',
  'pais' => '', 'provincia' => '', 'localidad' => '', 'barrio' => '',
  'calle' => '', 'altura' => '', 'piso' => '', 'departamento' => ''
];
try {
  require_once __DIR__ . '/../../clases/PersonaRepositorio.php';
  $pr = new PersonaRepositorio();
  $dom = $pr->obtenerDomicilioActual((int)$emp['Persona_id']);
  if ($dom) {
    $val['pais'] = $dom['pais'] ?? '';
    $val['provincia'] = $dom['provincia'] ?? '';
    $val['localidad'] = $dom['localidad'] ?? '';
    $val['barrio'] = $dom['barrio'] ?? '';
    $val['calle'] = $dom['calle'] ?? '';
    $val['altura'] = $dom['altura'] ?? '';
    $val['piso'] = $dom['piso'] ?? '';
    $val['departamento'] = $dom['departamento'] ?? '';
  }
} catch (Throwable $e) { }
$extraCampos = '<label>Cargo<select name="cargo_id" required>'
  . implode('', array_map(function($c){ return '<option value="'.(int)$c['id'].'">'.htmlspecialchars($c['descripcion']).'</option>'; }, iterator_to_array($db->query("SELECT id, descripcion FROM Cargos ORDER BY descripcion")) ))
  . '</select></label>';
$accion = $base . '/controladores/admin_empleado_actualizar.php';
$titulo = 'Editar empleado';
$submitLabel = 'Guardar cambios';
include __DIR__ . '/../../plantillas/form_persona_domicilio.php';
?>
</main>
</body>
</html>
