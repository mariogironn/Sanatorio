<?php
// ajax/crear_historial_paciente.php  (FIX)
// - Corrige Undefined index: Unidad  -> usa alias 'paquete' del SELECT
// - Acepta $_POST['unidad'] o $_POST['Unidad']
// - Acepta $_POST['sucursal'] (nombre) o $_POST['sucursal_id'] (id)

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once '../config/connection.php';

$out = ['success'=>false, 'message'=>'', 'row'=>null];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  $uid = (int)($_SESSION['user_id'] ?? 0);
  if ($uid <= 0) {
    http_response_code(401);
    throw new Exception('No autenticado');
  }

  // --- Inputs seguros
  $paciente     = (int)($_POST['paciente_id'] ?? 0);
  $fecha        = trim($_POST['fecha_visita'] ?? '');
  $enfermedad   = trim($_POST['enfermedad'] ?? '');
  $medicina_id  = (int)($_POST['medicina_id'] ?? 0);
  $cantidad     = (int)($_POST['cantidad'] ?? 0);
  $dosis        = trim($_POST['dosis'] ?? '');
  // acepta 'unidad' o 'Unidad'
  $paquete      = trim($_POST['unidad'] ?? ($_POST['Unidad'] ?? ''));
  // acepta nombre directo o id de sucursal
  $sucursal_nombre = trim($_POST['sucursal'] ?? '');

  if ($paciente <= 0 || $fecha === '' || $enfermedad === '' || $medicina_id <= 0 || $cantidad <= 0 || $dosis === '') {
    http_response_code(400);
    throw new Exception('Datos incompletos');
  }

  // Si no llegó el nombre, intenta resolver desde sucursal_id
  if ($sucursal_nombre === '') {
    $sucursal_id = (int)($_POST['sucursal_id'] ?? 0);
    if ($sucursal_id > 0) {
      try {
        $q = $con->prepare("SELECT nombre FROM sucursales WHERE id = ?");
        $q->execute([$sucursal_id]);
        $sucursal_nombre = $q->fetchColumn() ?: '';
      } catch (Throwable $e) { /* ignora y cae al default */ }
    }
  }
  if ($sucursal_nombre === '') { $sucursal_nombre = 'General'; }

  $con->beginTransaction();

  // 1) Cabecera en prescripciones
  $p = $con->prepare("
    INSERT INTO prescripciones
      (id_paciente, fecha_visita, enfermedad, sucursal, estado, medico_id, created_by, created_at)
    VALUES
      (:pac, :f, :enf, :suc, 'completada', :med, :cb, NOW())
  ");
  $p->execute([
    ':pac' => $paciente,
    ':f'   => $fecha,
    ':enf' => $enfermedad,
    ':suc' => $sucursal_nombre,
    ':med' => $uid,
    ':cb'  => $uid
  ]);
  $pid = (int)$con->lastInsertId();

  // 2) Detalle
  $d = $con->prepare("
    INSERT INTO detalle_prescripciones
      (id_prescripcion, id_medicamento, empaque, cantidad, dosis, created_by)
    VALUES
      (:pid, :mid, :paq, :cant, :dosis, :cb)
  ");
  $d->execute([
    ':pid'  => $pid,
    ':mid'  => $medicina_id,
    ':paq'  => $paquete,
    ':cant' => $cantidad,
    ':dosis'=> $dosis,
    ':cb'   => $uid
  ]);
  $detId = (int)$con->lastInsertId();

  // 3) Devolver fila para pintar en la tabla
  $st = $con->prepare("
    SELECT 
      d.id_detalle, 
      p.id_prescripcion, 
      p.fecha_visita, 
      p.enfermedad, 
      p.sucursal,
      m.id AS id_medicina, 
      m.nombre_medicamento AS medicina, 
      d.empaque AS paquete,
      d.cantidad, 
      d.dosis,
      pc.nombre AS nombre_paciente
    FROM detalle_prescripciones d
    JOIN prescripciones p ON p.id_prescripcion = d.id_prescripcion
    JOIN medicamentos m   ON m.id = d.id_medicamento
    JOIN pacientes pc     ON p.id_paciente = pc.id_paciente
    WHERE d.id_detalle = :det
  ");
  $st->execute([':det' => $detId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);

  $out['row'] = [
    'detalle_id'      => (int)$r['id_detalle'],
    'prescripcion_id' => (int)$r['id_prescripcion'],
    // Genera un N. Serie “007”, “045”, etc.
    'n_serie'         => str_pad((string)$r['id_prescripcion'], 3, '0', STR_PAD_LEFT),
    'fecha_visita'    => $r['fecha_visita'],
    'enfermedad'      => $r['enfermedad'],
    'id_medicina'     => (int)$r['id_medicina'],
    'medicina'        => $r['medicina'],
    'paquete'         => $r['paquete'],   // <-- FIX: era $r['Unidad']
    'cantidad'        => (int)$r['cantidad'],
    'dosis'           => $r['dosis'],
    'sucursal'        => $r['sucursal'],
    'nombre_paciente' => $r['nombre_paciente']
  ];

  $con->commit();
  $out['success'] = true;
  $out['message'] = 'Registro agregado correctamente';

} catch (Throwable $e) {
  if (isset($con) && $con instanceof PDO && $con->inTransaction()) {
    $con->rollBack();
  }
  if (!http_response_code()) { http_response_code(400); }
  $out['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
