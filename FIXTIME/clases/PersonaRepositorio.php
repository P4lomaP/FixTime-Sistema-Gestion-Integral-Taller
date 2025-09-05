<?php
require_once __DIR__ . '/Conexion.php';

class PersonaRepositorio
{
    
    private \PDO $db;

    public function __construct() {
        $this->db = Conexion::obtener();
    }
    public function buscarPorIdentificador(string $identificador): ?array
    {
        $pdo = Conexion::obtener();

        if (filter_var($identificador, FILTER_VALIDATE_EMAIL)) {
            $sqlTipo = "SELECT id FROM Tipos_Contactos WHERE descripcion = 'Email' LIMIT 1";
            $tipoId = (int) $pdo->query($sqlTipo)->fetchColumn();

            $sql = "SELECT p.id, p.nombre, p.apellido, p.contrasenia
                    FROM Personas p
                    INNER JOIN Contacto_Persona cp ON cp.Persona_id = p.id
                    WHERE cp.Tipo_Contacto_id = :tipo AND cp.valor = :email
                    LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([':tipo' => $tipoId, ':email' => $identificador]);
            $fila = $st->fetch();
            return $fila ?: null;
        }

        $dni = preg_replace('/\D+/', '', $identificador);
        if ($dni === '') return null;

        $st = $pdo->prepare("SELECT id, nombre, apellido, contrasenia
                             FROM Personas
                             WHERE dni = :dni LIMIT 1");
        $st->execute([':dni' => $dni]);
        $fila = $st->fetch();
        return $fila ?: null;
    }

    

    public function existeDni(string $dni): bool
    {
        $pdo = Conexion::obtener();
        $st = $pdo->prepare("SELECT COUNT(*) FROM Personas WHERE dni = :dni");
        $st->execute([':dni' => $dni]);
        return (int)$st->fetchColumn() > 0;
    }

    public function obtenerTipoContactoIdEmail(): int
    {
        $pdo = Conexion::obtener();
        $id = $pdo->query("SELECT id FROM Tipos_Contactos WHERE descripcion = 'Email' LIMIT 1")->fetchColumn();
        if (!$id) {
            
            $pdo->exec("INSERT INTO Tipos_Contactos (descripcion) VALUES ('Email')");
            $id = $pdo->lastInsertId();
        }
        return (int)$id;
    }

    public function existeEmail(string $email): bool
    {
        $pdo = Conexion::obtener();
        $tipoId = $this->obtenerTipoContactoIdEmail();
        $st = $pdo->prepare("SELECT COUNT(*) FROM Contacto_Persona WHERE Tipo_Contacto_id = :tipo AND valor = :v");
        $st->execute([':tipo' => $tipoId, ':v' => $email]);
        return (int)$st->fetchColumn() > 0;
    }

    
    public function crearClienteConEmail(string $nombre, string $apellido, string $dni, string $email, string $hash): int
    {
        $pdo = Conexion::obtener();
        $pdo->beginTransaction();
        try {
            // 1) Persona
            $st = $pdo->prepare("INSERT INTO Personas (nombre, apellido, dni, contrasenia) 
                                 VALUES (:n, :a, :dni, :c)");
            $st->execute([':n' => $nombre, ':a' => $apellido, ':dni' => $dni, ':c' => $hash]);
            $personaId = (int)$pdo->lastInsertId();

            // 2) Cliente
            $st2 = $pdo->prepare("INSERT INTO Clientes (Persona_id) VALUES (:pid)");
            $st2->execute([':pid' => $personaId]);

            // 3) Contacto (Email)
            $tipoId = $this->obtenerTipoContactoIdEmail();
            $st3 = $pdo->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) 
                                  VALUES (:pid, :tid, :val)");
            $st3->execute([':pid' => $personaId, ':tid' => $tipoId, ':val' => $email]);

            $pdo->commit();
            return $personaId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    
    public function actualizarContraseniaHash(int $personaId, string $hash): void
    {
        $pdo = Conexion::obtener();
        $st = $pdo->prepare("UPDATE Personas SET contrasenia = :h WHERE id = :id");
        $st->execute([':h' => $hash, ':id' => $personaId]);
    }

public function crearPersona(string $nombre, string $apellido, string $dni, string $hash): int {
        $st = $this->db->prepare(
            "INSERT INTO Personas (nombre, apellido, dni, contrasenia) VALUES (?,?,?,?)"
        );
        $st->execute([$nombre, $apellido, $dni, $hash]);
        return (int)$this->db->lastInsertId();
    }

    public function marcarComoCliente(int $personaId): void {
        $st = $this->db->prepare("INSERT INTO Clientes (Persona_id) VALUES (?)");
        $st->execute([$personaId]);
    }
    public function guardarEmail(int $personaId, string $email): void {
        
        $st = $this->db->prepare("SELECT id FROM Tipos_Contactos WHERE descripcion = 'Email' LIMIT 1");
        $st->execute();
        $tipoId = (int)$st->fetchColumn();

        if (!$tipoId) {
           
            $this->db->prepare("INSERT INTO Tipos_Contactos (descripcion) VALUES ('Email')")->execute();
            $tipoId = (int)$this->db->lastInsertId();
        }

        $st = $this->db->prepare(
            "INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)"
        );
        $st->execute([$personaId, $tipoId, $email]);
    }


    public function buscarPorEmailExacto(string $email): ?array
    {
        $pdo = Conexion::obtener();
        $tipoId = $this->obtenerTipoContactoIdEmail();

        $sql = "SELECT p.id, p.nombre, p.apellido
                FROM Personas p
                INNER JOIN Contacto_Persona cp ON cp.Persona_id = p.id
                WHERE cp.Tipo_Contacto_id = :tipo AND cp.valor = :email
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':tipo' => $tipoId, ':email' => $email]);
        $fila = $st->fetch();
        return $fila ?: null;
    }
public function actualizarPassword(int $personaId, string $hash): void {
    $st = $this->db->prepare("UPDATE Personas SET contrasenia = ? WHERE id = ?");
    $st->execute([$hash, $personaId]);
}
    public function actualizarPerfil(int $personaId, string $nombre, string $apellido, string $dni, string $email): void
    {
        $st = $this->db->prepare("UPDATE Personas SET nombre=?, apellido=?, dni=? WHERE id=?");
        $st->execute([$nombre, $apellido, $dni, $personaId]);

        // upsert de email (Tipo_Contacto_id = 2)
        $st = $this->db->prepare("SELECT id FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=2 LIMIT 1");
        $st->execute([$personaId]);
        $id = $st->fetchColumn();

        if ($id) {
            $st = $this->db->prepare("UPDATE Contacto_Persona SET valor=? WHERE id=?");
            $st->execute([$email, $id]);
        } else {
            $st = $this->db->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,2,?)");
            $st->execute([$personaId, $email]);
        }
    }
    public function emailPrincipal(int $personaId): ?string
    {
        // Tipo_Contacto_id: 2 = Email (según tus inserts)
        $st = $this->db->prepare("
            SELECT valor FROM Contacto_Persona 
            WHERE Persona_id=? AND Tipo_Contacto_id=2
            ORDER BY id ASC LIMIT 1
        ");
        $st->execute([$personaId]);
        $v = $st->fetchColumn();
        return $v ?: null;
    }
    public function buscarPorId(int $id): ?array
    {
        $st = $this->db->prepare("SELECT id, nombre, apellido, dni FROM Personas WHERE id=?");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    
}
