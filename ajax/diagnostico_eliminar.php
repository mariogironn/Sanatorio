<?php
// ajax/diagnostico_eliminar.php
// Elimina un diagnóstico por su PK (diagnosticos.id)

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connection.php';

$out = ['success' => false, 'message' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  // ACEPTA VARIOS NOMBRES DE PARÁMETRO: id, id_diagnostico, diagnostico_id, iddx
  $keys = ['id', 'id_diagnostico', 'diagnostico_id', 'iddx'];
  $id = 0;
  foreach ($keys as $k) {
    if (isset($_POST[$k])) {
      $id = (int)$_POST[$k];
      break;
    }
  }
  // fallback por si llegó por GET (no debería, pero por si acaso)
  if ($id <= 0) {
    foreach ($keys as $k) {
      if (isset($_GET[$k])) {
        $id = (int)$_GET[$k];
        break;
      }
    }
  }

  if ($id <= 0) {
    http_response_code(400);
    throw new Exception('ID inválido');
  }

  $st = $con->prepare('DELETE FROM diagnosticos WHERE id = ?');
  $st->execute([$id]);

  $out['success'] = true;
  $out['message'] = 'Diagnóstico eliminado';
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  if (!http_response_code()) { http_response_code(400); }
  $out['message'] = 'Error: ' . $e->getMessage();
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}
