<?php
// ajax/tratamiento_eliminar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$res = ['success' => false, 'message' => ''];

try {
  // Acepta id o id_tratamiento por compatibilidad
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if ($id <= 0 && isset($_POST['id_tratamiento'])) {
    $id = (int)$_POST['id_tratamiento'];
  }
  if ($id <= 0) { throw new Exception('ID inválido'); }

  // Transacción porque hay tablas hijas
  $con->beginTransaction();

  // 1) Eliminar medicamentos asociados
  $st = $con->prepare("DELETE FROM tratamiento_medicamentos WHERE id_tratamiento = ?");
  $st->execute([$id]);

  // 2) Eliminar el tratamiento (la PK real es `id`)
  $st = $con->prepare("DELETE FROM tratamientos WHERE id = ? LIMIT 1");
  $st->execute([$id]);

  $con->commit();

  $res['success'] = true;
  $res['message'] = 'Tratamiento eliminado correctamente.';
} catch (Throwable $e) {
  if ($con && $con->inTransaction()) { $con->rollBack(); }
  $res['success'] = false;
  $res['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
