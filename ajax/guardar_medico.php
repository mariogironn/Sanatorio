<?php
// ajax/guardar_medico.php (con campo correo en medicos)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$res = ['success'=>false,'message'=>''];

/* ===== Helpers mínimos para auditoría (no rompen si falta la tabla) ===== */
function _aud_table_exists(PDO $con, string $t): bool {
  try {
    $st = $con->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
    $st->execute([':t'=>$t]); return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function _aud_row(PDO $con, int $id): array {
  try {
    $st = $con->prepare("SELECT * FROM medicos WHERE id_medico = ? LIMIT 1");
    $st->execute([$id]); $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: ['id_medico'=>$id];
  } catch (Throwable $e) { return ['id_medico'=>$id]; }
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
function _aud_ins(PDO $con, array $payload): void {
  if (!_aud_table_exists($con,'auditoria')) return; // si no existe, no hacemos nada
  $sql = "INSERT INTO auditoria
          (modulo,tabla,id_registro,accion,usuario_id,sucursal_id,estado_resultante,antes_json,despues_json,ip,user_agent,creado_en)
          VALUES (:modulo,:tabla,:id_registro,:accion,:uid,:sid,:estado,:antes,:despues,:ip,:ua,NOW())";
  $st = $con->prepare($sql);
  $st->execute($payload);
}
/* ======================================================================= */

try {
  $medico_id       = isset($_POST['medico_id']) ? (int)$_POST['medico_id'] : 0; // para UPDATE
  $usuario_id      = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0; // en INSERT = id_medico
  $especialidad_id = isset($_POST['especialidad_id']) && $_POST['especialidad_id']!=='' ? (int)$_POST['especialidad_id'] : null;
  $colegiado       = trim($_POST['colegiado']   ?? '');
  $telefono        = trim($_POST['telefono']    ?? '');
  $correo          = trim($_POST['correo']      ?? '');           // <<--- NUEVO
  $estado_txt = strtolower(trim($_POST['estado'] ?? 'activo'));
  $mapEstado = [
    'activo' => 1, '1' => 1,
    'inactivo' => 0, '0' => 0,
    'vacaciones' => 2, '2' => 2,
    'licencia' => 3, 'licencia médica' => 3, 'licencia medica' => 3, '3' => 3,
  ];
  $estado = $mapEstado[$estado_txt] ?? 1; // default activo

  if (!isset($_POST['editar'])) {
    if ($usuario_id <= 0) throw new Exception('Debes seleccionar un usuario para crear el médico.');
    $chk = $con->prepare("SELECT 1 FROM medicos WHERE id_medico = ?");
    $chk->execute([$usuario_id]);
    if ($chk->fetchColumn()) throw new Exception('Ese usuario ya está registrado como médico.');
  } else {
    if ($medico_id <= 0) throw new Exception('ID de médico inválido.');
  }

  if ($colegiado !== '') {
    $sqlUC = "SELECT id_medico FROM medicos WHERE colegiado = ?";
    $parUC = [$colegiado];
    if ($medico_id > 0) { $sqlUC .= " AND id_medico <> ?"; $parUC[] = $medico_id; }
    $uc = $con->prepare($sqlUC); $uc->execute($parUC);
    if ($uc->fetchColumn()) throw new Exception('El número de colegiado ya está registrado.');
  }

  if (isset($_POST['editar'])) {
    /* ===== Auditoría: snapshot ANTES ===== */
    $rowAntes = _aud_row($con, $medico_id);

    // UPDATE
    $sets = [];
    $par  = [];
    $sets[] = "estado = ?";           $par[] = $estado;
    if ($especialidad_id !== null) { $sets[] = "especialidad_id = ?"; $par[] = $especialidad_id; }
    else { $sets[] = "especialidad_id = NULL"; }
    $sets[] = "colegiado = ?";        $par[] = ($colegiado !== '' ? $colegiado : null);
    $sets[] = "telefono  = ?";        $par[] = ($telefono  !== '' ? $telefono  : null);
    $sets[] = "correo    = ?";        $par[] = ($correo    !== '' ? $correo    : null); // <<--- NUEVO

    $par[] = $medico_id;
    $sql = "UPDATE medicos SET ".implode(', ',$sets)." WHERE id_medico = ? LIMIT 1";
    $st  = $con->prepare($sql);
    $st->execute($par);

    $res['success'] = true;
    $res['message'] = 'Médico actualizado correctamente';

    /* ===== Auditoría: snapshot DESPUÉS + INSERT ===== */
    try {
      $rowDespues = _aud_row($con, $medico_id);
      $payload = [
        ':modulo'      => 'Médicos',
        ':tabla'       => 'medicos',
        ':id_registro' => $medico_id,
        ':accion'      => 'UPDATE',
        ':uid'         => _aud_uid(),
        ':sid'         => _aud_sid(),
        ':estado'      => (($rowDespues['estado'] ?? 1) ? 'activo' : 'inactivo'),
        ':antes'       => json_encode($rowAntes,   JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ':despues'     => json_encode($rowDespues, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua'          => $_SERVER['HTTP_USER_AGENT'] ?? null,
      ];
      _aud_ins($con, $payload);
    } catch (Throwable $e) { /* no romper respuesta */ }

  } else {
    // INSERT
    $cols = ['id_medico','estado'];
    $vals = ['?','?'];
    $par  = [$usuario_id, $estado];

    if ($especialidad_id !== null){ $cols[]='especialidad_id'; $vals[]='?'; $par[]=$especialidad_id; }
    if ($colegiado !== '')        { $cols[]='colegiado';       $vals[]='?'; $par[]=$colegiado; }
    if ($telefono  !== '')        { $cols[]='telefono';        $vals[]='?'; $par[]=$telefono; }
    if ($correo    !== '')        { $cols[]='correo';          $vals[]='?'; $par[]=$correo; }  // <<--- NUEVO

    $sql = "INSERT INTO medicos (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
    $st  = $con->prepare($sql);
    $st->execute($par);

    $res['success'] = true;
    $res['message'] = 'Médico creado correctamente';

    /* ===== Auditoría: snapshot DESPUÉS + INSERT ===== */
    try {
      $rowDespues = _aud_row($con, $usuario_id); // id_medico = usuario_id
      $payload = [
        ':modulo'      => 'Médicos',
        ':tabla'       => 'medicos',
        ':id_registro' => $usuario_id,
        ':accion'      => 'CREATE',
        ':uid'         => _aud_uid(),
        ':sid'         => _aud_sid(),
        ':estado'      => (($rowDespues['estado'] ?? 1) ? 'activo' : 'inactivo'),
        ':antes'       => null,
        ':despues'     => json_encode($rowDespues, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua'          => $_SERVER['HTTP_USER_AGENT'] ?? null,
      ];
      _aud_ins($con, $payload);
    } catch (Throwable $e) { /* no romper respuesta */ }
  }

} catch (Throwable $e) {
  $res['success'] = false;
  $res['message'] = $e->getMessage();
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);