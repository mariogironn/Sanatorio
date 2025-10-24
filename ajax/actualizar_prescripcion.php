
<?php
// ajax/actualizar_prescripcion.php - Actualizar prescripción
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/connection.php';

$response = ['success' => false, 'message' => ''];

// === Usuario + rol (usando la vista de rol principal) ===
$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 0);
$rol = '';
if ($userId > 0) {
  $q = $con->prepare("
    SELECT LOWER(v.rol_nombre) AS rol
    FROM vw_usuario_rol_principal v
    WHERE v.id_usuario = ? LIMIT 1
  ");
  $q->execute([$userId]);
  $rol = $q->fetchColumn() ?: '';
}
$esPersonalClinico = in_array($rol, ['doctor','medico','enfermero','enfermera']);

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido');
  }

  // Validaciones mínimas
  $id_prescripcion = (int)($_POST['id_prescripcion'] ?? 0);
  if ($id_prescripcion <= 0) throw new Exception('ID de prescripción inválido');

  if (empty($_POST['id_paciente']) || empty($_POST['fecha_visita']) ||
      empty($_POST['enfermedad'])   || empty($_POST['sucursal'])) {
    throw new Exception('Todos los campos obligatorios deben ser completados');
  }

  if (!isset($_POST['medicinas']) || empty($_POST['medicinas'])) {
    throw new Exception('Debe agregar al menos una medicina');
  }

  $con->beginTransaction();

  // Update principal
  $estado = $_POST['estado'] ?? 'activa';

  $sql = "UPDATE prescripciones 
             SET id_paciente    = ?,
                 fecha_visita   = ?,
                 proxima_visita = ?,
                 peso           = ?,
                 presion        = ?,
                 enfermedad     = ?,
                 sucursal       = ?,
                 estado         = ?,
                 updated_by     = ?,
                 updated_at     = NOW()";
  $params = [
    $_POST['id_paciente'],
    $_POST['fecha_visita'],
    $_POST['proxima_visita'] ?: null,
    $_POST['peso'] ?: null,
    $_POST['presion'] ?: null,
    $_POST['enfermedad'],
    $_POST['sucursal'],
    $estado,
    $userId
  ];

  // Si edita un clínico, reasignamos SIEMPRE el médico al editor
  if ($esPersonalClinico) {
    $sql .= ", medico_id = ?";
    $params[] = $userId;
  }

  $sql .= " WHERE id_prescripcion = ?";
  $params[] = $id_prescripcion;

  $stmt = $con->prepare($sql);
  $stmt->execute($params);

  // Reemplazar detalle
  $stmtDel = $con->prepare("DELETE FROM detalle_prescripciones WHERE id_prescripcion = ?");
  $stmtDel->execute([$id_prescripcion]);

  $stmtDet = $con->prepare("INSERT INTO detalle_prescripciones
      (id_prescripcion, id_medicamento, empaque, cantidad, dosis, created_by)
      VALUES (?, ?, ?, ?, ?, ?)");
  foreach ($_POST['medicinas'] as $m) {
    if (empty($m['id_medicamento']) || empty($m['empaque']) || empty($m['cantidad']) || empty($m['dosis'])) {
      continue;
    }
    $stmtDet->execute([
      $id_prescripcion,
      $m['id_medicamento'],
      $m['empaque'],
      $m['cantidad'],
      $m['dosis'],
      $userId
    ]);
  }

  $con->commit();
  $response = ['success' => true, 'message' => 'Prescripción actualizada correctamente'];

} catch (Throwable $e) {
  if ($con->inTransaction()) $con->rollBack();
  $response['message'] = $e->getMessage();
  error_log("actualizar_prescripcion ERROR: ".$e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
