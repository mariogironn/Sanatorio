<?php
// ajax/get_medicina.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';

$res = ['success'=>false,'message'=>'','data'=>null];

try{
  $id = (int)($_GET['id'] ?? 0);
  if($id<=0) throw new Exception('ID inválido');

  $st = $con->prepare("SELECT id, nombre_medicamento, principio_activo, stock_actual, stock_minimo FROM medicamentos WHERE id = ?");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if(!$row) throw new Exception('Medicina no encontrada');

  $res['success'] = true;
  $res['data'] = $row;

}catch(Throwable $e){
  $res['message'] = $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
