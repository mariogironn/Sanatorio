<?php
// Elimina un turno de distribucion_personal (Admin-only) + AUDITORÍA
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../common_service/auditoria_service.php';
require_once __DIR__ . '/../common_service/audit_helpers.php';
if (!isset($con)) { if (isset($pdo)) $con=$pdo; elseif(isset($dbh)) $con=$dbh; }

function is_admin(PDO $con, int $uid): bool {
  $q = $con->prepare("SELECT 1
                        FROM usuario_rol ur
                        JOIN roles r ON r.id_rol=ur.id_rol
                       WHERE ur.id_usuario=:u
                         AND UPPER(r.nombre) IN ('ADMIN','ADMINISTRADOR','PROPIETARIO','SUPERADMIN','OWNER')
                       LIMIT 1");
  $q->execute([':u'=>$uid]);
  return (bool)$q->fetchColumn();
}

try{
  if (!($con instanceof PDO)) throw new Exception('Sin conexión PDO');
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $uid = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
  if (!$uid || !is_admin($con,$uid)) { echo 'Solo un Administrador puede eliminar turnos.'; exit; }

  $id = (int)($_POST['id'] ?? 0);
  if ($id<=0){ echo 'ID inválido'; exit; }

  // Verificar existencia y tomar snapshot ANTES
  $antes = null;
  $ck = $con->prepare("SELECT 1 FROM distribucion_personal WHERE id_distribucion=:i");
  $ck->execute([':i'=>$id]);
  if (!$ck->fetchColumn()) { echo 'El turno no existe.'; exit; }

  try { $antes = audit_row($con,'distribucion_personal','id_distribucion',$id); } catch(Throwable $e){ $antes = null; }

  // Borrar
  $st = $con->prepare("DELETE FROM distribucion_personal WHERE id_distribucion=:i");
  $st->execute([':i'=>$id]);
  if ($st->rowCount()!==1){ echo 'No se pudo eliminar'; exit; }

  // Auditoría (no interrumpe el flujo si falla)
  try { audit_delete($con,'distribucion_personal','distribucion_personal',$id,$antes); } catch (Throwable $e) { /* log opcional */ }

  echo 'OK';

} catch(Throwable $e){
  echo 'Error: '.$e->getMessage();
}
