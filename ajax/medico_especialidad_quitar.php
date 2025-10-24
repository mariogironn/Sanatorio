<?php
// ajax/medico_especialidad_quitar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ok  = fn($m)=> (print json_encode(['success'=>true,'message'=>$m], JSON_UNESCAPED_UNICODE)) && exit;
$err = function($m,$d=null){ echo json_encode(['success'=>false,'message'=>$m,'debug'=>$d]); exit; };

try {
  require_once __DIR__ . '/../config/connection.php';
} catch (Throwable $e) {
  $err('Conexión no disponible',$e->getMessage());
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) $err('Sesión expirada, inicia sesión.');

$medico_id = (int)($_POST['medico_id'] ?? 0);
if ($medico_id <= 0) $err('ID de personal inválido.');

try {
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $con->beginTransaction();

  // Bloquea la fila actual
  $st = $con->prepare("SELECT id_medico, especialidad_id, estado FROM medicos WHERE id_medico = ? FOR UPDATE");
  $st->execute([$medico_id]);
  $before = $st->fetch(PDO::FETCH_ASSOC);
  if (!$before) { $con->rollBack(); $err('Personal no encontrado.'); }

  // Armamos el UPDATE: sólo limpiamos la especialidad (y, si existen, los campos opcionales)
  $sql = "UPDATE medicos SET especialidad_id = NULL";
  $hasDesc = $con->query("SHOW COLUMNS FROM medicos LIKE 'especialidad_descripcion'")->rowCount() > 0;
  $hasFec  = $con->query("SHOW COLUMNS FROM medicos LIKE 'especialidad_fecha_certificacion'")->rowCount() > 0;
  if ($hasDesc) $sql .= ", especialidad_descripcion = NULL";
  if ($hasFec)  $sql .= ", especialidad_fecha_certificacion = NULL";
  $sql .= " WHERE id_medico = :id";

  $up = $con->prepare($sql);
  $up->execute([':id'=>$medico_id]);

  // Auditoría (opcional)
  $after = ['id_medico'=>$medico_id,'especialidad_id'=>null];
  $aud = $con->prepare("
    INSERT INTO auditoria (modulo, tabla, id_registro, accion, usuario_id, estado_resultante, antes_json, despues_json, ip, user_agent)
    VALUES ('Médicos','medicos', :id,'UPDATE', :uid, 'sin_especialidad', :antes, :despues, :ip, :ua)
  ");
  $aud->execute([
    ':id'=>$medico_id,
    ':uid'=>$uid,
    ':antes'=>json_encode($before, JSON_UNESCAPED_UNICODE),
    ':despues'=>json_encode($after, JSON_UNESCAPED_UNICODE),
    ':ip'=>$_SERVER['REMOTE_ADDR'] ?? null,
    ':ua'=>$_SERVER['HTTP_USER_AGENT'] ?? null,
  ]);

  $con->commit();
  $ok('Especialidad quitada');
} catch (Throwable $e) {
  if ($con->inTransaction()) $con->rollBack();
  $err('No se pudo quitar la especialidad', $e->getMessage());
}
