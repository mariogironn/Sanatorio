<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
  require_once __DIR__ . '/../config/connection.php'; // $con = PDO

  // ---------- Filtros ----------
  $paciente_id   = isset($_GET['paciente_id'])   ? (int)$_GET['paciente_id']   : 0;
  $enfermedad_id = isset($_GET['enfermedad_id']) ? (int)$_GET['enfermedad_id'] : 0;
  $medico_id     = isset($_GET['medico_id'])     ? (int)$_GET['medico_id']     : 0;

  $where = [];
  $args  = [];

  if ($paciente_id > 0)   { $where[] = "d.id_paciente = ?";   $args[] = $paciente_id; }
  if ($enfermedad_id > 0) { $where[] = "d.id_enfermedad = ?"; $args[] = $enfermedad_id; }
  if ($medico_id > 0)     { $where[] = "d.id_medico = ?";     $args[] = $medico_id; }

  $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

  // ---------- Consulta principal (con joins a cat. y banderas) ----------
  $sql = "
    SELECT
      d.id,
      d.id_paciente, d.id_enfermedad, d.id_medico,
      d.sintomas, d.observaciones, d.gravedad,
      DATE_FORMAT(d.fecha, '%Y-%m-%d') AS fecha,
      p.nombre AS paciente,
      e.nombre AS enfermedad,
      e.cie10,
      c.nombre AS categoria,
      u.nombre_mostrar AS medico,
      GROUP_CONCAT(b.nombre ORDER BY b.id SEPARATOR ',') AS banderas
    FROM diagnosticos d
    INNER JOIN pacientes p         ON p.id_paciente = d.id_paciente
    INNER JOIN enfermedades e      ON e.id         = d.id_enfermedad
    LEFT  JOIN categorias_enfermedad c ON c.id     = e.categoria_id
    INNER JOIN usuarios u          ON u.id         = d.id_medico
    LEFT  JOIN enfermedad_banderas eb ON eb.enfermedad_id = e.id
    LEFT  JOIN banderas_enfermedad b  ON b.id = eb.bandera_id
    $whereSql
    GROUP BY d.id
    ORDER BY d.fecha DESC, d.id DESC
  ";

  $st = $con->prepare($sql);
  $st->execute($args);

  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // ---------- Armar payload que consume diagnosticos.php ----------
  $data = [];
  $setPac = [];
  $setEnf = [];
  $setMed = [];

  foreach ($rows as $r) {
    $banderas = array_filter(array_map('trim', explode(',', (string)$r['banderas'])));
    $hasCron  = in_array('Crónica', $banderas, true);
    $hasCont  = in_array('Contagiosa', $banderas, true);

    $data[] = [
      'id'                 => (int)$r['id'],
      'paciente'           => $r['paciente'],
      'enfermedad_corta'   => $r['enfermedad'] . (!empty($r['cie10']) ? " ({$r['cie10']})" : ""),
      'categoria'          => $r['categoria'] ?: null,
      'bandera_cronica'    => $hasCron,
      'bandera_contagiosa' => $hasCont,
      'gravedad'           => $r['gravedad'] ?: '',
      'sintomas'           => $r['sintomas'] ?: '',
      'observaciones'      => $r['observaciones'] ?: '',
      'medico'             => $r['medico'],
      'fecha'              => $r['fecha'],
      // por si luego quieres reutilizar:
      'id_paciente'        => (int)$r['id_paciente'],
      'id_enfermedad'      => (int)$r['id_enfermedad'],
      'id_medico'          => (int)$r['id_medico'],
    ];

    $setPac[$r['id_paciente']] = true;
    $setEnf[$r['id_enfermedad']] = true;
    $setMed[$r['id_medico']] = true;
  }

  $resumen = [
    'total'        => count($rows),
    'pacientes'    => count($setPac),
    'enfermedades' => count($setEnf),
    'medicos'      => count($setMed),
  ];

  echo json_encode(['data' => $data, 'resumen' => $resumen], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['data'=>[], 'resumen'=>['total'=>0,'pacientes'=>0,'enfermedades'=>0,'medicos'=>0], 'error'=>$e->getMessage()]);
}
