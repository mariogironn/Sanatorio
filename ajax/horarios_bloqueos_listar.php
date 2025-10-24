<?php
// ajax/horarios_bloqueos_listar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$out = ['success'=>false, 'data'=>[], 'message'=>''];

try {
  require_once __DIR__ . '/../config/connection.php';
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // 1) Detecta la tabla que existe
  $table = null;
  foreach (['ausencias_medicas','horarios_bloqueos'] as $t) {
    $q = $con->query("SHOW TABLES LIKE " . $con->quote($t));
    if ($q && $q->fetchColumn()) { $table = $t; break; }
  }
  if (!$table) { throw new Exception('No existe tabla de ausencias.'); }

  // 2) Lee columnas de esa tabla
  $cols = [];
  $stc = $con->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $stc->execute([$table]);
  foreach ($stc->fetchAll(PDO::FETCH_COLUMN, 0) as $c) { $cols[strtolower($c)] = $c; }

  // helper para elegir el primer nombre de columna que exista
  $pick = function(array $cands) use ($cols){
    foreach ($cands as $c) { if (isset($cols[strtolower($c)])) return $cols[strtolower($c)]; }
    return null;
  };

  // 3) Mapeos tolerantes
  $cId     = $pick(['id','id_bloqueo','id_ausencia']);
  $cMed    = $pick(['medico_id','id_usuario_medico','id_medico','usuario_id']);
  $cFecha  = $pick(['fecha','fecha_bloqueo','fecha_ausencia']);
  $cIni    = $pick(['hora_inicio','desde','hora_desde','inicio']);
  $cFin    = $pick(['hora_fin','hasta','hora_hasta','fin']);
  $cMotivo = $pick(['motivo','razon','observacion','comentario']);

  if (!$cId || !$cMed || !$cFecha || !$cIni || !$cFin) {
    throw new Exception("Faltan columnas obligatorias en $table");
  }

  // 4) SELECT normalizado
  $sql = "SELECT $cId   AS id,
                 $cMed  AS medico_id,
                 $cFecha AS fecha,
                 $cIni  AS hora_inicio,
                 $cFin  AS hora_fin"
         . ($cMotivo ? ", $cMotivo AS motivo" : ", '' AS motivo")
         . " FROM $table
            ORDER BY $cFecha DESC, $cIni DESC";

  $rows = $con->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // 5) Normaliza formatos (fecha YYYY-MM-DD / hora HH:MM)
  $data = array_map(function($r){
    $fecha = (string)($r['fecha'] ?? '');
    if (strlen($fecha) > 10) { $fecha = substr($fecha, 0, 10); }
    $ini = substr((string)($r['hora_inicio'] ?? ''), 0, 5);
    $fin = substr((string)($r['hora_fin'] ?? ''), 0, 5);
    return [
      'id'          => (int)($r['id'] ?? 0),
      'medico_id'   => (int)($r['medico_id'] ?? 0),
      'fecha'       => $fecha,
      'hora_inicio' => $ini,
      'hora_fin'    => $fin,
      'motivo'      => (string)($r['motivo'] ?? '')
    ];
  }, $rows);

  $out['success'] = true;
  $out['data']    = $data;

} catch (Throwable $e) {
  $out['success'] = false;
  $out['message'] = 'No se pudieron listar las ausencias';
  $out['debug']   = $e->getMessage(); // quítalo en producción si quieres
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
