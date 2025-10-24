<?php
// ajax/buscar_historial_paciente.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once '../config/connection.php';

$out = ['success' => false, 'message' => '', 'rows' => [], 'paciente_info' => null];

try {
    $paciente_id = (int)($_GET['paciente_id'] ?? 0);
    $sucursal_filtro = trim($_GET['sucursal_id'] ?? '');
    $fecha_desde = trim($_GET['fecha_desde'] ?? '');
    $fecha_hasta = trim($_GET['fecha_hasta'] ?? '');

    if ($paciente_id <= 0) {
        throw new Exception('Selecciona un paciente');
    }

    // Obtener información del paciente
    $queryPaciente = "SELECT p.id_paciente, p.nombre, p.dpi, p.telefono, 
                             p.direccion, p.genero, p.tipo_sangre
                      FROM pacientes p 
                      WHERE p.id_paciente = ? AND p.estado = 'activo'";
    
    $stmtPaciente = $con->prepare($queryPaciente);
    $stmtPaciente->execute([$paciente_id]);
    $paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        throw new Exception('Paciente no encontrado');
    }

    // Construir consulta para el historial - CORREGIDA
    $query = "
        SELECT 
            d.id_detalle,
            p.id_prescripcion,
            p.fecha_visita,
            p.enfermedad,
            p.sucursal,
            m.id AS id_medicamento,  -- CORREGIDO: id_medicamento en lugar de id_medicina
            m.nombre_medicamento AS medicina,
            d.cantidad,
            d.dosis,
            d.empaque AS paquete,
            pat.nombre AS nombre_paciente
        FROM detalle_prescripciones d
        JOIN prescripciones p ON p.id_prescripcion = d.id_prescripcion
        JOIN medicamentos m ON m.id = d.id_medicamento  -- CORREGIDO: id_medicamento
        JOIN pacientes pat ON pat.id_paciente = p.id_paciente
        WHERE p.id_paciente = ?
          AND p.estado != 'cancelada'
    ";

    $params = [$paciente_id];

    // Filtro por sucursal (usando el campo sucursal como texto)
    if (!empty($sucursal_filtro) && $sucursal_filtro !== '') {
        $query .= " AND p.sucursal = ?";
        $params[] = $sucursal_filtro;
    }

    // Filtro por fechas
    if (!empty($fecha_desde)) {
        $query .= " AND p.fecha_visita >= ?";
        $params[] = $fecha_desde;
    }

    if (!empty($fecha_hasta)) {
        $query .= " AND p.fecha_visita <= ?";
        $params[] = $fecha_hasta;
    }

    $query .= " ORDER BY p.fecha_visita DESC, d.id_detalle DESC";

    $stmt = $con->prepare($query);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas del paciente
    $queryStats = "
        SELECT 
            COUNT(DISTINCT p.id_prescripcion) as total_visitas,
            COUNT(DISTINCT p.enfermedad) as enfermedades_diferentes,
            MIN(p.fecha_visita) as primera_visita,
            MAX(p.fecha_visita) as ultima_visita
        FROM prescripciones p
        WHERE p.id_paciente = ?
          AND p.estado != 'cancelada'
    ";
    
    $stmtStats = $con->prepare($queryStats);
    $stmtStats->execute([$paciente_id]);
    $estadisticas = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Formatear los datos para la respuesta
    $rows = [];
    foreach ($registros as $reg) {
        $rows[] = [
            'detalle_id' => (int)$reg['id_detalle'],
            'prescripcion_id' => (int)$reg['id_prescripcion'],
            'n_serie' => str_pad($reg['id_prescripcion'], 3, '0', STR_PAD_LEFT),
            'fecha_visita' => $reg['fecha_visita'],
            'enfermedad' => $reg['enfermedad'],
            'id_medicina' => (int)$reg['id_medicamento'],  // Mapeado para el frontend
            'medicina' => $reg['medicina'],
            'cantidad' => (int)$reg['cantidad'],
            'dosis' => $reg['dosis'],
            'paquete' => $reg['paquete'] ?: '-',
            'sucursal' => $reg['sucursal'] ?: 'No especificada',
            'nombre_paciente' => $reg['nombre_paciente'],
            'sucursal_principal' => $reg['sucursal'] ?: 'No especificada',
            'primera_visita' => $estadisticas['primera_visita'] ?? null,
            'ultima_visita' => $estadisticas['ultima_visita'] ?? null
        ];
    }

    $out['success'] = true;
    $out['rows'] = $rows;
    $out['paciente_info'] = [
        'nombre' => $paciente['nombre'],
        'sucursal' => $registros[0]['sucursal'] ?? 'No especificada',
        'primera_visita' => $estadisticas['primera_visita'] ?? null,
        'ultima_visita' => $estadisticas['ultima_visita'] ?? null
    ];

    if (empty($rows)) {
        $out['message'] = 'No se encontraron registros para este paciente';
    }

} catch (Exception $e) {
    http_response_code(400);
    $out['message'] = $e->getMessage();
} catch (Throwable $e) {
    http_response_code(500);
    $out['message'] = 'Error interno del servidor: ' . $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);