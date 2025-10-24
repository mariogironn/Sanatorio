<?php
// ajax/opciones_distribucion.php
// Devuelve opciones (JSON) para filtros del reporte "Distribución de Personal y Pacientes".
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

$out = ['sucursales' => [], 'sucursal_activa' => null];

try {
  if (!($con instanceof PDO)) {
    throw new Exception('Sin conexión PDO');
  }

  // usuario en sesión (acepta user_id o id_usuario)
  $userId = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
  $out['sucursal_activa'] = isset($_SESSION['id_sucursal_activa']) ? (int)$_SESSION['id_sucursal_activa'] : null;

  // ¿es administrador?
  $isAdmin = false;
  if ($userId > 0) {
    $q = $con->prepare("
      SELECT 1
        FROM usuario_rol ur
        JOIN roles r ON r.id_rol = ur.id_rol
       WHERE ur.id_usuario = :u
         AND UPPER(r.nombre) IN ('ADMIN', 'ADMINISTRADOR', 'PROPIETARIO', 'SUPERADMIN', 'OWNER')
       LIMIT 1
    ");
    $q->execute([':u' => $userId]);
    $isAdmin = (bool)$q->fetchColumn();
  }

  $rows = [];

  if ($userId > 0) {
    // Sucursales asignadas al usuario (ACTIVAS)
    $st = $con->prepare("
      SELECT s.id, s.nombre
        FROM usuario_sucursal us
        JOIN sucursales s ON s.id = us.id_sucursal
       WHERE us.id_usuario = :u
         AND s.estado = 1
       ORDER BY s.nombre
    ");
    $st->execute([':u' => $userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }

  if (empty($rows) && $isAdmin) {
    // Admin sin asignaciones: mostrar TODAS las activas
    $st = $con->query("SELECT id, nombre FROM sucursales WHERE estado = 1 ORDER BY nombre");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }

  // Si no es admin y no tiene asignadas => lista vacía (la UI ya muestra "Todas" como primera opción)
  $out['sucursales'] = array_map(function($r){
    return ['id' => (int)$r['id'], 'nombre' => $r['nombre']];
  }, $rows);

  echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['sucursales' => [], 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
