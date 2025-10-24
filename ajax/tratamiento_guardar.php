<?php
/**
 * Guardar/actualizar Tratamiento
 * - Inserta o actualiza la fila de `tratamientos`
 * - Sincroniza sus medicamentos en `tratamiento_medicamentos`
 * - Acepta alias de campos para compatibilidad (id_diagnostico/diagnostico_id, id_medico/medico_id, duracion_estimada/duracion, meds[]/medicamentos[])
 */

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }                    // Asegura sesión
header('Content-Type: application/json; charset=utf-8');                             // Respuesta JSON

// Helpers para responder rápido
$ok  = function(array $data){ echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; };
$err = function(string $msg, ?string $debug=null){
  echo json_encode(['success'=>false,'message'=>$msg] + ($debug?['debug'=>$debug]:[]), JSON_UNESCAPED_UNICODE);
  exit;
};

// Autenticación / conexión
try{
  require_once __DIR__.'/../config/auth.php';
  require_once __DIR__.'/../config/connection.php';
}catch(Throwable $e){
  $err('Conexión a BD no disponible.', $e->getMessage());
}

// Usuario logueado
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { $err('Sesión expirada. Vuelve a iniciar sesión.'); }

// Resolver PDO
$pdo = null;
if (isset($con) && $con instanceof PDO)        { $pdo = $con; }
elseif (isset($pdo) && $pdo instanceof PDO)     { /* $pdo ya está */ }
else { $err('Conexión a BD no disponible (no se detectó objeto PDO).'); }

// Excepciones
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ===== Auditoría (opcional, tolerante) =====
$AUDIT_READY = false;
try {
  @require_once __DIR__.'/../common_service/auditoria_service.php';
  if (function_exists('audit_log')) $AUDIT_READY = true;
} catch (Throwable $e) { /* seguimos sin auditar */ }

// Helper: snapshot del tratamiento + meds
function tr_snapshot(PDO $pdo, int $id): ?array {
  $sql = "SELECT id, id_diagnostico, id_medico, fecha_inicio, duracion, estado, instrucciones
          FROM tratamientos WHERE id = :id LIMIT 1";
  $st  = $pdo->prepare($sql); $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return null;

  $m  = $pdo->prepare("SELECT id_medicamento FROM tratamiento_medicamentos WHERE id_tratamiento = :t ORDER BY id_medicamento");
  $m->execute([':t'=>$id]);
  $row['meds'] = array_map('intval', array_column($m->fetchAll(PDO::FETCH_ASSOC), 'id_medicamento'));
  return $row;
}

// === Recibir y normalizar parámetros (con aliases) ===
$id            = (int)($_POST['id'] ?? $_POST['id_tratamiento'] ?? 0);
$id_dx         = (int)($_POST['id_diagnostico'] ?? $_POST['diagnostico_id'] ?? 0);
$id_med        = (int)($_POST['id_medico']      ?? $_POST['medico_id']      ?? 0);
$fecha         = trim($_POST['fecha_inicio'] ?? '');
$duracion      = trim($_POST['duracion_estimada'] ?? $_POST['duracion'] ?? '');
$estado_in     = trim($_POST['estado'] ?? 'Activo');
$instrucciones = trim($_POST['instrucciones'] ?? '');

// Medicamentos
$meds = $_POST['meds'] ?? ($_POST['medicamentos'] ?? []);
if (is_string($meds)) { $meds = [$meds]; }
$meds = array_values(array_filter(array_map('intval', (array)$meds)));

// Validaciones
if ($id_dx <= 0)  { $err('Selecciona un diagnóstico válido.'); }
if ($id_med <= 0) { $err('Selecciona un médico tratante válido.'); }
if ($fecha === ''){ $err('La fecha de inicio es obligatoria.'); }

// Normalizar fecha dd/mm/yyyy -> yyyy-mm-dd
if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $fecha)) {
  [$dd,$mm,$yy] = explode('/', $fecha);
  $fecha = sprintf('%04d-%02d-%02d', $yy, $mm, $dd);
}

// Mapear estado UI -> ENUM BD
$estado = strtolower($estado_in);
if ($estado === 'completado') { $estado = 'finalizado'; }
if (!in_array($estado, ['activo','inactivo','finalizado'], true)) { $estado = 'activo'; }

// === Transacción ===
try{
  $pdo->beginTransaction();

  // Para auditoría
  $antes = null;
  if ($id > 0 && $AUDIT_READY) {
    $antes = tr_snapshot($pdo, $id);
  }

  if ($id > 0) {
    // UPDATE
    $sql = "UPDATE tratamientos
              SET id_diagnostico = :dx,
                  id_medico      = :med,
                  fecha_inicio   = :fecha,
                  duracion       = :dur,
                  estado         = :estado,
                  instrucciones  = :inst,
                  updated_at     = NOW()
            WHERE id = :id";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':dx'    => $id_dx,
      ':med'   => $id_med,
      ':fecha' => $fecha,
      ':dur'   => $duracion,
      ':estado'=> $estado,
      ':inst'  => $instrucciones,
      ':id'    => $id
    ]);
    $tratId = $id;
  } else {
    // INSERT
    $sql = "INSERT INTO tratamientos
              (id_diagnostico, id_medico, fecha_inicio, duracion, estado, instrucciones, created_at, updated_at)
            VALUES
              (:dx, :med, :fecha, :dur, :estado, :inst, NOW(), NOW())";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':dx'    => $id_dx,
      ':med'   => $id_med,
      ':fecha' => $fecha,
      ':dur'   => $duracion,
      ':estado'=> $estado,
      ':inst'  => $instrucciones
    ]);
    $tratId = (int)$pdo->lastInsertId();
  }

  // Sincronizar medicamentos
  $pdo->prepare("DELETE FROM tratamiento_medicamentos WHERE id_tratamiento = ?")->execute([$tratId]);
  if (!empty($meds)) {
    $ins = $pdo->prepare("INSERT INTO tratamiento_medicamentos (id_tratamiento, id_medicamento) VALUES (:t,:m)");
    foreach ($meds as $mid) {
      if ($mid > 0) { $ins->execute([':t'=>$tratId, ':m'=>$mid]); }
    }
  }

  // ===== Auditoría dentro de la transacción =====
  if ($AUDIT_READY) {
    $despues = tr_snapshot($pdo, $tratId);

    if ($id > 0 && function_exists('audit_update')) {
      audit_update($pdo, 'tratamientos', 'tratamientos', $tratId, $antes, $despues, $estado);
    } elseif ($id === 0 && function_exists('audit_create')) {
      audit_create($pdo, 'tratamientos', 'tratamientos', $tratId, $despues, $estado);
    } elseif (function_exists('audit_log')) {
      // Fallback genérico
      audit_log($pdo, [
        'modulo'         => 'tratamientos',
        'tabla'          => 'tratamientos',
        'id_registro'    => $tratId,
        'accion'         => ($id > 0 ? 'UPDATE' : 'CREATE'),
        'antes'          => $antes,
        'despues'        => $despues,
        'estado_resultante' => $estado
      ]);
    }
  }

  $pdo->commit();

  $codigo = 'T-'.str_pad((string)$tratId, 3, '0', STR_PAD_LEFT);
  $ok([
    'success' => true,
    'id'      => $tratId,
    'codigo'  => $codigo,
    'message' => 'Tratamiento guardado correctamente'
  ]);
}
catch(Throwable $e){
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  $err('Error al guardar', $e->getMessage());
}
