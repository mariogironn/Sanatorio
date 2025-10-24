<?php
// ajax/horarios_guardar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$ok  = fn($p)=> (print json_encode($p, JSON_UNESCAPED_UNICODE)) && exit;
$err = function($m,$d=null){ echo json_encode(['success'=>false,'message'=>$m,'debug'=>$d]); exit; };

try {
  require_once __DIR__.'/../config/connection.php';
} catch (Throwable $e) { $err('Conexión no disponible', $e->getMessage()); }

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) $err('Sesión expirada, inicia sesión.');

$id          = (int)($_POST['id_horario']   ?? 0);
$medico_id   = (int)($_POST['medico_id']    ?? 0);     // debe apuntar a usuarios.id
$dia_semana  = (int)($_POST['dia_semana']   ?? 0);     // 1..7
$hora_inicio = trim($_POST['hora_inicio']   ?? '');
$hora_fin    = trim($_POST['hora_fin']      ?? '');
$estado_in   = strtolower(trim($_POST['estado'] ?? 'activo')); // activo | inactivo | disponible

// CORRECCIÓN: Mapear a números según la estructura de la base de datos
$map = [
    'activo' => 1,      // 1 = Activo
    'inactivo' => 2,    // 2 = Inactivo  
    'disponible' => 3   // 3 = Disponible
];
$estado = $map[$estado_in] ?? 1; // Por defecto Activo

// Validaciones básicas
if ($medico_id<=0)         $err('Selecciona el médico.');
if ($dia_semana<1||$dia_semana>7) $err('Día de semana inválido.');
if ($hora_inicio===''||$hora_fin==='') $err('Indica hora inicio y fin.');
if ($hora_fin <= $hora_inicio) $err('La hora fin debe ser mayor que la hora inicio.');

try {
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if ($id > 0) {
    $sql = "UPDATE horarios_medicos
              SET medico_id=:medico_id,
                  dia_semana=:dia,
                  hora_inicio=:hi,
                  hora_fin=:hf,
                  estado=:estado,
                  updated_at=NOW()
            WHERE id=:id";
    $st = $con->prepare($sql);
    $st->execute([
      ':medico_id'=>$medico_id, ':dia'=>$dia_semana,
      ':hi'=>$hora_inicio, ':hf'=>$hora_fin,
      ':estado'=>$estado, ':id'=>$id
    ]);
  } else {
    $sql = "INSERT INTO horarios_medicos
              (medico_id, dia_semana, hora_inicio, hora_fin, estado, created_at, updated_at)
            VALUES
              (:medico_id, :dia, :hi, :hf, :estado, NOW(), NOW())";
    $st = $con->prepare($sql);
    $st->execute([
      ':medico_id'=>$medico_id, ':dia'=>$dia_semana,
      ':hi'=>$hora_inicio, ':hf'=>$hora_fin, ':estado'=>$estado
    ]);
    $id = (int)$con->lastInsertId();
  }

  // CORRECCIÓN: Devolver el estado como texto para el frontend
  $estado_texto = [
      1 => 'Activo',
      2 => 'Inactivo',
      3 => 'Disponible'
  ][$estado] ?? 'Activo';

  $ok(['success'=>true, 'message'=>'Horario guardado', 'id_horario'=>$id, 'estado'=>$estado_texto]);

} catch (Throwable $e) {
  $err('No se pudo guardar el horario', $e->getMessage());
}