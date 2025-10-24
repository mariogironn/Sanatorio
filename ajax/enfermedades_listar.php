<?php
// ajax/enfermedades_listar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connection.php';

/** Helpers para chequear existencia de tabla/columna de forma segura */
function tableExists(PDO $con, $table) {
  $sql = "SELECT 1 FROM information_schema.tables 
          WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1";
  $st = $con->prepare($sql); $st->execute([':t'=>$table]);
  return (bool)$st->fetchColumn();
}
function colExists(PDO $con, $table, $col) {
  $sql = "SELECT 1 FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1";
  $st = $con->prepare($sql); $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

try {
  // ==================== DEVOLVER CATÁLOGOS (filtros/checkboxes) ====================
  if (isset($_GET['meta']) && $_GET['meta']==='catalogos') {
    $out = ['categorias'=>[], 'banderas'=>[]];

    // Categorías (si existe la tabla)
    if (tableExists($con,'categorias_enfermedad')) {
      $rows = $con->query("SELECT id, nombre FROM categorias_enfermedad ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
      $out['categorias'] = $rows ?: [];
    }

    // Banderas (si existen tablas de banderas)
    if (tableExists($con,'banderas_enfermedad')) {
      $rows = $con->query("SELECT id, nombre FROM banderas_enfermedad ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
      $out['banderas'] = $rows ?: [];
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ==================== LISTADO PARA DATATABLES ====================
  // Detectar columnas/tablas opcionales
  $hasCIE      = colExists($con,'enfermedades','cie10');
  $hasCatFk    = colExists($con,'enfermedades','categoria_id') && tableExists($con,'categorias_enfermedad');
  $hasBanderas = tableExists($con,'enfermedad_banderas') && tableExists($con,'banderas_enfermedad');

  // SELECT base (nunca falla con tu esquema mínimo)
  $select = "SELECT e.id, e.nombre, e.descripcion, e.estado, e.created_at";
  if ($hasCIE)   { $select .= ", e.cie10";        } else { $select .= ", NULL AS cie10"; }
  if ($hasCatFk) { $select .= ", e.categoria_id, c.nombre AS categoria";
                 } else { $select .= ", NULL AS categoria_id, NULL AS categoria"; }

  $from = " FROM enfermedades e";
  if ($hasCatFk) { $from .= " LEFT JOIN categorias_enfermedad c ON c.id = e.categoria_id"; }

  $sql = $select . $from . " ORDER BY e.id ASC";
  $rows = $con->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  // Adjuntar banderas (si existen tablas)
  if ($hasBanderas && $rows) {
    $ids = array_column($rows, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $q = $con->prepare("
      SELECT eb.enfermedad_id, b.id AS bandera_id, b.nombre
      FROM enfermedad_banderas eb
      JOIN banderas_enfermedad b ON b.id = eb.bandera_id
      WHERE eb.enfermedad_id IN ($in)
      ORDER BY b.nombre
    ");
    $q->execute($ids);
    $map = [];
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
      $map[$r['enfermedad_id']]['nombres'][] = $r['nombre'];
      $map[$r['enfermedad_id']]['ids'][]     = (int)$r['bandera_id'];
    }
    foreach ($rows as &$r) {
      $enfId = (int)$r['id'];
      $r['banderas']     = isset($map[$enfId]['nombres']) ? $map[$enfId]['nombres'] : [];
      $r['banderas_ids'] = isset($map[$enfId]['ids'])     ? $map[$enfId]['ids']     : [];
    }
    unset($r);
  } else {
    // Si no hay tablas de banderas, proveer arrays vacíos para no romper el render
    foreach ($rows as &$r) { $r['banderas'] = []; $r['banderas_ids']=[]; }
    unset($r);
  }

  // Numerador para la primera columna
  $i=1; foreach ($rows as &$r) { $r['rownum'] = $i++; } unset($r);

  echo json_encode(['data'=>$rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  // IMPORTANTE: DataTables espera un JSON válido.
  http_response_code(200);
  echo json_encode(['data'=>[], 'error'=> $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
