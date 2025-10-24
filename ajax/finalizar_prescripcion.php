<?php
// ajax/finalizar_prescripcion.php — Finaliza una prescripción (estado -> 'completada')
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';
require_once '../common_service/auditoria_service.php';

$out = ['success'=>false,'message'=>'Error desconocido'];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Método no permitido'); }
  $uid = (int)($_SESSION['user_id'] ?? 0);
  if ($uid <= 0) { throw new Exception('No autenticado'); }

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { throw new Exception('ID inválido'); }

  // ===== Autorización: rol clínico + permiso actualizar en módulos relevantes
  $roles = [];
  $rs = $con->prepare("SELECT LOWER(r.nombre) rol
                       FROM usuario_rol ur JOIN roles r ON r.id_rol = ur.id_rol
                       WHERE ur.id_usuario = :u");
  $rs->execute([':u'=>$uid]);
  $roles = array_map(fn($x)=>$x['rol'],$rs->fetchAll(PDO::FETCH_ASSOC));
  $esClinico = (bool) array_intersect($roles, ['medico','doctor','enfermero','enfermera']);
  if (!$esClinico) { throw new Exception('No autorizado (rol)'); }

  $mods = $con->query("SELECT id_modulo FROM modulos WHERE LOWER(nombre) IN ('prescripciones','medicinas','medicamentos','pacientes')")->fetchAll(PDO::FETCH_COLUMN);
  $permisoActualizar = false;
  if ($mods) {
    $qp = $con->prepare("
      SELECT 1
      FROM rol_permiso rp
      JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
      WHERE ur.id_usuario = :u AND rp.id_modulo IN (".implode(',', array_map('intval',$mods)).") AND rp.actualizar = 1
      LIMIT 1
    ");
    $qp->execute([':u'=>$uid]);
    $permisoActualizar = (bool)$qp->fetchColumn();
  }
  if (!$permisoActualizar) { throw new Exception('No autorizado (permiso)'); }

  // ===== Datos actuales
  $st = $con->prepare("SELECT * FROM prescripciones WHERE id_prescripcion = :id LIMIT 1");
  $st->execute([':id'=>$id]);
  $antes = $st->fetch(PDO::FETCH_ASSOC);
  if (!$antes) { throw new Exception('Prescripción no encontrada'); }
  if (strtolower($antes['estado']) === 'completada') {
    $out = ['success'=>true,'message'=>'Ya estaba completada']; echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
  }

  // ===== Actualizar
  $con->beginTransaction();
  $up = $con->prepare("UPDATE prescripciones SET estado='completada', updated_at = NOW() WHERE id_prescripcion = :id");
  $up->execute([':id'=>$id]);

  // Snapshot después
  $st2 = $con->prepare("SELECT * FROM prescripciones WHERE id_prescripcion = :id LIMIT 1");
  $st2->execute([':id'=>$id]);
  $despues = $st2->fetch(PDO::FETCH_ASSOC);

  // Auditoría
  audit_update($con, 'Prescripciones', 'prescripciones', $id, $antes, $despues);

  $con->commit();

  $out = ['success'=>true,'message'=>'Prescripción finalizada correctamente'];
} catch (Throwable $e) {
  if ($con->inTransaction()) { $con->rollBack(); }
  $out['message'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
