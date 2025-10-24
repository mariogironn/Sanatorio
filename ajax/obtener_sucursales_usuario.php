<?php
// ajax/obtener_sucursales_usuario.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require '../config/connection.php';

header('Content-Type: application/json; charset=UTF-8');

$uid = (int)($_GET['user_id'] ?? 0);
if ($uid <= 0) { echo json_encode(['ok'=>false,'msg'=>'user_id inválido']); exit; }

try {
  $st = $con->prepare("
    SELECT us.id_sucursal AS id, s.nombre
    FROM usuario_sucursal us
    JOIN sucursales s ON s.id = us.id_sucursal
    WHERE us.id_usuario = :u
    ORDER BY s.nombre
  ");
  $st->execute([':u'=>$uid]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'ok'    => true,
    'ids'   => array_map(fn($r)=>(int)$r['id'], $rows),
    'items' => $rows
  ]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
