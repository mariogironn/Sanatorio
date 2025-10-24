<?php
// sanatorio/ajax/guardar_permisos_rol.php
// Guarda permisos de un rol (por módulo) aplicando diferencias (sin auditoría)

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require '../config/connection.php';

header('Content-Type: text/plain; charset=UTF-8');

$idRol = (int)($_POST['id_rol'] ?? 0);
$json  = $_POST['json'] ?? '[]';
$data  = json_decode($json, true);

if ($idRol <= 0 || !is_array($data)) { echo 'Datos inválidos'; exit; }

try {
  // 1) Verifica que el rol exista
  $stRole = $con->prepare("SELECT id_rol, nombre FROM roles WHERE id_rol = :r");
  $stRole->execute([':r' => $idRol]);
  $role = $stRole->fetch(PDO::FETCH_ASSOC);
  if (!$role) { echo 'El rol no existe.'; exit; }

  // 2) Normaliza entrada (0/1) y junta ids de módulo
  $incoming = [];     // id_modulo => ['v'=>0/1, 'c'=>0/1, 'a'=>0/1, 'e'=>0/1]
  $moduleIds = [];
  foreach ($data as $row) {
    $m = isset($row['id_modulo']) ? (int)$row['id_modulo'] : 0;
    if ($m <= 0) continue;
    $incoming[$m] = [
      'v' => (int)($row['ver'] ?? 0) ? 1 : 0,
      'c' => (int)($row['crear'] ?? 0) ? 1 : 0,
      'a' => (int)($row['actualizar'] ?? 0) ? 1 : 0,
      'e' => (int)($row['eliminar'] ?? 0) ? 1 : 0,
    ];
    $moduleIds[] = $m;
  }
  $moduleIds = array_values(array_unique($moduleIds));

  // 3) Valida módulos existentes y trae slugs/nombres
  $validMods = []; // id_modulo => ['slug'=>..., 'nombre'=>...]
  if (!empty($moduleIds)) {
    $ph = [];
    $bind = [];
    foreach ($moduleIds as $i => $mid) { $ph[] = ':m'.$i; $bind[':m'.$i] = $mid; }
    $sqlMods = "SELECT id_modulo, slug, nombre FROM modulos WHERE id_modulo IN (".implode(',', $ph).")";
    $stMods = $con->prepare($sqlMods);
    $stMods->execute($bind);
    while ($m = $stMods->fetch(PDO::FETCH_ASSOC)) {
      $validMods[(int)$m['id_modulo']] = ['slug'=>$m['slug'], 'nombre'=>$m['nombre']];
    }
  }

  // Filtra incoming a solo módulos válidos
  $incoming = array_intersect_key($incoming, $validMods);

  // 4) Permisos actuales (ANTES)
  $stOld = $con->prepare("
    SELECT rp.id_modulo, m.slug, rp.ver, rp.crear, rp.actualizar, rp.eliminar
    FROM rol_permiso rp
    JOIN modulos m ON m.id_modulo = rp.id_modulo
    WHERE rp.id_rol = :r
  ");
  $stOld->execute([':r' => $idRol]);
  $old = []; // id_modulo => ['v','c','a','e','slug']
  while ($row = $stOld->fetch(PDO::FETCH_ASSOC)) {
    $mid = (int)$row['id_modulo'];
    $old[$mid] = [
      'v' => (int)$row['ver'],
      'c' => (int)$row['crear'],
      'a' => (int)$row['actualizar'],
      'e' => (int)$row['eliminar'],
      'slug' => $row['slug']
    ];
  }

  // 5) Diferencias
  $oldIds = array_keys($old);
  $newIds = array_keys($incoming);

  $toAdd    = array_values(array_diff($newIds, $oldIds));
  $toRemove = array_values(array_diff($oldIds, $newIds));
  $toUpdate = [];
  foreach (array_intersect($newIds, $oldIds) as $mid) {
    $o = $old[$mid]; $n = $incoming[$mid];
    if ($o['v'] !== $n['v'] || $o['c'] !== $n['c'] || $o['a'] !== $n['a'] || $o['e'] !== $n['e']) {
      $toUpdate[] = $mid;
    }
  }

  // 6) Persistencia por diferencias
  $con->beginTransaction();

  if (!empty($toRemove)) {
    $ph = []; $bind = [':r' => $idRol];
    foreach ($toRemove as $i => $mid) { $ph[] = ':d'.$i; $bind[':d'.$i] = (int)$mid; }
    $sqlDel = "DELETE FROM rol_permiso WHERE id_rol = :r AND id_modulo IN (".implode(',', $ph).")";
    $con->prepare($sqlDel)->execute($bind);
  }

  if (!empty($toUpdate)) {
    $up = $con->prepare("
      UPDATE rol_permiso
      SET ver=:v, crear=:c, actualizar=:a, eliminar=:e
      WHERE id_rol=:r AND id_modulo=:m
    ");
    foreach ($toUpdate as $mid) {
      $n = $incoming[$mid];
      $up->execute([
        ':v'=>$n['v'], ':c'=>$n['c'], ':a'=>$n['a'], ':e'=>$n['e'],
        ':r'=>$idRol,  ':m'=>$mid
      ]);
    }
  }

  if (!empty($toAdd)) {
    $ins = $con->prepare("
      INSERT INTO rol_permiso (id_rol, id_modulo, ver, crear, actualizar, eliminar)
      VALUES (:r, :m, :v, :c, :a, :e)
    ");
    foreach ($toAdd as $mid) {
      $n = $incoming[$mid];
      $ins->execute([
        ':r'=>$idRol, ':m'=>$mid,
        ':v'=>$n['v'], ':c'=>$n['c'], ':a'=>$n['a'], ':e'=>$n['e']
      ]);
    }
  }

  $con->commit();
  echo 'OK';

} catch (PDOException $ex) {
  if ($con->inTransaction()) { $con->rollBack(); }
  echo 'Error: ' . $ex->getMessage();
}
