<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: text/html; charset=utf-8');

try {
  $st = $con->query("SELECT id, nombre FROM especialidades WHERE nombre IS NOT NULL AND nombre <> '' ORDER BY nombre ASC");
  $opts = [];
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $id = (int)$r['id'];
    $nm = htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8');
    $opts[] = "<option value=\"{$id}\">{$nm}</option>";
  }
  echo implode('', $opts);
} catch (Throwable $e) {
  echo '<option value="">Error al cargar especialidades</option>';
}
