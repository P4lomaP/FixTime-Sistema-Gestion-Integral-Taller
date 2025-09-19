<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

class PersonaRepositorio
{
    private \PDO $db;

    public function __construct() {
        $this->db = Conexion::obtener();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /* ===================== Transacciones ===================== */
    public function begin(): void    { $this->db->beginTransaction(); }
    public function commit(): void   { $this->db->commit(); }
    public function rollBack(): void { if ($this->db->inTransaction()) $this->db->rollBack(); }

    /* ===================== Autenticación / Búsquedas ===================== */
    public function buscarPorIdentificador(string $identificador): ?array
    {
        $pdo = $this->db;

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

    public function buscarPorEmailExacto(string $email): ?array
    {
        $tipoId = $this->obtenerTipoContactoIdEmail();
        $sql = "SELECT p.id, p.nombre, p.apellido
                FROM Personas p
                INNER JOIN Contacto_Persona cp ON cp.Persona_id = p.id
                WHERE cp.Tipo_Contacto_id = :tipo AND cp.valor = :email
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':tipo' => $tipoId, ':email' => $email]);
        $fila = $st->fetch();
        return $fila ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $st = $this->db->prepare("SELECT id, nombre, apellido, dni FROM Personas WHERE id=?");
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /* ===================== Reglas de unicidad ===================== */
    public function existeDni(string $dni): bool
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM Personas WHERE dni = :dni");
        $st->execute([':dni' => $dni]);
        return (int)$st->fetchColumn() > 0;
    }

    public function obtenerTipoContactoIdEmail(): int
    {
        $id = $this->db->query("SELECT id FROM Tipos_Contactos WHERE descripcion = 'Email' LIMIT 1")->fetchColumn();
        if (!$id) {
            $this->db->exec("INSERT INTO Tipos_Contactos (descripcion) VALUES ('Email')");
            $id = $this->db->lastInsertId();
        }
        return (int)$id;
    }

    public function existeEmail(string $email): bool
    {
        $tipoId = $this->obtenerTipoContactoIdEmail();
        $st = $this->db->prepare("SELECT COUNT(*) FROM Contacto_Persona WHERE Tipo_Contacto_id = :tipo AND valor = :v");
        $st->execute([':tipo' => $tipoId, ':v' => $email]);
        return (int)$st->fetchColumn() > 0;
    }

    public function dniPerteneceAOtro(string $dni, int $miId): bool {
        $st = $this->db->prepare("SELECT COUNT(*) FROM Personas WHERE dni = :dni AND id <> :id");
        $st->execute([':dni' => $dni, ':id' => $miId]);
        return (int)$st->fetchColumn() > 0;
    }

    public function emailPerteneceAOtro(string $email, int $miId): bool {
        $tipoId = $this->obtenerTipoContactoIdEmail();
        $st = $this->db->prepare("
            SELECT COUNT(*)
              FROM Contacto_Persona
             WHERE Tipo_Contacto_id = :tipo
               AND valor = :v
               AND Persona_id <> :id
        ");
        $st->execute([':tipo' => $tipoId, ':v' => $email, ':id' => $miId]);
        return (int)$st->fetchColumn() > 0;
    }

    /* ===================== Altas / Actualizaciones base ===================== */
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
        $tipoId = $this->obtenerTipoContactoIdEmail();
        $st = $this->db->prepare(
            "INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)"
        );
        $st->execute([$personaId, $tipoId, $email]);
    }

    public function actualizarContraseniaHash(int $personaId, string $hash): void
    {
        $st = $this->db->prepare("UPDATE Personas SET contrasenia = :h WHERE id = :id");
        $st->execute([':h' => $hash, ':id' => $personaId]);
    }

    public function actualizarPassword(int $personaId, string $hash): void {
        $st = $this->db->prepare("UPDATE Personas SET contrasenia = ? WHERE id = ?");
        $st->execute([$hash, $personaId]);
    }

    public function actualizarPerfil(int $personaId, string $nombre, string $apellido, string $dni, string $email): void
    {
        $st = $this->db->prepare("UPDATE Personas SET nombre=?, apellido=?, dni=? WHERE id=?");
        $st->execute([$nombre, $apellido, $dni, $personaId]);

        $tipoId = $this->obtenerTipoContactoIdEmail();
        $st = $this->db->prepare("SELECT id FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=? LIMIT 1");
        $st->execute([$personaId, $tipoId]);
        $id = $st->fetchColumn();

        if ($id) {
            $st = $this->db->prepare("UPDATE Contacto_Persona SET valor=? WHERE id=?");
            $st->execute([$email, $id]);
        } else {
            $st = $this->db->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)");
            $st->execute([$personaId, $tipoId, $email]);
        }
    }

    public function emailPrincipal(int $personaId): ?string
    {
        $tipoId = $this->obtenerTipoContactoIdEmail();
        $st = $this->db->prepare("
            SELECT valor FROM Contacto_Persona
            WHERE Persona_id=? AND Tipo_Contacto_id=?
            ORDER BY id ASC LIMIT 1
        ");
        $st->execute([$personaId, $tipoId]);
        $v = $st->fetchColumn();
        return $v ?: null;
    }

    /* ===================== Catálogos: get-or-create (para primeras cargas) ===================== */
    public function getOrCreatePais(string $desc): int {
        $desc = trim($desc);
        $st = $this->db->prepare("SELECT id FROM Paises WHERE descripcion = ? LIMIT 1");
        $st->execute([$desc]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;
        $st = $this->db->prepare("INSERT INTO Paises (descripcion) VALUES (?)");
        $st->execute([$desc]);
        return (int)$this->db->lastInsertId();
    }

    public function getOrCreateProvincia(string $desc, int $paisId): int {
        $desc = trim($desc);
        $st = $this->db->prepare("SELECT id FROM Provincias WHERE descripcion = ? AND Pais_id = ? LIMIT 1");
        $st->execute([$desc, $paisId]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;
        $st = $this->db->prepare("INSERT INTO Provincias (descripcion, Pais_id) VALUES (?, ?)");
        $st->execute([$desc, $paisId]);
        return (int)$this->db->lastInsertId();
    }

    public function getOrCreateLocalidad(string $desc, int $provinciaId): int {
        $desc = trim($desc);
        $st = $this->db->prepare("SELECT id FROM Localidades WHERE descripcion = ? AND Provincia_id = ? LIMIT 1");
        $st->execute([$desc, $provinciaId]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;
        $st = $this->db->prepare("INSERT INTO Localidades (descripcion, Provincia_id) VALUES (?, ?)");
        $st->execute([$desc, $provinciaId]);
        return (int)$this->db->lastInsertId();
    }

    public function getOrCreateBarrio(string $desc, int $localidadId): int {
        $desc = trim($desc);
        $st = $this->db->prepare("SELECT id FROM Barrios WHERE descripcion = ? AND Localidad_id = ? LIMIT 1");
        $st->execute([$desc, $localidadId]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;
        $st = $this->db->prepare("INSERT INTO Barrios (descripcion, Localidad_id) VALUES (?, ?)");
        $st->execute([$desc, $localidadId]);
        return (int)$this->db->lastInsertId();
    }

    /* ===================== Domicilios ===================== */
    public function crearDomicilio(?int $barrioId, string $calle, string $altura, ?string $piso, ?string $dto): int {
        $st = $this->db->prepare("
            INSERT INTO Domicilios (Barrio_id, calle, altura, piso, departamento)
            VALUES (?, ?, ?, ?, ?)
        ");
        $st->execute([$barrioId, $calle, $altura, $piso, $dto]);
        return (int)$this->db->lastInsertId();
    }

    public function vincularPersonaDomicilio(int $personaId, int $domicilioId): void {
        $chk = $this->db->prepare("SELECT 1 FROM Personas_Domicilios WHERE Persona_id=? AND Domicilio_id=? LIMIT 1");
        $chk->execute([$personaId, $domicilioId]);
        if (!$chk->fetchColumn()) {
            $st = $this->db->prepare("INSERT INTO Personas_Domicilios (Persona_id, Domicilio_id) VALUES (?, ?)");
            $st->execute([$personaId, $domicilioId]);
        }
    }

    public function obtenerDomicilioActual(int $personaId): ?array {
        $sql = "
          SELECT
            p.descripcion  AS pais,
            pr.descripcion AS provincia,
            l.descripcion  AS localidad,
            b.descripcion  AS barrio,
            d.calle, d.altura, d.piso, d.departamento
          FROM Personas_Domicilios pd
          INNER JOIN Domicilios d   ON d.id = pd.Domicilio_id
          LEFT JOIN Barrios b       ON b.id = d.Barrio_id
          LEFT JOIN Localidades l   ON l.id = b.Localidad_id
          LEFT JOIN Provincias pr   ON pr.id = l.Provincia_id
          LEFT JOIN Paises p        ON p.id = pr.Pais_id
          WHERE pd.Persona_id = :pid
          ORDER BY pd.id DESC
          LIMIT 1
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':pid' => $personaId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** IDs del árbol actual del usuario (si lo tiene) */
    public function obtenerIdsDomicilioActual(int $personaId): ?array {
        $sql = "
          SELECT
            d.id AS domicilio_id,
            b.id AS barrio_id,
            l.id AS localidad_id,
            pr.id AS provincia_id,
            p.id AS pais_id
          FROM Personas_Domicilios pd
          INNER JOIN Domicilios d ON d.id = pd.Domicilio_id
          LEFT JOIN Barrios b     ON b.id = d.Barrio_id
          LEFT JOIN Localidades l ON l.id = b.Localidad_id
          LEFT JOIN Provincias pr ON pr.id = l.Provincia_id
          LEFT JOIN Paises p      ON p.id = pr.Pais_id
          WHERE pd.Persona_id = :pid
          ORDER BY pd.id DESC
          LIMIT 1
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':pid' => $personaId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** Actualiza descripciones y domicilio sobre LAS MISMAS FILAS (no crea nuevas). */
    public function actualizarArbolDomicilioExistente(
        int $personaId,
        array $datos // ['pais','provincia','localidad','barrio','calle','altura','piso','departamento']
    ): void {
        $ids = $this->obtenerIdsDomicilioActual($personaId);
        if (!$ids) {
            throw new RuntimeException('La persona no tiene domicilio vinculado.');
        }

        // Normalizar
        $pais = trim((string)($datos['pais'] ?? ''));
        $prov = trim((string)($datos['provincia'] ?? ''));
        $loc  = trim((string)($datos['localidad'] ?? ''));
        $bar  = trim((string)($datos['barrio'] ?? ''));
        $calle= trim((string)($datos['calle'] ?? ''));
        $alt  = trim((string)($datos['altura'] ?? ''));
        $piso = trim((string)($datos['piso'] ?? ''));
        $dto  = trim((string)($datos['departamento'] ?? ''));

        // Actualizar catálogos solo si enviaron texto (evita sobreescribir con vacío)
        if ($pais !== '' && $ids['pais_id']) {
            $st = $this->db->prepare("UPDATE Paises SET descripcion = :d WHERE id = :id");
            $st->execute([':d' => $pais, ':id' => $ids['pais_id']]);
        }
        if ($prov !== '' && $ids['provincia_id']) {
            $st = $this->db->prepare("UPDATE Provincias SET descripcion = :d WHERE id = :id");
            $st->execute([':d' => $prov, ':id' => $ids['provincia_id']]);
        }
        if ($loc !== '' && $ids['localidad_id']) {
            $st = $this->db->prepare("UPDATE Localidades SET descripcion = :d WHERE id = :id");
            $st->execute([':d' => $loc, ':id' => $ids['localidad_id']]);
        }
        if ($bar !== '' && $ids['barrio_id']) {
            $st = $this->db->prepare("UPDATE Barrios SET descripcion = :d WHERE id = :id");
            $st->execute([':d' => $bar, ':id' => $ids['barrio_id']]);
        }

        // Domicilio (si dejan campos vacíos, no los toco)
        $sets = [];
        $params = [':id' => $ids['domicilio_id']];
        if ($calle !== '') { $sets[] = "calle = :calle"; $params[':calle'] = $calle; }
        if ($alt   !== '') { $sets[] = "altura = :altura"; $params[':altura'] = $alt; }
        if ($piso  !== '') { $sets[] = "piso = :piso"; $params[':piso'] = $piso; }
        if ($dto   !== '') { $sets[] = "departamento = :dto"; $params[':dto'] = $dto; }

        if ($sets) {
            $sql = "UPDATE Domicilios SET ".implode(', ', $sets)." WHERE id = :id";
            $st = $this->db->prepare($sql);
            $st->execute($params);
        }
    }

    /** Para primeras cargas: busca domicilio igual; si no, crea. */
    public function findOrCreateDomicilioReusando(?int $barrioId, string $calle, string $altura, ?string $piso, ?string $dto): int {
        $sql = "
          SELECT id FROM Domicilios
           WHERE ".($barrioId === null ? "Barrio_id IS NULL" : "Barrio_id = :b")."
             AND calle = :c AND altura = :a
             AND ".($piso === null || $piso === '' ? "COALESCE(piso,'')=''" : "piso = :p")."
             AND ".($dto  === null || $dto  === '' ? "COALESCE(departamento,'')=''" : "departamento = :d")."
          LIMIT 1
        ";
        $st = $this->db->prepare($sql);
        $params = [':c' => $calle, ':a' => $altura];
        if ($barrioId !== null) $params[':b'] = $barrioId;
        if ($piso   !== null && $piso !== '') $params[':p'] = $piso;
        if ($dto    !== null && $dto  !== '') $params[':d'] = $dto;

        $st->execute($params);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        return $this->crearDomicilio($barrioId, $calle, $altura, $piso !== '' ? $piso : null, $dto !== '' ? $dto : null);
    }

    /** Compat: setea Personas.domicilio_id si tu esquema tiene esa columna. */
    public function setDomicilioDirectoSiExisteColumna(int $personaId, int $domicilioId): void {
        try {
            $sql = "UPDATE Personas SET domicilio_id = :dom WHERE id = :id";
            $st  = $this->db->prepare($sql);
            $st->execute([':dom' => $domicilioId, ':id' => $personaId]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false) return;
            throw $e;
        }
    }

    /* ===================== Teléfonos ===================== */
    public function obtenerTipoContactoIdTelefono(): int {
        // Acepta 'Teléfono', 'Telefono' o 'Tel'
        $id = $this->db->query("SELECT id FROM Tipos_Contactos WHERE descripcion IN ('Teléfono','Telefono','Tel') LIMIT 1")->fetchColumn();
        if(!$id){
            $this->db->exec("INSERT INTO Tipos_Contactos (descripcion) VALUES ('Telefono')");
            $id = $this->db->lastInsertId();
        }
        return (int)$id;
    }

    /** Devuelve los teléfonos actuales (solo valores). */
    public function listarTelefonos(int $personaId): array {
        $tipoId = $this->obtenerTipoContactoIdTelefono();
        $st = $this->db->prepare("SELECT valor FROM Contacto_Persona WHERE Persona_id = ? AND Tipo_Contacto_id = ? ORDER BY id ASC");
        $st->execute([$personaId, $tipoId]);
        return array_values(array_map(fn($r)=> (string)$r['valor'], $st->fetchAll()));
    }

    /** Borra todos los teléfonos del usuario. */
    public function eliminarTelefonosPersona(int $personaId): void {
        $tipoId = $this->obtenerTipoContactoIdTelefono();
        $st = $this->db->prepare("DELETE FROM Contacto_Persona WHERE Persona_id = ? AND Tipo_Contacto_id = ?");
        $st->execute([$personaId, $tipoId]);
    }

    /** Reemplaza por completo los teléfonos del usuario (normaliza y deduplica). */
    public function reemplazarTelefonos(int $personaId, array $telefonos): void {
        // normalizar: deja dígitos y + al inicio; 6-15 dígitos
        $norm = [];
        foreach ($telefonos as $t) {
            $t = trim((string)$t);
            if ($t==='') continue;
            $t = preg_replace('/[^\d+]/', '', $t);
            $t = preg_replace('/(?!^)\+/', '', $t);       // si hay '+' en el medio, lo elimina
            if (!preg_match('/^\+?\d{6,15}$/', $t)) continue;
            $norm[] = $t;
        }
        $norm = array_values(array_unique($norm));

        $this->eliminarTelefonosPersona($personaId);
        if (!$norm) return;

        $tipoId = $this->obtenerTipoContactoIdTelefono();
        $ins = $this->db->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)");
        foreach ($norm as $v) {
            $ins->execute([$personaId, $tipoId, $v]);
        }
    }

    /* Métodos de compatibilidad que ya tenías */
    public function guardarTelefono(int $personaId,string $telefono):void{
        $tipoId=$this->obtenerTipoContactoIdTelefono();
        $st=$this->db->prepare("INSERT INTO Contacto_Persona (Persona_id,Tipo_Contacto_id,valor) VALUES (?,?,?)");
        $st->execute([$personaId,$tipoId,$telefono]);
    }
    public function guardarTelefonos(int $personaId,array $telefonos):void{
        if(empty($telefonos))return;
        $tipoId=$this->obtenerTipoContactoIdTelefono();
        $st=$this->db->prepare("INSERT INTO Contacto_Persona (Persona_id,Tipo_Contacto_id,valor) VALUES (?,?,?)");
        foreach($telefonos as $tel){ $st->execute([$personaId,$tipoId,$tel]); }
    }
        /**
     * Crea una persona con email, teléfonos y la marca como cliente.
     * Devuelve el ID de la persona creada.
     */
    public function crearClienteConEmail(
        string $nombre,
        string $apellido,
        string $dni,
        string $hash,
        string $email,
        array $telefonos = []
    ): int {
        $this->db->beginTransaction();
        try {
            // Persona
            $personaId = $this->crearPersona($nombre, $apellido, $dni, $hash);

            // Contactos
            $this->guardarEmail($personaId, $email);
            if (!empty($telefonos)) {
                $this->guardarTelefonos($personaId, $telefonos);
            }

            // Marcar como cliente
            $this->marcarComoCliente($personaId);

            $this->db->commit();
            return $personaId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

}
