<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../config/connection.php';

$res = ['success'=>false];

try{
  // Horarios activos
  $act = (int)$con->query("SELECT COUNT(*) FROM horarios WHERE estado=1")->fetchColumn();

  // Médicos con horario (distintos)
  $med = (int)$con->query("SELECT COUNT(DISTINCT medico_id) FROM horarios WHERE estado=1")->fetchColumn();

  // Ausencias del mes actual
  $ini = date('Y-m-01'); $fin = date('Y-m-d', strtotime($ini.' +1 month'));
  $aus = $con->prepare("SELECT COUNT(*) FROM horarios_bloqueos WHERE fecha>=? AND fecha<?");
  $aus->execute([$ini,$fin]);
  $ausencias = (int)$aus->fetchColumn();

  $res['success']=true;
  $res['kpis']=['activos'=>$act,'medicos'=>$med,'ausencias_mes'=>$ausencias];
}catch(Throwable $e){
  $res['success']=false; $res['message']='No se pudieron cargar los KPIs';
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
