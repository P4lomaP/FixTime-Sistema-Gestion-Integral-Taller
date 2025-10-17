<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/clases/Sesion.php';
require_once dirname(__DIR__, 3) . '/clases/Conexion.php';
Sesion::requiereLogin(); $db=Conexion::obtener(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$oid=(int)($_POST['orden_id']??0);
$tid=(int)($_POST['turno_id']??0);
$mot=trim((string)($_POST['motivo']??''));
$des=trim((string)($_POST['descripcion']??''));
$km = isset($_POST['km'])? (int)$_POST['km'] : null;
$ti = isset($_POST['tiempo_estimado'])? (float)$_POST['tiempo_estimado'] : null;
$co = isset($_POST['costo_estimado'])? (float)$_POST['costo_estimado'] : null;
$pr = trim((string)($_POST['prioridad']??''));

if(!$oid){ http_response_code(400); echo json_encode(['error'=>'orden_id requerido']); exit; }

// actualiza motivo/desc en Turnos (si te gusta mantener ahí)
if($tid && ($mot!=='' || $des!=='')){
  $db->prepare("UPDATE Turnos SET motivo=:m, descripcion=:d WHERE id=:id")->execute(['m'=>$mot,'d'=>$des,'id'=>$tid]);
}

// actualiza campos técnicos de OR si existen
$cols = $db->query("SHOW COLUMNS FROM ordenes_reparaciones")->fetchAll(PDO::FETCH_COLUMN,0);
$sets=[]; $params=['oid'=>$oid];
if(in_array('km',$cols,true) && $km!==null){ $sets[]="km=:km"; $params['km']=$km; }
if(in_array('tiempo_estimado',$cols,true) && $ti!==null){ $sets[]="tiempo_estimado=:te"; $params['te']=$ti; }
if(in_array('costo_estimado',$cols,true) && $co!==null){ $sets[]="costo_estimado=:ce"; $params['ce']=$co; }
if(in_array('prioridad',$cols,true) && $pr!==''){ $sets[]="prioridad=:pr"; $params['pr']=$pr; }
if($sets){
  $pk='id'; try{$r=$db->query("SHOW KEYS FROM ordenes_reparaciones WHERE Key_name='PRIMARY'")->fetch(PDO::FETCH_ASSOC); if(!empty($r['Column_name'])) $pk=$r['Column_name'];}catch(Throwable $e){}
  $sql="UPDATE ordenes_reparaciones SET ".implode(',', $sets)." WHERE `$pk`=:oid";
  $db->prepare($sql)->execute($params);
}

header('Content-Type: application/json'); echo json_encode(['ok'=>true]);
