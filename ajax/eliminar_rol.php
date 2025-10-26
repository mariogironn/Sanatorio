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
  $st->execute([':id' => $id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { echo 'El rol no existe.'; exit; }

  // ¿está asignado a usuarios?
  // (si existe la tabla usuario_rol y tiene filas, no se permite eliminar)
  $tieneUR = 0;
  try {
    $ck = $con->prepare("SELECT COUNT(*) FROM usuario_rol WHERE id_rol = :id");
    $ck->execute([':id' => $id]);
    $tieneUR = (int)$ck->fetchColumn();
  } catch (Throwable $e) {
    // si no existe la tabla, se ignora
  }
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
  } catch (Throwable $e) {
    $antes = $row;
  }

  $con->beginTransaction();

  // --- LIMPIEZA DE DEPENDENCIAS ---
  // Tu esquema real usa "rol_permiso". Algunos entornos antiguos
  // podrían llamarla "permisos_roles". Limpiamos ambas si existen.
  foreach (['rol_permiso', 'permisos_roles'] as $tbl) {
    try {
      $con->query("SELECT 1 FROM `$tbl` LIMIT 1");
      $delP = $con->prepare("DELETE FROM `$tbl` WHERE id_rol = :id");
      $delP->execute([':id' => $id]);
    } catch (Throwable $e) {
      // si la tabla no existe o falla el SELECT 1, se ignora y se sigue
    }
  }

  // Si manejas alguna otra tabla de relaciones por rol, puedes añadirla aquí
  // siguiendo el mismo patrón de borrado condicional.

  // --- BORRADO DEL ROL ---
  $del = $con->prepare("DELETE FROM roles WHERE id_rol = :id");
  $del->execute([':id' => $id]);

  $con->commit();

  // Auditoría: registrar eliminación
  try { audit_delete($con, 'Roles', 'roles', $id, $antes); } catch (Throwable $e) {}

  echo ($del->rowCount() === 1) ? 'OK' : 'No se pudo eliminar.';
  exit;

} catch (PDOException $ex) {
  // Revertir en caso de error durante la transacción
  if ($con->inTransaction()) { $con->rollBack(); }
  // 23000: violación de integridad (FK)
  if ($ex->getCode() === '23000') {
    echo 'No se puede eliminar: el rol está siendo utilizado.';
  } else {
    echo 'Error: ' . $ex->getMessage();
  }
  exit;

} catch (Throwable $ex) {
  if ($con->inTransaction()) { $con->rollBack(); }
  echo 'Error inesperado: ' . $ex->getMessage();
  exit;
}
