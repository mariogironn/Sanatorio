<?php
session_start();
require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    $cita_id = $_POST['id_cita'] ?? null;
    
    if (!$cita_id) {
        throw new Exception('ID de cita no proporcionado');
    }

    $query = "SELECT * FROM citas_medicas WHERE id_cita = ?";
    $stmt = $con->prepare($query);
    $stmt->execute([$cita_id]);
    
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cita) {
        $response['success'] = true;
        $response['data'] = $cita;
    } else {
        throw new Exception('Cita no encontrada');
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>