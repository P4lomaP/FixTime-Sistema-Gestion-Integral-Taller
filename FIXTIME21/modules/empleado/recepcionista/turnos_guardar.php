<?php
declare(strict_types=1);
$ROOT = dirname(__DIR__, 3);

require_once $ROOT . '/clases/Sesion.php';
require_once $ROOT . '/clases/Conexion.php';

Sesion::requiereLogin();

$app   = require $ROOT . '/config/app.php';
$base  = rtrim($app['base_url'], '/');
$PANEL = $base . '/modules/empleado/recepcionista/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: '.$PANEL.'?error='.urlencode('Método inválido').'&tab=turnos'); exit;
}

$id          = (int)($_POST['id'] ?? 0);
$autoId      = (int)($_POST['automovil_id'] ?? 0);
$fecha       = trim((string)($_POST['fecha'] ?? ''));
$hora        = trim((string)($_POST['hora'] ?? ''));
$estadoId    = (int)($_POST['estado_id'] ?? 0);
$motivo      = trim((string)($_POST['motivo'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));

if ($autoId<=0 || $fecha==='' || $hora==='' || $motivo==='') {
  header('Location: '.$PANEL.'?error='.urlencode('Completá vehículo, fecha, hora y motivo.').'&tab=turnos'); exit;
}

try{
  $db = Conexion::obtener();
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Si no viene estado, default a "pendiente asignacion"
  if ($estadoId<=0) {
    $st=$db->prepare("SELECT id FROM Estados_Turnos WHERE descripcion='pendiente asignacion' LIMIT 1");
    $st->execute(); $estadoId=(int)($st->fetchColumn() ?: 0);
    if ($estadoId<=0) {
      $db->exec("INSERT INTO Estados_Turnos (descripcion) VALUES ('pendiente asignacion')");
      $estadoId=(int)$db->lastInsertId();
    }
  }

  if ($id>0){
    $sql="UPDATE Turnos 
          SET fecha_turno=?, hora_turno=?, Estado_Turno_id=?, Automovil_id=?, motivo=?, descripcion=? 
          WHERE id=?";
    $st=$db->prepare($sql);
    $st->execute([$fecha,$hora,$estadoId,$autoId,$motivo,$descripcion,$id]);
    $msg='Turno actualizado.';

    $turnoId = $id; // guardar ID para enviar correo
    // 🔹 Buscar email del cliente a partir del turno
$sql = "
    SELECT p.nombre, p.apellido, c.valor AS email, a.descripcion AS auto
    FROM Turnos t
    JOIN Automoviles a        ON a.id = t.Automovil_id
    JOIN Vehiculos_Personas vp ON vp.automoviles_id = a.id
    JOIN Personas p           ON p.id = vp.Persona_id
    JOIN Contacto_Persona c   ON c.Persona_id = p.id
    JOIN Tipos_Contactos tc   ON tc.id = c.Tipo_Contacto_id
    WHERE t.id = ? AND tc.descripcion = 'Email'
    LIMIT 1
";
$st = $db->prepare($sql);
$st->execute([$turnoId]);
$cliente = $st->fetch(PDO::FETCH_ASSOC);

if ($cliente && filter_var($cliente['email'], FILTER_VALIDATE_EMAIL)) {
    $to = $cliente['email'];
    $subject = "Fixtime - Turno asignado";
    $message = "
        <h2>Hola {$cliente['nombre']} {$cliente['apellido']},</h2>
        <p>Se te asignó un turno con estos datos:</p>
        <ul>
            <li><b>Vehículo:</b> {$cliente['auto']}</li>
            <li><b>Fecha:</b> $fecha</li>
            <li><b>Hora:</b> $hora</li>
            <li><b>Motivo:</b> $motivo</li>
        </ul>
        <p>Podés ingresar a tu panel de cliente para confirmar o solicitar una reprogramación.</p>
        <br>
        <p>Saludos,<br>Equipo Fixtime</p>
    ";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Fixtime <no-reply@fixtimear.site>\r\n";

    @mail($to, $subject, $message, $headers);
}


}else{
    $sql="INSERT INTO Turnos (fecha_turno, hora_turno, Estado_Turno_id, Automovil_id, motivo, descripcion) 
          VALUES (?,?,?,?,?,?)";
    $st=$db->prepare($sql);
    $st->execute([$fecha,$hora,$estadoId,$autoId,$motivo,$descripcion]);
    $msg='Turno creado.';

    $turnoId = (int)$db->lastInsertId(); // nuevo turno
}


  header('Location: '.$PANEL.'?ok='.urlencode($msg).'&tab=turnos'); exit;

}catch(Throwable $e){
  header('Location: '.$PANEL.'?error='.urlencode('No se pudo guardar el turno: '.$e->getMessage()).'&tab=turnos'); exit;
}
