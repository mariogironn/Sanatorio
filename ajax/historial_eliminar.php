<?php
// ajax/historial_eliminar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once '../config/connection.php';

$res = ['success'=>false,'message'=>''];

try{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { http_response_code(400); throw new Exception('ID inválido'); }

  $con->beginTransaction();

  // obtener cabecera asociada
  $q = $con->prepare("SELECT id_prescripcion FROM detalle_prescripciones WHERE id_detalle = :id");
  $q->execute([':id'=>$id]);
  $idPres = (int)$q->fetchColumn();
  if ($idPres <= 0) { throw new Exception('Registro no encontrado'); }

  // eliminar detalle
  $del = $con->prepare("DELETE FROM detalle_prescripciones WHERE id_detalle = :id");
  $del->execute([':id'=>$id]);

  // si la cabecera queda sin detalles, se limpia
  $cnt = $con->prepare("SELECT COUNT(*) FROM detalle_prescripciones WHERE id_prescripcion = :p");
  $cnt->execute([':p'=>$idPres]);
  if ((int)$cnt->fetchColumn() === 0) {
    $con->prepare("DELETE FROM prescripciones WHERE id_prescripcion = :p")->execute([':p'=>$idPres]);
  }

  $con->commit();
  $res['success'] = true;

} catch(Throwable $e){
  if ($con && $con->inTransaction()) $con->rollBack();
  if (!http_response_code()) http_response_code(400);
  $res['message'] = $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
