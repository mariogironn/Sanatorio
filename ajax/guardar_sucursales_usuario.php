<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: text/plain; charset=UTF-8');

/* === Auditoría (opcional; no rompe si falta) === */
$__AUDIT_READY__ = false;
try {
  @require_once __DIR__ . '/../common_service/auditoria_service.php';
  @require_once __DIR__ . '/../common_service/audit_helpers.php';
  $__AUDIT_READY__ = function_exists('audit_update') || function_exists('audit_event') || function_exists('audit_create');
} catch (Throwable $e) {
  $__AUDIT_READY__ = false;
}

$uid = (int)($_POST['user_id'] ?? 0);
$raw = $_POST['sucursales'] ?? '[]';

$ids = json_decode($raw, true);
if (!is_array($ids)) { echo 'Datos inválidos'; exit; }
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($x)=>$x>0)));

if ($uid <= 0) { echo 'Usuario inválido'; exit; }

/* Detectar PK de usuarios (id | id_usuario) */
$PK = 'id';
try {
  $ck = $con->query("SHOW COLUMNS FROM usuarios LIKE 'id_usuario'");
  if ($ck && $ck->rowCount() > 0) { $PK = 'id_usuario'; }
} catch (Throwable $e) { /* seguir con 'id' */ }

try{
  // valida usuario
  $stU = $con->prepare("SELECT `$PK` AS pk FROM usuarios WHERE `$PK` = :u");
  $stU->execute([':u'=>$uid]);
  if (!$stU->fetch(PDO::FETCH_ASSOC)) { echo 'El usuario no existe'; exit; }

  // valida sucursales existentes y activas
  $valid = [];
  if (!empty($ids)) {
    $ph=[]; $bind=[];
    foreach ($ids as $i=>$v){ $ph[]=':s'.$i; $bind[':s'.$i]=$v; }
    $sql = "SELECT id FROM sucursales WHERE estado=1 AND id IN (".implode(',',$ph).")";
    $st = $con->prepare($sql); $st->execute($bind);
    $valid = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN,0));
  }

  // actuales (ANTES)
  $stA = $con->prepare("SELECT id_sucursal FROM usuario_sucursal WHERE id_usuario=:u");
  $stA->execute([':u'=>$uid]);
  $antes = array_map('intval', $stA->fetchAll(PDO::FETCH_COLUMN,0));

  $toAdd = array_values(array_diff($valid, $antes));
  $toRem = array_values(array_diff($antes, $valid));

  $con->beginTransaction();

  if (!empty($toRem)) {
    $ph=[]; $bind=[':u'=>$uid];
    foreach ($toRem as $i=>$v){ $ph[]=':d'.$i; $bind[':d'.$i]=$v; }
    $sql = "DELETE FROM usuario_sucursal WHERE id_usuario=:u AND id_sucursal IN (".implode(',',$ph).")";
    $con->prepare($sql)->execute($bind);
  }

  if (!empty($toAdd)) {
    $ins = $con->prepare("INSERT INTO usuario_sucursal(id_usuario,id_sucursal) VALUES(:u,:s)");
    foreach ($toAdd as $v){ $ins->execute([':u'=>$uid, ':s'=>$v]); }
  }

  $con->commit();

  // Releer DESPUÉS de la transacción para auditar
  $stD = $con->prepare("SELECT id_sucursal FROM usuario_sucursal WHERE id_usuario=:u");
  $stD->execute([':u'=>$uid]);
  $despuesIDs = array_map('intval', $stD->fetchAll(PDO::FETCH_COLUMN,0));

  echo 'OK';

  // === AUDITORÍA: ANTES -> DESPUÉS (no interrumpir si falla)
  if ($__AUDIT_READY__) {
    try {
      // Mapear nombres de sucursal para claridad en auditoría
      $idsParaNombres = array_values(array_unique(array_merge($antes, $despuesIDs)));
      $mapNom = [];
      if (!empty($idsParaNombres)) {
        $ph = implode(',', array_fill(0, count($idsParaNombres), '?'));
        $qN = $con->prepare("SELECT id, nombre FROM sucursales WHERE id IN ($ph)");
        $qN->execute($idsParaNombres);
        foreach ($qN->fetchAll(PDO::FETCH_ASSOC) as $r) {
          $mapNom[(int)$r['id']] = $r['nombre'] ?? null;
        }
      }

      $antesArr = array_map(function($id) use ($mapNom){
        return ['id_sucursal' => $id, 'nombre' => $mapNom[$id] ?? null];
      }, $antes);

      $despuesArr = array_map(function($id) use ($mapNom){
        return ['id_sucursal' => $id, 'nombre' => $mapNom[$id] ?? null];
      }, $despuesIDs);

      $agregadas = array_values(array_diff($despuesIDs, $antes));
      $quitadas  = array_values(array_diff($antes, $despuesIDs));

      if (function_exists('audit_update')) {
        // audit_update($con, $modulo, $tabla, $registro_id, $antes, $despues)
        audit_update($con, 'usuarios/sucursales', 'usuario_sucursal', $uid,
          ['sucursales' => $antesArr],
          ['sucursales' => $despuesArr]
        );
      } elseif (function_exists('audit_event')) {
        // audit_event($con, $accion, $modulo, $tabla, $registro_id, $payload=[])
        audit_event($con, 'ACTUALIZAR_SUCURSALES', 'usuarios/sucursales', 'usuario_sucursal', $uid, [
          'antes'     => $antesArr,
          'despues'   => $despuesArr,
          'agregadas' => $agregadas,
          'quitadas'  => $quitadas
        ]);
      } elseif (function_exists('audit_create')) {
        // fallback: snapshot final
        audit_create($con, 'usuarios/sucursales', 'usuario_sucursal', $uid, ['sucursales' => $despuesArr], 'activo');
      }
    } catch (Throwable $eAud) {
      error_log('AUDITORIA SUCURSALES usuario: '.$eAud->getMessage());
    }
  }

} catch(Throwable $e){
  if ($con->inTransaction()) $con->rollBack();
  echo 'Error: '.$e->getMessage();
}
