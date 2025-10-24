<?php
// ajax/guardar_rol.php
// Crea un rol nuevo + auditoría (CREATE). Respuesta: "OK" o mensaje de error.

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

// Auditoría
require_once __DIR__ . '/../common_service/auditoria_service.php';
$haveHelpers = @include_once __DIR__ . '/../common_service/audit_helpers.php';

$nombre      = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$estadoIn    = $_POST['estado'] ?? 1;
$estado      = in_array((int)$estadoIn, [0,1], true) ? (int)$estadoIn : 1;

if ($nombre === '') { echo 'Nombre requerido'; exit; }

try {
  if (!($con instanceof PDO)) { echo 'Conexión no disponible.'; exit; }
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Duplicado (case-insensitive, con TRIM)
  $c = $con->prepare("SELECT 1 FROM roles WHERE TRIM(UPPER(nombre)) = TRIM(UPPER(:n)) LIMIT 1");
  $c->execute([':n' => $nombre]);
  if ((int)$c->fetchColumn() === 1) { echo 'El nombre de rol ya existe.'; exit; }

  // Insert
  $con->beginTransaction();
  $st = $con->prepare("
    INSERT INTO roles (nombre, descripcion, estado, creado_en)
    VALUES (:n, :d, :e, NOW())
  ");
  $st->execute([':n' => $nombre, ':d' => $descripcion, ':e' => $estado]);

  $newId = (int)$con->lastInsertId();
  $con->commit();

  // ===== Auditoría: snapshot DESPUÉS + audit_create =====
  try {
    if ($newId <= 0) {
      // Fallback si la PK no es autoincrement o el lastInsertId no devolvió valor
      $aux = $con->prepare("SELECT id_rol FROM roles WHERE TRIM(UPPER(nombre))=TRIM(UPPER(:n)) ORDER BY id_rol DESC LIMIT 1");
      $aux->execute([':n' => $nombre]);
      $newId = (int)($aux->fetchColumn() ?: 0);
    }

    $despues = null;
    if ($newId > 0) {
      if ($haveHelpers && function_exists('audit_row')) {
        $despues = audit_row($con, 'roles', 'id_rol', $newId);
      }
      if (!$despues) {
        $sf = $con->prepare("SELECT * FROM roles WHERE id_rol = :i");
        $sf->execute([':i' => $newId]);
        $despues = $sf->fetch(PDO::FETCH_ASSOC) ?: [
          'id_rol' => $newId, 'nombre' => $nombre, 'descripcion' => $descripcion, 'estado' => $estado
        ];
      }

      $estadoFinal = ($estado === 1) ? 'activo' : 'inactivo';
      audit_create($con, 'Roles', 'roles', $newId, $despues, $estadoFinal);
    }
  } catch (Throwable $eAud) {
    // No romper UX si falla la auditoría
    error_log('AUDITORIA CREATE roles: ' . $eAud->getMessage());
  }

  echo 'OK';
  exit;

} catch (PDOException $ex) {
  if ($con instanceof PDO && $con->inTransaction()) { $con->rollBack(); }
  // 23000 = violación de integridad (por ejemplo, unique index)
  $msg = ($ex->getCode() === '23000') ? 'El nombre de rol ya existe.' : ('Error: ' . $ex->getMessage());
  echo $msg;
  exit;
}
