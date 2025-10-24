 <?php
// sanatorio/ajax/eliminar_usuario.php
// Elimina un usuario y sus roles  +  AUDITORÍA (DELETE)

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require_once __DIR__ . '/../config/connection.php';

// === Auditoría ===
require_once __DIR__ . '/../common_service/auditoria_service.php';
require_once __DIR__ . '/../common_service/audit_helpers.php';

header('Content-Type: text/plain; charset=UTF-8');

$id = (int)($_POST['user_id'] ?? 0);
if ($id <= 0) { echo 'ID inválido'; exit; }

// Detectar PK (id | id_usuario) en tabla usuarios
$PK = 'id';
try {
  $ck = $con->query("SHOW COLUMNS FROM usuarios LIKE 'id_usuario'");
  if ($ck && $ck->rowCount() > 0) { $PK = 'id_usuario'; }
} catch (Throwable $e) { /* continuar con 'id' por defecto */ }

// No permitir que un usuario se borre a sí mismo
$sessionUid = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
if ($sessionUid === $id) {
  echo 'No puedes eliminar tu propia cuenta.'; exit;
}

// Helper local: enmascara campos sensibles del snapshot
$mask_user = function (?array $row) {
  if (!$row) return $row;
  unset($row['contrasena'], $row['password'], $row['pass'], $row['pwd']);
  return $row;
};

try {
  // Traer datos para validar + posible eliminación de imagen
  $stInfo = $con->prepare("SELECT `$PK` AS pk, usuario, nombre_mostrar, imagen_perfil FROM usuarios WHERE `$PK` = :i");
  $stInfo->execute([':i' => $id]);
  $info = $stInfo->fetch(PDO::FETCH_ASSOC);

  if (!$info) { echo 'El usuario no existe.'; exit; }

  // === AUDITORÍA: snapshot ANTES (fuera de la transacción, estado previo tal cual)
  try {
    $antes = audit_row($con, 'usuarios', $PK, $id);
    $antes = $mask_user($antes);
  } catch (Throwable $e) {
    $antes = null; // no romper el flujo de borrado
  }

  $con->beginTransaction();

  // Borrar relaciones de roles primero
  $con->prepare("DELETE FROM usuario_rol WHERE id_usuario = :i")->execute([':i' => $id]);

  // Borrar usuario
  $stDel = $con->prepare("DELETE FROM usuarios WHERE `$PK` = :i");
  $stDel->execute([':i' => $id]);

  if ($stDel->rowCount() !== 1) {
    $con->rollBack();
    echo 'No se pudo eliminar el usuario.'; exit;
  }

  $con->commit();

  // === AUDITORÍA: registrar DELETE (no interrumpir si falla)
  try {
    // Registra DELETE con snapshot "antes". (después = null)
    audit_delete($con, 'usuarios', 'usuarios', $id, $antes);
  } catch (Throwable $e) {
    error_log('AUDITORIA DELETE usuarios: '.$e->getMessage());
  }

  // Borrar archivo físico fuera de la transacción (si existiera)
  if (!empty($info['imagen_perfil'])) {
    $path = __DIR__ . '/../user_images/' . $info['imagen_perfil'];
    if (is_file($path)) { @unlink($path); }
  }

  echo 'OK';

} catch (PDOException $ex) {
  if ($con->inTransaction()) { $con->rollBack(); }
  echo 'Error: ' . $ex->getMessage();
}
