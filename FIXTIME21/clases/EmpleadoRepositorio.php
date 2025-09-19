<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';
require_once __DIR__ . '/Empleado.php';

final class EmpleadoRepositorio
{
    private \PDO $db;
    public function __construct() { $this->db = Conexion::obtener(); }

    /** Lista empleados con datos de persona y cargo */
    public function listar(): array
    {
        $sql = "SELECT e.id, e.Persona_id, e.Cargo_id, p.nombre, p.apellido, p.dni, c.descripcion AS cargo
                FROM Empleados e
                JOIN Personas p ON p.id = e.Persona_id
                JOIN Cargos c ON c.id = e.Cargo_id
                ORDER BY p.apellido, p.nombre";
        return $this->db->query($sql)->fetchAll();
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
        $st->execute([':e'=>$email]);
        return (bool)$st->fetchColumn();
    }

    /** Crea Persona + Contacto (email) + Empleado. Devuelve id empleado */
    public function crearEmpleado(string $nombre, string $apellido, string $dni, string $email, string $passwordHash, int $cargoId): int
    {
        $this->db->beginTransaction();
        try {
            // Persona
            $ps = $this->db->prepare("INSERT INTO Personas (nombre, apellido, dni, contrasenia) VALUES (:n,:a,:dni,:pass)");
            $ps->execute([':n'=>$nombre, ':a'=>$apellido, ':dni'=>$dni, ':pass'=>$passwordHash]);
            $personaId = (int)$this->db->lastInsertId();

            // Email
            $tipo = (int)$this->db->query("SELECT id FROM Tipos_Contactos WHERE descripcion='Email' LIMIT 1")->fetchColumn();
            if (!$tipo) throw new RuntimeException("No existe el tipo de contacto 'Email' en Tipos_Contactos.");
            $cs = $this->db->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (:pid, :tipo, :val)");
            $cs->execute([':pid'=>$personaId, ':tipo'=>$tipo, ':val'=>$email]);

            // Empleado
            $es = $this->db->prepare("INSERT INTO Empleados (Persona_id, Cargo_id) VALUES (:pid, :cargo)");
            $es->execute([':pid'=>$personaId, ':cargo'=>$cargoId]);
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
        $st->execute([':id'=>$empleadoId]);
        $personaId = (int)$st->fetchColumn();
        if (!$personaId) throw new RuntimeException("Empleado no encontrado");

        $p = $this->db->prepare("UPDATE Personas SET nombre=:n, apellido=:a, dni=:dni WHERE id=:pid");
        $p->execute([':n'=>$nombre, ':a'=>$apellido, ':dni'=>$dni, ':pid'=>$personaId]);

        $e = $this->db->prepare("UPDATE Empleados SET Cargo_id=:c WHERE id=:id");
        $e->execute([':c'=>$cargoId, ':id'=>$empleadoId]);
    }

    /** Baja lógica mínima: quitamos la relación en Empleados (no tocamos Personas) */
    public function desactivar(int $empleadoId): void
    {
        $d = $this->db->prepare("DELETE FROM Empleados WHERE id=:id");
        $d->execute([':id'=>$empleadoId]);
    }
}
