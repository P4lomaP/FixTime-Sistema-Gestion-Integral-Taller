<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';
require_once __DIR__ . '/Empleado.php';

class EmpleadoRepositorio
{
    private \PDO $db;

    public function __construct() {
        $this->db = Conexion::obtener();
    }

    /**
     * Lista empleados con datos de persona y cargo (omite bajas lógicas).
     * Si $cargoId es null, lista todos. Si trae un id, filtra por e.Cargo_id.
     */
    public function listar(?int $cargoId = null): array
    {
        $sql = "SELECT 
                    e.id,
                    e.Persona_id,
                    e.Cargo_id,
                    p.nombre,
                    p.apellido,
                    p.dni,
                    c.descripcion AS cargo
                FROM Empleados e
                JOIN Personas p ON p.id = e.Persona_id
                JOIN Cargos   c ON c.id = e.Cargo_id
                WHERE e.fecha_baja IS NULL";
        $params = [];

        if ($cargoId !== null) {
            $sql .= " AND e.Cargo_id = :cargoId";
            $params[':cargoId'] = $cargoId;
        }

        $sql .= " ORDER BY p.apellido, p.nombre";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Verifica existencia de email (tipo = Email) */
    public function emailExiste(string $email): bool
    {
        $sql = "SELECT 1
                FROM Contacto_Persona cp
                JOIN Tipos_Contactos t ON t.id = cp.Tipo_Contacto_id
                WHERE t.descripcion='Email' AND LOWER(cp.valor)=LOWER(:e)
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $email]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Crea Persona + Contacto (email) + Empleado.
     * Devuelve id empleado.
     * (El alta en Administradores, si el cargo es "Administrador", lo manejás desde el controlador.)
     */
    public function crearEmpleado(string $nombre, string $apellido, string $dni, string $email, string $passwordHash, int $cargoId): int
    {
        $this->db->beginTransaction();
        try {
            // Persona
            $ps = $this->db->prepare(
                "INSERT INTO Personas (nombre, apellido, dni, contrasenia)
                 VALUES (:n, :a, :dni, :pass)"
            );
            $ps->execute([':n' => $nombre, ':a' => $apellido, ':dni' => $dni, ':pass' => $passwordHash]);
            $personaId = (int)$this->db->lastInsertId();

            // Email
            $tipo = (int)$this->db->query("SELECT id FROM Tipos_Contactos WHERE descripcion='Email' LIMIT 1")->fetchColumn();
            if (!$tipo) {
                throw new \RuntimeException("No existe el tipo de contacto 'Email' en Tipos_Contactos.");
            }
            $cs = $this->db->prepare(
                "INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor)
                 VALUES (:pid, :tipo, :val)"
            );
            $cs->execute([':pid' => $personaId, ':tipo' => $tipo, ':val' => $email]);

            // Empleado
            $es = $this->db->prepare("INSERT INTO Empleados (Persona_id, Cargo_id) VALUES (:pid, :cargo)");
            $es->execute([':pid' => $personaId, ':cargo' => $cargoId]);
            $empleadoId = (int)$this->db->lastInsertId();

            $this->db->commit();
            return $empleadoId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Actualiza datos básicos (Persona + cargo) */
    public function actualizar(int $empleadoId, string $nombre, string $apellido, string $dni, int $cargoId): void
    {
        $st = $this->db->prepare("SELECT Persona_id FROM Empleados WHERE id=:id");
        $st->execute([':id' => $empleadoId]);
        $personaId = (int)$st->fetchColumn();
        if (!$personaId) {
            throw new \RuntimeException("Empleado no encontrado");
        }

        $p = $this->db->prepare(
            "UPDATE Personas SET nombre=:n, apellido=:a, dni=:dni WHERE id=:pid"
        );
        $p->execute([':n' => $nombre, ':a' => $apellido, ':dni' => $dni, ':pid' => $personaId]);

        $e = $this->db->prepare("UPDATE Empleados SET Cargo_id=:c WHERE id=:id");
        $e->execute([':c' => $cargoId, ':id' => $empleadoId]);
    }

    /**
     * Baja lógica del empleado y del rol administrador (si lo tiene).
     * NO borra registros.
     */
    public function desactivar(int $empleadoId): void
    {
        $this->db->beginTransaction();
        try {
            // Persona del empleado
            $st = $this->db->prepare("SELECT Persona_id FROM Empleados WHERE id = :id LIMIT 1");
            $st->execute([':id' => $empleadoId]);
            $personaId = (int)($st->fetchColumn() ?: 0);
            if (!$personaId) {
                throw new \RuntimeException('Empleado no encontrado.');
            }

            // Empleado -> fecha_baja (si aún está activo)
            $updEmp = $this->db->prepare(
                "UPDATE Empleados SET fecha_baja = CURDATE()
                 WHERE id = :id AND fecha_baja IS NULL"
            );
            $updEmp->execute([':id' => $empleadoId]);

            // Administradores -> fecha_baja si tenía rol
            $updAdm = $this->db->prepare(
                "UPDATE Administradores SET fecha_baja = CURDATE()
                 WHERE Persona_id = :pid AND fecha_baja IS NULL"
            );
            $updAdm->execute([':pid' => $personaId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * (Opcional) Reactivar empleado. Si $reactivarAdmin es true,
     * vuelve a activar también el rol administrador.
     */
    public function reactivar(int $empleadoId, bool $reactivarAdmin = false): void
    {
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare("SELECT Persona_id FROM Empleados WHERE id = :id LIMIT 1");
            $st->execute([':id' => $empleadoId]);
            $personaId = (int)($st->fetchColumn() ?: 0);
            if (!$personaId) {
                throw new \RuntimeException('Empleado no encontrado.');
            }

            $this->db->prepare("UPDATE Empleados SET fecha_baja = NULL WHERE id = :id")
                     ->execute([':id' => $empleadoId]);

            if ($reactivarAdmin) {
                $this->db->prepare("UPDATE Administradores SET fecha_baja = NULL WHERE Persona_id = :pid")
                         ->execute([':pid' => $personaId]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }



    /** Lista mecánicos activos (sin fecha_baja) */
public function listarMecanicosActivos(): array {
    $sql = "SELECT e.id AS empleado_id, p.id AS persona_id, p.nombre, p.apellido
              FROM Empleados e
              JOIN Personas p ON p.id = e.Persona_id
              JOIN Cargos c   ON c.id = e.Cargo_id
             WHERE c.descripcion = 'Mecánico'
               AND (e.fecha_baja IS NULL OR e.fecha_baja > CURDATE())
             ORDER BY p.apellido, p.nombre";
    $st = $this->db->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
/** ¿Tiene algún cargo activo como empleado? */
public function esEmpleado(int $personaId): bool {
    $sql = "SELECT 1 FROM Empleados
            WHERE Persona_id=? AND (fecha_baja IS NULL OR fecha_baja > CURDATE())
            LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([$personaId]);
    return (bool)$st->fetchColumn();
}

/** Devuelve el único cargo activo (o null). Si hay más de uno (no debería), retorna el primero. */
public function obtenerCargoUnicoActivo(int $personaId): ?string {
    $sql = "SELECT c.descripcion
            FROM Empleados e
            JOIN Cargos c ON c.id = e.Cargo_id
            WHERE e.Persona_id=? AND (e.fecha_baja IS NULL OR e.fecha_baja > CURDATE())
            ORDER BY c.descripcion
            LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([$personaId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)$row['descripcion'] : null;
}

/** Mapea cargo → ruta de panel */
public static function rutaPanelPorCargo(string $cargo): ?string {
    $cargo = mb_strtolower(trim($cargo), 'UTF-8');
    if ($cargo === 'recepcionista') return '/modules/empleado/recepcionista';
    if ($cargo === 'mecánico' || $cargo === 'mecanico') return '/modules/empleado/mecanico';
    return null;
}


}
