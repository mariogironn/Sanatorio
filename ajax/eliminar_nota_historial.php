<?php
// ajax/eliminar_nota_historial.php - ELIMINAR NOTAS DEL HISTORIAL (DELETE)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json');

// Verificar sesión
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

include '../config/connection.php';

$response = ['success' => false, 'message' => ''];

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    if (empty($_POST['id'])) {
        throw new Exception('ID de nota no proporcionado');
    }

    $id_nota = (int)$_POST['id'];

    // Verificar que la nota existe
    $queryCheck = "SELECT id_nota, id_paciente FROM historial_notas WHERE id_nota = ?";
    $stmtCheck = $con->prepare($queryCheck);
    $stmtCheck->execute([$id_nota]);
    $nota = $stmtCheck->fetch();
    
    if (!$nota) {
        throw new Exception('La nota no existe');
    }

    // Eliminar la nota
    $queryDelete = "DELETE FROM historial_notas WHERE id_nota = ?";
    $stmtDelete = $con->prepare($queryDelete);
    $stmtDelete->execute([$id_nota]);

    $response['success'] = true;
    $response['message'] = 'Nota eliminada correctamente';
    $response['id_paciente'] = $nota['id_paciente']; // Para posibles redirecciones

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);
?>