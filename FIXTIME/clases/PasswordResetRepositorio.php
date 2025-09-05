<?php
require_once __DIR__ . '/Conexion.php';

class PasswordResetRepositorio
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtener();
    }

    // Invalida todos los tokens viejos de la persona
    public function invalidarTokensPersona(int $personaId): void
    {
        $st = $this->db->prepare("UPDATE PasswordResets SET used = 1 WHERE persona_id = ?");
        $st->execute([$personaId]);
    }

    // Crea un nuevo token con vencimiento en $minutos
    public function crearToken(int $personaId, int $minutos): string
    {
        $token = bin2hex(random_bytes(32));
        $expira = (new DateTime("+$minutos minutes"))->format('Y-m-d H:i:s');

        $st = $this->db->prepare("
            INSERT INTO PasswordResets (persona_id, token, expires_at, used)
            VALUES (?,?,?,0)
        ");
        $st->execute([$personaId, $token, $expira]);

        return $token;
    }

    // Valida si el token existe, no está usado y no venció
    public function validarToken(string $token): ?int
    {
        $st = $this->db->prepare("
            SELECT persona_id
            FROM PasswordResets
            WHERE token = ? AND used = 0 AND expires_at > NOW()
            LIMIT 1
        ");
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['persona_id'] : null;
    }

    // Marca el token como usado
    public function marcarUsado(string $token): void
    {
        $st = $this->db->prepare("UPDATE PasswordResets SET used = 1 WHERE token = ?");
        $st->execute([$token]);
    }
}
