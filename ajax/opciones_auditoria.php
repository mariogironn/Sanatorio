<?php
// ajax/opciones_auditoria.php
// Opciones para filtros: módulos, acciones (ES) y usuarios (mejor esfuerzo para nombre)

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connection.php';

// Normaliza conexión a $con
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

$out = [
  'modulos'      => [],
  // Mostramos en ESPAÑOL en el UI:
  'acciones'     => ['Crear','Actualizar','Eliminar'],
  // Mapa de traducción por si el backend lo necesita:
  'acciones_map' => [
    'Crear'       => 'CREATE',
    'Actualizar'  => 'UPDATE',
    'Eliminar'    => 'DELETE',
    
  ],
  'usuarios'     => [] // [{id, nombre}]
];

try {
  if ($con instanceof PDO) {
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ====== Módulos ======
    $mods = $con->query("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo ASC")->fetchAll(PDO::FETCH_COLUMN);
    if (!$mods || !is_array($mods)) $mods = [];

    // Limpieza + título y unicidad case-insensitive
    $seen = [];
    $modsClean = [];
    foreach ($mods as $m) {
      $m = trim((string)$m);
      if ($m === '') continue;
      $key = mb_strtoupper($m, 'UTF-8');
      if (isset($seen[$key])) continue;
      $seen[$key] = true;
      $modsClean[] = mb_convert_case($m, MB_CASE_TITLE, "UTF-8");
    }
    if (!$modsClean) {
      // Fallback si la auditoría aún está vacía
      $modsClean = ['Pacientes','Usuarios','Medicinas'];
    }
    $out['modulos'] = array_values($modsClean);

    // ====== Usuarios ======
    // Detectamos columnas de usuarios para PK y nombre visible
    $cols = [];
    try {
      $cols = $con->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
      $cols = [];
    }

    $pk = 'id';
    if ($cols && !in_array('id', $cols, true) && in_array('id_usuario', $cols, true)) {
      $pk = 'id_usuario';
    }

    $expr = null;
    if ($cols) {
      $has = function($c) use ($cols) { return in_array($c, $cols, true); };
      if     ($has('nombre_mostrar'))               $expr = 'nombre_mostrar';
      elseif ($has('nombre_completo'))              $expr = 'nombre_completo';
      elseif ($has('nombre'))                       $expr = 'nombre';
      elseif ($has('name'))                         $expr = 'name';
      elseif ($has('nombres') && $has('apellidos')) $expr = "CONCAT_WS(' ',nombres,apellidos)";
      elseif ($has('usuario'))                      $expr = 'usuario';
      elseif ($has('username'))                     $expr = 'username';
      elseif ($has('login'))                        $expr = 'login';
      elseif ($has('email'))                        $expr = 'email';
    }

    if ($expr) {
      $stmt = $con->query("SELECT `$pk` AS id, $expr AS nombre FROM usuarios ORDER BY nombre ASC");
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $nombre = trim((string)($u['nombre'] ?? ''));
        if ($nombre === '') $nombre = '#'.$u['id'];
        $out['usuarios'][] = ['id' => (int)$u['id'], 'nombre' => $nombre];
      }
    } else {
      // Fallback: si no podemos leer la tabla usuarios, al menos devuelve los IDs usados en auditoría
      $stmt = $con->query("SELECT DISTINCT usuario_id FROM auditoria WHERE usuario_id IS NOT NULL ORDER BY usuario_id");
      foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $out['usuarios'][] = ['id'=>(int)$id, 'nombre'=>'#'.$id];
      }
    }
  }
} catch (Throwable $e) {
  // Silencioso: devolvemos valores por defecto si algo falla
  if (!$out['modulos']) $out['modulos'] = ['Pacientes','Usuarios','Medicinas'];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
