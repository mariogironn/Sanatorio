<?php
// Crea/edita turnos en distribucion_personal (Admin-only) + AUDITORÍA
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../common_service/auditoria_service.php';
require_once __DIR__ . '/../common_service/audit_helpers.php';
if (!isset($con)) { if (isset($pdo)) $con=$pdo; elseif(isset($dbh)) $con=$dbh; }

function norm_fecha($f){
  $f = trim((string)$f);
  if (!$f) return date('Y-m-d');
  if (preg_match('~^\d{2}/\d{2}/\d{4}$~',$f)){ [$d,$m,$y]=explode('/',$f); return "$y-$m-$d"; }
  return $f;
}
function is_admin(PDO $con, int $uid): bool {
  $q = $con->prepare("SELECT 1
                        FROM usuario_rol ur
                        JOIN roles r ON r.id_rol = ur.id_rol
                       WHERE ur.id_usuario = :u
                         AND UPPER(r.nombre) IN ('ADMIN','ADMINISTRADOR','PROPIETARIO','SUPERADMIN','OWNER')
                       LIMIT 1");
  $q->execute([':u'=>$uid]);
  return (bool)$q->fetchColumn();
}
function norm_estado($e){
  $v = strtoupper(trim((string)$e));
  return ($v==='1' || $v==='SI' || $v==='TRUE' || $v==='ACTIVO') ? 1 : 0;
}

try{
  if (!($con instanceof PDO)) throw new Exception('Sin conexión PDO');
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $uid = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
  if (!$uid || !is_admin($con,$uid)) { echo 'Solo un Administrador puede programar turnos.'; exit; }

  // ¿Existe columna 'cupos'?
  $hasCupos = false;
  try{
    $ck = $con->query("SHOW COLUMNS FROM distribucion_personal LIKE 'cupos'");
    if ($ck && $ck->rowCount() > 0) $hasCupos = true;
  }catch(Throwable $e){}

  $id    = (int)($_POST['id'] ?? 0);
  $u     = (int)($_POST['id_usuario'] ?? 0);
  $s     = (int)($_POST['id_sucursal'] ?? 0);
  $f     = norm_fecha($_POST['fecha'] ?? '');
  $he    = trim($_POST['hora_entrada'] ?? '');
  $hs    = trim($_POST['hora_salida'] ?? '');
  $est   = norm_estado($_POST['estado'] ?? '1');
  $idRol = isset($_POST['id_rol']) && $_POST['id_rol']!=='' ? (int)$_POST['id_rol'] : null;
  $cupos = $hasCupos ? (($_POST['cupos'] ?? '') === '' ? null : max(0, (int)$_POST['cupos'])) : null;

  if ($u<=0 || $s<=0 || !$f) { echo 'Datos incompletos'; exit; }

  // Validar existencia usuario y sucursal
  $cku = $con->prepare("SELECT 1 FROM usuarios WHERE id=:i");
  $cku->execute([':i'=>$u]);
  if(!$cku->fetchColumn()){ echo 'Usuario inexistente'; exit; }

  $cks = $con->prepare("SELECT 1 FROM sucursales WHERE id=:i");
  $cks->execute([':i'=>$s]);
  if(!$cks->fetchColumn()){ echo 'Sucursal inexistente'; exit; }

  // Validar orden de horas si ambas se envían
  if ($he !== '' && $hs !== '') {
    if ($he >= $hs) { echo 'La hora de entrada debe ser menor que la hora de salida.'; exit; }
  }

  // Validar solapamiento (si vienen horas) — usar placeholders distintos para evitar HY093
  if ($he !== '' && $hs !== '') {
    $qSol = $con->prepare("SELECT 1
                             FROM distribucion_personal
                            WHERE id_usuario=:u AND id_sucursal=:s AND fecha=:f
                              AND (:id0=0 OR id_distribucion<>:id1)
                              AND NOT (:hs<=hora_entrada OR :he>=hora_salida)
                            LIMIT 1");
    $qSol->execute([
      ':u'=>$u, ':s'=>$s, ':f'=>$f,
      ':id0'=>$id, ':id1'=>$id,
      ':he'=>$he, ':hs'=>$hs
    ]);
    if ($qSol->fetchColumn()) { echo 'El horario se solapa con otro turno del mismo usuario.'; exit; }
  }

  // Si es edición, asegurarnos de que el registro exista
  if ($id>0) {
    $ck = $con->prepare("SELECT 1 FROM distribucion_personal WHERE id_distribucion=:id");
    $ck->execute([':id'=>$id]);
    if(!$ck->fetchColumn()){ echo 'El turno no existe.'; exit; }
  }

  $con->beginTransaction();

  if ($id>0) {
    // snapshot antes
    $antes = null; try { $antes = audit_row($con,'distribucion_personal','id_distribucion',$id); } catch(Throwable $e){}

    // construir SET dinámico
    $sets = [
      "id_usuario = :u",
      "id_sucursal = :s",
      "fecha = :f",
      "hora_entrada = :he",
      "hora_salida  = :hs",
      "estado = :e"
    ];
    $params = [':u'=>$u,':s'=>$s,':f'=>$f,':he'=>$he?:null,':hs'=>$hs?:null,':e'=>$est,':id'=>$id];

    if ($idRol!==null) { $sets[] = "id_rol = :r"; $params[':r']=$idRol; }
    if ($hasCupos)     { $sets[] = "cupos = :c";  $params[':c']=$cupos; }

    $sql = "UPDATE distribucion_personal SET ".implode(", ",$sets)." WHERE id_distribucion = :id";
    $up = $con->prepare($sql);
    $up->execute($params);

    $con->commit();
    try{
      $despues = audit_row($con,'distribucion_personal','id_distribucion',$id);
      audit_update($con,'distribucion_personal','distribucion_personal',$id,$antes,$despues);
    }catch(Throwable $e){}
    echo 'OK';
  } else {
    // construir INSERT dinámico
    $cols = ["id_usuario","id_sucursal","fecha","hora_entrada","hora_salida","estado"];
    $vals = [":u",":s",":f",":he",":hs",":e"];
    $params = [':u'=>$u,':s'=>$s,':f'=>$f,':he'=>$he?:null,':hs'=>$hs?:null,':e'=>$est];

    if ($idRol!==null) { $cols[]="id_rol"; $vals[]=":r"; $params[':r']=$idRol; }
    if ($hasCupos)     { $cols[]="cupos";  $vals[]=":c"; $params[':c']=$cupos; }

    $sql = "INSERT INTO distribucion_personal(".implode(',',$cols).") VALUES(".implode(',',$vals).")";
    $ins = $con->prepare($sql);
    $ins->execute($params);

    $newId = (int)$con->lastInsertId();
    $con->commit();
    try{
      $des = audit_row($con,'distribucion_personal','id_distribucion',$newId);
      audit_create($con,'distribucion_personal','distribucion_personal',$newId,$des, ($est==1?'activo':'inactivo'));
    }catch(Throwable $e){}
    echo 'OK';
  }

}catch(Throwable $e){
  if ($con instanceof PDO && $con->inTransaction()) $con->rollBack();
  echo 'Error: '.$e->getMessage();
}
