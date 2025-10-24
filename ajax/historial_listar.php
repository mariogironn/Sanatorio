<?php
// ajax/historial_listar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';

$out = ['success'=>false,'message'=>'','data'=>[]];

try {
  $pid = (int)($_GET['paciente_id'] ?? 0);
  if ($pid <= 0) {
    http_response_code(400);
    throw new Exception('Paciente inválido.');
  }

  // OJO: la FK correcta es d.id_medicamento
  $sql = "
    SELECT
      d.id_detalle                              AS id,
      p.id_prescripcion                         AS prescripcion_id,
      LPAD(p.id_prescripcion,3,'0')             AS n_serie,
      DATE_FORMAT(p.fecha_visita,'%d/%m/%Y')    AS fecha_visita,
      p.enfermedad,
      COALESCE(m.nombre_medicamento,'(eliminado)') AS medicina,
      d.cantidad,
      d.dosis
    FROM prescripciones p
    JOIN detalle_prescripciones d
      ON d.id_prescripcion = p.id_prescripcion
    LEFT JOIN medicamentos m
      ON m.id = d.id_medicamento
    WHERE p.id_paciente = :pid
    ORDER BY p.fecha_visita DESC, d.id_detalle DESC
  ";

  $st = $con->prepare($sql);
  $st->execute([':pid'=>$pid]);
  $out['success'] = true;
  $out['data']    = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  if (!http_response_code()) { http_response_code(400); }
  $out['message'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
