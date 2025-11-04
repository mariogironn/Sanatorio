<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once '../config/connection.php';

$out = ['success'=>false,'message'=>'','data'=>null];

try{
  $id = (int)($_GET['id'] ?? 0);
  if($id<=0) throw new Exception('ID inválido');

  // detectar cómo se llama la FK en medicamentos_meta
  $cands = ['med_id','medicamento_id','id_medicamento','idMedicamento','medicina_id','id_medicina'];
  $place = implode(',', array_fill(0,count($cands),'?'));
  $q = $con->prepare(
    "SELECT COLUMN_NAME
       FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'medicamentos_meta'
        AND COLUMN_NAME IN ($place)
      LIMIT 1");
  $q->execute($cands);
  $fkcol = $q->fetchColumn();

  if($fkcol){
    $sql = "SELECT
              m.id, m.nombre_medicamento, m.principio_activo, m.stock_actual, m.stock_minimo,
              COALESCE(mm.presentacion,'') AS presentacion,
              COALESCE(mm.laboratorio,'')  AS laboratorio,
              COALESCE(mm.categoria,'')    AS categoria,
              COALESCE(mm.descripcion,'')  AS descripcion
            FROM medicamentos m
            LEFT JOIN medicamentos_meta mm ON mm.$fkcol = m.id
            WHERE m.id = :id
            LIMIT 1";
  }else{
    // meta inexistente: devolvemos vacíos
    $sql = "SELECT
              m.id, m.nombre_medicamento, m.principio_activo, m.stock_actual, m.stock_minimo,
              '' AS presentacion, '' AS laboratorio, '' AS categoria, '' AS descripcion
            FROM medicamentos m
            WHERE m.id = :id
            LIMIT 1";
  }

  $st = $con->prepare($sql);
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if(!$row) throw new Exception('Medicina no encontrada');

  $out = ['success'=>true,'data'=>$row];
}catch(Throwable $e){
  $out['message'] = $e->getMessage();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
