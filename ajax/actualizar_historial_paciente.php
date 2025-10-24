<?php
// ajax/actualizar_historial_paciente.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once '../config/connection.php';

$out = ['success'=>false, 'message'=>'', 'row'=>null];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  $uid = (int)($_SESSION['user_id'] ?? 0);
  if ($uid <= 0) {
    http_response_code(401);
    throw new Exception('No autenticado');
  }

  $detalle_id     = (int)($_POST['detalle_id'] ?? 0);
  $prescripcion_id= (int)($_POST['prescripcion_id'] ?? 0);
  $fecha          = trim($_POST['fecha_visita'] ?? '');
  $enfermedad     = trim($_POST['enfermedad'] ?? '');
  $medicina_id    = (int)($_POST['medicina_id'] ?? 0);
  $cantidad       = (int)($_POST['cantidad'] ?? 0);
  $dosis          = trim($_POST['dosis'] ?? '');

  if ($detalle_id<=0 || $prescripcion_id<=0 || !$fecha || !$enfermedad || $medicina_id<=0 || $cantidad<=0 || !$dosis) {
    http_response_code(400);
    throw new Exception('Datos incompletos');
  }

  $con->beginTransaction();

  // 1) Actualizar cabecera
  $u1 = $con->prepare("
    UPDATE prescripciones
       SET fecha_visita = :f, enfermedad = :enf, updated_by = :ub, updated_at = NOW()
     WHERE id_prescripcion = :pid
  ");
  $u1->execute([':f'=>$fecha, ':enf'=>$enfermedad, ':ub'=>$uid, ':pid'=>$prescripcion_id]);

  // 2) Actualizar detalle (empaque vacío)
  $u2 = $con->prepare("
    UPDATE detalle_prescripciones
       SET id_medicina = :mid, cantidad = :cant, dosis = :d, empaque = ''
     WHERE id_detalle = :did
  ");
  $u2->execute([':mid'=>$medicina_id, ':cant'=>$cantidad, ':d'=>$dosis, ':did'=>$detalle_id]);

  // 3) Devolver fila
  $st = $con->prepare("
    SELECT 
      d.id_detalle, p.id_prescripcion, p.fecha_visita, p.enfermedad, p.sucursal,
      m.id AS id_medicina, m.nombre_medicamento AS medicina, d.cantidad, d.dosis
    FROM detalle_prescripciones d
    JOIN prescripciones p ON p.id_prescripcion = d.id_prescripcion
    JOIN medicamentos m ON m.id = d.id_medicina
    WHERE d.id_detalle = :det
  ");
  $st->execute([':det'=>$detalle_id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);

  $out['row'] = [
    'detalle_id'      => (int)$r['id_detalle'],
    'prescripcion_id' => (int)$r['id_prescripcion'],
    'n_serie'         => str_pad($r['id_prescripcion'], 3, '0', STR_PAD_LEFT),
    'fecha_visita'    => $r['fecha_visita'],
    'enfermedad'      => $r['enfermedad'],
    'id_medicina'     => (int)$r['id_medicina'],
    'medicina'        => $r['medicina'],
    'cantidad'        => (int)$r['cantidad'],
    'dosis'           => $r['dosis'],
    'sucursal'        => $r['sucursal'],
  ];

  $con->commit();
  $out['success'] = true;
  $out['message'] = 'Registro actualizado';
} catch (Throwable $e) {
  if ($con && $con->inTransaction()) { $con->rollBack(); }
  if (!http_response_code()) { http_response_code(400); }
  $out['message'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
