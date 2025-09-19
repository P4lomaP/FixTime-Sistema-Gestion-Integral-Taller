<?php
// clases/DomicilioRepositorio.php
declare(strict_types=1);

require_once __DIR__ . '/Conexion.php';

final class DomicilioRepositorio
{
    private static function norm(string $v): string {
        $v = trim($v);
        return preg_replace('/\s+/', ' ', $v);
    }
    private static function title(string $v): string {
        return mb_convert_case(self::norm($v), MB_CASE_TITLE, "UTF-8");
    }

    /** Para el perfil: devuelve todo el árbol del domicilio de la persona (o null) */
    public static function obtenerDomicilioCompletoPorPersona(int $personaId): ?array {
        $pdo = Conexion::obtener();
        $sql = "
          SELECT
              p.descripcion  AS pais,
              pr.descripcion AS provincia,
              l.descripcion  AS localidad,
              b.descripcion  AS barrio,
              d.calle, d.altura, d.piso, d.departamento
          FROM Personas pe
          LEFT JOIN Domicilios d   ON d.id = pe.domicilio_id
          LEFT JOIN Barrios b      ON b.id = d.Barrio_id
          LEFT JOIN Localidades l  ON l.id = b.Localidad_id
          LEFT JOIN Provincias pr  ON pr.id = l.Provincia_id
          LEFT JOIN Paises p       ON p.id = pr.Pais_id
          WHERE pe.id = ?
          LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$personaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ----- Find-or-create helpers (no duplican: primero buscan, si no existe crean)

    public static function findOrCreatePais(PDO $pdo, string $descripcion): int {
        $descripcion = self::title($descripcion ?: 'Argentina'); // default opcional
        $st = $pdo->prepare("SELECT id FROM Paises WHERE descripcion = ? LIMIT 1");
        $st->execute([$descripcion]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        $ins = $pdo->prepare("INSERT INTO Paises (descripcion) VALUES (?)");
        $ins->execute([$descripcion]);
        return (int)$pdo->lastInsertId();
    }

    public static function findOrCreateProvincia(PDO $pdo, string $desc, int $paisId): int {
        $desc = self::title($desc);
        $st = $pdo->prepare("SELECT id FROM Provincias WHERE Pais_id = ? AND descripcion = ? LIMIT 1");
        $st->execute([$paisId, $desc]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        $ins = $pdo->prepare("INSERT INTO Provincias (descripcion, Pais_id) VALUES (?, ?)");
        $ins->execute([$desc, $paisId]);
        return (int)$pdo->lastInsertId();
    }

    public static function findOrCreateLocalidad(PDO $pdo, string $desc, int $provId): int {
        $desc = self::title($desc);
        $st = $pdo->prepare("SELECT id FROM Localidades WHERE Provincia_id = ? AND descripcion = ? LIMIT 1");
        $st->execute([$provId, $desc]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        $ins = $pdo->prepare("INSERT INTO Localidades (descripcion, Provincia_id) VALUES (?, ?)");
        $ins->execute([$desc, $provId]);
        return (int)$pdo->lastInsertId();
    }

    public static function findOrCreateBarrio(PDO $pdo, string $desc, int $locId): int {
        $desc = self::title($desc);
        $st = $pdo->prepare("SELECT id FROM Barrios WHERE Localidad_id = ? AND descripcion = ? LIMIT 1");
        $st->execute([$locId, $desc]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        $ins = $pdo->prepare("INSERT INTO Barrios (descripcion, Localidad_id) VALUES (?, ?)");
        $ins->execute([$desc, $locId]);
        return (int)$pdo->lastInsertId();
    }

    public static function findOrCreateDomicilio(PDO $pdo, int $barrioId, string $calle, string $altura, string $piso = '', string $depto = ''): int {
        $calle = self::title($calle);
        $altura = self::norm($altura);
        $piso = self::norm($piso);
        $depto = self::norm($depto);

        // Reusar domicilio idéntico si existe
        $st = $pdo->prepare("
          SELECT id FROM Domicilios
           WHERE Barrio_id = ? AND calle = ? AND altura = ?
             AND COALESCE(piso,'') = ? AND COALESCE(departamento,'') = ?
           LIMIT 1
        ");
        $st->execute([$barrioId, $calle, $altura, $piso, $depto]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        $ins = $pdo->prepare("
          INSERT INTO Domicilios (Barrio_id, calle, altura, piso, departamento)
          VALUES (?, ?, ?, ?, ?)
        ");
        $ins->execute([$barrioId, $calle, $altura, $piso !== '' ? $piso : null, $depto !== '' ? $depto : null]);
        return (int)$pdo->lastInsertId();
    }
}
