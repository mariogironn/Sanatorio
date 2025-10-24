<?php
/**
 * sanatorio/common_service/auditoria_service.php
 * Registro en tabla `auditoria` con soporte de: estado_resultante, sucursal e IP/UA.
 */

if (!function_exists('_audit_db')) {
  function _audit_db($maybeCon = null) {
    if ($maybeCon instanceof mysqli || $maybeCon instanceof PDO) return $maybeCon;
    if (isset($GLOBALS['con']) && ($GLOBALS['con'] instanceof mysqli || $GLOBALS['con'] instanceof PDO)) return $GLOBALS['con'];
    $guess = __DIR__ . '/../config/connection.php';
    if (is_file($guess)) {
      require_once $guess;
      if (isset($GLOBALS['con']) && ($GLOBALS['con'] instanceof mysqli || $GLOBALS['con'] instanceof PDO)) return $GLOBALS['con'];
    }
    throw new RuntimeException('No se encontró conexión ($con PDO/mysqli).');
  }
}

if (!function_exists('_audit_json'))  {
  function _audit_json($v){ return $v===null ? null : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
}
if (!function_exists('_audit_upper')) {
  function _audit_upper($t){ return strtoupper(trim((string)$t)); }
}

if (!function_exists('_audit_guess_user_id')) {
  function _audit_guess_user_id(){
    if (!isset($_SESSION)) return null;
    foreach (['usuario_id','id_usuario','user_id','id'] as $k){
      if (!empty($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k];
    }
    return null;
  }
}

if (!function_exists('_audit_guess_sucursal_id')) {
  // ✅ Ampliada para detectar sucursal_activa e id_sucursal_activa
  function _audit_guess_sucursal_id(){
    if (!isset($_SESSION)) return null;
    foreach (['sucursal_id','id_sucursal','sucursal','sucursal_activa','id_sucursal_activa'] as $k){
      if (!empty($_SESSION[$k]) && is_numeric($_SESSION[$k])) return (int)$_SESSION[$k];
    }
    return null;
  }
}

if (!function_exists('_audit_ip'))         { function _audit_ip(){ return $_SERVER['REMOTE_ADDR']     ?? null; } }
if (!function_exists('_audit_user_agent')) { function _audit_user_agent(){ return $_SERVER['HTTP_USER_AGENT'] ?? null; } }

if (!function_exists('audit_log')) {
  /**
   * audit_log($con, array $opts)  |  audit_log(array $opts, $con=null)
   * $opts:
   *  - modulo, tabla, id_registro, accion (CREATE/UPDATE/DELETE/ACTIVAR/DESACTIVAR/GENERAR)
   *  - antes, despues (array|mixed|null)
   *  - usuario_id, sucursal_id (opcional, autodetecta de $_SESSION si no los pasas)
   *  - estado_resultante ('activo'|'inactivo'|null)
   */
  function audit_log($a, $b = null) {
    if ($a instanceof mysqli || $a instanceof PDO) { $con = $a; $opts = (array)$b; }
    else { $con = _audit_db($b); $opts = (array)$a; }

    $modulo      = trim((string)($opts['modulo'] ?? ''));
    $tabla       = trim((string)($opts['tabla'] ?? ''));
    $id_registro = isset($opts['id_registro']) ? (is_numeric($opts['id_registro']) ? (int)$opts['id_registro'] : null) : null;
    $accion      = _audit_upper($opts['accion'] ?? '');
    $estado_res  = isset($opts['estado_resultante']) ? $opts['estado_resultante'] : null;

    $usuario_id  = isset($opts['usuario_id'])  ? (is_numeric($opts['usuario_id'])  ? (int)$opts['usuario_id']  : null) : _audit_guess_user_id();
    $sucursal_id = isset($opts['sucursal_id']) ? (is_numeric($opts['sucursal_id']) ? (int)$opts['sucursal_id'] : null) : _audit_guess_sucursal_id();

    $antes_json  = array_key_exists('antes',   $opts) ? _audit_json($opts['antes'])   : null;
    $desp_json   = array_key_exists('despues', $opts) ? _audit_json($opts['despues']) : null;

    $ip         = _audit_ip();
    $user_agent = _audit_user_agent();

    if ($modulo === '' || $tabla === '' || $accion === '') {
      error_log('[auditoria] Falta modulo/tabla/accion');
      return false;
    }

    // Inserción (PDO o mysqli)
    if ($con instanceof PDO){
      $sql = "INSERT INTO auditoria
              (modulo,tabla,id_registro,accion,usuario_id,sucursal_id,estado_resultante,antes_json,despues_json,ip,user_agent,creado_en)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())";
      $st = $con->prepare($sql);
      return $st->execute([$modulo,$tabla,$id_registro,$accion,$usuario_id,$sucursal_id,$estado_res,$antes_json,$desp_json,$ip,$user_agent]);

    } elseif ($con instanceof mysqli){
      $sql = "INSERT INTO auditoria
              (modulo,tabla,id_registro,accion,usuario_id,sucursal_id,estado_resultante,antes_json,despues_json,ip,user_agent,creado_en)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())";
      $st = $con->prepare($sql);
      // Tipos: s s i s i i s s s s s  (11 valores)
      $st->bind_param(
        "ssississsss",
        $modulo, $tabla, $id_registro, $accion, $usuario_id, $sucursal_id, $estado_res, $antes_json, $desp_json, $ip, $user_agent
      );
      $ok = $st->execute(); $st->close(); return $ok;
    }

    throw new RuntimeException('Conexión no soportada.');
  }
}

if (!function_exists('audit_log_report')) {
  function audit_log_report($a, $slugReporte, array $params = [], ?string $rutaArchivo = null) {
    $con = ($a instanceof mysqli || $a instanceof PDO) ? $a : _audit_db(null);
    $payload = ['reporte'=>$slugReporte,'parametros'=>$params,'ruta_pdf'=>$rutaArchivo];
    return audit_log($con, [
      'modulo'=>'reportes','tabla'=>$slugReporte,'id_registro'=>null,'accion'=>'GENERAR',
      'antes'=>null,'despues'=>$payload
    ]);
  }
}

if (!function_exists('audit_create')) {
  function audit_create($con, $modulo, $tabla, $idNuevo, $rowDespues, $estado='activo') {
    return audit_log($con, [
      'modulo'=>$modulo,'tabla'=>$tabla,'id_registro'=>$idNuevo,'accion'=>'CREATE',
      'antes'=>null,'despues'=>$rowDespues,'estado_resultante'=>$estado
    ]);
  }
}

if (!function_exists('audit_update')) {
  function audit_update($con, $modulo, $tabla, $id, $rowAntes, $rowDespues, $estado=null) {
    return audit_log($con, [
      'modulo'=>$modulo,'tabla'=>$tabla,'id_registro'=>$id,'accion'=>'UPDATE',
      'antes'=>$rowAntes,'despues'=>$rowDespues,'estado_resultante'=>$estado
    ]);
  }
}

if (!function_exists('audit_delete')) {
  // ⚠️ Guarda estado_resultante='inactivo' para que en la UI se muestre "Inactivo" tras eliminar
  function audit_delete($con, $modulo, $tabla, $id, $rowAntes) {
    return audit_log($con, [
      'modulo' => $modulo,
      'tabla'  => $tabla,
      'id_registro' => $id,
      'accion' => 'DELETE',
      'antes'  => $rowAntes,
      'despues'=> null,
      'estado_resultante' => 'inactivo'
    ]);
  }
}

if (!function_exists('audit_toggle_state')) {
  function audit_toggle_state($con, $modulo, $tabla, $id, $estadoAntes, $estadoDespues) {
    $accion = (
      $estadoDespues === 'activo' || $estadoDespues === 1 || $estadoDespues === true ||
      strtoupper((string)$estadoDespues) === 'ACTIVO'
    ) ? 'ACTIVAR' : 'DESACTIVAR';
    $estado = ($accion === 'ACTIVAR') ? 'activo' : 'inactivo';

    return audit_log($con, [
      'modulo' => $modulo,
      'tabla'  => $tabla,
      'id_registro' => $id,
      'accion' => $accion,
      'antes'  => ['estado'=>$estadoAntes],
      'despues'=> ['estado'=>$estadoDespues],
      'estado_resultante' => $estado
    ]);
  }
}
