<?php
session_start();
require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

try {
    $cita_id = $_POST['id_cita'] ?? null;
    
    if (!$cita_id) {
        throw new Exception('ID de cita no proporcionado');
    }

    $query = "DELETE FROM citas_medicas WHERE id_cita = ?";
    $stmt = $con->prepare($query);
    $stmt->execute([$cita_id]);
    
    $response['success'] = true;
    $response['message'] = 'Cita eliminada correctamente';
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>