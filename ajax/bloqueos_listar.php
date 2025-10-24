<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$out = ['success'=>false,'data'=>[],'message'=>''];
try{
  $st = $con->query("SELECT * FROM horarios_bloqueos ORDER BY fecha, hora_inicio");
  $out['success']=true;
  $out['data'] = $st->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){ $out['message']=$e->getMessage(); }
echo json_encode($out, JSON_UNESCAPED_UNICODE);
