<?php
session_start();
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$r=['success'=>false,'message'=>''];
try{
  $id=(int)($_POST['id_asignacion'] ?? 0);
  if ($id<=0) throw new Exception('ID inválido');
  $st=$con->prepare("DELETE FROM medico_especialidad WHERE id_asignacion=?");
  $st->execute([$id]);
  $r['success']=true; $r['message']='Asignación removida';
} catch (Throwable $e){ $r['message']=$e->getMessage(); }
echo json_encode($r, JSON_UNESCAPED_UNICODE);
