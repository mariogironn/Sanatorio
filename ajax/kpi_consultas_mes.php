<?php
// ajax/kpi_consultas_mes.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

$out = ['success' => false, 'consultas_mes' => 0, 'message' => ''];

try {
  // Rango del mes actual [inicio, fin)
  $inicio = (new DateTime('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
  $fin    = (new DateTime('first day of next month 00:00:00'))->format('Y-m-d H:i:s');

  // Detectar columnas disponibles para ser resiliente a tu esquema
  $cols = [];
  foreach ($con->query("SHOW COLUMNS FROM citas_medicas") as $r) {
    $cols[$r['Field']] = true;
  }

  // Elegir mejor columna de fecha disponible
  $dateCol = null;
  if (isset($cols['fecha']))        $dateCol = 'fecha';           // común en tu sistema
  elseif (isset($cols['fecha_cita'])) $dateCol = 'fecha_cita';
  elseif (isset($cols['created_at'])) $dateCol = 'created_at';
  else throw new Exception('No se encontró columna de fecha en citas_medicas');

  // Filtro por estado si existe columna "estado"
  $sql = "SELECT COUNT(*) AS n
          FROM citas_medicas
          WHERE {$dateCol} >= :ini AND {$dateCol} < :fin";

  $params = [':ini' => $inicio, ':fin' => $fin];

  if (isset($cols['estado'])) {
    // Contar solo consultas efectivamente realizadas/atendidas
    // (ajusta el listado si usas valores distintos)
    $sql .= " AND LOWER(estado) IN ('atendida','atendido','completada','realizada')";
  }

  $st = $con->prepare($sql);
  $st->execute($params);
  $n = (int)$st->fetchColumn();

  $out['success'] = true;
  $out['consultas_mes'] = $n;
  $out['from'] = $inicio;
  $out['to']   = $fin;

} catch (Throwable $e) {
  $out['success'] = false;
  $out['message'] = 'Error: '.$e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
