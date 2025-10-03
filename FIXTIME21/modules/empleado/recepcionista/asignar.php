<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 3);

require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';
require_once $ROOT . '/clases/RecepcionistaRepositorio.php';
require_once $ROOT . '/clases/TurnoRepositorio.php';
require_once $ROOT . '/clases/EmpleadoRepositorio.php';

Sesion::requiereLogin();

$app   = require $ROOT . '/config/app.php';
$base  = rtrim($app['base_url'], '/');

$PANEL = $base . '/modules/empleado/recepcionista/';
$IMPR  = $base . '/modules/empleado/recepcionista/imprimir.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $PANEL . '?error=' . urlencode('Método inválido.'));
  exit;
}

$turnoId    = (int)($_POST['turno_id'] ?? 0);
$mecanicoId = (int)($_POST['mecanico_id'] ?? 0);
$uid        = (int)($_SESSION['uid'] ?? 0); // id de usuario (NO de empleados)

if ($turnoId <= 0 || $mecanicoId <= 0) {
  header('Location: ' . $PANEL . '?error=' . urlencode('Datos incompletos.'));
  exit;
}

try {
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->beginTransaction();

  // Helper: verificar si existe una columna en la tabla dada
  $hasColumn = function(string $table, string $col) use ($db): bool {
    $q = $db->prepare("
      SELECT 1
        FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME   = ?
         AND COLUMN_NAME  = ?
       LIMIT 1
    ");
    $q->execute([$table, $col]);
    return (bool)$q->fetchColumn();
  };

  // Leer turno y bloquearlo
  $st = $db->prepare("SELECT id, Automovil_id, motivo, descripcion FROM turnos WHERE id=? FOR UPDATE");
  $st->execute([$turnoId]);
  $turno = $st->fetch(PDO::FETCH_ASSOC);
  if (!$turno) throw new RuntimeException('El turno no existe.');

  $autoId = (int)$turno['Automovil_id'];

  // Mapear recepcionista logueado -> empleados.id (si es posible). Si no, NULL.
  $recepEmpleadoId = null;
  try {
    $q = $db->prepare("
      SELECT e.id
        FROM empleados e
        JOIN recepcionistas r ON r.empleado_id = e.id
       WHERE r.usuario_id = ?
       LIMIT 1
    ");
    $q->execute([$uid]);
    $tmp = $q->fetchColumn();
    if ($tmp) $recepEmpleadoId = (int)$tmp;

    if ($recepEmpleadoId === null && $hasColumn('empleados','usuario_id')) {
      $q = $db->prepare("SELECT id FROM empleados WHERE usuario_id = ? LIMIT 1");
      $q->execute([$uid]);
      $tmp = $q->fetchColumn();
      if ($tmp) $recepEmpleadoId = (int)$tmp;
    }
  } catch (\Throwable $ign) {}

  // Estado OR "En proceso"
  $estadoOR = (int)($db->query("SELECT id FROM Estados_Ordenes WHERE descripcion='En proceso' LIMIT 1")->fetchColumn() ?: 0);
  if ($estadoOR <= 0) {
    $db->exec("INSERT INTO Estados_Ordenes (descripcion) VALUES ('En proceso')");
    $estadoOR = (int)$db->lastInsertId();
  }

  // Presupuesto base
  $descPres = trim((string)($turno['motivo'] ?? '')) ?: 'Trabajo recepcionado';
  $insP = $db->prepare("INSERT INTO presupuestos (descripcion, Detalle_Presupuesto_id) VALUES (?, NULL)");
  $insP->execute([$descPres]);
  $presupuestoId = (int)$db->lastInsertId();

  // ====== INSERT seguro en ordenes_reparaciones (columnas opcionales) ======
  $tablaOR = 'ordenes_reparaciones';

  $cols = [];
  $vals = [];

  // Obligatorios de cabecera
  $cols[] = 'Automovil_id';              $vals[] = $autoId;
  $cols[] = 'Empleado_id';               $vals[] = $mecanicoId;

  // recepcionista_id si existe la columna (NULL si no mapeamos)
  if ($hasColumn($tablaOR, 'recepcionista_id')) {
    $cols[] = 'recepcionista_id';        $vals[] = $recepEmpleadoId; // puede ir NULL sin romper FK
  }

  $cols[] = 'presupuesto_id';            $vals[] = $presupuestoId;
  $cols[] = 'EstadoOrdenReparacion_id';  $vals[] = $estadoOR;
  $cols[] = 'fecha_ingreso';             $vals[] = date('Y-m-d');

  $descOR = trim(($turno['motivo'] ?? '').' '.($turno['descripcion'] ?? ''));
  if ($descOR === '') $descOR = '—';
  $cols[] = 'descripcion';               $vals[] = $descOR;

  // (Opcional recomendado) vincular la OR con el turno si la columna existe
  if ($hasColumn($tablaOR, 'Turnos_id')) {
    $cols[] = 'Turnos_id';               $vals[] = $turnoId;
  }

  // Armar placeholders exactamente del mismo tamaño que $cols
  $placeholders = implode(',', array_fill(0, count($cols), '?'));
  $sql = "INSERT INTO {$tablaOR} (".implode(',', $cols).") VALUES ({$placeholders})";

  $insOR = $db->prepare($sql);
  $insOR->execute($vals);

  $ordenId = (int)$db->lastInsertId();

  // Marcar el turno como "asignado"
  $estadoAsignado = (int)($db->query("SELECT id FROM Estados_Turnos WHERE descripcion='asignado' LIMIT 1")->fetchColumn() ?: 0);
  if ($estadoAsignado <= 0) {
    $db->exec("INSERT INTO Estados_Turnos (descripcion) VALUES ('asignado')");
    $estadoAsignado = (int)$db->lastInsertId();
  }
  $upT = $db->prepare("UPDATE turnos SET Estado_Turno_id=? WHERE id=?");
  $upT->execute([$estadoAsignado, $turnoId]);

  $db->commit();

  // Redirigir a impresión
  header('Location: ' . $IMPR . '?orden_id=' . $ordenId);
  exit;

} catch (Throwable $e) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  $msg = $e->getMessage();
  header('Location: ' . $PANEL . '?tab=agenda&error=' . urlencode('No se pudo crear la OR: ' . $msg));
  exit;
}
