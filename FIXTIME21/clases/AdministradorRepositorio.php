<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';
require_once __DIR__ . '/Administrador.php';

final class AdministradorRepositorio
{
    private \PDO $db;
    public function __construct() { $this->db = Conexion::obtener(); }

    public function esAdmin(int $personaId): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM Administradores WHERE Persona_id = :pid LIMIT 1");
        $st->execute([':pid' => $personaId]);

        return (bool)$st->fetchColumn();
    }

    /** Crea un administrador a partir de una Persona existente */
    public function crearDesdePersona(int $personaId): int
    {
        $st = $this->db->prepare("INSERT INTO Administradores (Persona_id) VALUES (:pid)");
        $st->execute([':pid' => $personaId]);
        return (int)$this->db->lastInsertId();
    }
}
