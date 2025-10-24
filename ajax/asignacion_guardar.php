<?php
session_start();
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$r=['success'=>false,'message'=>''];
try{
  $id_asig   = $_POST['id_asignacion'] ?? null;
  $medico_id = (int)($_POST['medico_id'] ?? 0);
  $esp_id    = (int)($_POST['especialidad_id'] ?? 0);
  $tipo      = trim($_POST['tipo_personal'] ?? 'Médico');
  $estado    = strtolower(trim($_POST['estado'] ?? 'activa'));
  $ambito    = trim($_POST['descripcion_ambito'] ?? '');
  $fecha     = $_POST['fecha_certificacion'] ?? null;

  if ($medico_id<=0 || $esp_id<=0) throw new Exception('Seleccione personal y especialidad');
  if (!in_array($estado,['activa','inactiva','capacitacion'],true)) $estado='activa';

  if ($id_asig) {
    $sql="UPDATE medico_especialidad
          SET tipo_personal=?, estado=?, descripcion_ambito=?, fecha_certificacion=?, updated_at=NOW()
          WHERE id_asignacion=?";
    $st=$con->prepare($sql);
    $st->execute([$tipo,$estado,$ambito,($fecha ?: null),$id_asig]);
    $r['message']='Asignación actualizada';
  } else {
    // evitar duplicados por UNIQUE (medico_id,especialidad_id)
    $sql="INSERT INTO medico_especialidad (medico_id,especialidad_id,tipo_personal,estado,descripcion_ambito,fecha_certificacion,created_at)
          VALUES (?,?,?,?,?,?,NOW())";
    $st=$con->prepare($sql);
    $st->execute([$medico_id,$esp_id,$tipo,$estado,$ambito,($fecha ?: null)]);
    $r['message']='Especialidad asignada';
  }
  $r['success']=true;
} catch (Throwable $e){ $r['message']=$e->getMessage(); }
echo json_encode($r, JSON_UNESCAPED_UNICODE);
