<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 3); // .../FIXTIME21

require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';

Sesion::iniciar();

/**
 * Función auxiliar para encontrar el ID de Empleado a partir de un ID de sesión (uid).
 * Es una versión simplificada de la que está en index.php.
 */
function resolverEmpleadoIdDesdeUid(PDO $db, int $uid): int {
    // Primero, verificamos si el UID ya es un ID de Empleado válido
    $st = $db->prepare("SELECT id FROM Empleados WHERE id = ?");
    $st->execute([$uid]);
    if ($st->fetchColumn()) {
        return $uid;
    }

    // Si no, asumimos que el UID es de una Persona y buscamos su ID de Empleado
    $st = $db->prepare("SELECT id FROM Empleados WHERE Persona_id = ? LIMIT 1");
    $st->execute([$uid]);
    $empleadoId = $st->fetchColumn();

    // Si lo encontramos, lo devolvemos. Si no, devolvemos 0 (lo que causará un error controlado)
    return (int)$empleadoId;
}

try {
    // 1. OBTENER CONEXIÓN Y DATOS
    $db = Conexion::obtener();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $turnoId = (int)($_POST['turno_id'] ?? 0);
    $mecanicoId = (int)($_POST['mecanico_id'] ?? 0);
    $sessionUid = (int)($_SESSION['uid'] ?? 0);

    // === LA CORRECCIÓN CLAVE ESTÁ AQUÍ ===
    // Resolvemos el ID de la sesión para obtener el ID de empleado del recepcionista
    $recepcionistaId = resolverEmpleadoIdDesdeUid($db, $sessionUid);

    if ($turnoId === 0 || $mecanicoId === 0 || $recepcionistaId === 0) {
        throw new Exception('Faltan datos o no se pudo identificar al recepcionista como empleado.');
    }

    $db->beginTransaction();

    // 2. OBTENER INFORMACIÓN ADICIONAL DEL TURNO
    $stmt = $db->prepare("SELECT Automovil_id, motivo, descripcion FROM Turnos WHERE id = ?");
    $stmt->execute([$turnoId]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        throw new Exception('El turno especificado no existe.');
    }
    $automovilId = (int)$turno['Automovil_id'];
    $descripcionOrden = !empty($turno['motivo']) ? $turno['motivo'] : ($turno['descripcion'] ?? 'Sin descripción.');

    // 3. CREAR LA ORDEN DE REPARACIÓN
    $sql = "INSERT INTO Ordenes_Reparaciones 
                (Automovil_id, Empleado_id, recepcionista_id, Turnos_id, EstadoOrdenReparacion_id, fecha_ingreso, descripcion) 
            VALUES 
                (:automovil_id, :mecanico_id, :recepcionista_id, :turno_id, :estado_id, :fecha, :descripcion)";
    
    $stmt = $db->prepare($sql);
    
    $stmt->execute([
        ':automovil_id'     => $automovilId,
        ':mecanico_id'      => $mecanicoId,
        ':recepcionista_id' => $recepcionistaId,
        ':turno_id'         => $turnoId,
        ':estado_id'        => 1, // 'En proceso'
        ':fecha'            => date('Y-m-d H:i:s'),
        ':descripcion'      => $descripcionOrden
    ]);

    $orden_id = $db->lastInsertId();

    // 4. ACTUALIZAR EL ESTADO DEL TURNO A "ASIGNADO"
    $stmtTurno = $db->prepare("UPDATE Turnos SET Estado_Turno_id = ? WHERE id = ?");
    $stmtTurno->execute([2, $turnoId]); // 2 = 'asignado'

    $db->commit();

    // 5. DEVOLVER EL HTML DE LA ORDEN PARA EL MODAL
    $_GET['orden_id'] = $orden_id;

    ob_start();
    include 'imprimir.php';
    $html_para_imprimir = ob_get_clean();

    http_response_code(200);
    echo $html_para_imprimir;
    exit;

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    // Para depuración, puedes temporalmente reemplazar la línea de abajo por: echo $e->getMessage();
    error_log("Error en asignar.php: " . $e->getMessage());
    echo "Error al procesar la solicitud. Verifique los datos e intente de nuevo.";
    exit;
}