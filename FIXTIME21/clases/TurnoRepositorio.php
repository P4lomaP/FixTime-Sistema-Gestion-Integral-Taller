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

    /** Lista de turnos del cliente (muestra motivo/descripcion, no fecha/hora) */
    public function listarPorPersona(int $personaId): array
    {
        $sql = "
            SELECT 
                t.id,
                et.descripcion AS estado,
                t.Automovil_id AS auto_id,
                t.motivo,
                t.descripcion,
                ma.descripcion AS marca,
                mo.descripcion AS modelo,
                a.anio,
                a.color
            FROM Turnos t
            JOIN Estados_Turnos et    ON et.id = t.Estado_Turno_id
            JOIN Automoviles a        ON a.id = t.Automovil_id
            JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
            JOIN Marcas_Automoviles ma  ON ma.id = mo.Marca_Automvil_id
            JOIN Vehiculos_Personas vp  ON vp.automoviles_id = a.id
            WHERE vp.Persona_id = ?
            ORDER BY t.id DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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


    public function actualizarTurno(int $id, ?string $fecha, ?string $hora, int $estadoId): bool {
    $sql = "UPDATE Turnos 
            SET fecha_turno = :f, hora_turno = :h, Estado_Turno_id = :e
            WHERE id = :id";
    $st = $this->db->prepare($sql);
    return $st->execute([
        ':f' => $fecha,
        ':h' => $hora,
        ':e' => $estadoId,
        ':id'=> $id
    ]);
}
    public function eliminarTurno(int $id): bool {
    $sql = "DELETE FROM Turnos WHERE id = :id";
    $st = $this->db->prepare($sql);
    return $st->execute([':id' => $id]);
}




}


