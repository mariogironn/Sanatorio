<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require '../config/connection.php';
header('Content-Type: text/plain; charset=UTF-8');

$nombre = trim($_GET['nombre'] ?? '');
$id     = (int)($_GET['id'] ?? 0);

if ($nombre === '') { echo '0'; exit; }

try {
  if ($id > 0) {
    $st = $con->prepare("SELECT COUNT(*) FROM sucursales WHERE nombre = :n AND id <> :i");
    $st->execute([':n'=>$nombre, ':i'=>$id]);
  } else {
    $st = $con->prepare("SELECT COUNT(*) FROM sucursales WHERE nombre = :n");
    $st->execute([':n'=>$nombre]);
  }
  echo (int)$st->fetchColumn();
} catch (Throwable $e) {
  echo '1';
}
