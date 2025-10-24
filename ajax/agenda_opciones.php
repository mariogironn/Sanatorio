<?php
// ajax/agenda_opciones.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

$out = ['medicos'=>[], 'sucursales'=>[]];

try{
  if (!($con instanceof PDO)) throw new Exception('Sin conexión PDO');
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Médicos (si no hay rol MEDICO, devuelve todos activos)
  $sqlM = "
    SELECT DISTINCT u.id, COALESCE(u.nombre_mostrar, u.usuario) AS nombre
      FROM usuarios u
 LEFT JOIN usuario_rol ur ON ur.id_usuario = u.id
 LEFT JOIN roles r        ON r.id_rol = ur.id_rol
     WHERE u.estado = 1
       AND (r.nombre IS NULL OR UPPER(r.nombre) LIKE '%MEDIC%')
  ORDER BY nombre";
  $out['medicos'] = $con->query($sqlM)->fetchAll(PDO::FETCH_ASSOC);

  // Sucursales activas
  $sqlS = "SELECT id, nombre FROM sucursales WHERE estado = 1 ORDER BY nombre";
  $out['sucursales'] = $con->query($sqlS)->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e){
  $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
