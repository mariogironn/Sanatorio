<?php
// ajax/tratamientos_listar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';

header('Content-Type: application/json; charset=utf-8');
$res = ['success' => false, 'data' => [], 'message' => ''];

try {
  // SQL alineado a tu esquema
  $sql = "
    SELECT
      t.id,
      t.id_diagnostico,
      t.id_medico,
      t.fecha_inicio,
      t.duracion,
      t.estado,
      d.id               AS dx_id,
      enf.nombre         AS diagnostico_nombre,
      p.nombre           AS paciente_nombre,
      u.nombre_mostrar   AS medico_nombre
    FROM tratamientos t
    INNER JOIN diagnosticos d   ON d.id = t.id_diagnostico
    LEFT  JOIN enfermedades enf ON enf.id = d.id_enfermedad
    LEFT  JOIN pacientes p      ON p.id_paciente = d.id_paciente
    LEFT  JOIN usuarios u       ON u.id = t.id_medico
    ORDER BY t.id DESC
  ";

  $st = $con->prepare($sql);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Adaptar al formato que consume tu DataTable
  $data = array_map(function ($r) {
    $id   = (int)$r['id'];
    $code = 'TR-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);

    return [
      'id'           => $id,
      'codigo'       => $code,
      'diagnostico'  => $r['diagnostico_nombre'] ?: '—',
      'paciente'     => $r['paciente_nombre'] ?: '—',
      'medico'       => $r['medico_nombre'] ?: '—',
      'fecha_inicio' => $r['fecha_inicio'],
      'duracion'     => $r['duracion'] ?: '—',
      'estado'       => $r['estado'] ?: 'activo',
    ];
  }, $rows);

  $res['success'] = true;
  $res['data']    = $data;

} catch (Throwable $e) {
  $res['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
