<?php
// ajax/medicina_detalle.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';

$out = ['success'=>false,'message'=>'','data'=>null,'pacientes'=>[]];

try {
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) throw new Exception('ID inválido');

  // Detalle de medicina + meta
  $sql = "
    SELECT
      m.id,
      m.nombre_medicamento,
      m.principio_activo,
      m.stock_actual,
      m.stock_minimo,
      m.tipo_medicamento,
      COALESCE(mm.presentacion,'')     AS presentacion,
      COALESCE(mm.laboratorio,'')      AS laboratorio,
      COALESCE(cm.nombre,'')           AS categoria,
      (
        SELECT COUNT(*)
        FROM paciente_medicinas pm
        WHERE pm.medicina_id = m.id AND pm.estado = 'activo'
      ) AS pacientes_activos
    FROM medicamentos m
    LEFT JOIN medicamentos_meta       mm ON mm.medicamento_id = m.id   /* <-- FIX */
    LEFT JOIN categorias_medicamentos cm ON cm.id = mm.categoria_id
    WHERE m.id = :id
    LIMIT 1
  ";
  $st = $con->prepare($sql);
  $st->execute([':id'=>$id]);
  $med = $st->fetch(PDO::FETCH_ASSOC);
  if (!$med) throw new Exception('Medicina no encontrada');

  // Pacientes que usan esta medicina
  $sqlP = "
    SELECT
      p.nombre                                  AS paciente,
      COALESCE(u.nombre_mostrar,'—')            AS medico,
      pm.dosis, pm.frecuencia,
      COALESCE(pm.motivo_prescripcion,'')       AS motivo,
      COALESCE(pm.duracion_tratamiento,'')      AS duracion,
      pm.fecha_asignacion
    FROM paciente_medicinas pm
    JOIN pacientes p   ON p.id_paciente = pm.paciente_id
    LEFT JOIN usuarios u ON u.id = pm.usuario_id
    WHERE pm.medicina_id = :id AND pm.estado = 'activo'
    ORDER BY pm.fecha_asignacion DESC
  ";
  $sp = $con->prepare($sqlP);
  $sp->execute([':id'=>$id]);
  $rows = $sp->fetchAll(PDO::FETCH_ASSOC);

  $out['success']   = true;
  $out['data']      = $med;
  $out['pacientes'] = $rows;

} catch (Throwable $e) {
  $out['message'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
