<?php
// ajax/medico_especialidad_reasignar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ok  = fn($p)=> (print json_encode($p, JSON_UNESCAPED_UNICODE)) && exit;
$err = function($m,$d=null){ echo json_encode(['success'=>false,'message'=>$m,'debug'=>$d]); exit; };

try {
  require_once __DIR__ . '/../config/connection.php';
} catch (Throwable $e) {
  $err('Conexión no disponible',$e->getMessage());
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) $err('Sesión expirada, inicia sesión.');

$medico_id       = (int)($_POST['medico_id'] ?? 0);
$especialidad_id = (int)($_POST['especialidad_id'] ?? 0);
$estado_in       = strtolower(trim($_POST['estado'] ?? 'activa'));
$desc            = trim($_POST['descripcion'] ?? '');
$fecha_cert      = trim($_POST['fecha_certificacion'] ?? '');
$fec = ($fecha_cert !== '') ? $fecha_cert : null;

// map a tinyint usado en medicos.estado (1=activa, 0=inactiva?, 3=capacitacion)
$estado_map = ['activa'=>1,'inactiva'=>0,'capacitacion'=>3];
$estado_val = $estado_map[$estado_in] ?? 1;

if ($medico_id<=0)       $err('Selecciona el personal.');
if ($especialidad_id<=0) $err('Selecciona la especialidad.');

/*
  Tablas/columnas:
  - medicos.id_medico (PK), medicos.especialidad_id, medicos.estado
  - especialidades.id
  - usuarios.id
  (ver sanatorio.sql) :contentReference[oaicite:1]{index=1}
*/

try {
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $con->beginTransaction();

  // estado anterior (para auditoría)
  $st = $con->prepare("
    SELECT m.id_medico, m.especialidad_id, m.estado, 
           m.especialidad_descripcion, m.especialidad_fecha_certificacion,
           u.nombre_mostrar
    FROM medicos m JOIN usuarios u ON u.id = m.id_medico
    WHERE m.id_medico = ?
    FOR UPDATE
  ");
  $st->execute([$medico_id]);
  $before = $st->fetch(PDO::FETCH_ASSOC);
  if (!$before) { $con->rollBack(); $err('Personal no encontrado.'); }

  // ¿existe la especialidad?
  $chk = $con->prepare("SELECT id, nombre FROM especialidades WHERE id=?");
  $chk->execute([$especialidad_id]);
  $espRow = $chk->fetch(PDO::FETCH_ASSOC);
  if (!$espRow) { $con->rollBack(); $err('Especialidad no válida.'); }

  // === UPDATE (con los nuevos campos) ===
  $up = $con->prepare("
    UPDATE medicos
       SET especialidad_id                  = :esp,
           estado                           = :est,
           especialidad_descripcion         = :desc,
           especialidad_fecha_certificacion = :fec
     WHERE id_medico = :id
  ");
  $up->execute([
    ':esp'  => $especialidad_id,
    ':est'  => $estado_val,
    ':desc' => $desc,
    ':fec'  => $fec,
    ':id'   => $medico_id
  ]);

  // auditoría básica (tabla auditoria)
  $after = $before;
  $after['especialidad_id']                  = $especialidad_id;
  $after['estado']                           = $estado_val;
  $after['especialidad_descripcion']         = $desc;
  $after['especialidad_fecha_certificacion'] = $fec;

  $aud = $con->prepare("
    INSERT INTO auditoria (modulo, tabla, id_registro, accion, usuario_id, estado_resultante, antes_json, despues_json, ip, user_agent)
    VALUES ('Médicos','medicos', :id,'UPDATE', :uid, :estate, :antes, :despues, :ip, :ua)
  ");
  $estate = ($estado_val===0)? 'inactivo' : 'activo';
  $aud->execute([
    ':id'     => $medico_id,
    ':uid'    => $uid,
    ':estate' => $estate,
    ':antes'  => json_encode($before, JSON_UNESCAPED_UNICODE),
    ':despues'=> json_encode($after,  JSON_UNESCAPED_UNICODE),
    ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
    ':ua'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
  ]);

  $con->commit();

  $ok([
    'success'=>true,
    'message'=>'Especialidad actualizada correctamente',
    'medico_id'=>$medico_id,
    'especialidad_id'=>$especialidad_id,
    'estado'=>$estado_in,
    'descripcion'=>$desc,
    'fecha_certificacion'=>$fecha_cert
  ]);

} catch (Throwable $e) {
  if ($con->inTransaction()) $con->rollBack();
  $err('No se pudo reasignar la especialidad', $e->getMessage());
}