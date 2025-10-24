<?php
// ajax/especialidad_guardar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ok  = function(array $p){ echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; };
$err = function(string $m, ?string $dbg=null){
  echo json_encode(['success'=>false,'message'=>$m] + ($dbg?['debug'=>$dbg]:[]), JSON_UNESCAPED_UNICODE);
  exit;
};

try{ require_once __DIR__.'/../config/connection.php'; }
catch(Throwable $e){ $err('Conexión a BD no disponible.', $e->getMessage()); }

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid<=0) $err('Sesión expirada. Vuelve a iniciar sesión.');

// === Entrada ===
$id     = (int)($_POST['id_especialidad'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$estado_in = strtolower(trim($_POST['estado'] ?? 'activa'));
$descripcion = trim($_POST['descripcion'] ?? ''); // opcional: sólo se guardará si la columna existe

if ($nombre === '') $err('Escribe el nombre de la especialidad.');
$estado = ($estado_in==='activa' || $estado_in==='1') ? 1 : 0;

// === Helper: ¿existe columna descripcion? ===
$hasDescripcion = false;
try {
  $chk = $con->query("SHOW COLUMNS FROM especialidades LIKE 'descripcion'");
  $hasDescripcion = (bool)$chk->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e) {
  // si falla SHOW COLUMNS, seguimos como si no existiera
  $hasDescripcion = false;
}

try{
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $con->beginTransaction();

  // Unicidad por nombre (case-insensitive)
  if ($id>0) {
    $st = $con->prepare("SELECT COUNT(*) FROM especialidades WHERE LOWER(nombre)=LOWER(:n) AND id<>:id");
    $st->execute([':n'=>$nombre, ':id'=>$id]);
  } else {
    $st = $con->prepare("SELECT COUNT(*) FROM especialidades WHERE LOWER(nombre)=LOWER(:n)");
    $st->execute([':n'=>$nombre]);
  }
  if ((int)$st->fetchColumn() > 0) {
    $con->rollBack();
    $err('Ya existe una especialidad con ese nombre.');
  }

  // Carga para auditoría (si es UPDATE)
  $before = null;
  if ($id>0){
    $st = $con->prepare("SELECT * FROM especialidades WHERE id=:id FOR UPDATE");
    $st->execute([':id'=>$id]);
    $before = $st->fetch(PDO::FETCH_ASSOC);
    if (!$before) { $con->rollBack(); $err('Especialidad no encontrada.'); }
  }

  if ($id>0){
    // UPDATE
    if ($hasDescripcion) {
      $sql = "UPDATE especialidades
                 SET nombre=:n, estado=:e, descripcion=:d
               WHERE id=:id";
      $params = [':n'=>$nombre, ':e'=>$estado, ':d'=>$descripcion, ':id'=>$id];
    } else {
      $sql = "UPDATE especialidades
                 SET nombre=:n, estado=:e
               WHERE id=:id";
      $params = [':n'=>$nombre, ':e'=>$estado, ':id'=>$id];
    }
    $con->prepare($sql)->execute($params);
    $newId = $id;
    $accion = 'UPDATE';
  } else {
    // INSERT
    if ($hasDescripcion) {
      $sql = "INSERT INTO especialidades (nombre, estado, descripcion, creado_en)
              VALUES (:n, :e, :d, NOW())";
      $params = [':n'=>$nombre, ':e'=>$estado, ':d'=>$descripcion];
    } else {
      $sql = "INSERT INTO especialidades (nombre, estado, creado_en)
              VALUES (:n, :e, NOW())";
      $params = [':n'=>$nombre, ':e'=>$estado];
    }
    $con->prepare($sql)->execute($params);
    $newId = (int)$con->lastInsertId();
    $accion = 'INSERT';
  }

  // Auditoría (si existe tabla auditoria)
  try {
    $afterStmt = $con->prepare("SELECT * FROM especialidades WHERE id=:id");
    $afterStmt->execute([':id'=>$newId]);
    $after = $afterStmt->fetch(PDO::FETCH_ASSOC);

    $aud = $con->prepare("
      INSERT INTO auditoria (modulo, tabla, id_registro, accion, usuario_id, estado_resultante,
                             antes_json, despues_json, ip, user_agent)
      VALUES ('Especialidades','especialidades', :id, :acc, :uid,
              :estate, :antes, :despues, :ip, :ua)
    ");
    $aud->execute([
      ':id'     => $newId,
      ':acc'    => $accion,
      ':uid'    => $uid,
      ':estate' => ($after && (int)$after['estado']===1)? 'activo' : 'inactivo',
      ':antes'  => json_encode($before, JSON_UNESCAPED_UNICODE),
      ':despues'=> json_encode($after,  JSON_UNESCAPED_UNICODE),
      ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
      ':ua'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
  } catch(Throwable $e) {
    // si no existe auditoria o falla, no bloquea la operación
  }

  $con->commit();

  $ok([
    'success' => true,
    'id'      => $newId,
    'message' => ($accion==='INSERT' ? 'Especialidad creada correctamente.' : 'Especialidad actualizada.'),
  ]);

} catch(Throwable $e){
  if ($con->inTransaction()) $con->rollBack();
  $err('No se pudo guardar la especialidad.', $e->getMessage());
}
