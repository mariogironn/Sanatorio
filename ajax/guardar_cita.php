<?php
// ajax/guardar_cita.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');
// MUY IMPORTANTE: no imprimir warnings/notices en la salida JSON:
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

$res = ['success' => false, 'message' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    throw new Exception('Método no permitido');
  }

  // En todo el sistema usas 'user_id' (no 'usuario_id')
  $usuario_id = (int)($_SESSION['user_id'] ?? 0);

  // Campos
  $cita_id     = isset($_POST['cita_id']) ? (int)$_POST['cita_id'] : 0;
  $paciente_id = isset($_POST['paciente_id']) ? (int)$_POST['paciente_id'] : 0;
  $medico_id   = isset($_POST['medico_id']) ? (int)$_POST['medico_id'] : 0;
  $fecha       = trim($_POST['fecha'] ?? '');
  $hora        = trim($_POST['hora'] ?? '');
  $motivo      = trim($_POST['motivo'] ?? '');
  $estado      = trim($_POST['estado'] ?? 'pendiente');

  // Validaciones mínimas
  if ($paciente_id <= 0 || $medico_id <= 0 || $fecha === '' || $hora === '' || $motivo === '') {
    http_response_code(400);
    throw new Exception('Todos los campos son obligatorios.');
  }

  if ($cita_id > 0) {
    // UPDATE
    $sql = "UPDATE citas_medicas
            SET paciente_id = :paciente, medico_id = :medico, fecha = :fecha, hora = :hora,
                motivo = :motivo, estado = :estado, updated_by = :uid, updated_at = NOW()
            WHERE id_cita = :id";
    $st = $con->prepare($sql);
    $st->execute([
      ':paciente' => $paciente_id,
      ':medico'   => $medico_id,
      ':fecha'    => $fecha,
      ':hora'     => $hora,
      ':motivo'   => $motivo,
      ':estado'   => $estado,
      ':uid'      => $usuario_id,
      ':id'       => $cita_id
    ]);

    $res['success'] = true;
    $res['message'] = 'Cita actualizada correctamente.';
    $res['id_cita'] = $cita_id;

  } else {
    // INSERT
    $sql = "INSERT INTO citas_medicas
              (paciente_id, medico_id, fecha, hora, motivo, estado, created_by, created_at)
            VALUES
              (:paciente, :medico, :fecha, :hora, :motivo, :estado, :uid, NOW())";
    $st = $con->prepare($sql);
    $st->execute([
      ':paciente' => $paciente_id,
      ':medico'   => $medico_id,
      ':fecha'    => $fecha,
      ':hora'     => $hora,
      ':motivo'   => $motivo,
      ':estado'   => $estado,
      ':uid'      => $usuario_id
    ]);

    $res['success'] = true;
    $res['message'] = 'Cita creada correctamente.';
    $res['id_cita'] = (int)$con->lastInsertId();
  }

} catch (Throwable $e) {
  if (!http_response_code()) { http_response_code(400); }
  $res['success'] = false;
  $res['message'] = $e->getMessage();
}

// Devolver SIEMPRE JSON limpio
echo json_encode($res, JSON_UNESCAPED_UNICODE);
