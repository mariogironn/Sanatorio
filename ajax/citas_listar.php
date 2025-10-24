<?php
session_start();
require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    // Obtener filtros
    $estado = $_POST['estado'] ?? 'todos';
    $fecha = $_POST['fecha'] ?? '';
    $medico = $_POST['medico'] ?? 'todos';

    // Construir consulta base
    $query = "SELECT c.*, p.nombre as nombre_paciente, u.nombre_mostrar as nombre_medico 
              FROM citas_medicas c 
              INNER JOIN pacientes p ON c.paciente_id = p.id_paciente 
              INNER JOIN usuarios u ON c.medico_id = u.id 
              WHERE 1=1";

    $params = [];

    // Aplicar filtros
    if ($estado !== 'todos') {
        $query .= " AND c.estado = ?";
        $params[] = $estado;
    }
    
    if (!empty($fecha)) {
        $query .= " AND c.fecha = ?";
        $params[] = $fecha;
    }
    
    if ($medico !== 'todos') {
        $query .= " AND c.medico_id = ?";
        $params[] = $medico;
    }

    $query .= " ORDER BY c.fecha DESC, c.hora DESC";

    $stmt = $con->prepare($query);
    $stmt->execute($params);
    
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $citas;
    
} catch (PDOException $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>