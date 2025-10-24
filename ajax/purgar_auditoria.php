<?php
// ajax/purgar_auditoria.php
// Elimina registros de la tabla `auditoria` hasta una fecha (incluida).
// Entrada: POST fecha_hasta=YYYY-MM-DD  (acepta también dd/mm/yyyy)
// Opcional: POST dry_run=1  -> solo cuenta, no borra
// Salida: JSON { ok:bool, deleted:int, msg:string }

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';

// Auditoría (mejor esfuerzo, sin romper)
$haveAud = false;
try {
  require_once __DIR__ . '/../common_service/auditoria_service.php';
  $haveAud = true;
  @include_once __DIR__ . '/../common_service/audit_helpers.php';
} catch (Throwable $e) { /* noop */ }

function parse_fecha_hasta(string $s): ?string {
  $s = trim($s);
  if ($s === '') return null;

  // HTML <input type="date"> -> YYYY-MM-DD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

  // dd/mm/yyyy
  $dt = DateTime::createFromFormat('d/m/Y', $s);
  if ($dt && $dt->format('d/m/Y') === $s) return $dt->format('Y-m-d');

  // mm/dd/yyyy por si acaso
  $dt = DateTime::createFromFormat('m/d/Y', $s);
  if ($dt && $dt->format('m/d/Y') === $s) return $dt->format('Y-m-d');

  return null;
}

try {
  if (!($con instanceof PDO)) {
    echo json_encode(['ok'=>false,'msg'=>'Conexión no disponible.']); exit;
  }
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $rawFecha = $_POST['fecha_hasta'] ?? ($_GET['fecha_hasta'] ?? ($_POST['hasta'] ?? ''));
  $fecha = parse_fecha_hasta((string)$rawFecha);
  if (!$fecha) { echo json_encode(['ok'=>false,'msg'=>'Fecha inválida.']); exit; }

  $dry = (int)($_POST['dry_run'] ?? 0) === 1;

  // Contar primero
  $stCnt = $con->prepare("SELECT COUNT(*) FROM auditoria WHERE DATE(creado_en) <= :f");
  $stCnt->execute([':f'=>$fecha]);
  $total = (int)$stCnt->fetchColumn();

  if ($dry) {
    echo json_encode(['ok'=>true, 'deleted'=>0, 'to_delete'=>$total, 'msg'=>"Se eliminarían $total registros hasta $fecha"]); 
    exit;
  }

  if ($total === 0) {
    echo json_encode(['ok'=>true, 'deleted'=>0, 'msg'=>'No hay registros para purgar.']); 
    exit;
  }

  // Borrar en transacción
  $con->beginTransaction();
  $stDel = $con->prepare("DELETE FROM auditoria WHERE DATE(creado_en) <= :f");
  $stDel->execute([':f'=>$fecha]);
  $deleted = (int)$stDel->rowCount();
  $con->commit();

  // Registrar un rastro de la purga (si tu servicio de auditoría está disponible)
  if ($haveAud) {
    try {
      if (function_exists('audit_log')) {
        audit_log($con, [
          'modulo'            => 'Auditoría',
          'tabla'             => 'auditoria',
          'id_registro'       => null,
          'accion'            => 'DELETE',
          'antes'             => ['purga_hasta'=>$fecha, 'borrados'=>$deleted],
          'despues'           => null,
          'estado_resultante' => 'activo'
        ]);
      } else if (function_exists('audit_create')) {
        // Como fallback, registra un CREATE simbólico con el resumen de purga
        audit_create($con, 'Auditoría', 'auditoria', 0, ['purga_hasta'=>$fecha, 'borrados'=>$deleted], 'activo');
      }
    } catch (Throwable $e) {
      // no interrumpir la respuesta
      error_log('AUD PURGA auditoria: '.$e->getMessage());
    }
  }

  echo json_encode(['ok'=>true, 'deleted'=>$deleted, 'msg'=>"Se eliminaron $deleted registros hasta $fecha."]);
  exit;

} catch (Throwable $e) {
  if ($con instanceof PDO && $con->inTransaction()) { $con->rollBack(); }
  echo json_encode(['ok'=>false, 'msg'=>'Error: '.$e->getMessage()]);
  exit;
}
