<?php
declare(strict_types=1);
$ROOT = dirname(__DIR__, 3);

require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';

Sesion::requiereLogin();

$app   = require $ROOT . '/config/app.php';
$base  = rtrim($app['base_url'], '/');
$PANEL = $base . '/modules/empleado/recepcionista/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: '.$PANEL.'?error='.urlencode('Método inválido').'&tab=turnos'); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id<=0){
  header('Location: '.$PANEL.'?error='.urlencode('ID inválido').'&tab=turnos'); exit;
}

try{
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $st=$db->prepare("DELETE FROM Turnos WHERE id=?");
  $st->execute([$id]);

  header('Location: '.$PANEL.'?ok='.urlencode('Turno eliminado.').'&tab=turnos'); exit;

}catch(Throwable $e){
  header('Location: '.$PANEL.'?error='.urlencode('No se pudo eliminar: '.$e->getMessage()).'&tab=turnos'); exit;
}
