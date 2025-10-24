<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ok  = fn($m)=> (print json_encode(['success'=>true,'message'=>$m], JSON_UNESCAPED_UNICODE)) && exit;
$err = function($m,$dbg=null){ echo json_encode(['success'=>false,'message'=>$m,'debug'=>$dbg]); exit; };

try { require_once __DIR__ . '/../config/connection.php'; }
catch(Throwable $e){ $err('Conexión no disponible',$e->getMessage()); }

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) $err('Sesión expirada');

$medico_id = (int)($_POST['medico_id'] ?? $_POST['id_usuario_medico'] ?? 0);
$fecha     = trim($_POST['fecha'] ?? '');
$hi        = trim($_POST['hora_inicio'] ?? '');
$hf        = trim($_POST['hora_fin'] ?? '');
$motivo    = trim($_POST['motivo'] ?? '');

if ($medico_id<=0)        $err('Selecciona el médico');
if (!$fecha || !$hi || !$hf) $err('Completa fecha y horas');
if ($hf <= $hi)           $err('La hora fin debe ser mayor que la hora inicio');

try {
  $st = $con->prepare("INSERT INTO horarios_bloqueos
    (medico_id, fecha, hora_inicio, hora_fin, motivo)
    VALUES (:m,:f,:hi,:hf,:mo)");
  $st->execute([':m'=>$medico_id, ':f'=>$fecha, ':hi'=>$hi, ':hf'=>$hf, ':mo'=>$motivo ?: null]);
  $ok('Ausencia registrada');
} catch(Throwable $e){
  $err('No se pudo guardar la ausencia', $e->getMessage());
}
