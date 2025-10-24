<?php
// ajax/tratamiento_meds.php
// Respuesta: { ok:true, info:{codigo,paciente,diagnostico}, meds:[{id,nombre,detalle}], error?:string }

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$res = ['ok'=>false, 'info'=>null, 'meds'=>[], 'error'=>null];

try {
  // Acepta id o id_tratamiento
  $id = isset($_REQUEST['id_tratamiento']) ? (int)$_REQUEST['id_tratamiento'] : 0;
  if ($id <= 0) { $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0; }
  if ($id <= 0) { throw new Exception('ID de tratamiento inválido'); }

  // --- Encabezado: código, paciente y diagnóstico
  // tratamientos.id -> diagnosticos.id -> pacientes.nombre / enfermedades.nombre
  $sqlInfo = "
    SELECT
      t.id,
      CONCAT('TR-', LPAD(t.id, 4, '0')) AS codigo,
      p.nombre   AS paciente,
      enf.nombre AS diagnostico
    FROM tratamientos t
    JOIN diagnosticos d    ON d.id = t.id_diagnostico
    JOIN pacientes   p     ON p.id_paciente = d.id_paciente
    JOIN enfermedades enf  ON enf.id = d.id_enfermedad
    WHERE t.id = :id
    LIMIT 1
  ";
  $stI = $con->prepare($sqlInfo);
  $stI->execute([':id' => $id]);
  $info = $stI->fetch(PDO::FETCH_ASSOC);
  if (!$info) { throw new Exception('Tratamiento no encontrado'); }

  // --- Medicamentos asociados (según tu esquema)
  // tratamiento_medicamentos.id_tratamiento -> medicamentos.id (nombre_medicamento)
  // detalles_medicina (opcional) para mostrar “empaque”
  $sqlMeds = "
    SELECT
      m.id,
      m.nombre_medicamento AS nombre,
      dm.empaque
    FROM tratamiento_medicamentos tm
    JOIN medicamentos m           ON m.id = tm.id_medicamento
    LEFT JOIN detalles_medicina dm ON dm.id_medicamento = m.id
    WHERE tm.id_tratamiento = :id
    ORDER BY m.nombre_medicamento ASC
  ";
  $stM = $con->prepare($sqlMeds);
  $stM->execute([':id' => $id]);
  $rows = $stM->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Mapeo a la forma que espera el front
  $meds = array_map(function($r){
    $detalle = '';
    if (!empty($r['empaque'])) {
      $detalle = $r['empaque'] . ' mg, 1 tableta al día'; // ajusta el texto si quieres
    }
    return [
      'id'      => (int)$r['id'],
      'nombre'  => $r['nombre'],
      'detalle' => $detalle
    ];
  }, $rows);

  $res['ok']   = true;
  $res['info'] = $info;
  $res['meds'] = $meds;

} catch (Throwable $e) {
  $res['ok'] = false;
  $res['error'] = $e->getMessage();  // <- visible para depurar si algo falla
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
