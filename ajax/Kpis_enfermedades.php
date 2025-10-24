<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connection.php';

try {
  $out = [
    'total' => (int)$con->query("SELECT COUNT(*) FROM enfermedades")->fetchColumn(),
    'act'   => (int)$con->query("SELECT COUNT(*) FROM enfermedades WHERE estado='activa'")->fetchColumn(),
    'ina'   => (int)$con->query("SELECT COUNT(*) FROM enfermedades WHERE estado='inactiva'")->fetchColumn(),
  ];
  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>true,'message'=>$e->getMessage()]);
}
