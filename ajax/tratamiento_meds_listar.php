<?php
// Encabezado + lista de medicamentos por tratamiento
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: application/json');

$TAB_T  = 'tratamientos';
$TAB_TM = 'tratamiento_medicamentos';

try {
  $id_trat = (int)($_GET['id_tratamiento'] ?? $_GET['id'] ?? 0);
  if (!$id_trat) { echo json_encode(['ok'=>false,'error'=>'Falta id_tratamiento']); exit; }

  // Encabezado
  $sqlH = "SELECT t.id_tratamiento,
                  CONCAT('T-', LPAD(t.id_tratamiento,3,'0')) AS codigo,
                  p.nombre AS paciente,
                  (SELECT e.nombre FROM enfermedades e WHERE e.id = d.id_enfermedad) AS diagnostico
           FROM `$TAB_T` t
           JOIN diagnosticos d ON d.id_diagnostico = t.id_diagnostico
           JOIN pacientes p    ON p.id_paciente    = d.id_paciente
           WHERE t.id_tratamiento = ?";
  $st = $con->prepare($sqlH);
  $st->execute([$id_trat]);
  $header = $st->fetch(PDO::FETCH_ASSOC) ?: [];

  // Medicamentos
  $sql = "SELECT tm.id, tm.id_medicamento, m.nombre AS medicamento,
                 tm.dosis, tm.frecuencia, tm.duracion, tm.notas
          FROM `$TAB_TM` tm
          JOIN medicamentos m ON m.id_medicamento = tm.id_medicamento
          WHERE tm.id_tratamiento = ?
          ORDER BY m.nombre";
  $st = $con->prepare($sql);
  $st->execute([$id_trat]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true, 'header'=>$header, 'meds'=>$rows]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
