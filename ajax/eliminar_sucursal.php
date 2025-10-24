<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require '../config/connection.php';
header('Content-Type: text/plain; charset=UTF-8');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo 'ID inválido'; exit; }

try{
  // ¿Está usada por usuarios?
  $st = $con->prepare("SELECT COUNT(*) FROM usuario_sucursal WHERE id_sucursal = :i");
  $st->execute([':i'=>$id]);
  $enUso = (int)$st->fetchColumn();

  if ($enUso > 0) {
    // Inactivar
    $up = $con->prepare("UPDATE sucursales SET estado = 0 WHERE id = :i");
    $up->execute([':i'=>$id]);
    echo 'OK';
    exit;
  }

  // Intentar borrar
  $del = $con->prepare("DELETE FROM sucursales WHERE id = :i");
  $del->execute([':i'=>$id]);
  echo 'OK';
} catch(Throwable $e){
  // Si por FK no se puede borrar, inactiva
  try{
    $up = $con->prepare("UPDATE sucursales SET estado = 0 WHERE id = :i");
    $up->execute([':i'=>$id]);
    echo 'OK';
  } catch(Throwable $e2){
    echo 'Error: '.$e2->getMessage();
  }
}
