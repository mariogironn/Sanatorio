<?php
// Reemplaza los roles del usuario por los recibidos.
// Acepta roles como JSON (string) o como array POST.

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/plain; charset=UTF-8');

try {
  require_once __DIR__ . '/../config/connection.php';
} catch (Throwable $e) {
  echo 'Error de conexión'; exit;
}

/* === Auditoría (opcional, no rompe si falta) === */
$__AUDIT_READY__ = false;
try {
  @require_once __DIR__ . '/../common_service/auditoria_service.php';
  @require_once __DIR__ . '/../common_service/audit_helpers.php';
  // Detecta si hay funciones típicas disponibles
  $__AUDIT_READY__ = function_exists('audit_update') || function_exists('audit_event') || function_exists('audit_create');
} catch (Throwable $e) {
  // si no existen archivos/funciones de auditoría, continuamos sin auditar
  $__AUDIT_READY__ = false;
}

$uid = (int)($_POST['user_id'] ?? 0);
if ($uid <= 0) { echo 'user_id inválido'; exit; }

$rolesIn = $_POST['roles'] ?? '[]';
$roles   = is_array($rolesIn) ? $rolesIn : json_decode($rolesIn, true);
if (!is_array($roles)) { $roles = []; }
$roles = array_values(array_unique(array_map('intval', $roles)));

try {
  $con->beginTransaction();

  /* ----- Snapshot ANTES (roles actuales) ----- */
  $stmtAntes = $con->prepare("SELECT id_rol FROM usuario_rol WHERE id_usuario = :u ORDER BY id_rol");
  $stmtAntes->execute([':u' => $uid]);
  $rolesAntes = array_map('intval', $stmtAntes->fetchAll(PDO::FETCH_COLUMN));

  /* ----- Reemplazo de asignaciones ----- */
  // Borra asignaciones actuales
  $del = $con->prepare("DELETE FROM usuario_rol WHERE id_usuario = :u");
  $del->execute([':u' => $uid]);

  // Inserta nuevas
  if (!empty($roles)) {
    $ins = $con->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (:u, :r)");
    foreach ($roles as $rid) {
      if ($rid > 0) { $ins->execute([':u' => $uid, ':r' => $rid]); }
    }
  }

  /* ----- Auditoría del cambio de roles ----- */
  if ($__AUDIT_READY__) {
    try {
      // Construir "antes" y "después" con nombres de rol para claridad
      $rolesDespues = $roles;

      $idsParaNombres = array_values(array_unique(array_merge($rolesAntes, $rolesDespues)));
      $mapNombre = [];
      if (count($idsParaNombres) > 0) {
        $ph = implode(',', array_fill(0, count($idsParaNombres), '?'));
        $qN = $con->prepare("SELECT id_rol, nombre FROM roles WHERE id_rol IN ($ph)");
        $qN->execute($idsParaNombres);
        foreach ($qN->fetchAll(PDO::FETCH_ASSOC) as $rr) {
          $mapNombre[(int)$rr['id_rol']] = $rr['nombre'];
        }
      }

      $antesArr = array_map(function($rid) use ($mapNombre){
        return ['id_rol' => $rid, 'nombre' => $mapNombre[$rid] ?? null];
      }, $rolesAntes);
      $despuesArr = array_map(function($rid) use ($mapNombre){
        return ['id_rol' => $rid, 'nombre' => $mapNombre[$rid] ?? null];
      }, $rolesDespues);

      // Diferencias (por si tu servicio quiere granularidad)
      $agregados = array_values(array_diff($rolesDespues, $rolesAntes));
      $quitados  = array_values(array_diff($rolesAntes, $rolesDespues));

      // 1) Preferencia: audit_update(… antes, despues …)
      if (function_exists('audit_update')) {
        // Firma esperada: audit_update($con, $modulo, $tabla, $registro_id, $antes, $despues)
        audit_update($con, 'usuarios/roles', 'usuario_rol', $uid,
          ['roles' => $antesArr],
          ['roles' => $despuesArr]
        );
      }
      // 2) Alternativa: audit_event(… 'CAMBIAR_ROLES' … payload)
      elseif (function_exists('audit_event')) {
        // Firma común: audit_event($con, $accion, $modulo, $tabla, $registro_id, $payload = [])
        audit_event($con, 'CAMBIAR_ROLES', 'usuarios/roles', 'usuario_rol', $uid, [
          'antes'    => $antesArr,
          'despues'  => $despuesArr,
          'agregados'=> $agregados,
          'quitados' => $quitados
        ]);
      }
      // 3) Fallback: al menos deja un snapshot con audit_create
      elseif (function_exists('audit_create')) {
        // Firma vista en tu proyecto: audit_create($con, $modulo, $tabla, $registro_id, $despues, $estado)
        // No hay "estado" real aquí; usamos 'activo' como marca neutra.
        audit_create($con, 'usuarios/roles', 'usuario_rol', $uid, ['roles' => $despuesArr], 'activo');
      }
    } catch (Throwable $eAud) {
      // No romper la operación por problemas de auditoría
      error_log('AUDITORIA CAMBIAR_ROLES: ' . $eAud->getMessage());
    }
  }

  $con->commit();
  echo 'OK';

} catch (Throwable $e) {
  if ($con->inTransaction()) { $con->rollBack(); }
  echo 'Error: ' . $e->getMessage();
}
