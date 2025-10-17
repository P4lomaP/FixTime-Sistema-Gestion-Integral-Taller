<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/clases/Sesion.php';
require_once dirname(__DIR__, 3) . '/clases/Conexion.php';
Sesion::requiereLogin(); $db=Conexion::obtener(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$oid=(int)($_POST['orden_id']??0);
$texto=trim((string)($_POST['texto']??''));
if(!$oid || $texto===''){ http_response_code(400); echo json_encode(['error'=>'params']); exit; }

// resolver empleado_id (autor)
$uid=(int)($_SESSION['uid'] ?? 0);
$st=$db->prepare("SELECT CASE WHEN EXISTS(SELECT 1 FROM Personas WHERE id=:u) THEN :u ELSE (SELECT Persona_id FROM Empleados WHERE id=:u LIMIT 1) END");
$st->execute(['u'=>$uid]);
$personaId=(int)($st->fetchColumn() ?: 0);
$empId=0;
if($personaId){
  $s=$db->prepare("SELECT id FROM Empleados WHERE Persona_id=? AND (fecha_baja IS NULL OR fecha_baja>CURDATE()) ORDER BY id DESC LIMIT 1");
  $s->execute([$personaId]); $empId=(int)($s->fetchColumn() ?: 0);
}

$db->prepare("INSERT INTO Observaciones_OR (Orden_id, Empleado_id, texto, fecha) VALUES (:o,:e,:t,NOW())")
   ->execute(['o'=>$oid,'e'=>$empId?:null,'t'=>$texto]);

header('Content-Type: application/json'); echo json_encode(['ok'=>true]);
