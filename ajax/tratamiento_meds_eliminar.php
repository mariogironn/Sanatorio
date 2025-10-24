<?php
// Eliminar un medicamento del tratamiento
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: application/json');

$TAB_TM = 'tratamiento_medicamentos';

try {
  $id = (int)($_POST['id'] ?? 0);
  if (!$id) { echo json_encode(['success'=>false,'message'=>'ID inválido']); exit; }

  $con->prepare("DELETE FROM `$TAB_TM` WHERE id=?")->execute([$id]);
  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
