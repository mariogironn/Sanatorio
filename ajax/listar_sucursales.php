<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require '../config/connection.php';
header('Content-Type: text/html; charset=UTF-8');

// Solo activas
try{
  $st = $con->query("SELECT id, nombre FROM sucursales WHERE estado=1 ORDER BY nombre");
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    echo '<option value="'.$r['id'].'">'.htmlspecialchars($r['nombre']).'</option>';
  }
} catch(Throwable $e) { /* salida vacía */ }
