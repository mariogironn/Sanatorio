<?php
// ajax/especialidades_eliminar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ok  = fn($p)=> (print json_encode($p, JSON_UNESCAPED_UNICODE)) && exit;
$err = function($m,$d=null){ echo json_encode(['success'=>false,'message'=>$m,'debug'=>$d]); exit; };

try { require_once __DIR__ . '/../config/connection.php'; }
catch (Throwable $e) { $err('Conexión no disponible', $e->getMessage()); }

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) $err('Sesión expirada, inicia sesión.');

$id = (int)($_POST['id_especialidad'] ?? 0);
if ($id <= 0) $err('ID de especialidad inválido.');

try {
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // ¿Tiene médicos asignados?
  $st = $con->prepare("SELECT COUNT(*) FROM medicos WHERE especialidad_id = ?");
  $st->execute([$id]);
  $asignados = (int)$st->fetchColumn();
  if ($asignados > 0) {
    $err('No se puede eliminar: tiene médicos asignados.');
  }

  $con->beginTransaction();

  // Traer datos para auditoría (opcional)
  $pre = $con->prepare("SELECT id, nombre, descripcion, estado, creado_en FROM especialidades WHERE id=?");
  $pre->execute([$id]);
  $before = $pre->fetch(PDO::FETCH_ASSOC);
  if (!$before) { $con->rollBack(); $err('Especialidad no encontrada.'); }

  // Eliminar
  $del = $con->prepare("DELETE FROM especialidades WHERE id=?");
  $del->execute([$id]);

  // Auditoría
  $aud = $con->prepare("
    INSERT INTO auditoria (modulo, tabla, id_registro, accion, usuario_id, estado_resultante, antes_json, despues_json, ip, user_agent)
    VALUES ('Especialidades','especialidades', :id, 'DELETE', :uid, 'inactivo', :antes, NULL, :ip, :ua)
  ");
  $aud->execute([
    ':id'    => $id,
    ':uid'   => $uid,
    ':antes' => json_encode($before, JSON_UNESCAPED_UNICODE),
    ':ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
    ':ua'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
  ]);

  $con->commit();
  $ok(['success'=>true, 'message'=>'Especialidad eliminada correctamente']);

} catch (Throwable $e) {
  if ($con->inTransaction()) $con->rollBack();
  $err('No se pudo eliminar la especialidad', $e->getMessage());
}
