<?php
// Devuelve en JSON todos los roles y si están asignados al usuario.
// No asume que exista columna "estado" en roles.

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

try {
  require_once __DIR__ . '/../config/connection.php';
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'msg' => 'No se pudo conectar.']); exit;
}

$uid = (int)($_GET['user_id'] ?? 0);
if ($uid <= 0) {
  echo json_encode(['ok' => false, 'msg' => 'user_id inválido']); exit;
}

try {
  // Todos los roles disponibles
  $stAll = $con->query("SELECT id_rol, nombre FROM roles ORDER BY nombre");
  $all   = $stAll->fetchAll(PDO::FETCH_ASSOC);

  // Roles ya asignados al usuario
  $stAsg = $con->prepare("SELECT id_rol FROM usuario_rol WHERE id_usuario = :u");
  $stAsg->execute([':u' => $uid]);
  $asignados = array_map('intval', array_column($stAsg->fetchAll(PDO::FETCH_ASSOC), 'id_rol'));

  $roles = [];
  foreach ($all as $r) {
    $roles[] = [
      'id_rol'   => (int)$r['id_rol'],
      'nombre'   => (string)$r['nombre'],
      'asignado' => in_array((int)$r['id_rol'], $asignados, true) ? 1 : 0
    ];
  }

  echo json_encode(['ok' => true, 'roles' => $roles], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
