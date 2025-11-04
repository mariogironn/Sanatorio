<?php
// ajax/eliminar_medicina.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';

$res = ['success'=>false,'message'=>'Acción no ejecutada','action'=>null];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido');
  }

  // 1) ID
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { throw new Exception('ID inválido'); }

  // 2) Existe
  $st = $con->prepare("SELECT id, estado FROM medicamentos WHERE id = :id FOR UPDATE");
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { throw new Exception('La medicina no existe'); }

  // Helper: verificar existencia de tabla
  $hasTable = function(PDO $con, string $t): bool {
    $q = $con->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $q->execute([':t'=>$t]);
    return (int)$q->fetchColumn() > 0;
  };

  $con->beginTransaction();

  // 3) Conteo de referencias
  $refs = 0;

  // paciente_medicinas
  if ($hasTable($con,'paciente_medicinas')) {
    $q = $con->prepare("SELECT COUNT(*) FROM paciente_medicinas WHERE medicina_id = :id_pm");
    $q->execute([':id_pm'=>$id]);
    $refs += (int)$q->fetchColumn();
  }

  // detalle_recetas
  if ($hasTable($con,'detalle_recetas')) {
    $q = $con->prepare("SELECT COUNT(*) FROM detalle_recetas WHERE id_medicamento = :id_dr");
    $q->execute([':id_dr'=>$id]);
    $refs += (int)$q->fetchColumn();
  }

  // detalles_medicina (si existiera con esa ortografía)
  if ($hasTable($con,'detalles_medicina')) {
    $q = $con->prepare("SELECT COUNT(*) FROM detalles_medicina WHERE id_medicamento = :id_dm");
    $q->execute([':id_dm'=>$id]);
    $refs += (int)$q->fetchColumn();
  }

  // detalle_prescripciones (según tu esquema)
  if ($hasTable($con,'detalle_prescripciones')) {
    $q = $con->prepare("SELECT COUNT(*) FROM detalle_prescripciones WHERE id_medicamento = :id_dp");
    $q->execute([':id_dp'=>$id]);
    $refs += (int)$q->fetchColumn();
  }

  if ($refs > 0) {
    // 4a) Inactivar si está en uso
    $u = $con->prepare("UPDATE medicamentos SET estado = 'inactivo', updated_at = NOW() WHERE id = :id_upd");
    $u->execute([':id_upd'=>$id]);

    $con->commit();
    $res = [
      'success'=>true,
      'action'=>'inactivated',
      'message'=>'La medicina está referenciada. Se inactivó correctamente.'
    ];
  } else {
    // 4b) Eliminar si no tiene referencias
    // Borrar metadatos si existen
    if ($hasTable($con,'medicamentos_meta')) {
      $dmm = $con->prepare("DELETE FROM medicamentos_meta WHERE id_medicamento = :id_mm");
      $dmm->execute([':id_mm'=>$id]);
    }

    $d = $con->prepare("DELETE FROM medicamentos WHERE id = :id_del");
    $d->execute([':id_del'=>$id]);

    $con->commit();
    $res = [
      'success'=>true,
      'action'=>'deleted',
      'message'=>'Medicina eliminada definitivamente.'
    ];
  }

} catch (Throwable $e) {
  if ($con->inTransaction()) { $con->rollBack(); }
  $res['message'] = $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
