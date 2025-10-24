<?php
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/../config/connection.php';

try {
  $a  = isset($_GET['id_medicamento_a']) ? (int)$_GET['id_medicamento_a'] : 0;
  $b  = isset($_GET['id_medicamento_b']) ? (int)$_GET['id_medicamento_b'] : 0;
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  if ($a <= 0 || $b <= 0 || $a === $b) { echo '0'; exit; }

  // Normalizamos en PHP y consultamos por a_norm/b_norm
  $an = min($a, $b);
  $bn = max($a, $b);

  if ($id > 0) {
    $sql = "SELECT 1 FROM interacciones_medicamentos
            WHERE a_norm = :an AND b_norm = :bn AND estado = 1 AND id <> :id LIMIT 1";
    $st = $con->prepare($sql);
    $st->execute([':an'=>$an, ':bn'=>$bn, ':id'=>$id]);
  } else {
    $sql = "SELECT 1 FROM interacciones_medicamentos
            WHERE a_norm = :an AND b_norm = :bn AND estado = 1 LIMIT 1";
    $st = $con->prepare($sql);
    $st->execute([':an'=>$an, ':bn'=>$bn]);
  }

  echo $st->fetchColumn() ? '1' : '0';
} catch (PDOException $ex) {
  echo '0';
}
