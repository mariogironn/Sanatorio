<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connection.php';

try {
  $nombre = trim($_POST['nombre'] ?? '');
  if ($nombre==='') throw new Exception('Nombre requerido');

  $q = $con->prepare("INSERT INTO categorias_enfermedad (nombre) VALUES (:n)");
  $q->execute([':n'=>$nombre]);
  echo json_encode(['success'=>true,'id'=>(int)$con->lastInsertId()]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
