<?php
// ajax/diagnostico_guardar.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/connection.php';

/* === Conexión PDO robusta === */
$pdo = null;
try {
  if (isset($con) && $con instanceof PDO)              { $pdo = $con; }
  elseif (isset($conexion) && $conexion instanceof PDO){ $pdo = $conexion; }
  elseif (function_exists('get_db'))                   { $pdo = get_db(); }
  else {
    $pdo = new PDO('mysql:host=localhost;dbname=la_esperanza;charset=utf8','root','',[
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'message'=>'No se pudo conectar a la BD']); exit;
}

/* === Inputs === */
$id = (int)(
  $_POST['id'] ??
  $_POST['id_diagnostico'] ??
  $_POST['diagnostico_id'] ??
  $_POST['iddx'] ?? 0
);
$id_paciente   = (int)($_POST['id_paciente'] ?? 0);
$id_enfermedad = (int)($_POST['id_enfermedad'] ?? 0);
$id_medico     = (int)($_POST['id_medico'] ?? 0); // id de tabla medicos
$sintomas      = trim($_POST['sintomas'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');
$gravedad      = $_POST['gravedad'] ?? 'Leve';
$fecha         = trim($_POST['fecha'] ?? '');

/* Normaliza fecha dd/mm/yyyy -> yyyy-mm-dd */
if ($fecha && preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $fecha, $m)) {
  $fecha = "{$m[3]}-{$m[2]}-{$m[1]}";
}

/* Validaciones mínimas */
if (!$id_paciente || !$id_enfermedad || !$id_medico || !$fecha) {
  echo json_encode(['success'=>false,'message'=>'Faltan campos obligatorios']); exit;
}

try {
  if ($id > 0) {
    // UPDATE
    $sql = "UPDATE diagnosticos
               SET id_paciente=?, id_enfermedad=?, id_medico=?,
                   sintomas=?, observaciones=?, gravedad=?, fecha=?
             WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $id_paciente, $id_enfermedad, $id_medico,
      $sintomas, $observaciones, $gravedad, $fecha,
      $id
    ]);
  } else {
    // INSERT
    $sql = "INSERT INTO diagnosticos
              (id_paciente, id_enfermedad, id_medico, sintomas, observaciones, gravedad, fecha)
            VALUES (?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $id_paciente, $id_enfermedad, $id_medico,
      $sintomas, $observaciones, $gravedad, $fecha
    ]);
    $id = (int)$pdo->lastInsertId();
  }

  echo json_encode([
    'success' => true,
    'id'      => $id,
    'codigo'  => sprintf('D-%03d', $id)
  ]);
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
