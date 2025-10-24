<?php
// Crear/editar un medicamento vinculado al tratamiento
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: application/json');

$TAB_TM = 'tratamiento_medicamentos';

try {
  $id            = (int)($_POST['id'] ?? 0);
  $id_trat       = (int)($_POST['id_tratamiento'] ?? 0);
  $id_med        = (int)($_POST['id_medicamento'] ?? 0);
  $dosis         = trim($_POST['dosis'] ?? '');
  $frecuencia    = trim($_POST['frecuencia'] ?? '');
  $duracion      = trim($_POST['duracion'] ?? '');
  $notas         = trim($_POST['notas'] ?? '');

  if (!$id_trat || !$id_med) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos obligatorios']); exit;
  }

  if ($id > 0) {
    $sql = "UPDATE `$TAB_TM`
            SET id_medicamento=?, dosis=?, frecuencia=?, duracion=?, notas=?
            WHERE id=?";
    $con->prepare($sql)->execute([$id_med, $dosis, $frecuencia, $duracion, $notas, $id]);
  } else {
    $sql = "INSERT INTO `$TAB_TM`
            (id_tratamiento, id_medicamento, dosis, frecuencia, duracion, notas)
            VALUES (?,?,?,?,?,?)";
    $con->prepare($sql)->execute([$id_trat, $id_med, $dosis, $frecuencia, $duracion, $notas]);
    $id = (int)$con->lastInsertId();
  }

  echo json_encode(['success'=>true,'id'=>$id]);
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
