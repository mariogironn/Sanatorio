<?php
// sanatorio/ajax/cambiar_estado_rol.php
// Activa/Inactiva un rol (sin auditoría)

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require '../config/connection.php';

header('Content-Type: text/plain; charset=UTF-8');

$id    = (int)($_POST['id_rol'] ?? 0);
$nuevo = (int)($_POST['nuevo'] ?? -1);
if ($id <= 0 || !in_array($nuevo, [0,1], true)) { echo 'Parámetros inválidos'; exit; }

try {
  // 1) Obtener rol actual
  $st = $con->prepare("SELECT id_rol, nombre, estado FROM roles WHERE id_rol = :i");
  $st->execute([':i' => $id]);
  $rol = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rol) { echo 'El rol no existe.'; exit; }

  $estadoActual = (int)$rol['estado'];
  $estadoNuevo  = $nuevo;

  // 2) Si no hay cambio, responder OK
  if ($estadoActual === $estadoNuevo) { echo 'OK'; exit; }

  // 3) Actualizar
  $up = $con->prepare("UPDATE roles SET estado = :e WHERE id_rol = :i");
  $up->execute([':e' => $estadoNuevo, ':i' => $id]);

  echo 'OK';
} catch (PDOException $ex) {
  echo 'Error: ' . $ex->getMessage();
}
