<?php
// ajax/historial_guardar.php  (crear o actualizar)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once '../config/connection.php';

$res = ['success'=>false,'message'=>'','row'=>null];

try{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  // Campos del formulario
  $detalle_id   = (int)($_POST['detalle_id'] ?? 0);
  $paciente_id  = (int)($_POST['paciente_id'] ?? 0);
  $fecha_visita = trim($_POST['fecha_visita'] ?? '');
  $enfermedad   = trim($_POST['enfermedad'] ?? '');
  $medicina_txt = trim($_POST['medicina'] ?? '');
  $cantidad     = (int)($_POST['cantidad'] ?? 0);
  $dosis        = trim($_POST['dosis'] ?? '');

  if ($fecha_visita === '' || $enfermedad === '' || $medicina_txt === '') {
    http_response_code(400);
    throw new Exception('Complete los campos requeridos.');
  }

  // Resolver/crear medicamento por nombre
  $con->beginTransaction();
  $medId = null;
  $q = $con->prepare("SELECT id FROM medicamentos WHERE nombre_medicamento = :n LIMIT 1");
  $q->execute([':n'=>$medicina_txt]);
  $medId = $q->fetchColumn();

  if (!$medId) {
    $ins = $con->prepare("INSERT INTO medicamentos (nombre_medicamento) VALUES (:n)");
    $ins->execute([':n'=>$medicina_txt]);
    $medId = (int)$con->lastInsertId();
  }

  if ($detalle_id > 0) {
    // --- UPDATE ---
    // Obtener id_prescripcion
    $q = $con->prepare("SELECT id_prescripcion FROM detalle_prescripciones WHERE id_detalle = :id");
    $q->execute([':id'=>$detalle_id]);
    $idPres = (int)$q->fetchColumn();
    if ($idPres <= 0) { throw new Exception('Prescripción no encontrada'); }

    // Actualizar cabecera (fecha y enfermedad)
    $upP = $con->prepare("UPDATE prescripciones SET fecha_visita=:f, enfermedad=:e WHERE id_prescripcion=:id");
    $upP->execute([':f'=>$fecha_visita, ':e'=>$enfermedad, ':id'=>$idPres]);

    // Actualizar detalle (usa id_medicamento correcto)
    $upD = $con->prepare("
      UPDATE detalle_prescripciones
         SET id_medicamento=:m, cantidad=:c, dosis=:d
       WHERE id_detalle=:id
    ");
    $upD->execute([':m'=>$medId, ':c'=>$cantidad, ':d'=>$dosis, ':id'=>$detalle_id]);

    $newIdDet = $detalle_id;

  } else {
    // --- CREATE ---
    if ($paciente_id <= 0) { throw new Exception('Paciente inválido'); }

    // Crear cabecera mínima (sucursal obligatoria en tu tabla -> usamos un valor por defecto)
    $insP = $con->prepare("
      INSERT INTO prescripciones (id_paciente, fecha_visita, enfermedad, sucursal, estado)
      VALUES (:p, :f, :e, 'Historial', 'activa')
    ");
    $insP->execute([':p'=>$paciente_id, ':f'=>$fecha_visita, ':e'=>$enfermedad]);
    $idPres = (int)$con->lastInsertId();

    // Crear detalle
    $insD = $con->prepare("
      INSERT INTO detalle_prescripciones (id_prescripcion, id_medicamento, empaque, cantidad, dosis)
      VALUES (:idp, :idm, '', :c, :d)
    ");
    $insD->execute([':idp'=>$idPres, ':idm'=>$medId, ':c'=>$cantidad, ':d'=>$dosis]);
    $newIdDet = (int)$con->lastInsertId();
  }

  // Devolver la fila formateada igual que en listar
  $st = $con->prepare("
    SELECT
      d.id_detalle           AS id,
      psc.id_prescripcion    AS id_prescripcion,
      LPAD(psc.id_prescripcion,3,'0') AS n_serie,
      DATE_FORMAT(psc.fecha_visita,'%d/%m/%Y') AS fecha_visita,
      psc.enfermedad,
      COALESCE(m.nombre_medicamento,'') AS medicina,
      d.cantidad,
      d.dosis
    FROM detalle_prescripciones d
    JOIN prescripciones psc ON psc.id_prescripcion = d.id_prescripcion
    LEFT JOIN medicamentos m ON m.id = d.id_medicamento
    WHERE d.id_detalle = :id
  ");
  $st->execute([':id'=>$newIdDet]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  $con->commit();
  $res['success'] = true;
  $res['row'] = $row;

} catch(Throwable $e){
  if ($con && $con->inTransaction()) $con->rollBack();
  if (!http_response_code()) http_response_code(400);
  $res['message'] = $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
