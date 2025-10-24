<?php
// ajax/catalogos_diagnosticos.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connection.php';

$out = ['ok' => true, 'pacientes' => [], 'enfermedades' => [], 'medicos' => []];

try {
  // PACIENTES (activos)
  $sql = "SELECT id_paciente AS id, nombre AS paciente
          FROM pacientes
          WHERE estado = 'activo'
          ORDER BY nombre";
  $out['pacientes'] = $con->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $out['ok'] = false; $out['error'] = 'PAC: '.$e->getMessage();
}

try {
  // ENFERMEDADES (todas)
  $sql = "SELECT id, nombre AS enfermedad
          FROM enfermedades
          ORDER BY nombre";
  $out['enfermedades'] = $con->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $out['ok'] = false; $out['error'] = ($out['error'] ?? '').' | ENF: '.$e->getMessage();
}

try {
  // MÉDICOS desde tabla medicos (incluye activos e inactivos)
  $sql = "
    SELECT
      m.id_medico AS id,
      TRIM(
        CONCAT(
          COALESCE(u.nombre_mostrar, u.usuario),
          CASE
            WHEN s.nombre IS NULL OR s.nombre = '' THEN ''
            ELSE CONCAT(' - ', s.nombre)
          END
        )
      ) AS medico
    FROM medicos m
    JOIN usuarios u          ON u.id = m.usuario_id
    LEFT JOIN especialidades s ON s.id_especialidad = m.especialidad_id
    WHERE m.estado IN ('activo','inactivo')
    ORDER BY u.nombre_mostrar, u.usuario
  ";
  $out['medicos'] = $con->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $out['ok'] = false; $out['error'] = ($out['error'] ?? '').' | MED: '.$e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
