<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connection.php';

try {
  if ($_SERVER['REQUEST_METHOD']!=='POST') { throw new Exception('Método no permitido'); }
  $id = (int)($_POST['id']??0);
  if ($id<=0) throw new Exception('ID inválido');

  $con->beginTransaction();
  $con->prepare("DELETE FROM enfermedad_bandera WHERE id_enfermedad=?")->execute([$id]);
  $con->prepare("DELETE FROM enfermedades WHERE id=?")->execute([$id]);
  $con->commit();

  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  if ($con && $con->inTransaction()) $con->rollBack();
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
