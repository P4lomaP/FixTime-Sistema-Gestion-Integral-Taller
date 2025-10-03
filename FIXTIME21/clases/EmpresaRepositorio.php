<?php
declare(strict_types=1);
require_once __DIR__ . '/Conexion.php';

final class EmpresaRepositorio {
  private \PDO $db;

  public function __construct() {
    $this->db = Conexion::obtener();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }

  /* ===================== Helpers ===================== */

  private function tipoIdPorDescripcion(string $desc): int {
    $desc = trim($desc);
    $q = $this->db->prepare("SELECT id FROM Tipos_Contactos WHERE descripcion = ? LIMIT 1");
    $q->execute([$desc]);
    $id = $q->fetchColumn();
    if ($id) return (int)$id;

    $ins = $this->db->prepare("INSERT INTO Tipos_Contactos (descripcion) VALUES (?)");
    $ins->execute([$desc]);
    return (int)$this->db->lastInsertId();
  }

  /** Deja sólo dígitos en CUIT. */
  public function normalizarCUIT(string $cuit): string {
    $d = preg_replace('/\D+/', '', $cuit);
    return $d ?? '';
  }

  /* ===================== CRUD Empresa ===================== */

  public function buscarIdPorCUIT(string $cuitSoloDigitos): ?int {
    $q = $this->db->prepare("SELECT id FROM Empresas WHERE CUIT = ? LIMIT 1");
    $q->execute([$cuitSoloDigitos]);
    $id = $q->fetchColumn();
    return $id ? (int)$id : null;
  }

  public function crear(string $razonSocial, string $cuitSoloDigitos): int {
    $st = $this->db->prepare("INSERT INTO Empresas (razon_social, CUIT) VALUES (?, ?)");
    $st->execute([$razonSocial, $cuitSoloDigitos]);
    return (int)$this->db->lastInsertId();
  }

  /** Inserta contacto en empresa evitando duplicados exactos (empresa,tipo,valor) */
  public function agregarContacto(int $empresaId, string $tipoDesc, string $valor): void {
    $valor = trim((string)$valor);
    if ($valor === '') return;
    $tipoId = $this->tipoIdPorDescripcion($tipoDesc);

    $chk = $this->db->prepare(
      "SELECT 1 FROM Contactos_Empresas 
        WHERE Empresas_id = ? AND Tipo_Contacto_id = ? AND valor = ? LIMIT 1"
    );
    $chk->execute([$empresaId, $tipoId, $valor]);
    if ($chk->fetchColumn()) return;

    $ins = $this->db->prepare(
      "INSERT INTO Contactos_Empresas (Empresas_id, Tipo_Contacto_id, valor) VALUES (?,?,?)"
    );
    $ins->execute([$empresaId, $tipoId, $valor]);
  }

  /* ============ vínculo persona↔empresa por contacto compartido ============ */

  private function agregarContactoPersonaSiNoExiste(int $personaId, string $tipoDesc, string $valor): void {
    $valor = trim($valor);
    if ($valor === '') return;
    $tipoId = $this->tipoIdPorDescripcion($tipoDesc);

    $q = $this->db->prepare(
      "SELECT 1 FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id=? AND valor=? LIMIT 1"
    );
    $q->execute([$personaId, $tipoId, $valor]);
    if ($q->fetchColumn()) return;

    $ins = $this->db->prepare(
      "INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)"
    );
    $ins->execute([$personaId, $tipoId, $valor]);
  }

  /**
   * Upsert empresa por CUIT y graba el mismo CUIT (sólo dígitos) como
   * contacto CUIT_EMPRESA en empresa y en persona, para compartir valor.
   */

  /** Devuelve true si por datos existentes el perfil puede considerarse “empresarial”. */
public function esPerfilEmpresarialPorDatos(int $personaId): bool
{
    // 1) Si tiene una empresa “principal” deducida por contactos espejo (CUIT_EMPRESA | EMAIL | Teléfono)
    if ($this->obtenerEmpresaPrincipalDePersona($personaId)) {
        return true;
    }

    // 2) Si tiene al menos una empresa relacionada por cualquier contacto compartido
    $rel = $this->listarEmpresasPorPersonaContactos($personaId);
    if (!empty($rel)) {
        return true;
    }

    // 3) (Opcional) Si alguno de sus vehículos también está asociado a una empresa
    //    (este join no cambia el esquema, solo consulta lo que ya existe)
    $sql = "
        SELECT 1
          FROM Vehiculos_Personas vp
          JOIN Vehiculos_Empresas ve ON ve.automoviles_id = vp.automoviles_id
         WHERE vp.Persona_id = :pid
         LIMIT 1
    ";
    $st = $this->db->prepare($sql);
    $st->execute([':pid' => $personaId]);
    if ($st->fetchColumn()) {
        return true;
    }

    return false;
}


  public function upsertEmpresaYVincularPorCUIT(
    int $personaId,
    string $razonSocial,
    string $cuit,                   // puede venir con guiones
    ?string $emailEmpresa = null,
    ?string $telEmpresa   = null
  ): int {
    $cuitDig = $this->normalizarCUIT($cuit);
    if ($cuitDig === '') throw new \RuntimeException('CUIT_INVALIDO');

    // 1) empresa por CUIT
    $empresaId = $this->buscarIdPorCUIT($cuitDig);
    if (!$empresaId) {
      $empresaId = $this->crear($razonSocial, $cuitDig);
    } else {
      // actualizar razón social si vino distinta
      $rs = trim($razonSocial);
      if ($rs !== '') {
        $upd = $this->db->prepare("UPDATE Empresas SET razon_social = ? WHERE id = ?");
        $upd->execute([$rs, $empresaId]);
      }
    }

    // 2) contactos empresa
    if ($emailEmpresa && filter_var($emailEmpresa, FILTER_VALIDATE_EMAIL)) {
      $this->agregarContacto($empresaId, 'EMAIL', mb_strtolower(trim($emailEmpresa)));
    }
    if ($telEmpresa && trim($telEmpresa) !== '') {
      $this->agregarContacto($empresaId, 'Teléfono', trim($telEmpresa)); // respeta tilde
    }
    // CUIT espejo en empresa
    $this->agregarContacto($empresaId, 'CUIT_EMPRESA', $cuitDig);

    // 3) contacto espejo en persona
    $this->agregarContactoPersonaSiNoExiste($personaId, 'CUIT_EMPRESA', $cuitDig);

    return $empresaId;
  }

  /* ===================== Consultas ===================== */

  /** Empresas relacionadas con la persona por compartir al menos UN contacto (mismo valor). */
  public function listarEmpresasPorPersonaContactos(int $personaId): array {
    $sql = "
      SELECT e.id, e.razon_social, e.CUIT
        FROM Empresas e
        JOIN Contactos_Empresas ce ON ce.Empresas_id = e.id
       WHERE ce.valor IN (
             SELECT valor FROM Contacto_Persona WHERE Persona_id = :pid
       )
       GROUP BY e.id, e.razon_social, e.CUIT
       ORDER BY e.razon_social ASC
    ";
    $st = $this->db->prepare($sql);
    $st->execute([':pid'=>$personaId]);
    return $st->fetchAll();
  }

  /** Una empresa “principal” (prioriza CUIT_EMPRESA, luego EMAIL, luego Teléfono). */
  public function obtenerEmpresaPrincipalDePersona(int $personaId): ?array {
    // 1) por CUIT_EMPRESA
    $sql1 = "
      SELECT e.*
        FROM Empresas e
        JOIN Contactos_Empresas ce ON ce.Empresas_id = e.id
        JOIN Tipos_Contactos t ON t.id = ce.Tipo_Contacto_id
       WHERE ce.valor IN (
              SELECT valor FROM Contacto_Persona cp
              JOIN Tipos_Contactos tp ON tp.id = cp.Tipo_Contacto_id
             WHERE cp.Persona_id = :pid AND UPPER(tp.descripcion) = 'CUIT_EMPRESA'
       )
       LIMIT 1
    ";
    $st = $this->db->prepare($sql1);
    $st->execute([':pid'=>$personaId]);
    $emp = $st->fetch();
    if ($emp) return $emp;

    // 2) por EMAIL
    $sql2 = "
      SELECT e.*
        FROM Empresas e
        JOIN Contactos_Empresas ce ON ce.Empresas_id = e.id
        JOIN Tipos_Contactos t ON t.id = ce.Tipo_Contacto_id
       WHERE UPPER(t.descripcion)='EMAIL'
         AND LOWER(ce.valor) IN (
              SELECT LOWER(valor) FROM Contacto_Persona cp
              JOIN Tipos_Contactos tp ON tp.id = cp.Tipo_Contacto_id
             WHERE cp.Persona_id = :pid AND UPPER(tp.descripcion)='EMAIL'
       )
       LIMIT 1
    ";
    $st = $this->db->prepare($sql2);
    $st->execute([':pid'=>$personaId]);
    $emp = $st->fetch();
    if ($emp) return $emp;

    // 3) por Teléfono (respeta tilde)
    $sql3 = "
      SELECT e.*
        FROM Empresas e
        JOIN Contactos_Empresas ce ON ce.Empresas_id = e.id
        JOIN Tipos_Contactos t ON t.id = ce.Tipo_Contacto_id
       WHERE t.descripcion='Teléfono'
         AND ce.valor IN (
              SELECT valor FROM Contacto_Persona cp
              JOIN Tipos_Contactos tp ON tp.id = cp.Tipo_Contacto_id
             WHERE cp.Persona_id = :pid AND tp.descripcion='Teléfono'
       )
       LIMIT 1
    ";
    $st = $this->db->prepare($sql3);
    $st->execute([':pid'=>$personaId]);
    $emp = $st->fetch();
    return $emp ?: null;
  }

  /** Seguridad: ¿esta persona puede operar con esta empresa? (contacto compartido) */
  public function personaPuedeUsarEmpresa(int $empresaId, int $personaId): bool {
    $sql = "
      SELECT 1
        FROM Contactos_Empresas ce
       WHERE ce.Empresas_id = :eid
         AND ce.valor IN (SELECT valor FROM Contacto_Persona WHERE Persona_id = :pid)
       LIMIT 1
    ";
    $st = $this->db->prepare($sql);
    $st->execute([':eid'=>$empresaId, ':pid'=>$personaId]);
    return (bool)$st->fetchColumn();
  }
}
