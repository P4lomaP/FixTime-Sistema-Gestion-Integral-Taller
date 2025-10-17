<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 3); // .../FIXTIME21
require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';

Sesion::requiereLogin();
$app  = require $ROOT . '/config/app.php';
$base = rtrim($app['base_url'], '/');

function hredir(string $url){ header('Location: ' . $url); exit; }

try {
    $db = Conexion::obtener();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Resolver persona/empleado del usuario logueado ---
    $uid = (int)($_SESSION['uid'] ?? 0);
    if (!$uid) throw new RuntimeException('Sesión inválida.');

    // Si uid es Persona.id -> persona_id = uid; si uid es Empleados.id -> buscar Persona_id
    $st = $db->prepare("SELECT id FROM Personas WHERE id=?");
    $st->execute([$uid]);
    $personaIdSesion = (int)$st->fetchColumn();

    if ($personaIdSesion <= 0) {
        $st = $db->prepare("SELECT Persona_id FROM Empleados WHERE id=?");
        $st->execute([$uid]);
        $personaIdSesion = (int)($st->fetchColumn() ?: 0);
    }
    if ($personaIdSesion <= 0) throw new RuntimeException('No se pudo resolver la persona en sesión.');

    // --- Datos POST ---
    $personaId = (int)($_POST['persona_id'] ?? 0);
    $empleadoId = (int)($_POST['empleado_id'] ?? 0);

    // Seguridad: solo puede editar su propio perfil
    if ($personaId !== $personaIdSesion) throw new RuntimeException('No tiene permisos para editar este perfil.');

    $nombre     = trim((string)($_POST['nombre'] ?? ''));
    $apellido   = trim((string)($_POST['apellido'] ?? ''));
    $dni        = trim((string)($_POST['dni'] ?? ''));
    $email      = trim((string)($_POST['email'] ?? ''));
    $telefonos  = $_POST['telefonos'] ?? [];
    if (!is_array($telefonos)) $telefonos = [];
    $telefonos  = array_values(array_filter(array_map('trim', $telefonos), fn($v)=>$v!==''));

    $pais         = trim((string)($_POST['pais'] ?? ''));
    $provincia    = trim((string)($_POST['provincia'] ?? ''));
    $localidad    = trim((string)($_POST['localidad'] ?? ''));
    $barrio       = trim((string)($_POST['barrio'] ?? ''));
    $calle        = trim((string)($_POST['calle'] ?? ''));
    $altura       = trim((string)($_POST['altura'] ?? ''));
    $piso         = trim((string)($_POST['piso'] ?? ''));
    $departamento = trim((string)($_POST['departamento'] ?? ''));

    if ($nombre === '' || $apellido === '') throw new RuntimeException('Nombre y Apellido son obligatorios.');

    // --- Helpers de introspección ---
    $tableExists = function(string $table) use ($db): bool {
        try {
            $db->query("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (Throwable $e) { return false; }
    };
    $columns = function(string $table) use ($db): array {
        try {
            return $db->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
        } catch (Throwable $e) { return []; }
    };

    $db->beginTransaction();

    // --- 1) Actualizar Personas (nombre, apellido, dni) ---
    $p = $db->prepare("UPDATE Personas SET nombre=:n, apellido=:a, dni=:dni WHERE id=:pid");
    $p->execute([':n'=>$nombre, ':a'=>$apellido, ':dni'=>$dni, ':pid'=>$personaId]);

    // --- 2) Email en Contacto_Persona (tipo = 'Email') ---
    if ($email !== '') {
        // buscar id tipo Email (case-insensitive)
        $tipoEmail = (int)($db->query("SELECT id FROM Tipos_Contactos WHERE LOWER(descripcion)='email' LIMIT 1")->fetchColumn() ?: 0);
        if (!$tipoEmail) throw new RuntimeException("No existe el tipo de contacto 'Email'.");

        // ¿ya tiene email?
        $st = $db->prepare("SELECT id FROM Contacto_Persona WHERE Persona_id=:pid AND Tipo_Contacto_id=:tid LIMIT 1");
        $st->execute([':pid'=>$personaId, ':tid'=>$tipoEmail]);
        $cid = (int)($st->fetchColumn() ?: 0);

        if ($cid) {
            $u = $db->prepare("UPDATE Contacto_Persona SET valor=:v WHERE id=:id");
            $u->execute([':v'=>$email, ':id'=>$cid]);
        } else {
            $i = $db->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (:pid,:tid,:v)");
            $i->execute([':pid'=>$personaId, ':tid'=>$tipoEmail, ':v'=>$email]);
        }
    }

    // --- 3) Teléfonos: borra los actuales (tel/cel) y vuelve a insertar ---
    // detecta tipos con descripcion que contenga 'tel' o 'cel' (case-insensitive)
    $tiposTel = $db->query("SELECT id FROM Tipos_Contactos WHERE LOWER(descripcion) LIKE '%tel%' OR LOWER(descripcion) LIKE '%cel%'")->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    if ($tiposTel) {
        $in = implode(',', array_fill(0, count($tiposTel), '?'));
        $del = $db->prepare("DELETE FROM Contacto_Persona WHERE Persona_id=? AND Tipo_Contacto_id IN ($in)");
        $del->execute(array_merge([$personaId], $tiposTel));
    }
    if ($telefonos) {
        // Si no hay tiposTel, intento crear uno simple 'Teléfono'
        if (!$tiposTel) {
            $db->prepare("INSERT INTO Tipos_Contactos (descripcion) VALUES ('Teléfono')")->execute();
            $tiposTel = [(int)$db->lastInsertId()];
        }
        $tipoTel = (int)$tiposTel[0];
        $ins = $db->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (:p,:t,:v)");
        foreach ($telefonos as $tel) {
            $ins->execute([':p'=>$personaId, ':t'=>$tipoTel, ':v'=>$tel]);
        }
    }

    // --- 4) Domicilio: si existen Personas_Domicilios + Domicilios, actualizo ahí; 
    // de lo contrario, si Personas tiene columnas simples, las actualizo ahí.
    $hasPD = $tableExists('Personas_Domicilios');
    $hasDom = $tableExists('Domicilios');

    if ($hasPD && $hasDom) {
        // buscar domicilio vinculado
        $st = $db->prepare("SELECT Domicilio_id FROM Personas_Domicilios WHERE Persona_id=? LIMIT 1");
        $st->execute([$personaId]);
        $domId = (int)($st->fetchColumn() ?: 0);

        // columnas disponibles en Domicilios
        $domCols = $columns('Domicilios');
        $sets = []; $params = [];
        // seteamos solo campos de texto básicos (evitamos tocar FK a Barrios/Localidades si no corresponde)
        if (in_array('calle',$domCols,true))         { $sets[]="calle=:calle"; $params[':calle']=$calle; }
        if (in_array('altura',$domCols,true))        { $sets[]="altura=:altura"; $params[':altura']=$altura; }
        if (in_array('piso',$domCols,true))          { $sets[]="piso=:piso"; $params[':piso']=$piso; }
        if (in_array('departamento',$domCols,true))  { $sets[]="departamento=:dto"; $params[':dto']=$departamento; }

        // Si tenés columnas libres para texto (pais/provincia/localidad/barrio) en Domicilios, también:
        foreach (['pais','provincia','localidad','barrio'] as $txtCol) {
            if (in_array($txtCol, $domCols, true)) {
                $sets[] = "{$txtCol} = :{$txtCol}";
                $params[":{$txtCol}"] = ${$txtCol};
            }
        }

        if ($sets) {
            if ($domId) {
                $sql = "UPDATE Domicilios SET ".implode(',', $sets)." WHERE id=:id";
                $params[':id'] = $domId;
                $db->prepare($sql)->execute($params);
            } else {
                $sql = "INSERT INTO Domicilios SET ".implode(',', $sets);
                $db->prepare($sql)->execute($params);
                $domId = (int)$db->lastInsertId();
                // vincular
                $db->prepare("INSERT INTO Personas_Domicilios (Persona_id, Domicilio_id) VALUES (?, ?)")->execute([$personaId, $domId]);
            }
        }
    } else {
        // Fallback: si Personas tiene columnas simples de domicilio, actualizarlas allí
        $pCols = $columns('Personas');
        $sets = []; $params = [':pid'=>$personaId];
        foreach ([
            'pais' => $pais, 'provincia' => $provincia, 'localidad' => $localidad, 'barrio' => $barrio,
            'calle' => $calle, 'altura' => $altura, 'piso' => $piso, 'departamento' => $departamento
        ] as $k=>$v) {
            if (in_array($k, $pCols, true)) { $sets[]="$k=:{$k}"; $params[":{$k}"]=$v; }
        }
        if ($sets) {
            $sql="UPDATE Personas SET ".implode(',', $sets)." WHERE id=:pid";
            $db->prepare($sql)->execute($params);
        }
    }

    $db->commit();
    hredir($base . '/modules/empleado/mecanico/?tab=perfil&ok=' . urlencode('Datos actualizados'));
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    hredir($base . '/modules/empleado/mecanico/?tab=perfil&error=' . urlencode($e->getMessage()));
}
