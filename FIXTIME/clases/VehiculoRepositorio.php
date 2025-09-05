<?php
require_once __DIR__ . '/Conexion.php';

class VehiculoRepositorio
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtener();
    }

   
    public function listarMarcas(): array
    {
        $st = $this->db->query("SELECT id, descripcion FROM Marcas_Automoviles ORDER BY descripcion ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    
    public function listarPorPersona(int $personaId): array
    {
        $sql = "
            SELECT
                a.id                    AS id_auto,
                ma.descripcion          AS marca,
                mo.descripcion          AS modelo,
                a.anio,
                a.color,
                a.km
            FROM Vehiculos_Personas vp
            INNER JOIN Automoviles a        ON a.id = vp.automoviles_id
            INNER JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
            INNER JOIN Marcas_Automoviles ma  ON ma.id = mo.Marca_Automvil_id
            WHERE vp.Persona_id = ?
            ORDER BY ma.descripcion, mo.descripcion, a.anio DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    
    private function obtenerModeloId(int $marcaId, string $modeloTexto): int
    {
        
        $sqlSel = "
            SELECT id
            FROM Modelos_Automoviles
            WHERE Marca_Automvil_id = ? AND LOWER(descripcion) = LOWER(?)
            LIMIT 1
        ";
        $st = $this->db->prepare($sqlSel);
        $st->execute([$marcaId, $modeloTexto]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

       
        $sqlIns = "INSERT INTO Modelos_Automoviles (descripcion, Marca_Automvil_id) VALUES (?, ?)";
        $st = $this->db->prepare($sqlIns);
        $st->execute([$modeloTexto, $marcaId]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Crea un automovil y lo asocia a la persona.
     * Tablas: Automoviles, Vehiculos_Personas
     *
     * @param int    $personaId
     * @param int    $marcaId
     * @param string $modeloTexto  
     * @param string $anio
     * @param string $km
     * @param string $color
     */
    public function crearParaPersona(
        int $personaId,
        int $marcaId,
        string $modeloTexto,
        string $anio,
        string $km,
        string $color
    ): int {
        $this->db->beginTransaction();
        try {
           
            $modeloId = $this->obtenerModeloId($marcaId, $modeloTexto);

            
            $sqlAuto = "
                INSERT INTO Automoviles (descripcion, anio, km, color, Modelo_Automovil_id)
                VALUES (?, ?, ?, ?, ?)
            ";
            
            $descripcion = trim($modeloTexto . ' ' . $anio);
            $st = $this->db->prepare($sqlAuto);
            $st->execute([$descripcion, $anio, $km, $color, $modeloId]);
            $autoId = (int)$this->db->lastInsertId();

            // Asociar a la persona
            $sqlLink = "INSERT INTO Vehiculos_Personas (Persona_id, automoviles_id) VALUES (?, ?)";
            $st = $this->db->prepare($sqlLink);
            $st->execute([$personaId, $autoId]);

            $this->db->commit();
            return $autoId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
public function perteneceAPersona(int $personaId, int $autoId): bool
{
    $st = $this->db->prepare("
        SELECT 1
        FROM Vehiculos_Personas
        WHERE Persona_id = ? AND automoviles_id = ?
        LIMIT 1
    ");
    $st->execute([$personaId, $autoId]);
    return (bool)$st->fetchColumn();
}


}
