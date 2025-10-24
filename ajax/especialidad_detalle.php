<?php
session_start();
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$r=['success'=>false,'data'=>[], 'message'=>''];
try{
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  if(!$id) throw new Exception('ID requerido');

  $sql="SELECT e.*, (SELECT COUNT(*) FROM medicos m WHERE m.especialidad_id=e.id_especialidad) medicos_asignados
        FROM especialidades e WHERE id_especialidad=?";
  $st=$con->prepare($sql); $st->execute([$id]);
  $row=$st->fetch(PDO::FETCH_ASSOC);
  if(!$row) throw new Exception('No encontrada');

  $r=['success'=>true,'data'=>$row];
}catch(Throwable $e){ $r['message']='Error: '.$e->getMessage(); }
echo json_encode($r, JSON_UNESCAPED_UNICODE);
