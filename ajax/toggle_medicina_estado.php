<?php
// ajax/toggle_medicina_estado.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';

$out = ['success'=>false, 'message'=>'', 'estado'=>null];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
  $id = (int)($_POST['id'] ?? 0);
  $accion = strtolower(trim($_POST['accion'] ?? ''));
  if ($id <= 0 || !in_array($accion, ['activar','inactivar'], true)) {
    throw new Exception('Parámetros inválidos');
  }

  $nuevo = ($accion === 'activar') ? 'activo' : 'inactivo';
  $st = $con->prepare("UPDATE medicamentos SET estado=:e, updated_at=NOW() WHERE id=:id");
  $st->execute([':e'=>$nuevo, ':id'=>$id]);

  $out = ['success'=>true, 'message'=>'Estado actualizado', 'estado'=>$nuevo];

} catch (Throwable $e) {
  $out['message'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
