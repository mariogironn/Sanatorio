<?php
// ajax/eliminar_paciente.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';

// === Auditoría ===
require_once __DIR__ . '/../common_service/auditoria_service.php';
$haveHelpers = @include_once __DIR__ . '/../common_service/audit_helpers.php';

$out = ['ok' => false, 'msg' => ''];

try {
  // ID y cascada pueden venir por POST o GET
  $id      = isset($_POST['id'])      ? (int)$_POST['id']      : (int)($_GET['id'] ?? 0);
  $cascade = isset($_POST['cascade']) ? (int)$_POST['cascade'] : (int)($_GET['cascade'] ?? 0);

  if ($id <= 0) throw new Exception('ID inválido.');

  // ------ Detectar PK real de la tabla pacientes ------
  $PKP = 'id_paciente';
  try {
    $ck = $con->query("SHOW COLUMNS FROM pacientes LIKE 'id_paciente'");
    if (!$ck || $ck->rowCount() === 0) { $PKP = 'id'; }
  } catch (Throwable $e) { /* noop */ }

  // ¿existe el paciente? y traer snapshot ANTES
  $st = $con->prepare("SELECT `$PKP` FROM pacientes WHERE `$PKP` = :id LIMIT 1");
  $st->execute([':id' => $id]);
  if (!$st->fetchColumn()) throw new Exception('El paciente no existe.');

  // Snapshot ANTES (mejor esfuerzo)
  $antes = null;
  try {
    if ($haveHelpers && function_exists('audit_row')) {
      $antes = audit_row($con, 'pacientes', $PKP, $id);
    }
    if (!$antes) {
      $stFull = $con->prepare("SELECT * FROM pacientes WHERE `$PKP` = :id");
      $stFull->execute([':id' => $id]);
      $antes = $stFull->fetch(PDO::FETCH_ASSOC) ?: [$PKP => $id];
    }
  } catch (Throwable $e) {
    $antes = [$PKP => $id]; // fallback mínimo
  }

  // Conteos relacionados (hijos suelen usar id_paciente)
  $qv = $con->prepare("SELECT COUNT(*) FROM visitas_pacientes WHERE id_paciente = :id");
  $qv->execute([':id' => $id]);
  $numVisitas = (int)$qv->fetchColumn();

  $qh = $con->prepare("
    SELECT COUNT(*)
    FROM historial_medicacion_paciente h
    JOIN visitas_pacientes v ON v.id = h.id_visita_paciente
    WHERE v.id_paciente = :id
  ");
  $qh->execute([':id' => $id]);
  $numHist = (int)$qh->fetchColumn();

  // Si hay dependencias y no se pidió cascada
  if (($numVisitas > 0 || $numHist > 0) && !$cascade) {
    $out['ok']  = false;
    $out['msg'] = "No se puede eliminar: hay $numVisitas visita(s) y $numHist registro(s) en el historial.";
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ---------- Borrado en transacción ----------
  $con->beginTransaction();

  if ($numHist > 0) {
    $delH = $con->prepare("
      DELETE h
      FROM historial_medicacion_paciente h
      JOIN visitas_pacientes v ON v.id = h.id_visita_paciente
      WHERE v.id_paciente = :id
    ");
    $delH->execute([':id' => $id]);
  }

  if ($numVisitas > 0) {
    $delV = $con->prepare("DELETE FROM visitas_pacientes WHERE id_paciente = :id");
    $delV->execute([':id' => $id]);
  }

  $delP = $con->prepare("DELETE FROM pacientes WHERE `$PKP` = :id");
  $delP->execute([':id' => $id]);

  $con->commit();

  // ---------- Auditoría (fuera de la tx para no romper UX) ----------
  try {
    if ($delP->rowCount() > 0) {
      // audit_delete graba estado_resultante = 'inactivo'
      audit_delete($con, 'Pacientes', 'pacientes', $id, $antes);
    }
  } catch (Throwable $eAud) {
    error_log('AUDITORIA DELETE pacientes: ' . $eAud->getMessage());
  }

  $out['ok']  = true;
  $out['msg'] = 'Paciente eliminado correctamente.';
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  if (isset($con) && $con instanceof PDO && $con->inTransaction()) { $con->rollBack(); }

  // Mensaje amigable si es restricción de integridad
  $msg = $e->getMessage();
  if ($e instanceof PDOException && isset($e->errorInfo[0]) && $e->errorInfo[0] === '23000') {
    $msg = 'No se puede eliminar por registros relacionados.';
  }
  $out['ok']  = false;
  $out['msg'] = $msg;
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}
