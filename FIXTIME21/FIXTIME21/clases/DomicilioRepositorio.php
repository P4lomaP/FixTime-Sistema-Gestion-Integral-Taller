<?php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

final class DomicilioRepositorio {
  private \PDO $db;

  public function __construct() {
    $this->db = Conexion::obtener();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
  }

  /** Upsert del domicilio de la persona usando Personas_Domicilios como vínculo.
   *  - Crea (si no existen) Pais/Provincia/Localidad/Barrio por descripcion.
   *  - Inserta o actualiza la fila en Domicilios (calle/altura/piso/dto).
   *  - Mantiene el último vínculo en Personas_Domicilios.
   */
  public function guardarDomicilio(int $personaId, array $dom): void {
    if (!$this->tablaExiste('Domicilios') || !$this->tablaExiste('Personas_Domicilios')) return;

    $pais       = trim((string)($dom['pais'] ?? ''));
    $provincia  = trim((string)($dom['provincia'] ?? ''));
    $localidad  = trim((string)($dom['localidad'] ?? ''));
    $barrio     = trim((string)($dom['barrio'] ?? ''));
    $calle      = trim((string)($dom['calle'] ?? ''));
    $altura     = trim((string)($dom['altura'] ?? ''));
    $piso       = trim((string)($dom['piso'] ?? ''));
    $dto        = trim((string)($dom['departamento'] ?? ''));

    $this->db->beginTransaction();

    $barrioId = null;
    if ($pais !== '' && $provincia !== '' && $localidad !== '' && $barrio !== '') {
      $barrioId = $this->resolverBarrioId($pais, $provincia, $localidad, $barrio);
    }

    $st = $this->db->prepare("SELECT Domicilio_id FROM Personas_Domicilios WHERE Persona_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$personaId]);
    $domId = (int)($st->fetchColumn() ?: 0);

    if ($domId > 0) {
      // UPDATE
      $sql = "UPDATE Domicilios
                 SET Barrio_id = :bid,
                     calle = :calle,
                     altura = :altura,
                     piso = :piso,
                     departamento = :dto
               WHERE id = :id";
      $up = $this->db->prepare($sql);
      $up->execute([
        ':bid' => $barrioId ?: null,
        ':calle' => $calle, ':altura' => $altura, ':piso' => $piso, ':dto' => $dto,
        ':id' => $domId
      ]);
    } else {
      // INSERT y crear vínculo
      $ins = $this->db->prepare("
        INSERT INTO Domicilios (Barrio_id, calle, altura, piso, departamento)
        VALUES (:bid, :calle, :altura, :piso, :dto)
      ");
      $ins->execute([
        ':bid' => $barrioId ?: null,
        ':calle' => $calle, ':altura' => $altura, ':piso' => $piso, ':dto' => $dto
      ]);
      $nuevoId = (int)$this->db->lastInsertId();

      $this->db->prepare("
        INSERT INTO Personas_Domicilios (Persona_id, Domicilio_id)
        VALUES (?, ?)
      ")->execute([$personaId, $nuevoId]);
    }

    $this->db->commit();
  }

  /* ===================== Helpers ===================== */

  /** Busca/crea Barrio (y su cadena padre) y devuelve el id. */
  private function resolverBarrioId(?string $pais, ?string $provincia, ?string $localidad, ?string $barrio): ?int {
    // Paises
    $paisId = null;
    if ($this->tablaExiste('Paises')) {
      $paisId = $this->buscarOCrear('Paises', 'descripcion', $pais ?: 'Desconocido');
    }

    // Provincias
    $provId = null;
    if ($this->tablaExiste('Provincias')) {
      if (!$paisId) $paisId = $this->buscarOCrear('Paises', 'descripcion', 'Desconocido');
      // provincia con FK a Paises
      $st = $this->db->prepare("SELECT id FROM Provincias WHERE descripcion = ? AND Pais_id = ? LIMIT 1");
      $st->execute([$provincia ?: 'Desconocida', $paisId]);
      $provId = (int)($st->fetchColumn() ?: 0);
      if (!$provId) {
        $ins = $this->db->prepare("INSERT INTO Provincias (descripcion, Pais_id) VALUES (?, ?)");
        $ins->execute([$provincia ?: 'Desconocida', $paisId]);
        $provId = (int)$this->db->lastInsertId();
      }
    }

    // Localidades
    $locId = null;
    if ($this->tablaExiste('Localidades')) {
      if (!$provId) {
        // fallback mínimo
        $provId = $this->buscarOCrear('Provincias', 'descripcion', 'Desconocida');
        // asegurar Pais_id si existe columna
        try {
          $this->db->prepare("UPDATE Provincias SET Pais_id = ? WHERE id = ? AND (Pais_id IS NULL OR Pais_id = 0)")
                   ->execute([$paisId, $provId]);
        } catch (\Throwable $e) {}
      }
      $st = $this->db->prepare("SELECT id FROM Localidades WHERE descripcion = ? AND Provincia_id = ? LIMIT 1");
      $st->execute([$localidad ?: 'Desconocida', $provId]);
      $locId = (int)($st->fetchColumn() ?: 0);
      if (!$locId) {
        $ins = $this->db->prepare("INSERT INTO Localidades (descripcion, Provincia_id) VALUES (?, ?)");
        $ins->execute([$localidad ?: 'Desconocida', $provId]);
        $locId = (int)$this->db->lastInsertId();
      }
    }

    // Barrios
    if ($this->tablaExiste('Barrios')) {
      if (!$locId) {
        $locId = $this->buscarOCrear('Localidades', 'descripcion', 'Desconocida');
        // asegurar Provincia_id si existe
        try {
          $this->db->prepare("UPDATE Localidades SET Provincia_id = ? WHERE id = ? AND (Provincia_id IS NULL OR Provincia_id = 0)")
                   ->execute([$provId, $locId]);
        } catch (\Throwable $e) {}
      }
      $st = $this->db->prepare("SELECT id FROM Barrios WHERE descripcion = ? AND Localidad_id = ? LIMIT 1");
      $st->execute([$barrio ?: 'Desconocido', $locId]);
      $barId = (int)($st->fetchColumn() ?: 0);
      if (!$barId) {
        $ins = $this->db->prepare("INSERT INTO Barrios (descripcion, Localidad_id) VALUES (?, ?)");
        $ins->execute([$barrio ?: 'Desconocido', $locId]);
        $barId = (int)$this->db->lastInsertId();
      }
      return $barId;
    }

    return null; // si no existe Barrios
  }

  /** Busca por igualdad exacta (colación de tu DB es unicode_ci) y crea si no existe. */
  private function buscarOCrear(string $tabla, string $col, string $valor): int {
    $sqlSel = "SELECT id FROM `$tabla` WHERE `$col` = ? LIMIT 1";
    $st = $this->db->prepare($sqlSel);
    $st->execute([$valor]);
    $id = (int)($st->fetchColumn() ?: 0);
    if ($id) return $id;

    $sqlIns = "INSERT INTO `$tabla` (`$col`) VALUES (?)";
    $this->db->prepare($sqlIns)->execute([$valor]);
    return (int)$this->db->lastInsertId();
  }

  private function tablaExiste(string $tabla): bool {
    try { $this->db->query("SELECT 1 FROM `$tabla` LIMIT 1"); return true; }
    catch (\Throwable $e) { return false; }
  }
}
