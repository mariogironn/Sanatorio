<?php
// ajax/eliminar_comparativo.php - ELIMINAR COMPARACIONES (DELETE)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json');

include '../config/connection.php';

$response = ['success' => false, 'message' => ''];

try {
    if (empty($_POST['id'])) {
        throw new Exception('ID de comparación no proporcionado');
    }

    $id_comparacion = (int)$_POST['id'];

    // Verificar que la comparación existe
    $queryCheck = "SELECT id FROM comparaciones_pacientes WHERE id = ?";
    $stmtCheck = $con->prepare($queryCheck);
    $stmtCheck->execute([$id_comparacion]);
    
    if (!$stmtCheck->fetch()) {
        throw new Exception('La comparación no existe');
    }

    // Eliminar la comparación
    $queryDelete = "DELETE FROM comparaciones_pacientes WHERE id = ?";
    $stmtDelete = $con->prepare($queryDelete);
    $stmtDelete->execute([$id_comparacion]);

    $response['success'] = true;
    $response['message'] = 'Comparación eliminada correctamente';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);
?>