<?php
// ajax/eliminar_prescripcion.php - Eliminar prescripción
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json');

include '../config/connection.php';

$response = ['success' => false, 'message' => ''];

try {
    if (empty($_POST['id'])) {
        throw new Exception('ID de prescripción no proporcionado');
    }

    $id_prescripcion = (int)$_POST['id'];

    // Verificar que la prescripción existe
    $queryCheck = "SELECT id_prescripcion FROM prescripciones WHERE id_prescripcion = ?";
    $stmtCheck = $con->prepare($queryCheck);
    $stmtCheck->execute([$id_prescripcion]);
    
    if (!$stmtCheck->fetch()) {
        throw new Exception('La prescripción no existe');
    }

    // Iniciar transacción
    $con->beginTransaction();

    // Eliminar detalles primero (por la FK)
    $queryDetalles = "DELETE FROM detalle_prescripciones WHERE id_prescripcion = ?";
    $stmtDetalles = $con->prepare($queryDetalles);
    $stmtDetalles->execute([$id_prescripcion]);

    // Eliminar prescripción principal
    $queryPrescripcion = "DELETE FROM prescripciones WHERE id_prescripcion = ?";
    $stmtPrescripcion = $con->prepare($queryPrescripcion);
    $stmtPrescripcion->execute([$id_prescripcion]);

    $con->commit();
    $response['success'] = true;
    $response['message'] = 'Prescripción eliminada correctamente';

} catch (Exception $e) {
    $con->rollBack();
    $response['message'] = $e->getMessage();
} catch (PDOException $e) {
    $con->rollBack();
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);
?>