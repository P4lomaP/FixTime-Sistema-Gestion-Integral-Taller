<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class EstadoTurnoRepositorio {
    private PDO $db;
    public function __construct() { $this->db = Conexion::obtener(); }

    public function listarTodos(): array {
        $sql = "SELECT id, descripcion FROM Estados_Turnos ORDER BY id";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
    }

    public function obtenerIdPorDescripcion(string $desc): ?int {
        $st = $this->db->prepare("SELECT id FROM Estados_Turnos WHERE LOWER(descripcion)=LOWER(:d) LIMIT 1");
        $st->execute([':d' => $desc]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int)$id;
    }
}
