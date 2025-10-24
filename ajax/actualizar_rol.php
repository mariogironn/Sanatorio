<?php
// sanatorio/ajax/actualizar_rol.php
// Actualiza un rol existente (sin auditoría)

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require '../config/connection.php';

header('Content-Type: text/plain; charset=UTF-8');

$id          = (int)($_POST['id_rol'] ?? 0);
$nombre      = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$estadoIn    = $_POST['estado'] ?? 1;
$estado      = (in_array((int)$estadoIn, [0,1], true) ? (int)$estadoIn : 1);

if ($id <= 0 || $nombre === '') { echo 'Datos inválidos'; exit; }

try {
  // 1) Verifica que el rol exista
  $stOld = $con->prepare("SELECT id_rol, nombre, descripcion, estado FROM roles WHERE id_rol = :i");
  $stOld->execute([':i' => $id]);
  $old = $stOld->fetch(PDO::FETCH_ASSOC);
  if (!$old) { echo 'El rol no existe.'; exit; }

  // 2) Duplicado (case-insensitive) excluyendo el propio id
  $c = $con->prepare("
    SELECT 1
    FROM roles
    WHERE TRIM(UPPER(nombre)) = TRIM(UPPER(:n)) AND id_rol <> :i
    LIMIT 1
  ");
  $c->execute([':n' => $nombre, ':i' => $id]);
  if ($c->fetch()) { echo 'El nombre de rol ya existe.'; exit; }

  // 3) Detectar cambios para evitar UPDATE innecesario
  $changed = [];
  if (mb_strtolower($old['nombre']) !== mb_strtolower($nombre))   { $changed[] = 'nombre'; }
  if ((string)($old['descripcion'] ?? '') !== (string)$descripcion) { $changed[] = 'descripcion'; }
  if ((int)$old['estado'] !== (int)$estado)                        { $changed[] = 'estado'; }

  if (empty($changed)) {
    echo 'OK'; exit;
  }

  // 4) Ejecutar UPDATE
  $st = $con->prepare("
    UPDATE roles
    SET nombre = :n, descripcion = :d, estado = :e
    WHERE id_rol = :i
  ");
  $st->execute([
    ':n' => $nombre,
    ':d' => $descripcion,
    ':e' => $estado,
    ':i' => $id
  ]);

  echo 'OK';
} catch (PDOException $ex) {
  echo 'Error: ' . $ex->getMessage();
}
