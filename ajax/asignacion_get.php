<?php
session_start();
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$r=['success'=>false,'data'=>[],'message'=>''];
try{
  $id=(int)($_POST['id_asignacion'] ?? 0);
  if ($id<=0) throw new Exception('ID inválido');

  $sql="SELECT me.*, u.nombre_mostrar AS medico_nombre, m.colegiado, e.nombre AS especialidad_nombre
        FROM medico_especialidad me
        JOIN medicos m  ON m.id_medico = me.medico_id
        JOIN usuarios u ON u.id       = m.usuario_id
        JOIN especialidades e ON e.id_especialidad = me.especialidad_id
        WHERE me.id_asignacion=?";
  $st=$con->prepare($sql); $st->execute([$id]);
  $row=$st->fetch(PDO::FETCH_ASSOC);
  if(!$row) throw new Exception('No encontrado');

  $r['success']=true; $r['data']=$row;
} catch (Throwable $e){ $r['message']=$e->getMessage(); }
echo json_encode($r, JSON_UNESCAPED_UNICODE);
