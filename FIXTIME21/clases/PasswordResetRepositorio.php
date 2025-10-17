<?php
require_once __DIR__ . '/Conexion.php';

class PasswordResetRepositorio
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtener();
    }

        public function invalidarTokensPersona(int $personaId): void
    {
        $st = $this->db->prepare("UPDATE PasswordResets SET used = 1 WHERE persona_id = ?");
        $st->execute([$personaId]);
    }

    
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

    
    public function marcarUsado(string $token): void
    {
        $st = $this->db->prepare("UPDATE PasswordResets SET used = 1 WHERE token = ?");
        $st->execute([$token]);
    }
}
