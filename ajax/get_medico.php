<?php
// ajax/get_medico.php — correo desde medicos.correo + auditoría no intrusiva
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$res = ['success'=>false,'data'=>[],'message'=>''];

/* === Helpers mínimos de auditoría (silenciosos) === */
function _aud_table_exists(PDO $con, string $t): bool {
  try {
    $st = $con->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
    $st->execute([':t'=>$t]); return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function _aud_uid(): ?int {
  foreach (['user_id','usuario_id','id_usuario','id'] as $k) {
    if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k];
  } return null;
}
function _aud_sid(): ?int {
  foreach (['sucursal_id','id_sucursal','sucursal','sucursal_activa','id_sucursal_activa'] as $k) {
    if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k];
  } return null;
}
function _aud_log_view(PDO $con, int $id_medico, array $row){
  try{
    if(!_aud_table_exists($con,'auditoria')) return; // no rompe si no existe
    $sql = "INSERT INTO auditoria
            (modulo,tabla,id_registro,accion,usuario_id,sucursal_id,estado_resultante,antes_json,despues_json,ip,user_agent,creado_en)
            VALUES ('Médicos','medicos',:id,'GENERAR',:uid,:sid,NULL,:antes,NULL,:ip,:ua,NOW())";
    $st = $con->prepare($sql);
    $st->execute([
      ':id'    => $id_medico,
      ':uid'   => _aud_uid(),
      ':sid'   => _aud_sid(),
      ':antes' => json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      ':ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
      ':ua'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
  }catch(Throwable $e){ /* silencioso */ }
}
/* ==================================================== */

try {
  $id = isset($_POST['id_medico']) ? (int)$_POST['id_medico'] : 0;
  if ($id <= 0) { throw new Exception('ID de médico no proporcionado'); }

  $sql = "
    SELECT
      m.id_medico,
      m.especialidad_id,
      m.colegiado,
      m.telefono,
      m.correo AS correo,             -- << correo de medicos
      m.estado,
      m.fecha_registro,
      m.creado_en,
      u.id AS usuario_id,
      u.usuario,
      u.nombre_mostrar,
      e.nombre AS especialidad_nombre
    FROM medicos m
    LEFT JOIN usuarios u       ON u.id = m.id_medico
    LEFT JOIN especialidades e ON e.id = m.especialidad_id
    WHERE m.id_medico = :id
    LIMIT 1
  ";

  $st = $con->prepare($sql);
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { throw new Exception('Médico no encontrado'); }

  // Auditoría: apertura de edición (consulta previa)
  _aud_log_view($con, $id, $row);

  // Mapeo del estado para que el <select> haga match por value
  $estadoTxt = (function($v){
    $v = (int)$v;
    return $v===1 ? 'activo'
         : ($v===0 ? 'inactivo'
         : ($v===2 ? 'vacaciones'
         : ($v===3 ? 'licencia médica' : 'inactivo')));
  })($row['estado']);
  $row['estado'] = $estadoTxt;

  $res['success'] = true;
  $res['data'] = $row;

} catch (Throwable $e) {
  $res['message'] = 'Error: '.$e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);