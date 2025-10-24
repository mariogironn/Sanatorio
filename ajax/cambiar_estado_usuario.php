<?php
// sanatorio/ajax/cambiar_estado_usuario.php
// Cambia estado ACTIVO/INACTIVO de un usuario + AUDITORÍA (ACTIVAR/DESACTIVAR)

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require_once __DIR__ . '/../config/connection.php';

// === Auditoría ===
require_once __DIR__ . '/../common_service/auditoria_service.php';
require_once __DIR__ . '/../common_service/audit_helpers.php';

header('Content-Type: text/plain; charset=UTF-8');

$uid   = (int)($_POST['user_id'] ?? 0);
$input = strtoupper(trim($_POST['nuevo'] ?? ''));

if ($uid <= 0) { echo 'ID inválido'; exit; }

$map = [
  '1'            => 'ACTIVO',
  '0'            => 'INACTIVO',
  'ACTIVO'       => 'ACTIVO',
  'INACTIVO'     => 'INACTIVO',
  'BLOQUEAR'     => 'INACTIVO',
  'DESBLOQUEAR'  => 'ACTIVO'
];
if (!isset($map[$input])) { echo 'Estado inválido'; exit; }
$estadoNuevo = $map[$input];

// --- Normaliza a texto auditoría: 'activo' | 'inactivo'
$toAuditTxt = function($v){
  $u = strtoupper(trim((string)$v));
  return ($u === 'ACTIVO' || $u === '1' || $u === 'SI' || $u === 'TRUE') ? 'activo' : 'inactivo';
};

// --- Detectar PK (id | id_usuario)
$PK = 'id';
try {
  $ck = $con->query("SHOW COLUMNS FROM usuarios LIKE 'id_usuario'");
  if ($ck && $ck->rowCount() > 0) { $PK = 'id_usuario'; }
} catch (Throwable $e) { /* continuar con 'id' */ }

// --- Detectar tipo de columna 'estado' (numérica vs texto) para guardar 1/0 o ACTIVO/INACTIVO
$ESTADO_IS_NUM = false;
try {
  $c = $con->query("SHOW COLUMNS FROM usuarios LIKE 'estado'");
  if ($c) {
    $col = $c->fetch(PDO::FETCH_ASSOC);
    if ($col && isset($col['Type']) && stripos($col['Type'], 'int') !== false) {
      $ESTADO_IS_NUM = true;
    }
  }
} catch (Throwable $e) { /* ignorar, default texto */ }
$estadoNuevoDB = $ESTADO_IS_NUM ? ($estadoNuevo === 'ACTIVO' ? 1 : 0) : $estadoNuevo;

try {
  // Traer info actual del usuario
  $st = $con->prepare("SELECT `$PK` AS pk, usuario, nombre_mostrar, estado FROM usuarios WHERE `$PK` = :i");
  $st->execute([':i' => $uid]);
  $info = $st->fetch(PDO::FETCH_ASSOC);

  if (!$info) {
    echo 'El usuario no existe.'; exit;
  }

  // Evitar que un usuario se bloquee a sí mismo
  $sessionUid = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
  if ($sessionUid === $uid && $estadoNuevo === 'INACTIVO') {
    echo 'No puedes bloquear tu propia cuenta.'; exit;
  }

  // Normalizar estado actual para comparar contra el nuevo
  $estadoActualTxt = $toAuditTxt($info['estado']);               // 'activo' | 'inactivo'
  $estadoNuevoTxt  = $toAuditTxt($estadoNuevo);                  // 'activo' | 'inactivo'
  $estadoActualNorm = ($estadoActualTxt === 'activo') ? 'ACTIVO' : 'INACTIVO';

  // Si no hay cambio real, responder OK (sin tocar BD ni auditar)
  if ($estadoActualNorm === $estadoNuevo) {
    echo 'OK'; exit;
  }

  // Actualizar estado (respetando tipo de columna)
  $up = $con->prepare("UPDATE usuarios SET estado = :e WHERE `$PK` = :i");
  $up->execute([':e' => $estadoNuevoDB, ':i' => $uid]);

  // Aunque no cambien filas (carrera), responder OK igual
  if ($up->rowCount() < 1) {
    echo 'OK'; exit;
  }

  echo 'OK';

  // === AUDITORÍA: registrar ACTIVAR/DESACTIVAR (no interrumpir flujo si falla)
  try {
    $estadoAntes   = $estadoActualTxt; // 'activo' | 'inactivo'
    $estadoDespues = $estadoNuevoTxt;  // 'activo' | 'inactivo'
    audit_toggle_state($con, 'usuarios', 'usuarios', $uid, $estadoAntes, $estadoDespues);
  } catch (Throwable $e) {
    error_log('AUDITORIA TOGGLE usuarios: '.$e->getMessage());
  }

} catch (PDOException $ex) {
  echo 'Error: ' . $ex->getMessage();
}
