<?php
// ajax/historial_get.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once '../config/connection.php';

$res = ['success'=>false,'data'=>null,'message'=>''];

try{
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(400);
    throw new Exception('ID inválido');
  }

  $sql = "
    SELECT
      d.id_detalle              AS id,
      psc.id_paciente           AS paciente_id,
      psc.id_prescripcion       AS id_prescripcion,
      LPAD(psc.id_prescripcion,3,'0') AS n_serie,
      DATE_FORMAT(psc.fecha_visita,'%Y-%m-%d') AS fecha_visita,
      psc.enfermedad,
      d.id_medicamento          AS medicina_id,
      COALESCE(m.nombre_medicamento,'') AS medicina,
      d.cantidad,
      d.dosis
    FROM detalle_prescripciones d
    JOIN prescripciones psc ON psc.id_prescripcion = d.id_prescripcion
    LEFT JOIN medicamentos m ON m.id = d.id_medicamento
    WHERE d.id_detalle = :id
    LIMIT 1
  ";
  $st = $con->prepare($sql);
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) { http_response_code(404); throw new Exception('Registro no encontrado'); }

  $res['success'] = true;
  $res['data'] = $row;

} catch(Throwable $e){
  if (!http_response_code()) http_response_code(400);
  $res['message'] = $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
