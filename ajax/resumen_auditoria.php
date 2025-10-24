<?php
// ajax/resumen_auditoria.php
// Totales para KPIs con filtros: módulo (case-insensitive), acción (ES→ENUM), usuario y fecha (fecha única o rango)

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

// --- mapear acción en español a ENUM almacenado ---
function map_accion_enum($txt){
  $m = [
    'CREAR'       => 'CREATE',
    'ACTUALIZAR'  => 'UPDATE',
    'ELIMINAR'    => 'DELETE',
    'ACTIVAR'     => 'ACTIVAR',
    'DESACTIVAR'  => 'DESACTIVAR',
    'GENERAR'     => 'GENERAR',
  ];
  $k = mb_strtoupper(trim((string)$txt), 'UTF-8');
  return $m[$k] ?? ($k !== '' ? $k : '');
}

// --- normalizar fechas a Y-m-d (acepta dd/mm/aaaa, mm/dd/yyyy, yyyy-mm-dd) ---
function normalize_date($s){
  $s = trim((string)$s);
  if ($s === '') return '';
  $fmts = ['d/m/Y','m/d/Y','Y-m-d'];
  foreach ($fmts as $f){
    $dt = DateTime::createFromFormat($f, $s);
    if ($dt && $dt->format($f) === $s) return $dt->format('Y-m-d');
  }
  // si viene como 'd-m-Y'
  $dt = DateTime::createFromFormat('d-m-Y', $s);
  if ($dt && $dt->format('d-m-Y') === $s) return $dt->format('Y-m-d');
  return '';
}

try{
  $modulo      = trim($_GET['modulo']      ?? '');
  $accionRaw   = trim($_GET['accion']      ?? '');   // puede venir en español
  $usuario_id  = trim($_GET['usuario_id']  ?? '');
  $fechaUnica  = trim($_GET['fecha']       ?? '');   // soporte fecha única
  $desdeRaw    = trim($_GET['desde']       ?? '');
  $hastaRaw    = trim($_GET['hasta']       ?? '');

  $accion  = map_accion_enum($accionRaw);
  $desde   = $fechaUnica ? normalize_date($fechaUnica) : normalize_date($desdeRaw);
  $hasta   = $fechaUnica ? normalize_date($fechaUnica) : normalize_date($hastaRaw);

  $where = ["1=1"];
  $p     = [];

  if ($modulo !== '')     { $where[] = "UPPER(modulo) = UPPER(?)"; $p[] = $modulo; }
  if ($accion !== '')     { $where[] = "accion = ?";               $p[] = $accion; }
  if ($usuario_id !== '') { $where[] = "usuario_id = ?";           $p[] = (int)$usuario_id; }

  // fecha única (igualdad) o rango (>= <=)
  if ($fechaUnica !== '' && $desde !== ''){
    $where[] = "DATE(creado_en) = ?";
    $p[]     = $desde;
  } else {
    if ($desde !== '') { $where[] = "DATE(creado_en) >= ?"; $p[] = $desde; }
    if ($hasta !== '') { $where[] = "DATE(creado_en) <= ?"; $p[] = $hasta; }
  }

  $W = implode(' AND ', $where);

  if (!($con instanceof PDO)) {
    echo json_encode(['total'=>0,'creates'=>0,'updates'=>0,'deletes'=>0,'activaciones'=>0,'desactivaciones'=>0,'generados'=>0]);
    exit;
  }

  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Total
  $q = $con->prepare("SELECT COUNT(*) FROM auditoria WHERE $W");
  foreach ($p as $i=>$v) $q->bindValue($i+1,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
  $q->execute();
  $total = (int)$q->fetchColumn();

  // Conteo por acción
  $q = $con->prepare("SELECT accion, COUNT(*) c FROM auditoria WHERE $W GROUP BY accion");
  foreach ($p as $i=>$v) $q->bindValue($i+1,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
  $q->execute();
  $map = ['CREATE'=>0,'UPDATE'=>0,'DELETE'=>0,'ACTIVAR'=>0,'DESACTIVAR'=>0,'GENERAR'=>0];
  while($r=$q->fetch(PDO::FETCH_ASSOC)){
    $key = strtoupper($r['accion']);
    if (isset($map[$key])) $map[$key] = (int)$r['c'];
  }

  echo json_encode([
    'total'           => $total,
    'creates'         => $map['CREATE'],
    'updates'         => $map['UPDATE'],
    'deletes'         => $map['DELETE'],
    'activaciones'    => $map['ACTIVAR'],
    'desactivaciones' => $map['DESACTIVAR'],
    'generados'       => $map['GENERAR'],
  ], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e){
  echo json_encode([
    'total'=>0,'creates'=>0,'updates'=>0,'deletes'=>0,'activaciones'=>0,'desactivaciones'=>0,'generados'=>0,
    'error'=>'resumen_auditoria.php: '.$e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
