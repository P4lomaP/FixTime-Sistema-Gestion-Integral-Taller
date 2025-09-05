<?php
require_once __DIR__ . '/Conexion.php';

class TurnoRepositorio
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtener();
    }

    /* ====== helpers ====== */

    private function estadoId(string $nombre): int
    {
        $st = $this->db->prepare("SELECT id FROM Estados_Turnos WHERE descripcion = ? LIMIT 1");
        $st->execute([$nombre]);
        $id = $st->fetchColumn();
        if (!$id) {
            // Por si la tabla está vacía: creamos los 3 y devolvemos el pedido
            $this->db->exec("INSERT INTO Estados_Turnos (descripcion) VALUES ('Pendiente'),('Confirmado'),('Cancelado')");
            $st->execute([$nombre]);
            $id = $st->fetchColumn();
        }
        return (int)$id;
    }

    /* ====== disponibilidad ====== */

    // No permitir 2 turnos en mismo día/hora (de cualquier auto)
    public function disponible(string $fecha, string $hora): bool
    {
        $st = $this->db->prepare("
            SELECT COUNT(*) FROM Turnos 
            WHERE fecha_turno = ? AND hora_turno = ? 
              AND Estado_Turno_id <> (SELECT id FROM Estados_Turnos WHERE descripcion='Cancelado' LIMIT 1)
        ");
        $st->execute([$fecha, $hora]);
        return ((int)$st->fetchColumn()) === 0;
    }

    /* ====== ABM ====== */

    public function crear(int $personaId, int $automovilId, string $fecha, string $hora): int
    {
        // seguridad: auto debe ser de la persona
        $st = $this->db->prepare("SELECT 1 FROM Vehiculos_Personas WHERE Persona_id=? AND automoviles_id=? LIMIT 1");
        $st->execute([$personaId, $automovilId]);
        if (!$st->fetchColumn()) {
            throw new RuntimeException('Vehículo no pertenece al cliente');
        }

        $estadoPend = $this->estadoId('Pendiente');

        $st = $this->db->prepare("INSERT INTO Turnos (fecha_turno, hora_turno, Estado_Turno_id, Automovil_id) VALUES (?,?,?,?)");
        $st->execute([$fecha, $hora, $estadoPend, $automovilId]);
        return (int)$this->db->lastInsertId();
    }

    public function reprogramar(int $personaId, int $turnoId, string $fecha, string $hora, int $automovilId): void
    {
        // Validar pertenencia del turno
        $st = $this->db->prepare("
            SELECT t.id 
            FROM Turnos t
            JOIN Automoviles a ON a.id = t.Automovil_id
            JOIN Vehiculos_Personas vp ON vp.automoviles_id = a.id
            WHERE t.id = ? AND vp.Persona_id = ?
            LIMIT 1
        ");
        $st->execute([$turnoId, $personaId]);
        if (!$st->fetchColumn()) {
            throw new RuntimeException('Turno no válido');
        }

        $estadoPend = $this->estadoId('Pendiente');

        $st = $this->db->prepare("
            UPDATE Turnos 
               SET fecha_turno = ?, hora_turno = ?, Automovil_id = ?, Estado_Turno_id = ?
             WHERE id = ?
        ");
        $st->execute([$fecha, $hora, $automovilId, $estadoPend, $turnoId]);
    }

    public function cancelar(int $personaId, int $turnoId): void
    {
        $st = $this->db->prepare("
            SELECT t.id 
            FROM Turnos t
            JOIN Automoviles a ON a.id = t.Automovil_id
            JOIN Vehiculos_Personas vp ON vp.automoviles_id = a.id
            WHERE t.id = ? AND vp.Persona_id = ?
            LIMIT 1
        ");
        $st->execute([$turnoId, $personaId]);
        if (!$st->fetchColumn()) {
            throw new RuntimeException('Turno no válido');
        }

        $estadoCanc = $this->estadoId('Cancelado');
        $st = $this->db->prepare("UPDATE Turnos SET Estado_Turno_id = ? WHERE id = ?");
        $st->execute([$estadoCanc, $turnoId]);
    }

    /* ====== Consultas ====== */

    // Turnos del cliente (con marca/modelo/auto)
    public function listarPorPersona(int $personaId): array
    {
        $sql = "
        SELECT 
            t.id, t.fecha_turno, t.hora_turno,
            et.descripcion AS estado,
            ma.descripcion AS marca,
            mo.descripcion AS modelo,
            a.anio
        FROM Turnos t
        JOIN Estados_Turnos et ON et.id = t.Estado_Turno_id
        JOIN Automoviles a ON a.id = t.Automovil_id
        JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
        JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
        JOIN Vehiculos_Personas vp ON vp.automoviles_id = a.id
        WHERE vp.Persona_id = ?
        ORDER BY t.fecha_turno DESC, t.hora_turno DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Historial de servicios (Ordenes_Reparaciones) del cliente
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
        JOIN Automoviles a ON a.id = o.Automovil_id
        JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
        JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
        JOIN Vehiculos_Personas vp ON vp.automoviles_id = a.id
        WHERE vp.Persona_id = ?
        ORDER BY o.fecha_ingreso DESC, o.id DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
