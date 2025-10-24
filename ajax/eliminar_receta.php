 <?php
// ajax/eliminar_receta.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';
require_once '../common_service/auditoria_service.php';

$resp = ['success'=>false,'message'=>''];

try {
  if (empty($_SESSION['user_id'])) {
    throw new Exception('Sesión no válida');
  }
  $usuario_id = (int)$_SESSION['user_id'];
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    throw new Exception('ID inválido');
  }

  // --- Autorización ---
  $rolesStmt = $con->prepare("
    SELECT LOWER(r.nombre) rol
    FROM usuario_rol ur
    JOIN roles r ON r.id_rol = ur.id_rol
    WHERE ur.id_usuario = :u
  ");
  $rolesStmt->execute([':u'=>$usuario_id]);
  $rolesArr = array_map(fn($x)=>$x['rol'], $rolesStmt->fetchAll(PDO::FETCH_ASSOC));
  $isMed = (bool) array_intersect($rolesArr, ['medico','doctor','enfermero','enfermera']);

  $mods = $con->query("SELECT id_modulo FROM modulos WHERE slug='recetas' OR nombre LIKE '%Receta%'")
              ->fetchAll(PDO::FETCH_COLUMN);
  $canMatrix = false;
  if ($mods) {
    $mods = array_map('intval', $mods);
    $q = $con->prepare("
      SELECT 1
      FROM rol_permiso rp
      JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
      WHERE ur.id_usuario = :u
        AND rp.id_modulo IN (".implode(',', $mods).")
        AND rp.eliminar = 1
      LIMIT 1
    ");
    $q->execute([':u'=>$usuario_id]);
    $canMatrix = (bool)$q->fetchColumn();
  }
  if (!$isMed && !$canMatrix) {
    throw new Exception('No autorizado para eliminar recetas.');
  }

  // --- Snapshot para auditoría ---
  $ant = $con->prepare("SELECT * FROM recetas_medicas WHERE id_receta = ? LIMIT 1");
  $ant->execute([$id]);
  $prev = $ant->fetch(PDO::FETCH_ASSOC);
  if (!$prev) {
    throw new Exception('Receta no encontrada');
  }

  // --- Eliminación ---
  $con->beginTransaction();
  $con->prepare("DELETE FROM detalle_recetas WHERE id_receta = ?")->execute([$id]);
  $con->prepare("DELETE FROM recetas_medicas WHERE id_receta = ?")->execute([$id]);

  // Auditoría (no romper si falla)
  try { audit_delete($con, 'Recetas', 'recetas_medicas', $id, $prev); }
  catch (Throwable $e) { error_log('AUDIT eliminar_receta: '.$e->getMessage()); }

  $con->commit();
  $resp['success'] = true;
  $resp['message'] = 'Receta eliminada correctamente';

} catch (Throwable $e) {
  if ($con->inTransaction()) { $con->rollBack(); }
  $resp['message'] = $e->getMessage();
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE);
exit;
