<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

/**
 * Repositorio de Administradores
 * Base esperada:
 *   Administradores(id PK AI, Persona_id FK -> Personas.id)
 */
final class AdministradorRepositorio
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtener();
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /** Columna FK de persona en Administradores (según tu esquema) */
    private function fkCol(): string
    {
        return 'Persona_id';
    }

    /** Devuelve true si la persona tiene rol de administrador. */
    public function esAdmin(int $personaId): bool {
    $pdo = Conexion::obtener();
    $st = $pdo->prepare(
        "SELECT 1 FROM Administradores 
         WHERE Persona_id = ? AND (fecha_baja IS NULL) 
         LIMIT 1"
    );
    $st->execute([$personaId]);
    return (bool)$st->fetchColumn();
}


    /** Crea la fila en Administradores para la persona (id devuelto). */
    public function crearDesdePersona(int $personaId): int
    {
        if ($personaId <= 0) {
            throw new \InvalidArgumentException('Persona_id inválido');
        }
        $col = $this->fkCol();

        // Evitar duplicados si ya existe
        if ($this->esAdmin($personaId)) {
            // Buscar id existente y retornarlo
            $st = $this->db->prepare("SELECT id FROM Administradores WHERE `$col` = :pid LIMIT 1");
            $st->execute([':pid' => $personaId]);
            return (int)($st->fetchColumn() ?: 0);
        }

        $st = $this->db->prepare("INSERT INTO Administradores (`$col`) VALUES (:pid)");
        $st->execute([':pid' => $personaId]);
        return (int)$this->db->lastInsertId();
    }

    /** Elimina la fila en Administradores para la persona (si existe). */
    public function eliminarPorPersona(int $personaId): void
    {
        if ($personaId <= 0) return;
        $col = $this->fkCol();
        $st  = $this->db->prepare("DELETE FROM Administradores WHERE `$col` = :pid");
        $st->execute([':pid' => $personaId]);
    }

    /**
     * Asegura el rol de admin para la persona:
     *  - $habilitar = true  -> crea si no existe
     *  - $habilitar = false -> elimina si existe
     */
    public function asegurarRol(int $personaId, bool $habilitar): void
    {
        if ($habilitar) {
            $this->crearDesdePersona($personaId);
        } else {
            $this->eliminarPorPersona($personaId);
        }
    }
}
