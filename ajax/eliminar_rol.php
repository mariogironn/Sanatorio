<?php
// ajax/eliminar_rol.php
// Respuesta: "OK" o mensaje de error.

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';

// Auditoría
require_once __DIR__ . '/../common_service/auditoria_service.php';
$haveHelpers = @include_once __DIR__ . '/../common_service/audit_helpers.php';

$id = (int)($_POST['id_rol'] ?? 0);
if ($id <= 0) { echo 'Parámetros inválidos.'; exit; }

try {
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // ¿existe el rol?
  $st = $con->prepare("SELECT * FROM roles WHERE id_rol = :id LIMIT 1");
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { echo 'El rol no existe.'; exit; }

  // ¿está asignado a usuarios?
  $tieneUR = 0;
  try {
    $ck = $con->prepare("SELECT COUNT(*) FROM usuario_rol WHERE id_rol = :id");
    $ck->execute([':id'=>$id]);
    $tieneUR = (int)$ck->fetchColumn();
  } catch (Throwable $e) { /* si no existe la tabla, ignoramos */ }

  if ($tieneUR > 0) {
    echo 'No se puede eliminar: hay usuarios asignados a este rol.';
    exit;
  }

  // Snapshot para auditoría
  $antes = null;
  try {
    if ($haveHelpers && function_exists('audit_row')) {
      $antes = audit_row($con, 'roles', 'id_rol', $id);
    }
    if (!$antes) $antes = $row;
  } catch (Throwable $e) { $antes = $row; }

  $con->beginTransaction();

  // Borra permisos del rol si existe la tabla
  try {
    $con->query("SELECT 1 FROM permisos_roles LIMIT 1");
    $delP = $con->prepare("DELETE FROM permisos_roles WHERE id_rol = :id");
    $delP->execute([':id'=>$id]);
  } catch (Throwable $e) { /* tabla no existe: ignorar */ }

  // Borrar rol
  $del = $con->prepare("DELETE FROM roles WHERE id_rol = :id");
  $del->execute([':id'=>$id]);

  $con->commit();

  // Auditoría: eliminar (estado inactivo)
  try { audit_delete($con, 'Roles', 'roles', $id, $antes); } catch (Throwable $e) {}

  echo ($del->rowCount() === 1) ? 'OK' : 'No se pudo eliminar.';
  exit;

} catch (PDOException $ex) {
  // 23000: violación de integridad (FK)
  if ($ex->getCode() === '23000') {
    echo 'No se puede eliminar: el rol está siendo utilizado.';
  } else {
    echo 'Error: ' . $ex->getMessage();
  }
  exit;
}
