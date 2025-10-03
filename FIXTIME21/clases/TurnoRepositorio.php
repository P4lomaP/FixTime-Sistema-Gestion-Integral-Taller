<?php
require_once __DIR__ . '/Conexion.php';

class TurnoRepositorio
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtener();
    }

    /** Obtiene el id del estado (ej: 'Pendiente', 'Cancelado') */
    private function estadoId(string $nombre): int
    {
        $st = $this->db->prepare("SELECT id FROM Estados_Turnos WHERE descripcion = ? LIMIT 1");
        $st->execute([$nombre]);
        $id = (int)$st->fetchColumn();
        if (!$id) {
            throw new \RuntimeException('Estado no encontrado: ' . $nombre);
        }
        return $id;
    }

    /** Verifica que el vehículo pertenezca a la persona */
    private function validarAutoDePersona(int $personaId, int $automovilId): void
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM Vehiculos_Personas WHERE Persona_id = ? AND automoviles_id = ? LIMIT 1"
        );
        $st->execute([$personaId, $automovilId]);
        if (!$st->fetchColumn()) {
            throw new \RuntimeException('Vehículo inválido para este usuario.');
        }
    }

    /**
     * Crea una solicitud de turno (sin fecha ni hora).
     * Guarda motivo (obligatorio) y descripción (opcional).
     * Estado inicial = 'Pendiente'.
     */
    public function crearSolicitud(int $personaId, int $automovilId, string $motivo, ?string $descripcion = ''): void
    {
        $this->validarAutoDePersona($personaId, $automovilId);
        $estadoPendienteId = $this->estadoId('pendiente asignacion');

        $st = $this->db->prepare(
            "INSERT INTO Turnos (fecha_turno, hora_turno, Estado_Turno_id, Automovil_id, motivo, descripcion)
             VALUES (NULL, NULL, ?, ?, ?, ?)"
        );
        $st->execute([$estadoPendienteId, $automovilId, $motivo, $descripcion]);
    }

    /** Cancela un turno del cliente (pasa a 'Cancelado'). */
    public function cancelar(int $personaId, int $turnoId): void
    {
        // Validar pertenencia
        $st = $this->db->prepare(
            "SELECT 1
               FROM Turnos t
               JOIN Vehiculos_Personas vp ON vp.automoviles_id = t.Automovil_id
              WHERE t.id = ? AND vp.Persona_id = ?
              LIMIT 1"
        );
        $st->execute([$turnoId, $personaId]);
        if (!$st->fetchColumn()) {
            throw new \RuntimeException('Turno inválido para este usuario.');
        }

        $estadoCanceladoId = $this->estadoId('Cancelado');
        $up = $this->db->prepare("UPDATE Turnos SET Estado_Turno_id = ? WHERE id = ?");
        $up->execute([$estadoCanceladoId, $turnoId]);
    }

        public function reasignar(int $personaId, int $turnoId): void
    {
        // Validar que el turno pertenezca a este usuario
        $st = $this->db->prepare(
            "SELECT 1
            FROM Turnos t
            JOIN Vehiculos_Personas vp ON vp.automoviles_id = t.Automovil_id
            WHERE t.id = ? AND vp.Persona_id = ?
            LIMIT 1"
        );
        $st->execute([$turnoId, $personaId]);

        if (!$st->fetchColumn()) {
            throw new \RuntimeException('Turno inválido para este usuario.');
        }

        // Estado: pendiente reasignación
        $estadoPendienteReasigId = $this->estadoId('pendiente reasignacion');

        $up = $this->db->prepare(
            "UPDATE Turnos 
                SET Estado_Turno_id = ? 
            WHERE id = ?"
        );
        $up->execute([$estadoPendienteReasigId, $turnoId]);
    }
    /** Lista de turnos del cliente (muestra motivo/descripcion, no fecha/hora) */
    public function listarPorPersona(int $personaId): array
{
    $sql = "
        SELECT 
            t.id,
            t.fecha_turno,
            t.hora_turno,
            et.descripcion AS estado,
            t.Automovil_id AS auto_id,
            t.motivo,
            t.descripcion,
            ma.descripcion AS marca,
            mo.descripcion AS modelo,
            a.anio,
            a.color
        FROM Turnos t
        JOIN Estados_Turnos et       ON et.id = t.Estado_Turno_id
        JOIN Automoviles a           ON a.id = t.Automovil_id
        JOIN Modelos_Automoviles mo  ON mo.id = a.Modelo_Automovil_id
        JOIN Marcas_Automoviles ma   ON ma.id = mo.Marca_Automvil_id
        JOIN Vehiculos_Personas vp   ON vp.automoviles_id = a.id
        WHERE vp.Persona_id = ?
        ORDER BY t.id DESC
    ";

    $pdo = Conexion::obtener();
    $st = $pdo->prepare($sql);
    $st->execute([$personaId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


    /** Historial de servicios del cliente (órdenes) */
    public function historialServicios(int $personaId): array
    {
        $sql = "
            SELECT 
                o.fecha_ingreso,
                o.descripcion AS trabajo,
                eo.descripcion AS estado,
                ma.descripcion AS marca,
                mo.descripcion AS modelo,
                a.anio
            FROM Ordenes_Reparaciones o
            JOIN Estados_Ordenes eo ON eo.id = o.EstadoOrdenReparacion_id
            JOIN Automoviles a      ON a.id = o.Automovil_id
            JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
            JOIN Marcas_Automoviles ma  ON ma.id = mo.Marca_Automvil_id
            JOIN Vehiculos_Personas vp  ON vp.automoviles_id = a.id
            WHERE vp.Persona_id = ?
            ORDER BY o.fecha_ingreso DESC, o.id DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


        /** Lista de turnos del cliente (muestra motivo/descripcion, no fecha/hora) */
    public function listarTodosLosTurnos(): array {
    $sql = "SELECT t.id, t.fecha_turno, t.hora_turno, t.motivo, t.descripcion,
                   p.nombre, p.apellido,
                   ma.descripcion AS marca,
                   mo.descripcion AS modelo,
                   a.anio,
                   a.color,
                   e.id AS estado_id,
                   e.descripcion AS estado
            FROM Turnos t
            JOIN Automoviles a ON t.Automovil_id = a.id
            JOIN Modelos_Automoviles mo ON a.Modelo_Automovil_id = mo.id
            JOIN Marcas_Automoviles ma ON mo.Marca_Automvil_id = ma.id
            JOIN Vehiculos_Personas vp ON a.id = vp.automoviles_id
            JOIN Personas p ON vp.Persona_id = p.id
            JOIN Estados_Turnos e ON t.Estado_Turno_id = e.id
            ORDER BY t.id DESC";
    $st = $this->db->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


   public function actualizarTurno(
    int $turnoId, 
    ?string $fecha, 
    ?string $hora, 
    int $estadoId
): bool {
    $pdo = Conexion::obtener();

    $sql = "UPDATE Turnos 
            SET Estado_Turno_id = :estadoId";

    // actualiza fecha solo si viene valor
    if ($fecha !== null) {
        $sql .= ", fecha_turno = :fecha";
    }
    // actualiza hora solo si viene valor
    if ($hora !== null) {
        $sql .= ", hora_turno = :hora";
    }

    $sql .= " WHERE id = :id";

    $st = $pdo->prepare($sql);

    $params = [
        'estadoId' => $estadoId,
        'id'       => $turnoId
    ];
    if ($fecha !== null) {
        $params['fecha'] = $fecha;
    }
    if ($hora !== null) {
        $params['hora'] = $hora;
    }

    return $st->execute($params);
}

public function obtenerEmailClientePorTurno(int $turnoId): ?array {
    $pdo = Conexion::obtener();
    $sql = "
        SELECT p.nombre, p.apellido, c.valor AS email, t.fecha_turno, t.hora_turno
        FROM Turnos t
        JOIN Automoviles a ON a.id = t.Automovil_id
        JOIN Vehiculos_Personas vp ON vp.automoviles_id = a.id
        JOIN Personas p ON p.id = vp.Persona_id
        JOIN Contacto_Persona c ON c.Persona_id = p.id
        JOIN Tipos_Contactos tc ON tc.id = c.Tipo_Contacto_id
        WHERE t.id = :id AND tc.descripcion = 'Email'
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute(['id' => $turnoId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}


public function eliminarTurno(int $id): bool {
    $sql = "DELETE FROM Turnos WHERE id = :id";
    $st = $this->db->prepare($sql);
    return $st->execute([':id' => $id]);
}
public function turnosParaRecordatorio(): array {
    $pdo = Conexion::obtener();

    $sql = "
        SELECT 
            t.id,
            t.fecha_turno,
            t.hora_turno,
            p.nombre,
            p.apellido,
            cp.valor AS email
        FROM Turnos t
        JOIN Automoviles a       ON a.id = t.Automovil_id
        JOIN Vehiculos_Personas vp ON vp.automoviles_id = a.id
        JOIN Personas p          ON p.id = vp.Persona_id
        JOIN Contacto_Persona cp ON cp.Persona_id = p.id
        JOIN Tipos_Contactos tc  ON tc.id = cp.Tipo_Contacto_id
        WHERE tc.descripcion = 'Email'
          AND t.Estado_Turno_id = 2
          AND CONCAT(t.fecha_turno, ' ', t.hora_turno)
              BETWEEN NOW() + INTERVAL 23 HOUR AND NOW() + INTERVAL 25 HOUR
          AND t.recordatorio_enviado = 0
    ";

    $st = $pdo->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}



public function marcarRecordatorioEnviado(int $turnoId): bool {
    $pdo = Conexion::obtener();
    $st = $pdo->prepare("UPDATE Turnos SET recordatorio_enviado = 1 WHERE id = ?");
    return $st->execute([$turnoId]);
}





}


