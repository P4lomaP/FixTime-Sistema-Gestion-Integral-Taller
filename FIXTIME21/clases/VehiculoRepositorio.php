<?php
declare(strict_types=1);
require_once __DIR__ . '/Conexion.php';
require_once __DIR__ . '/EmpresaRepositorio.php';

/**
 * Repositorio de vehículos.
 * Compatible con el esquema provisto (sin cambios de DB).
 */
final class VehiculoRepositorio {
  private \PDO $db;

  public function __construct() {
    $this->db = Conexion::obtener();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }

  /* ================= MARCAS / MODELOS ================= */

  private function getOrCreateMarca(string $desc): int {
    $desc = trim($desc);
    if ($desc === '') throw new \InvalidArgumentException('Marca vacía');
    $st = $this->db->prepare("SELECT id FROM Marcas_Automoviles WHERE descripcion = ? LIMIT 1");
    $st->execute([$desc]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $ins = $this->db->prepare("INSERT INTO Marcas_Automoviles (descripcion) VALUES (?)");
    $ins->execute([$desc]);
    return (int)$this->db->lastInsertId();
  }

  private function getOrCreateModelo(string $desc, int $marcaId): int {
    $desc = trim($desc);
    if ($desc === '') throw new \InvalidArgumentException('Modelo vacío');
    $st = $this->db->prepare("SELECT id FROM Modelos_Automoviles WHERE descripcion = ? AND Marca_Automvil_id = ? LIMIT 1");
    $st->execute([$desc, $marcaId]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $ins = $this->db->prepare("INSERT INTO Modelos_Automoviles (descripcion, Marca_Automvil_id) VALUES (?,?)");
    $ins->execute([$desc, $marcaId]);
    return (int)$this->db->lastInsertId();
  }

  /* ================= DEDUP / HELPERS ================= */

  private function normalizarPatente(string $p): string {
    $p = strtoupper(preg_replace('/[^A-Z0-9]/','',trim($p)));
    return $p;
  }

  private function existePatente(string $patente, ?int $excluirAutoId = null): bool {
    $patente = $this->normalizarPatente($patente);
    if ($excluirAutoId) {
      $st = $this->db->prepare("SELECT 1 FROM Automoviles WHERE patente = ? AND id <> ? LIMIT 1");
      $st->execute([$patente, $excluirAutoId]);
    } else {
      $st = $this->db->prepare("SELECT 1 FROM Automoviles WHERE patente = ? LIMIT 1");
      $st->execute([$patente]);
    }
    return (bool)$st->fetchColumn();
  }

  private function existeVehiculoIgualParaPersona(int $personaId, string $marca, string $modelo, int $anio, string $color, ?int $excluirAutoId = null): bool {
    $sql = "
      SELECT 1
        FROM Vehiculos_Personas vp
        JOIN Automoviles a ON a.id = vp.automoviles_id
        JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
        JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
       WHERE vp.Persona_id = :pid
         AND ma.descripcion = :marca
         AND mo.descripcion = :modelo
         AND a.anio = :anio
         AND a.color = :color
    ";
    $params = [':pid'=>$personaId, ':marca'=>$marca, ':modelo'=>$modelo, ':anio'=>$anio, ':color'=>$color];
    if ($excluirAutoId) { $sql .= " AND a.id <> :aid"; $params[':aid'] = $excluirAutoId; }
    $sql .= " LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute($params);
    return (bool)$st->fetchColumn();
  }

  private function existeVehiculoIgualParaEmpresa(int $empresaId, string $marca, string $modelo, int $anio, string $color, ?int $excluirAutoId = null): bool {
    $sql = "
      SELECT 1
        FROM Vehiculos_Empresas ve
        JOIN Automoviles a ON a.id = ve.automoviles_id
        JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
        JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
       WHERE ve.Empresas_id = :eid
         AND ma.descripcion = :marca
         AND mo.descripcion = :modelo
         AND a.anio = :anio
         AND a.color = :color
    ";
    $params = [':eid'=>$empresaId, ':marca'=>$marca, ':modelo'=>$modelo, ':anio'=>$anio, ':color'=>$color];
    if ($excluirAutoId) { $sql .= " AND a.id <> :aid"; $params[':aid'] = $excluirAutoId; }
    $sql .= " LIMIT 1";
    $st = $this->db->prepare($sql);
    $st->execute($params);
    return (bool)$st->fetchColumn();
  }

  /* ================= ALTAS ================= */

  public function crearParaPersona(
    int $personaId,
    string $marca,
    string $modelo,
    int $anio,
    int $km,
    string $color,
    string $patente,
    ?string $descripcion_extra,
    ?string $foto_frente,
    ?string $foto_trasera
  ): int {
    $patente = $this->normalizarPatente($patente);
    if ($this->existePatente($patente)) throw new \RuntimeException('PATENTE_DUPLICADA');
    if ($this->existeVehiculoIgualParaPersona($personaId, $marca, $modelo, $anio, $color)) {
      throw new \RuntimeException('VEHICULO_DUPLICADO');
    }

    $this->db->beginTransaction();
    try {
      $marcaId  = $this->getOrCreateMarca($marca);
      $modeloId = $this->getOrCreateModelo($modelo, $marcaId);

      $ins = $this->db->prepare("
        INSERT INTO Automoviles (descripcion, anio, km, color, patente, descripcion_extra, foto_Cedula_Frente, foto_Cedula_Trasera, Modelo_Automovil_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $desc = trim($marca.' '.$modelo);
      $ins->execute([$desc, (string)$anio, (string)$km, $color, $patente, $descripcion_extra, $foto_frente, $foto_trasera, $modeloId]);
      $autoId = (int)$this->db->lastInsertId();

      $link = $this->db->prepare("INSERT INTO Vehiculos_Personas (Persona_id, automoviles_id) VALUES (?, ?)");
      $link->execute([$personaId, $autoId]);

      $this->db->commit();
      return $autoId;
    } catch (\Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }
  }

  public function crearParaEmpresa(
    int $empresaId,
    int $personaIdEjecutor,
    string $marca,
    string $modelo,
    int $anio,
    int $km,
    string $color,
    string $patente,
    ?string $descripcion_extra,
    ?string $foto_frente,
    ?string $foto_trasera
  ): int {
    // Seguridad: verificar que la persona tenga contacto compartido con la empresa
    $repoE = new EmpresaRepositorio();
    if (!$repoE->personaPuedeUsarEmpresa($empresaId, $personaIdEjecutor)) {
      throw new \RuntimeException('PERMISO_EMPRESA');
    }

    $patente = $this->normalizarPatente($patente);
    if ($this->existePatente($patente)) throw new \RuntimeException('PATENTE_DUPLICADA');
    if ($this->existeVehiculoIgualParaEmpresa($empresaId, $marca, $modelo, $anio, $color)) {
      throw new \RuntimeException('VEHICULO_DUPLICADO');
    }

    $this->db->beginTransaction();
    try {
      $marcaId  = $this->getOrCreateMarca($marca);
      $modeloId = $this->getOrCreateModelo($modelo, $marcaId);

      $ins = $this->db->prepare("
        INSERT INTO Automoviles (descripcion, anio, km, color, patente, descripcion_extra, foto_Cedula_Frente, foto_Cedula_Trasera, Modelo_Automovil_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $desc = trim($marca.' '.$modelo);
      $ins->execute([$desc, (string)$anio, (string)$km, $color, $patente, $descripcion_extra, $foto_frente, $foto_trasera, $modeloId]);
      $autoId = (int)$this->db->lastInsertId();

      $link = $this->db->prepare("INSERT INTO Vehiculos_Empresas (Empresas_id, automoviles_id) VALUES (?, ?)");
      $link->execute([$empresaId, $autoId]);

      $this->db->commit();
      return $autoId;
    } catch (\Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }
  }

  /* ================= ACTUALIZAR ================= */

  public function actualizar(int $autoId, int $personaIdSolicitante, array $data): void {
    // ¿El auto es personal del solicitante?
    $st = $this->db->prepare("SELECT 1 FROM Vehiculos_Personas WHERE Persona_id = ? AND automoviles_id = ? LIMIT 1");
    $st->execute([$personaIdSolicitante, $autoId]);
    $esPersonal = (bool)$st->fetchColumn();

    if (!$esPersonal) {
      // si está vinculado a empresa, bloquear
      $chk = $this->db->prepare("SELECT Empresas_id FROM Vehiculos_Empresas WHERE automoviles_id = ? LIMIT 1");
      $chk->execute([$autoId]);
      if ($chk->fetchColumn()) {
        throw new \RuntimeException('PERMISO_DENEGADO');
      } else {
        throw new \RuntimeException('PERMISO_DENEGADO');
      }
    }

    $marca  = trim((string)($data['marca'] ?? ''));
    $modelo = trim((string)($data['modelo'] ?? ''));
    $anio   = (int)($data['anio'] ?? 0);
    $km     = (int)($data['km'] ?? 0);
    $color  = trim((string)($data['color'] ?? ''));
    $pat    = $this->normalizarPatente((string)($data['patente'] ?? ''));
    $desc   = trim((string)($data['descripcion_extra'] ?? ''));
    $fr     = $data['cedula_frente'] ?? null;
    $dr     = $data['cedula_trasera'] ?? null;

    if ($this->existePatente($pat, $autoId)) throw new \RuntimeException('PATENTE_DUPLICADA');
    if ($this->existeVehiculoIgualParaPersona($personaIdSolicitante, $marca, $modelo, $anio, $color, $autoId)) {
      throw new \RuntimeException('VEHICULO_DUPLICADO');
    }

    $marcaId  = $this->getOrCreateMarca($marca);
    $modeloId = $this->getOrCreateModelo($modelo, $marcaId);

    $set = "descripcion = :d, anio = :anio, km = :km, color = :color, patente = :pat, descripcion_extra = :dex, Modelo_Automovil_id = :mid";
    $params = [
      ':d'=>trim($marca.' '.$modelo),
      ':anio'=>(string)$anio,
      ':km'=>(string)$km,
      ':color'=>$color,
      ':pat'=>$pat,
      ':dex'=>$desc,
      ':mid'=>$modeloId,
      ':id'=>$autoId
    ];

    if ($fr) { $set .= ", foto_Cedula_Frente = :fr"; $params[':fr'] = $fr; }
    if ($dr) { $set .= ", foto_Cedula_Trasera = :dr"; $params[':dr'] = $dr; }

    $sql = "UPDATE Automoviles SET $set WHERE id = :id";
    $up = $this->db->prepare($sql);
    $up->execute($params);
  }

  /* ================= ELIMINAR / DESVINCULAR ================= */

  public function eliminar(int $autoId, int $personaIdSolicitante): int {
    $st = $this->db->prepare("SELECT 1 FROM Vehiculos_Personas WHERE Persona_id = ? AND automoviles_id = ? LIMIT 1");
    $st->execute([$personaIdSolicitante, $autoId]);
    if (!$st->fetchColumn()) return 0;

    $this->db->beginTransaction();
    try {
      $del = $this->db->prepare("DELETE FROM Vehiculos_Personas WHERE Persona_id = ? AND automoviles_id = ?");
      $del->execute([$personaIdSolicitante, $autoId]);

      $q1 = $this->db->prepare("SELECT 1 FROM Vehiculos_Personas WHERE automoviles_id = ? LIMIT 1");
      $q1->execute([$autoId]);
      $quedanPersonas = (bool)$q1->fetchColumn();

      $q2 = $this->db->prepare("SELECT 1 FROM Vehiculos_Empresas WHERE automoviles_id = ? LIMIT 1");
      $q2->execute([$autoId]);
      $quedanEmpresas = (bool)$q2->fetchColumn();

      if (!$quedanPersonas && !$quedanEmpresas) {
        try { $this->db->prepare("DELETE FROM Automoviles WHERE id = ?")->execute([$autoId]); }
        catch (\Throwable $e) { /* puede estar referenciado por turnos/órdenes */ }
      }

      $this->db->commit();
      return 1;
    } catch (\Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }
  }

  /* ================= LISTADOS ================= */

  /**
   * NUEVO: Listado para el panel del USUARIO (cliente).
   * - Vehículos personales del usuario
   * - Vehículos de empresas a las que tiene acceso por contacto compartido
   * Si $empresaId se pasa, limita a esa empresa.
   *
   * Devuelve: id_auto, marca, modelo, anio, km, color, patente, descripcion_extra,
   *           cedula_frente, cedula_trasera, titular ('Personal'|'Empresa'),
   *           empresa (razon_social|NULL), empresa_id (INT|NULL)
   */
  public function listarParaUsuario(int $personaId, ?int $empresaId = null): array {
    $res = [];

    // Personales
    $sqlP = "
      SELECT 
        a.id AS id_auto,
        ma.descripcion AS marca,
        mo.descripcion AS modelo,
        a.anio, a.km, a.color, a.patente, a.descripcion_extra,
        a.foto_Cedula_Frente AS cedula_frente,
        a.foto_Cedula_Trasera AS cedula_trasera,
        'Personal' AS titular,
        NULL AS empresa,
        NULL AS empresa_id
      FROM Vehiculos_Personas vp
      JOIN Automoviles a ON a.id = vp.automoviles_id
      JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
      JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
      WHERE vp.Persona_id = :pid
      ORDER BY a.id DESC
    ";
    $st = $this->db->prepare($sqlP);
    $st->execute([':pid'=>$personaId]);
    $res = $st->fetchAll();

    // Empresariales (todas o filtradas por empresa)
    $sqlE = "
      SELECT DISTINCT
        a.id AS id_auto,
        ma.descripcion AS marca,
        mo.descripcion AS modelo,
        a.anio, a.km, a.color, a.patente, a.descripcion_extra,
        a.foto_Cedula_Frente AS cedula_frente,
        a.foto_Cedula_Trasera AS cedula_trasera,
        'Empresa' AS titular,
        e.razon_social AS empresa,
        e.id AS empresa_id
      FROM Vehiculos_Empresas ve
      JOIN Empresas e ON e.id = ve.Empresas_id
      JOIN Automoviles a ON a.id = ve.automoviles_id
      JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
      JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
      WHERE EXISTS (
        SELECT 1 FROM Contactos_Empresas ce
         WHERE ce.Empresas_id = e.id
           AND ce.valor IN (SELECT valor FROM Contacto_Persona WHERE Persona_id = :pid)
      )
    ";
    $params = [':pid' => $personaId];
    if ($empresaId !== null) {
      $sqlE .= " AND e.id = :eid";
      $params[':eid'] = $empresaId;
    }
    $sqlE .= " ORDER BY a.id DESC";

    $st2 = $this->db->prepare($sqlE);
    $st2->execute($params);
    $emp = $st2->fetchAll();

    // Unir evitando duplicados
    $byId = [];
    foreach ($res as $r) $byId[$r['id_auto']] = true;
    foreach ($emp as $e) {
      if (!isset($byId[$e['id_auto']])) $res[] = $e;
    }

    return $res;
  }

  /**
   * Alias histórico para compatibilidad (mismo resultado que listarParaUsuario($personaId)).
   */
  public function listarParaPanel(int $personaId): array {
    return $this->listarParaUsuario($personaId, null);
  }

  /**
   * Listado global para Admin: TODOS los vehículos con su dueño (persona o empresa).
   * Campos: auto_id, patente, marca, modelo, anio, color, dueno, tipo_dueno
   */
  public function listarTodosConDueno(): array {
    $sql = "
      SELECT 
        a.id AS auto_id,
        a.patente,
        ma.descripcion AS marca,
        mo.descripcion AS modelo,
        a.anio,
        a.color,
        CONCAT(p.nombre,' ',p.apellido) AS dueno,
        'Persona' AS tipo_dueno
      FROM Automoviles a
      JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
      JOIN Marcas_Automoviles ma  ON ma.id = mo.Marca_Automvil_id
      JOIN Vehiculos_Personas vp  ON vp.automoviles_id = a.id
      JOIN Personas p             ON p.id = vp.Persona_id

      UNION ALL

      SELECT 
        a.id AS auto_id,
        a.patente,
        ma.descripcion AS marca,
        mo.descripcion AS modelo,
        a.anio,
        a.color,
        e.razon_social AS dueno,
        'Empresa' AS tipo_dueno
      FROM Automoviles a
      JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
      JOIN Marcas_Automoviles ma  ON ma.id = mo.Marca_Automvil_id
      JOIN Vehiculos_Empresas ve  ON ve.automoviles_id = a.id
      JOIN Empresas e             ON e.id = ve.Empresas_id

      ORDER BY tipo_dueno, dueno, patente
    ";
    $st = $this->db->query($sql);
    return $st->fetchAll();
  }
}
