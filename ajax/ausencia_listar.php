<?php
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $sql = "SELECT
            a.id,
            a.id_usuario_medico AS medico_id,
            a.fecha_ausencia    AS fecha,
            DATE_FORMAT(a.hora_desde,'%H:%i') AS hora_inicio,
            DATE_FORMAT(a.hora_hasta,'%H:%i') AS hora_fin,
            a.motivo
          FROM ausencias_medicos a
          ORDER BY a.fecha_ausencia DESC, a.hora_desde ASC";
  $data = $con->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['success'=>true,'data'=>$data]);
} catch(Exception $e){
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
