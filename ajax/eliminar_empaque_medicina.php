<?php
// ajax/eliminar_empaque_medicina.php
// Elimina un registro de `detalles_medicina` (empaque) y devuelve texto plano.
// Respuestas posibles: "OK" (éxito) o mensaje de error en español.

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/plain; charset=UTF-8');

// ——— Solo permitir POST ———
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  echo 'Método no permitido'; exit;
}

// ——— Conexión ———
require_once __DIR__ . '/../config/connection.php';

// === Auditoría ===
require_once __DIR__ . '/../common_service/auditoria_service.php';
$haveHelpers = @include_once __DIR__ . '/../common_service/audit_helpers.php';

// ——— Parámetros ———
$idDetalle = (int)($_POST['id_detalle'] ?? 0);
if ($idDetalle <= 0) { echo 'Parámetros inválidos'; exit; }

try {
  // 1) Verificar existencia
  $st = $con->prepare("SELECT id FROM detalles_medicina WHERE id = :i LIMIT 1");
  $st->bindValue(':i', $idDetalle, PDO::PARAM_INT);
  $st->execute();
  if (!$st->fetch(PDO::FETCH_ASSOC)) { echo 'El empaque no existe.'; exit; }

  // 2) Verificar uso en historial (mensaje claro en vez de error de FK)
  $ck = $con->prepare("
    SELECT COUNT(*) 
    FROM historial_medicacion_paciente 
    WHERE id_detalle_medicina = :i
  ");
  $ck->bindValue(':i', $idDetalle, PDO::PARAM_INT);
  $ck->execute();
  if ((int)$ck->fetchColumn() > 0) {
    echo 'No se puede eliminar: el empaque está siendo utilizado en el historial de pacientes.';
    exit;
  }

  // 2.5) Snapshot ANTES (mejor esfuerzo) para auditoría
  $antes = null;
  try {
    if ($haveHelpers && function_exists('audit_row')) {
      $antes = audit_row($con, 'detalles_medicina', 'id', $idDetalle);
    }
    if (!$antes) {
      $sf = $con->prepare("SELECT * FROM detalles_medicina WHERE id = :i");
      $sf->execute([':i'=>$idDetalle]);
      $antes = $sf->fetch(PDO::FETCH_ASSOC) ?: ['id' => $idDetalle];
    }
  } catch (Throwable $e) {
    $antes = ['id' => $idDetalle]; // fallback mínimo
  }

  // 3) Borrar
  $del = $con->prepare("DELETE FROM detalles_medicina WHERE id = :i");
  $del->bindValue(':i', $idDetalle, PDO::PARAM_INT);
  $del->execute();

  if ($del->rowCount() === 1) {
    // Auditoría: DELETE (estado_resultante = 'inactivo' lo marca audit_delete)
    try {
      audit_delete($con, 'Medicinas', 'detalles_medicina', $idDetalle, $antes);
    } catch (Throwable $eAud) {
      error_log('AUD DELETE detalles_medicina: '.$eAud->getMessage());
      // no romper la UX
    }
    echo 'OK'; exit;
  } else {
    echo 'No se pudo eliminar.'; exit;
  }

} catch (PDOException $ex) {
  // 23000 = violación de integridad (por cualquier otra referencia)
  $msg = ($ex->getCode() === '23000')
    ? 'No se puede eliminar: el empaque está siendo utilizado.'
    : ('Error: ' . $ex->getMessage());
  echo $msg; exit;
}
