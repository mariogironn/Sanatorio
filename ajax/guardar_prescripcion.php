<?php
// ajax/guardar_prescripcion.php - Guardar nueva prescripción
if (session_status() !== PHP_SESSION_ACTIVE) { 
    session_start(); 
}
header('Content-Type: application/json');

// Incluir conexión
require_once '../config/connection.php';

// ✅ === FIX: obtener usuario y rol de sesión / BD (sin romper nada) ===
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
$esPersonalClinico = in_array($rol, ['doctor','medico','enfermero']);

// ✅ En tu INSERT, NO cambies campos, solo asegura estos dos valores:
$medicoId = $esPersonalClinico ? $userId : null; // si no es clínico, queda NULL
$createdBy = $userId;

$response = ['success' => false, 'message' => 'Error desconocido'];

try {
    // Verificar que es una petición POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Validar datos requeridos
    $campos_requeridos = ['id_paciente', 'fecha_visita', 'enfermedad', 'sucursal'];
    foreach ($campos_requeridos as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception("El campo '$campo' es obligatorio");
        }
    }

    // Validar medicinas
    if (!isset($_POST['medicinas']) || !is_array($_POST['medicinas']) || empty($_POST['medicinas'])) {
        throw new Exception('Debe agregar al menos una medicina');
    }

    // Validar que todas las medicinas tengan los campos requeridos
    foreach ($_POST['medicinas'] as $index => $medicina) {
        $campos_medicina = ['id_medicamento', 'empaque', 'cantidad', 'dosis'];
        foreach ($campos_medicina as $campo) {
            if (empty($medicina[$campo])) {
                throw new Exception("La medicina #" . ($index + 1) . " tiene campos incompletos");
            }
        }
    }

    // Iniciar transacción
    $con->beginTransaction();

    // Insertar prescripción principal
    $query = "INSERT INTO prescripciones 
              (id_paciente, fecha_visita, proxima_visita, peso, presion, enfermedad, sucursal, medico_id, created_by) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $con->prepare($query);
    
    $result = $stmt->execute([
        $_POST['id_paciente'],
        $_POST['fecha_visita'],
        !empty($_POST['proxima_visita']) ? $_POST['proxima_visita'] : null,
        !empty($_POST['peso']) ? $_POST['peso'] : null,
        !empty($_POST['presion']) ? $_POST['presion'] : null,
        $_POST['enfermedad'],
        $_POST['sucursal'],
        $medicoId,     // 👈 aquí (reemplaza $_SESSION['id'] ?? null)
        $createdBy     // 👈 y aquí (reemplaza $_SESSION['id'] ?? null)
    ]);

    if (!$result) {
        throw new Exception('Error al guardar la prescripción principal');
    }

    $id_prescripcion = $con->lastInsertId();

    // Insertar medicinas
    foreach ($_POST['medicinas'] as $medicina) {
        $queryDetalle = "INSERT INTO detalle_prescripciones 
                         (id_prescripcion, id_medicamento, empaque, cantidad, dosis, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmtDetalle = $con->prepare($queryDetalle);
        $resultDetalle = $stmtDetalle->execute([
            $id_prescripcion,
            $medicina['id_medicamento'],
            $medicina['empaque'],
            $medicina['cantidad'],
            $medicina['dosis'],
            $createdBy  // 👈 aquí también usa $createdBy
        ]);

        if (!$resultDetalle) {
            throw new Exception('Error al guardar las medicinas');
        }
    }

    // Confirmar transacción
    $con->commit();
    
    $response['success'] = true;
    $response['message'] = 'Prescripción guardada correctamente';
    $response['id_prescripcion'] = $id_prescripcion;

} catch (PDOException $e) {
    // Rollback en caso de error de BD
    if ($con->inTransaction()) {
        $con->rollBack();
    }
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    error_log("Error BD guardar_prescripcion: " . $e->getMessage());
    
} catch (Exception $e) {
    // Rollback en caso de otros errores
    if ($con->inTransaction()) {
        $con->rollBack();
    }
    $response['message'] = $e->getMessage();
    error_log("Error guardar_prescripcion: " . $e->getMessage());
}

// Asegurarse de que la respuesta sea JSON válida
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>