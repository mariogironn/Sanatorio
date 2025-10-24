<?php
// ajax/get_receta.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';

$resp = ['success'=>false,'message'=>'','data'=>null];

try {
  if (empty($_SESSION['user_id'])) { throw new Exception('Sesión no válida'); }

  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  if ($id <= 0) { throw new Exception('ID inválido'); }

  // Cabecera
  $sql = "
    SELECT 
      rm.id_receta,
      rm.numero_receta,
      rm.fecha_emision,
      rm.estado,
      rm.id_paciente,
      p.nombre         AS paciente_nombre,
      rm.id_medico,
      u.nombre_mostrar AS medico_nombre
    FROM recetas_medicas rm
    JOIN pacientes p ON p.id_paciente = rm.id_paciente
    JOIN usuarios  u ON u.id = rm.id_medico
    WHERE rm.id_receta = ?
    LIMIT 1
  ";
  $st = $con->prepare($sql);
  $st->execute([$id]);
  $head = $st->fetch(PDO::FETCH_ASSOC);
  if (!$head) { throw new Exception('Receta no encontrada'); }

  // Detalle
  $det = $con->prepare("
    SELECT id_detalle,id_medicamento,nombre_medicamento,dosis,duracion,frecuencia
    FROM detalle_recetas
    WHERE id_receta = ?
    ORDER BY id_detalle ASC
  ");
  $det->execute([$id]);
  $meds = $det->fetchAll(PDO::FETCH_ASSOC);

  $resp['success'] = true;
  $resp['data'] = array_merge($head, ['medicamentos' => $meds]);

} catch (Throwable $e) {
  $resp['message'] = $e->getMessage();
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE);
exit;

