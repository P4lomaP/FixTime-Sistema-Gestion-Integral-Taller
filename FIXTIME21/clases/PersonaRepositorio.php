<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';
require_once __DIR__ . '/DomicilioRepositorio.php';

final class PersonaRepositorio {
  private \PDO $db;

  public function __construct() {
    $this->db = Conexion::obtener();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }

  /* ================== Lecturas ================== */

  public function buscarPorId(int $id): ?array {
    $st = $this->db->prepare("SELECT * FROM Personas WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  /**
   * Login helper: busca por email (Contacto_Persona tipo Email) o por DNI en Personas.
   * Devuelve la fila completa de Personas (incluye 'contrasenia').
   */
  public function buscarPorIdentificador(string $identificador): ?array {
    $ident = trim($identificador);

    // 1) Si parece email, intentar por Contacto_Persona (tipo Email)
    if (str_contains($ident, '@')) {
      $pid = $this->personaIdPorEmail($ident);
      if ($pid) return $this->buscarPorId($pid);
      // fallback: si existiera Personas.email (no en tu esquema actual)
      if ($this->columnaExiste('Personas', 'email')) {
        $st = $this->db->prepare("SELECT * FROM Personas WHERE email = ? LIMIT 1");
        $st->execute([$ident]);
        $row = $st->fetch();
        if ($row) return $row;
      }
      return null;
    }

    // 2) Caso DNI (solo dígitos / con puntos o espacios)
    $dni = preg_replace('/\D+/', '', $ident);
    if ($dni === '') return null;

    $st = $this->db->prepare("SELECT * FROM Personas WHERE dni = ? LIMIT 1");
    $st->execute([$dni]);
    $row = $st->fetch();
    return $row ?: null;
  }

  /** Email principal (Personas.email si existe, si no, Contacto_Persona tipo Email). */
  public function emailPrincipal(int $personaId): ?string {
    if ($this->columnaExiste('Personas', 'email')) {
      $st = $this->db->prepare("SELECT email FROM Personas WHERE id = ? LIMIT 1");
      $st->execute([$personaId]);
      $em = (string)($st->fetchColumn() ?: '');
      if ($em !== '') return $em;
    }
    $tipoEmailId = $this->idTipoContactoEmail(true);
    $st = $this->db->prepare("
      SELECT valor
        FROM Contacto_Persona
       WHERE Persona_id = ? AND Tipo_Contacto_id = ?
       ORDER BY id ASC LIMIT 1
    ");
    $st->execute([$personaId, $tipoEmailId]);
    $em = $st->fetchColumn();
    return $em ? (string)$em : null;
  }

  /** Teléfonos (tipo Teléfono/Telefono). */
  public function listarTelefonos(int $personaId): array {
    $tipoTelId = $this->idTipoContactoTelefono(true);
    $st = $this->db->prepare("
      SELECT valor FROM Contacto_Persona
       WHERE Persona_id = ? AND Tipo_Contacto_id = ?
       ORDER BY id ASC
    ");
    $st->execute([$personaId, $tipoTelId]);
    $rows = $st->fetchAll(\PDO::FETCH_COLUMN);
    return array_map('strval', $rows ?: []);
  }

  /** Domicilio actual resolviendo por tabla intermedia Personas_Domicilios. */
  public function obtenerDomicilioActual(int $personaId): ?array {
    if (!$this->tablaExiste('Personas_Domicilios') || !$this->tablaExiste('Domicilios')) {
      return null;
    }
    $sql = "
      SELECT d.id AS domicilio_id,
             p.descripcion  AS pais,
             pr.descripcion AS provincia,
             l.descripcion  AS localidad,
             b.descripcion  AS barrio,
             d.calle, d.altura, d.piso, d.departamento
        FROM Personas_Domicilios pd
        JOIN Domicilios d       ON d.id = pd.Domicilio_id
        LEFT JOIN Barrios b     ON b.id = d.Barrio_id
        LEFT JOIN Localidades l ON l.id = b.Localidad_id
        LEFT JOIN Provincias pr ON pr.id = l.Provincia_id
        LEFT JOIN Paises p      ON p.id = pr.Pais_id
       WHERE pd.Persona_id = ?
       ORDER BY pd.id DESC
       LIMIT 1
    ";
    $st = $this->db->prepare($sql);
    $st->execute([$personaId]);
    $row = $st->fetch() ?: null;

    if (!$row) {
      return [
        'pais' => '', 'provincia' => '', 'localidad' => '', 'barrio' => '',
        'calle' => '', 'altura' => '', 'piso' => '', 'departamento' => ''
      ];
    }
    return [
      'pais'         => (string)($row['pais'] ?? ''),
      'provincia'    => (string)($row['provincia'] ?? ''),
      'localidad'    => (string)($row['localidad'] ?? ''),
      'barrio'       => (string)($row['barrio'] ?? ''),
      'calle'        => (string)($row['calle'] ?? ''),
      'altura'       => (string)($row['altura'] ?? ''),
      'piso'         => (string)($row['piso'] ?? ''),
      'departamento' => (string)($row['departamento'] ?? ''),
    ];
  }

  /* ================== Escrituras ================== */

  public function actualizarPerfil(int $personaId, array $data): void {
    $nombre   = trim((string)($data['nombre']   ?? ''));
    $apellido = trim((string)($data['apellido'] ?? ''));
    $dni      = trim((string)($data['dni']      ?? ''));
    $email    = trim((string)($data['email']    ?? ''));

    if ($nombre === '' || $apellido === '' || $dni === '') {
      throw new \InvalidArgumentException('Faltan datos obligatorios');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Email inválido');
    }

    $this->validarDuplicados($personaId, $dni, $email);

    $this->db->beginTransaction();

    // Tu tabla Personas tiene: nombre, apellido, dni, contrasenia
    $sql = "UPDATE Personas SET nombre=:n, apellido=:a, dni=:d WHERE id=:id LIMIT 1";
    $st  = $this->db->prepare($sql);
    $st->execute([':n'=>$nombre, ':a'=>$apellido, ':d'=>$dni, ':id'=>$personaId]);

    // Manejar email por Contacto_Persona (en tu esquema Personas no tiene email)
    $this->upsertEmailEnContactos($personaId, $email);

    $this->db->commit();
  }

  public function actualizarBasicos(int $personaId, string $nombre, string $apellido, string $dni, string $email = ''): void {
    $this->actualizarPerfil($personaId, compact('nombre','apellido','dni','email'));
  }

  /** Reemplaza TODOS los teléfonos (tipo Teléfono). */
  public function reemplazarTelefonos(int $personaId, array $telefonos): void {
    $tipoTelId = $this->idTipoContactoTelefono(true);
    $this->db->beginTransaction();
    $this->db->prepare("DELETE FROM Contacto_Persona WHERE Persona_id = ? AND Tipo_Contacto_id = ?")
             ->execute([$personaId, $tipoTelId]);
    $ins = $this->db->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)");
    foreach ($telefonos as $t) {
      $val = trim((string)$t);
      if ($val !== '') $ins->execute([$personaId, $tipoTelId, $val]);
    }
    $this->db->commit();
  }

  /** Guardar domicilio pasando por el repositorio (usa Personas_Domicilios). */
  public function guardarDomicilio(int $personaId, array $dom): void {
    (new DomicilioRepositorio())->guardarDomicilio($personaId, $dom);
  }
  public function upsertDomicilio(int $personaId, array $dom): void { $this->guardarDomicilio($personaId, $dom); }
  public function guardarDomicilioPersona(int $personaId, array $dom): void { $this->guardarDomicilio($personaId, $dom); }

  /* ================== Internos ================== */

  /** Devuelve el id de persona por email en Contacto_Persona (tipo Email). */
  private function personaIdPorEmail(string $email): ?int {
    $tipoEmailId = $this->idTipoContactoEmail(true);
    $st = $this->db->prepare("
      SELECT Persona_id
        FROM Contacto_Persona
       WHERE Tipo_Contacto_id = ? AND valor = ?
       LIMIT 1
    ");
    $st->execute([$tipoEmailId, $email]);
    $pid = $st->fetchColumn();
    return $pid ? (int)$pid : null;
  }

  private function validarDuplicados(int $personaId, string $dni, string $email): void {
    if ($dni !== '') {
      $st = $this->db->prepare("SELECT id FROM Personas WHERE dni = ? AND id <> ? LIMIT 1");
      $st->execute([$dni, $personaId]);
      if ($st->fetchColumn()) throw new \RuntimeException('El DNI ya está registrado por otro usuario.');
    }

    if ($email !== '') {
      // En tu esquema, email vive en Contacto_Persona
      $tipoEmailId = $this->idTipoContactoEmail(true);
      $st = $this->db->prepare("
        SELECT Persona_id
          FROM Contacto_Persona
         WHERE Tipo_Contacto_id = ? AND valor = ? AND Persona_id <> ?
         LIMIT 1
      ");
      $st->execute([$tipoEmailId, $email, $personaId]);
      if ($st->fetchColumn()) throw new \RuntimeException('El email ya está registrado por otro usuario.');
    }
  }

  private function upsertEmailEnContactos(int $personaId, string $email): void {
    $tipoEmailId = $this->idTipoContactoEmail(true);
    // Borramos emails previos del usuario (deja 0 o 1 registro según lo enviado)
    $this->db->prepare("DELETE FROM Contacto_Persona WHERE Persona_id = ? AND Tipo_Contacto_id = ?")
             ->execute([$personaId, $tipoEmailId]);

    if ($email !== '') {
      $this->db->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)")
               ->execute([$personaId, $tipoEmailId, $email]);
    }
  }

  private function idTipoContactoEmail(bool $autoCrear = false): int {
    // Coincidencia case-insensitive por Email
    $st = $this->db->prepare("SELECT id FROM Tipos_Contactos WHERE UPPER(descripcion) = 'EMAIL' LIMIT 1");
    $st->execute();
    $id = (int)($st->fetchColumn() ?: 0);
    if (!$id && $autoCrear) {
      $this->db->prepare("INSERT INTO Tipos_Contactos (descripcion) VALUES ('Email')")->execute();
      $id = (int)$this->db->lastInsertId();
    }
    return $id;
  }

  private function idTipoContactoTelefono(bool $autoCrear = false): int {
    // Soporta “Teléfono”, “Telefono”, variantes
    $st = $this->db->prepare("
      SELECT id FROM Tipos_Contactos
       WHERE UPPER(descripcion) IN ('TELÉFONO','TELEFONO','TELEPHONE','PHONE','TEL')
       ORDER BY id ASC LIMIT 1
    ");
    $st->execute();
    $id = (int)($st->fetchColumn() ?: 0);
    if (!$id && $autoCrear) {
      $this->db->prepare("INSERT INTO Tipos_Contactos (descripcion) VALUES ('Teléfono')")->execute();
      $id = (int)$this->db->lastInsertId();
    }
    return $id;
  }

  private function tablaExiste(string $tabla): bool {
    try { $this->db->query("SELECT 1 FROM `$tabla` LIMIT 1"); return true; }
    catch (\Throwable $e) { return false; }
  }

  private function columnaExiste(string $tabla, string $col): bool {
    try {
      $st = $this->db->query("DESCRIBE `$tabla`");
      foreach ($st->fetchAll() as $c) {
        if (isset($c['Field']) && strcasecmp($c['Field'], $col) === 0) return true;
      }
      return false;
    } catch (\Throwable $e) { return false; }
  }

  public function buscarPorEmailExacto(string $email): ?array {
    $email = trim($email);
    if ($email === '') return null;

    // Principal: buscar en Contacto_Persona tipo 'Email'
    $pid = $this->personaIdPorEmail($email);
    if ($pid) {
      return $this->buscarPorId($pid);
    }

    // Fallback: si Personas tiene columna 'email', intentar ahí
    if ($this->columnaExiste('Personas', 'email')) {
      $st = $this->db->prepare("SELECT * FROM Personas WHERE email = ? LIMIT 1");
      $st->execute([$email]);
      $row = $st->fetch();
      return $row ?: null;
    }

    return null;
  }
}
