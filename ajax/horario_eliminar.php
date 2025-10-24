<?php
// ajax/horario_eliminar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ok  = fn($p)=> (print json_encode($p, JSON_UNESCAPED_UNICODE)) && exit;
$err = function($m,$d=null){ echo json_encode(['success'=>false,'message'=>$m,'debug'=>$d]); exit; };

try {
  require_once __DIR__ . '/../config/connection.php';
} catch (Throwable $e) {
  $err('Conexión no disponible', $e->getMessage());
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) $err('Sesión expirada, inicia sesión.');

$id = (int)($_POST['id_horario'] ?? $_POST['id'] ?? 0);
if ($id <= 0) $err('Falta el identificador del horario.');

try {
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $con->beginTransaction();

  // Traer datos previos para auditoría / validación
  $st = $con->prepare("SELECT id, medico_id, dia_semana, hora_inicio, hora_fin, estado 
                         FROM horarios_medicos 
                        WHERE id = ? FOR UPDATE");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { $con->rollBack(); $err('El horario no existe.'); }

  // Borrar
  $del = $con->prepare("DELETE FROM horarios_medicos WHERE id = ?");
  $del->execute([$id]);

  // Auditoría (opcional, si usas la tabla auditoria)
  try {
    $aud = $con->prepare("
      INSERT INTO auditoria (modulo, tabla, id_registro, accion, usuario_id, antes_json, ip, user_agent)
      VALUES ('Horarios','horarios_medicos', :id, 'DELETE', :uid, :antes, :ip, :ua)
    ");
    $aud->execute([
      ':id'    => $id,
      ':uid'   => $uid,
      ':antes' => json_encode($row, JSON_UNESCAPED_UNICODE),
      ':ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
      ':ua'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
  } catch (Throwable $e) { /* no bloquear por auditoría */ }

  $con->commit();
  $ok(['success'=>true,'message'=>'Horario eliminado correctamente']);
} catch (Throwable $e) {
  if ($con->inTransaction()) $con->rollBack();
  $err('No se pudo eliminar', $e->getMessage());
}
