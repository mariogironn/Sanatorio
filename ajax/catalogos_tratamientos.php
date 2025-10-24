<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/connection.php';

try {
  // --- Diagnósticos (Dx NNN - Enfermedad (Paciente))
  $sqlDx = "
    SELECT d.id,
           CONCAT('D-', LPAD(d.id,3,'0'), ' - ', e.nombre, ' (', p.nombre, ')') AS text
    FROM diagnosticos d
    JOIN pacientes    p ON p.id_paciente = d.id_paciente
    JOIN enfermedades e ON e.id = d.id_enfermedad
    ORDER BY d.id DESC
  ";
  $dx = $con->query($sqlDx)->fetchAll(PDO::FETCH_ASSOC);

  // --- ¿Existe tabla medicos?
  $existsMedicos = $con->query("
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'medicos'
  ")->fetchColumn() > 0;

  if ($existsMedicos) {
    // Preferimos tabla medicos + especialidades
    $sqlMed = "
      SELECT u.id AS id,
             CONCAT(
               CASE
                 WHEN u.nombre_mostrar REGEXP '^[[:space:]]*Dra?\\.' THEN ''
                 ELSE CASE WHEN SUBSTRING_INDEX(u.nombre_mostrar,' ',1) IN ('Dra.','Dr.') THEN '' ELSE 'Dr. ' END
               END,
               u.nombre_mostrar,
               COALESCE(CONCAT(' - ', esp.nombre), '')
             ) AS text
      FROM medicos m
      JOIN usuarios u     ON u.id = m.id_medico AND u.estado='ACTIVO'
      LEFT JOIN especialidades esp ON esp.id = m.especialidad_id
      WHERE m.estado = 1
      ORDER BY u.nombre_mostrar
    ";
  } else {
    // Fallback: usuarios con rol clínico (nombre rol: 'Doctor' o 'medico')
    $sqlMed = "
      SELECT DISTINCT u.id AS id,
             CONCAT(
               CASE
                 WHEN u.nombre_mostrar REGEXP '^[[:space:]]*Dra?\\.' THEN ''
                 ELSE CASE WHEN SUBSTRING_INDEX(u.nombre_mostrar,' ',1) IN ('Dra.','Dr.') THEN '' ELSE 'Dr. ' END
               END,
               u.nombre_mostrar
             ) AS text
      FROM usuarios u
      JOIN usuario_rol ur ON ur.id_usuario = u.id
      JOIN roles r        ON r.id_rol = ur.id_rol AND r.estado = 1
      WHERE u.estado = 'ACTIVO' AND r.nombre IN ('Doctor','medico')
      ORDER BY u.nombre_mostrar
    ";
  }
  $medicos = $con->query($sqlMed)->fetchAll(PDO::FETCH_ASSOC);

  // --- Medicamentos
  $sqlMeds = "
    SELECT id,
           TRIM(CONCAT(nombre_medicamento,
               CASE WHEN NULLIF(concentracion,'') IS NOT NULL AND TRIM(concentracion) <> ''
                    THEN CONCAT(' - ', concentracion) ELSE '' END
           )) AS text
    FROM medicamentos
    WHERE estado = 'activo'
    ORDER BY nombre_medicamento
  ";
  $meds = $con->query($sqlMeds)->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'ok'            => true,
    'diagnosticos'  => $dx,
    'medicos'       => $medicos,
    'medicamentos'  => $meds
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'catalogos_tratamientos_error',
    'message' => $e->getMessage()
  ]);
}
