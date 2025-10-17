<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 3); // .../FIXTIME21
require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';

Sesion::requiereLogin();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

try {
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $oid = (int)($_GET['orden_id'] ?? 0);
  if (!$oid) { http_response_code(400); echo json_encode(['error'=>'orden_id requerido']); exit; }

  // detectar claves
  $cols = $db->query("SHOW COLUMNS FROM ordenes_reparaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
  $pk='id';
  try {
    $r=$db->query("SHOW KEYS FROM ordenes_reparaciones WHERE Key_name='PRIMARY'")->fetch(PDO::FETCH_ASSOC);
    if(!empty($r['Column_name'])) $pk=$r['Column_name'];
  } catch (Throwable $e) {}
  $colTurno=null; foreach(['Turnos_id','Turno_id','turno_id','id_turno'] as $c){ if(in_array($c,$cols,true)){$colTurno=$c; break;} }
  if(!$colTurno){ throw new RuntimeException('No se encontró la FK a Turnos en ordenes_reparaciones.'); }

  // extras si existen
  $extra = array_intersect($cols, ['km','tiempo_estimado','costo_estimado','prioridad']);
  $fields = $extra ? (', ' . implode(', ', array_map(fn($c)=>'orp.`'.$c.'`', $extra))) : '';

  $sql = "SELECT t.motivo, t.descripcion, t.Estado_Turno_id, et.descripcion AS estado $fields
          FROM ordenes_reparaciones orp
          LEFT JOIN Turnos t ON t.id = orp.`$colTurno`
          LEFT JOIN Estados_Turnos et ON et.id = t.Estado_Turno_id
          WHERE orp.`$pk` = :oid
          LIMIT 1";
  $st=$db->prepare($sql); $st->execute(['oid'=>$oid]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if(!$row){ http_response_code(404); echo json_encode(['error'=>'Orden no encontrada']); exit; }

  // observaciones (si existe tabla)
  $obs = [];
  try{
    $q = $db->prepare("SELECT o.texto, CONCAT(p.nombre,' ',p.apellido) AS autor, DATE_FORMAT(o.fecha,'%Y-%m-%d %H:%i') AS fecha
                       FROM Observaciones_OR o
                       LEFT JOIN Empleados e ON e.id=o.Empleado_id
                       LEFT JOIN Personas  p ON p.id=e.Persona_id
                       WHERE o.Orden_id=:oid ORDER BY o.fecha DESC");
    $q->execute(['oid'=>$oid]);
    $obs = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $obs = []; }

  echo json_encode(array_merge($row, ['observaciones'=>$obs]), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()]);
}
