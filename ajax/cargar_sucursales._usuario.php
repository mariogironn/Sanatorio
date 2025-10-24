<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require '../config/connection.php';
header('Content-Type: application/json; charset=UTF-8');

$uid = (int)($_GET['user_id'] ?? 0);
$out = ['ids'=>[]];
if ($uid <= 0) { echo json_encode($out); exit; }

try{
  $st = $con->prepare("SELECT id_sucursal FROM usuario_sucursal WHERE id_usuario = :u ORDER BY id_sucursal");
  $st->execute([':u'=>$uid]);
  $out['ids'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
} catch(Throwable $e){ /* nada */ }

echo json_encode($out);
