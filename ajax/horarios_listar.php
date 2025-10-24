<?php
// ajax/horarios_listar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ok  = fn($d)=> (print json_encode(['success'=>true,'data'=>$d], JSON_UNESCAPED_UNICODE)) && exit;
$err = function($m,$d=null){
  echo json_encode(['success'=>false,'data'=>[], 'message'=>$m,'debug'=>$d], JSON_UNESCAPED_UNICODE);
  exit;
};

try {
  require_once __DIR__ . '/../config/connection.php';
} catch (Throwable $e) {
  $err('Conexión no disponible', $e->getMessage());
}

try {
  $sql = "
    SELECT
      h.id,
      h.medico_id,
      h.dia_semana,
      CASE h.dia_semana
        WHEN 1 THEN 'Lunes'
        WHEN 2 THEN 'Martes'
        WHEN 3 THEN 'Miércoles'
        WHEN 4 THEN 'Jueves'
        WHEN 5 THEN 'Viernes'
        WHEN 6 THEN 'Sábado'
        WHEN 7 THEN 'Domingo'
      END AS dia_nombre,
      DATE_FORMAT(h.hora_inicio, '%H:%i') AS hora_inicio,
      DATE_FORMAT(h.hora_fin,    '%H:%i') AS hora_fin,
      CASE h.estado
        WHEN 1 THEN 'Activo'
        WHEN 2 THEN 'Inactivo' 
        WHEN 3 THEN 'Disponible'
        ELSE 'Inactivo'
      END AS estado,
      u.nombre_mostrar,
      m.colegiado
    FROM horarios_medicos h
    LEFT JOIN usuarios u ON u.id = h.medico_id
    LEFT JOIN medicos  m ON m.id_medico = h.medico_id
    ORDER BY
      u.nombre_mostrar ASC,
      h.dia_semana ASC,
      h.hora_inicio ASC
  ";

  $st   = $con->query($sql);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $data = array_map(function($r){
    return [
      'id_horario'  => (int)$r['id'],
      'medico_id'   => (int)$r['medico_id'],
      'dia_semana'  => (int)$r['dia_semana'],
      'dia_nombre'  => $r['dia_nombre'] ?: '',
      'hora_inicio' => $r['hora_inicio'],
      'hora_fin'    => $r['hora_fin'],
      'estado'      => $r['estado'],
      'medico'      => $r['nombre_mostrar'] ?: '',
      'colegiado'   => $r['colegiado'] ?: ''
    ];
  }, $rows);

  $ok($data);

} catch (Throwable $e) {
  $err('No se pudieron listar los horarios', $e->getMessage());
}