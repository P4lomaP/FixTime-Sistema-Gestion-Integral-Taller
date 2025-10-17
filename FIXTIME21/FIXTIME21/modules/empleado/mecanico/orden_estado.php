<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 3); // .../FIXTIME21
require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';

Sesion::requiereLogin();
$app  = require $ROOT . '/config/app.php';
$base = rtrim($app['base_url'], '/');

$wantsJson = (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'],'json')!==false)
          || ((string)($_POST['ajax'] ?? '') === '1');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new RuntimeException('Método inválido');
  }

  $ordenId = (int)($_POST['orden_id'] ?? 0);
  $turnoId = (int)($_POST['turno_id'] ?? 0);
  $estado  = mb_strtolower(trim((string)($_POST['estado'] ?? 'terminado')), 'UTF-8');

  if (!$ordenId || !$turnoId) throw new InvalidArgumentException('Faltan IDs');
  if ($estado !== 'terminado') throw new InvalidArgumentException('Estado no soportado');

  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // === IDs de "Terminado" en ambas tablas de estados ===
  $st = $db->query("SELECT id FROM Estados_Turnos WHERE LOWER(descripcion) LIKE 'terminado%' OR LOWER(descripcion)='finalizado' LIMIT 1");
  $estadoTurnoId = (int)($st->fetchColumn() ?: 0);
  if ($estadoTurnoId <= 0) throw new RuntimeException("No encontré 'Terminado' en Estados_Turnos");

  $st = $db->query("SELECT id FROM Estados_Ordenes WHERE LOWER(descripcion) LIKE 'terminado%' OR LOWER(descripcion)='finalizado' LIMIT 1");
  $estadoOrdenId = (int)($st->fetchColumn() ?: 0);
  // Si no existe Estados_Ordenes o no hay "Terminado", igual seguimos (solo se actualizará Turnos)
  if ($estadoOrdenId <= 0) $estadoOrdenId = null;

  // === Verificar que la OR pertenece al Turno ===
  // Detectar nombre de FK a Turno en la OR
  $colTurnoFK = null;
  foreach (['Turno_id','turno_id','id_turno'] as $c) {
    $chk = $db->prepare("SHOW COLUMNS FROM Ordenes_Reparaciones LIKE ?");
    $chk->execute([$c]);
    if ($chk->fetch()) { $colTurnoFK = $c; break; }
  }
  if (!$colTurnoFK) $colTurnoFK = 'Turno_id';

  $st = $db->prepare("SELECT `$colTurnoFK` FROM Ordenes_Reparaciones WHERE id = ?");
  $st->execute([$ordenId]);
  $turnoDeOR = (int)($st->fetchColumn() ?: 0);
  if ($turnoDeOR !== $turnoId) {
    if ($turnoDeOR > 0) { $turnoId = $turnoDeOR; }
    else throw new RuntimeException('La orden no está asociada al turno indicado');
  }

  $db->beginTransaction();

  // 1) Actualizar estado del Turno (se ve en el panel/listado)
  $st = $db->prepare("UPDATE Turnos SET Estado_Turno_id=? WHERE id=?");
  $st->execute([$estadoTurnoId, $turnoId]);

  // 2) Actualizar estado de la OR (se ve en la impresión)
  $cols = $db->query("SHOW COLUMNS FROM Ordenes_Reparaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
  $sets = [];
  $params = [':oid' => $ordenId];

  if ($estadoOrdenId && in_array('EstadoOrdenReparacion_id', $cols, true)) {
    $sets[] = "EstadoOrdenReparacion_id = :eor";
    $params[':eor'] = $estadoOrdenId;
  }
  if (in_array('fecha_fin', $cols, true)) {
    $sets[] = "fecha_fin = NOW()";
  }

  if ($sets) {
    $sql = "UPDATE Ordenes_Reparaciones SET ".implode(', ', $sets)." WHERE id = :oid";
    $db->prepare($sql)->execute($params);
  }

  $db->commit();

  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => true,
      'orden_id' => $ordenId,
      'turno_id' => $turnoId,
      'estado' => 'terminado',
      'estado_turno_id' => $estadoTurnoId,
      'estado_orden_id' => $estadoOrdenId
    ]);
  } else {
    header('Location: '.$base.'/modules/empleado/mecanico/?tab=ordenes&ok='.urlencode('Orden marcada como terminada'));
  }

} catch (Throwable $e) {
  if ($wantsJson) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  } else {
    header('Location: '.$base.'/modules/empleado/mecanico/?tab=ordenes&error='.urlencode($e->getMessage()));
  }
}
