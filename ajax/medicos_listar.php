<?php
// ajax/medicos_listar.php — usa correo desde medicos.correo
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$DEBUG = isset($_REQUEST['debug']) && $_REQUEST['debug'] == '1';
if ($DEBUG) { ini_set('display_errors', 1); error_reporting(E_ALL); }

$res = ['success' => false, 'data' => [], 'message' => ''];

try {
  // Filtros
  $estado_in       = isset($_REQUEST['estado']) ? trim($_REQUEST['estado']) : '';
  $especialidad_id = isset($_REQUEST['especialidad_id']) ? (int)$_REQUEST['especialidad_id'] : 0;
  $q               = isset($_REQUEST['q']) ? trim($_REQUEST['q']) : '';

  // Normaliza estado
  $estado = '';
  if ($estado_in !== '') {
    $e = strtolower($estado_in);
    if ($e === 'activo') $estado = '1';
    elseif ($e === 'inactivo') $estado = '0';
    elseif ($e === '1' || $e === '0') $estado = $e;
  }

  $where = []; $bind = [];
  if ($estado !== '') { $where[] = 'm.estado = :estado'; $bind[':estado'] = (int)$estado; }
  if ($especialidad_id > 0) { $where[] = 'm.especialidad_id = :esp'; $bind[':esp'] = $especialidad_id; }
  if ($q !== '') {
    $where[] = '(u.nombre_mostrar LIKE :q OR m.colegiado LIKE :q OR m.telefono LIKE :q OR m.correo LIKE :q)';
    $bind[':q'] = "%{$q}%";
  }

  // LEFT JOIN para no perder registros huérfanos
  $sql = "
    SELECT
      m.id_medico,
      m.especialidad_id,
      m.colegiado,
      m.telefono,
      m.correo,                 -- << correo viene de medicos
      m.estado,
      m.fecha_registro,
      m.especialidad_descripcion,
      m.especialidad_fecha_certificacion,
      u.nombre_mostrar,         -- solo lo que usamos del usuario
      e.nombre AS especialidad_nombre
    FROM medicos m
    LEFT JOIN usuarios u       ON u.id = m.id_medico
    LEFT JOIN especialidades e ON e.id = m.especialidad_id
    " . (count($where) ? ('WHERE ' . implode(' AND ', $where)) : '') . "
    ORDER BY
      CASE WHEN (u.nombre_mostrar IS NULL OR u.nombre_mostrar = '') THEN 1 ELSE 0 END,
      u.nombre_mostrar ASC, m.id_medico ASC
  ";

  if ($DEBUG) error_log("[medicos_listar] SQL:\n".$sql);

  $st = $con->prepare($sql);
  foreach ($bind as $k => $v) $st->bindValue($k, $v);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Salida para el front
  $data = array_map(function($r){
    return [
      'id_medico'           => (int)$r['id_medico'],
      'nombre_mostrar'      => (!empty($r['nombre_mostrar']) ? $r['nombre_mostrar'] : '—'),
      'especialidad_id'     => isset($r['especialidad_id']) ? (int)$r['especialidad_id'] : null,
      'especialidad_nombre' => (!empty($r['especialidad_nombre']) ? $r['especialidad_nombre'] : '—'),
      'colegiado'           => (!empty($r['colegiado']) ? $r['colegiado'] : '—'),
      'telefono'            => (!empty($r['telefono']) ? $r['telefono'] : '—'),
      'correo'              => (!empty($r['correo']) ? $r['correo'] : '—'),  // << usa medicos.correo
      'estado'              => (function($v){
        $v = (int)$v;
        // 1=activa, 0=inactiva, 3=en capacitación
        return $v===1 ? 'activa'
             : ($v===0 ? 'inactiva'
             : ($v===3 ? 'capacitacion' : 'inactiva'));
      })($r['estado']),
      'fecha_registro'      => $r['fecha_registro'] ?? '',
      // ▼ nuevos campos que ya pinta tu front:
      'especialidad_descripcion'         => $r['especialidad_descripcion'] ?? '',
      'especialidad_fecha_certificacion' => $r['especialidad_fecha_certificacion'] ?? ''
    ];
  }, $rows);

  $res['success'] = true;
  $res['data']    = $data;

} catch (Throwable $e) {
  if ($DEBUG) { $res['debug'] = $e->getMessage(); }
  $res['success'] = false;
  $res['message'] = 'Error al cargar los médicos';
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);