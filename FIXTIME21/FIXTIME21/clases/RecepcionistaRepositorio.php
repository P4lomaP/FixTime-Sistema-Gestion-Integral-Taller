<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

 class RecepcionistaRepositorio {
  private \PDO $db;

  public function __construct() {
    $this->db = Conexion::obtener();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }

  public function esRecepcionista(int $personaId): bool {
    $sql = "SELECT 1
              FROM Empleados e
              JOIN Cargos c ON c.id = e.Cargo_id
             WHERE e.Persona_id = ?
               AND c.descripcion = 'Recepcionista'
               AND (e.fecha_baja IS NULL OR e.fecha_baja > CURDATE())
             LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute([$personaId]);
    return (bool)$st->fetchColumn();
  }
}
