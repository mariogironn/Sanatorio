<?php
// sanatorio/ajax/verificar_nombre_rol.php
header('Content-Type: text/plain; charset=UTF-8');
require_once '../config/connection.php';

$nombre = trim($_GET['nombre'] ?? '');
$id_excluir = isset($_GET['id_excluir']) ? (int)$_GET['id_excluir'] : 0;

if ($nombre === '') { echo '0'; exit; }

try {
  if ($id_excluir>0) {
    $st = $con->prepare("SELECT COUNT(*) FROM roles WHERE nombre = :n AND id_rol <> :id");
    $st->execute([':n'=>$nombre, ':id'=>$id_excluir]);
  } else {
    $st = $con->prepare("SELECT COUNT(*) FROM roles WHERE nombre = :n");
    $st->execute([':n'=>$nombre]);
  }
  echo (string)$st->fetchColumn();
} catch(PDOException $ex){
  echo '0';
}
