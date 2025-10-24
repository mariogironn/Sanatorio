<?php
// ajax/medicos_catalogos.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $esp = $con->query("SELECT id, nombre FROM especialidades WHERE estado = 1 ORDER BY nombre")->fetchAll();
  $items = array_map(fn($x)=> ['id'=>(int)$x['id'],'text'=>$x['nombre']], $esp);
  echo json_encode(['especialidades'=>$items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['especialidades'=>[], 'error'=>'Catálogo no disponible'], JSON_UNESCAPED_UNICODE);
}
