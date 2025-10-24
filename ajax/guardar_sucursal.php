<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require '../config/connection.php';
header('Content-Type: text/plain; charset=UTF-8');

$id = (int)($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');

if ($nombre === '') { echo 'Nombre vacío'; exit; }

try{
  if ($id > 0) {
    $st = $con->prepare("UPDATE sucursales SET nombre = :n WHERE id = :i");
    $st->execute([':n'=>$nombre, ':i'=>$id]);
  } else {
    $st = $con->prepare("INSERT INTO sucursales(nombre, estado) VALUES(:n,1)");
    $st->execute([':n'=>$nombre]);
  }
  echo 'OK';
} catch(Throwable $e){
  echo 'Error: '.$e->getMessage();
}
