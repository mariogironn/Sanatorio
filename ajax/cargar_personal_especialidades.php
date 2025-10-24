<?php
// ajax/cargar_personal_especialidades.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/html; charset=utf-8');

try {
  require_once __DIR__ . '/../config/connection.php';
} catch (Throwable $e) {
  echo '<option value="">Error al cargar personal</option>';
  exit;
}

/*
  Estructura usada:
  - usuarios.id (ACTIVO)
  - medicos.id_medico, medicos.especialidad_id, medicos.estado, medicos.colegiado
  - especialidades.id, especialidades.nombre
  (ver sanatorio.sql) :contentReference[oaicite:0]{index=0}
*/

$sql = "
  SELECT
    u.id                  AS id_medico,
    u.nombre_mostrar      AS nombre,
    m.colegiado,
    m.estado              AS estado_medico,
    e.id                  AS especialidad_id,
    e.nombre              AS especialidad
  FROM usuarios u
  JOIN medicos m           ON m.id_medico = u.id
  LEFT JOIN especialidades e ON e.id = m.especialidad_id
  WHERE u.estado = 'ACTIVO'
    -- estados permitidos para asignación: 1=activa, 3=capacitacion (inactiva->0 si la usas)
    AND (m.estado IN (0,1,3))
  ORDER BY u.nombre_mostrar ASC";

try {
  $st = $con->prepare($sql);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  if (!$rows) {
    echo '<option value="">Sin personal disponible</option>';
    exit;
  }

  $html = '<option value="">Seleccione médico o enfermero</option>';
  foreach ($rows as $r) {
    $esp = $r['especialidad'] ? (' — ' . $r['especialidad']) : '';
    $col = $r['colegiado'] ? (' | Colegiado: ' . htmlspecialchars($r['colegiado'])) : '';
    $html .= '<option value="' . (int)$r['id_medico'] . '">'
           . htmlspecialchars($r['nombre']) . $esp . $col
           . '</option>';
  }
  echo $html;
} catch (Throwable $e) {
  echo '<option value="">Error al cargar personal</option>';
}
