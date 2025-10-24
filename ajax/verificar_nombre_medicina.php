<?php
// ajax/verificar_nombre_medicina.php
// Responde SOLO un número (0/1/2/...) indicando cuántos duplicados hay.

header('Content-Type: text/plain; charset=UTF-8');

// Conexión PDO ($con)
include '../config/connection.php';

// === Normalización EXACTA a la usada al guardar ===
// - recorta extremos
// - colapsa espacios múltiples
// - convierte a "Título" con minúsculas previas
$nombre = isset($_GET['medicine_name']) ? $_GET['medicine_name'] : '';
$nombre = trim($nombre);
$nombre = preg_replace('/\s+/', ' ', $nombre);
$nombre = ucwords(strtolower($nombre));

// Si viene vacío, no hay duplicado
if ($nombre === '') { echo 0; exit; }

// ---------- Autodetección de tabla/columnas (igual que en medicinas.php) ----------
function tableExists(PDO $con, $db, $table) {
  $q = $con->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :t LIMIT 1");
  $q->execute([':db'=>$db, ':t'=>$table]);
  return (int)$q->fetchColumn() > 0;
}
function colExists(PDO $con, $db, $table, $col) {
  $q = $con->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :db AND table_name = :t AND column_name = :c LIMIT 1");
  $q->execute([':db'=>$db, ':t'=>$table, ':c'=>$col]);
  return (int)$q->fetchColumn() > 0;
}

try {
  $dbName = $con->query("SELECT DATABASE()")->fetchColumn();

  // Candidatos
  $t_candidates  = ['medicamentos','medicinas','medicine','medications'];
  $nm_candidates = ['nombre_medicamento','nombre_medicina','nombre','medicine_name','name'];

  // Encuentra tabla
  $TBL = null;
  foreach ($t_candidates as $t) {
    if ($dbName && tableExists($con, $dbName, $t)) { $TBL = $t; break; }
  }
  if (!$TBL) { echo 0; exit; }

  // Encuentra columna de nombre
  $COL_NAME = null;
  foreach ($nm_candidates as $c) {
    if ($dbName && colExists($con, $dbName, $TBL, $c)) { $COL_NAME = $c; break; }
  }
  if (!$COL_NAME) { echo 0; exit; }

  // Cuenta coincidencias exactas del nombre normalizado
  $sql  = "SELECT COUNT(*) AS c FROM `$TBL` WHERE `$COL_NAME` = :nombre";
  $stmt = $con->prepare($sql);
  $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
  $stmt->execute();

  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  echo (int)($row['c'] ?? 0);

} catch (PDOException $ex) {
  // En error, responde 0 para no bloquear el guardado
  echo 0;
}
