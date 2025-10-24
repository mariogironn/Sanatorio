<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$res = ['success'=>false,'data'=>[], 'message'=>''];

try {
  require_once __DIR__.'/../config/connection.php';
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $sql = "
    SELECT
      e.id,
      e.nombre,
      e.descripcion,          -- << AQUÍ VIENE LA DESCRIPCIÓN
      e.estado,
      e.creado_en,
      (SELECT COUNT(*) FROM medicos m WHERE m.especialidad_id = e.id) AS medicos_asignados
    FROM especialidades e
    ORDER BY e.nombre ASC
  ";
  $st = $con->query($sql);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $data = array_map(function($r){
    return [
      'id_especialidad'   => (int)$r['id'],
      'nombre'            => (string)$r['nombre'],
      'descripcion'       => ($r['descripcion'] ?? ''),                 // << DEVUELVE TEXTO O VACÍO
      'estado'            => ((int)$r['estado'] === 1 ? 'activa' : 'inactiva'),
      'estado_val'        => (int)$r['estado'],
      'created_at'        => (string)$r['creado_en'],
      'medicos_asignados' => (int)$r['medicos_asignados'],
    ];
  }, $rows);

  $res['success'] = true;
  $res['data']    = $data;

} catch (Throwable $e) {
  $res['success'] = false;
  $res['message'] = 'No se pudieron cargar las especialidades.';
  $res['debug']   = $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
