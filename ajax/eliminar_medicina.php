<?php
// ajax/eliminar_medicina.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';
require_once '../common_service/auditoria_service.php'; // si no lo usas, puedes comentarlo

$res = ['success'=>false,'message'=>'','id'=>null,'nombre'=>null];

try {
  // ==== Método ====
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  // ==== Sesión / permisos básicos ====
  if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    throw new Exception('Sesión no válida');
  }
  $usuario_id = (int)$_SESSION['user_id'];

  // Roles clínicos
  $rs = $con->prepare("
    SELECT LOWER(r.nombre) rol
    FROM usuario_rol ur
    JOIN roles r ON r.id_rol = ur.id_rol
    WHERE ur.id_usuario = :u
  ");
  $rs->execute([':u'=>$usuario_id]);
  $roles = array_map(fn($x)=>$x['rol'], $rs->fetchAll(PDO::FETCH_ASSOC));
  $esPersonalMedico = (bool) array_intersect($roles, ['medico','doctor','enfermero','enfermera']);

  // Permiso por matriz (eliminar en módulo medicinas/medicamentos)
  $tienePermisoMatriz = false;
  $mods = $con->query("SELECT id_modulo FROM modulos WHERE slug IN ('medicinas','medicamentos') OR nombre LIKE '%Medicin%'")
              ->fetchAll(PDO::FETCH_COLUMN);
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
    $tienePermisoMatriz = (bool)$q->fetchColumn();
  }

  if (!$esPersonalMedico && !$tienePermisoMatriz) {
    http_response_code(403);
    throw new Exception('No autorizado para eliminar medicinas.');
  }

  // ==== Datos ====
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(400);
    throw new Exception('ID inválido');
  }

  // ==== Transacción ====
  $con->beginTransaction();

  // 1) Captura previa + lock
  $stmt = $con->prepare("SELECT * FROM medicamentos WHERE id = :id FOR UPDATE");
  $stmt->execute([':id'=>$id]);
  $prev = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$prev) {
    $con->rollBack();
    http_response_code(404);
    throw new Exception('La medicina no existe.');
  }
  $res['id']     = $id;
  $res['nombre'] = (string)$prev['nombre_medicamento'];

  // 2) Bloquear si tiene asignaciones activas a pacientes
  $chk = $con->prepare("SELECT COUNT(*) FROM paciente_medicinas WHERE medicina_id = :id AND estado = 'activo'");
  $chk->execute([':id'=>$id]);
  if ((int)$chk->fetchColumn() > 0) {
    $con->rollBack();
    http_response_code(400);
    throw new Exception('No se puede eliminar: la medicina tiene pacientes con medicación activa.');
  }

  // 3) Eliminar
  $del = $con->prepare("DELETE FROM medicamentos WHERE id = :id");
  $del->execute([':id'=>$id]);

  if ($del->rowCount() < 1) {
    $con->rollBack();
    http_response_code(409);
    throw new Exception('No se pudo eliminar la medicina.');
  }

  // 4) Auditoría (no rompe si falla)
  try {
    audit_delete($con, 'Medicinas', 'medicamentos', $id, $prev);
  } catch (Throwable $e) {
    error_log('AUDIT eliminar_medicina: '.$e->getMessage());
  }

  $con->commit();

  $res['success'] = true;
  $res['message'] = 'Medicina eliminada correctamente';

} catch (PDOException $e) {
  if ($con && $con->inTransaction()) { $con->rollBack(); }

  // MySQL: 1451 (FK constraint) o SQLSTATE 23000 => integridad referencial
  $driverCode = $e->errorInfo[1] ?? null;   // p.ej. 1451
  $sqlState   = $e->getCode();              // p.ej. 23000

  if ($driverCode == 1451 || $sqlState === '23000') {
    http_response_code(400);
    $res['message'] = 'No se puede eliminar: el registro está referenciado por otros datos (recetas o movimientos).';
  } else {
    http_response_code(500);
    $res['message'] = 'Error de base de datos al eliminar.';
  }

} catch (Throwable $e) {
  if ($con && $con->inTransaction()) { $con->rollBack(); }
  if (http_response_code() < 300) { http_response_code(400); }
  $res['message'] = $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
