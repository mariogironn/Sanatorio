<?php
// ajax/recetas_recientes.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';

header('Content-Type: text/html; charset=utf-8');

/* ==== Helpers para formato ==== */
function _fmt_es(string $fecha): string {
  $s = date('d/m/Y h:i a', strtotime($fecha));
  return str_replace(['am','pm'], ['a. m.','p. m.'], $s);
}
function _prefijo_rol(?string $rol): string {
  $r = strtolower((string)$rol);
  if ($r === 'medico' || $r === 'doctor') return 'Dr. ';
  if ($r === 'enfermero' || $r === 'enfermera') return 'Enf. ';
  return '';
}

try {
  // Trae rol del usuario que recetó (si lo tiene) para prefijo Dr./Enf.
  $sql = "
    SELECT pm.id,
           pm.fecha_asignacion,
           pm.dosis,
           pm.frecuencia,
           pm.motivo_prescripcion,
           p.nombre               AS paciente,
           m.nombre_medicamento   AS med,
           COALESCE(u.nombre_mostrar,'') AS medico,
           u.id                   AS medico_id,
           (
             SELECT LOWER(r.nombre)
               FROM usuario_rol ur
               JOIN roles r ON r.id_rol = ur.id_rol
              WHERE ur.id_usuario = u.id
              LIMIT 1
           ) AS rol_medico
      FROM paciente_medicinas pm
      JOIN pacientes     p ON p.id_paciente = pm.paciente_id
      JOIN medicamentos  m ON m.id          = pm.medicina_id
 LEFT JOIN usuarios      u ON u.id          = pm.usuario_id
     WHERE pm.estado='activo'
  ORDER BY pm.fecha_asignacion DESC
     LIMIT 20
  ";

  $st = $con->query($sql);

  $hay = false;
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $hay = true;

    // Extrae número de la dosis si existe; si no, usa el texto completo
    $dosisTxt = trim((string)($r['dosis'] ?? ''));
    if (preg_match('/\d+/', $dosisTxt, $m)) {
      $cant = $m[0]; // solo el número
    } else {
      $cant = $dosisTxt; // sin número, mostrar texto
    }

    $paciente  = htmlspecialchars($r['paciente'] ?? '');
    $med       = htmlspecialchars($r['med'] ?? '');
    $motivo    = htmlspecialchars($r['motivo_prescripcion'] ?? '');
    $fecha     = _fmt_es($r['fecha_asignacion'] ?? date('Y-m-d H:i:s'));
    $medico    = htmlspecialchars($r['medico'] ?? '');
    $medicoId  = (int)($r['medico_id'] ?? 0);
    $prefijo   = _prefijo_rol($r['rol_medico'] ?? '');

    echo '<div class="recent-item">';
    echo '  <div class="d-flex align-items-start">';
    echo '    <div class="flex-grow-1 pr-2">';
    echo '      <div class="font-weight-bold">'.$paciente.'</div>';
    echo '      <div>'.$med.($cant!=='' ? ' - '.htmlspecialchars($cant) : '').'</div>';
    echo '      <div class="text-muted">Para: '.$motivo.'</div>';
    echo '    </div>';
    echo '    <div class="text-right" style="min-width:180px;">';
    echo '      <div class="text-muted small">'.$fecha.'</div>';
    if ($medico !== '') {
      if ($medicoId > 0) {
        echo '    <div><a href="../usuarios.php?user='.$medicoId.'">'.$prefijo.$medico.'</a></div>';
      } else {
        echo '    <div>'.$prefijo.$medico.'</div>';
      }
    }
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
  }

  if (!$hay) {
    echo '<em>No hay recetas recientes.</em>';
  }

} catch (Throwable $e) {
  // Silencioso para el usuario final; evita fugas de información
  echo '<em>No hay recetas recientes.</em>';
}
