<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connection.php';

try {
  $id = (int)($_GET['id_enfermedad'] ?? 0);
  $limit = (int)($_GET['limit'] ?? 5);
  if ($id<=0) throw new Exception('ID inválido');

  // Tomamos el nombre oficial de la enfermedad
  $name = $con->prepare("SELECT nombre FROM enfermedades WHERE id=?");
  $name->execute([$id]);
  $nombre = $name->fetchColumn();
  if (!$nombre) throw new Exception('Enfermedad no encontrada');

  // Prescripciones recientes que coinciden con ese diagnóstico
  $sql = "
    SELECT p.id_prescripcion, DATE(p.fecha_visita) AS fecha,
           CONCAT(pc.nombres,' ',pc.apellidos) AS paciente,
           COALESCE(u.nombre_completo, u.username, '—') AS medico
    FROM prescripciones p
    JOIN pacientes pc ON pc.id_paciente = p.id_paciente
    LEFT JOIN usuarios u ON u.id = p.medico_id
    WHERE p.enfermedad = :enf
    ORDER BY p.fecha_visita DESC
    LIMIT :lim
  ";
  $stm = $con->prepare($sql);
  $stm->bindValue(':enf', $nombre);
  $stm->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stm->execute();

  echo json_encode(['items'=>$stm->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['items'=>[], 'message'=>$e->getMessage()]);
}
