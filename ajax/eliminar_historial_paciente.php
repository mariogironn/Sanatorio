<?php
// ajax/eliminar_historial_paciente.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once '../config/connection.php';

$out = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido');
    }

    $detalle_id = (int)($_POST['detalle_id'] ?? 0);
    
    if ($detalle_id <= 0) {
        throw new Exception('ID de detalle inválido');
    }

    // Verificar que existe el detalle
    $check = $con->prepare("
        SELECT d.id_detalle, p.id_prescripcion 
        FROM detalle_prescripciones d 
        JOIN prescripciones p ON p.id_prescripcion = d.id_prescripcion 
        WHERE d.id_detalle = ?
    ");
    $check->execute([$detalle_id]);
    $registro = $check->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        throw new Exception('El registro no existe');
    }

    $con->beginTransaction();

    // Eliminar el detalle de la prescripción
    $delete = $con->prepare("DELETE FROM detalle_prescripciones WHERE id_detalle = ?");
    $delete->execute([$detalle_id]);

    // Verificar si la prescripción queda sin detalles
    $checkPrescripcion = $con->prepare("
        SELECT COUNT(*) FROM detalle_prescripciones WHERE id_prescripcion = ?
    ");
    $checkPrescripcion->execute([$registro['id_prescripcion']]);
    $tieneDetalles = $checkPrescripcion->fetchColumn();

    // Si no quedan detalles, eliminar la prescripción también
    if ($tieneDetalles == 0) {
        $deletePrescripcion = $con->prepare("DELETE FROM prescripciones WHERE id_prescripcion = ?");
        $deletePrescripcion->execute([$registro['id_prescripcion']]);
    }

    $con->commit();
    
    $out['success'] = true;
    $out['message'] = 'Registro eliminado correctamente';

} catch (Exception $e) {
    if ($con && $con->inTransaction()) {
        $con->rollBack();
    }
    http_response_code(400);
    $out['message'] = $e->getMessage();
} catch (Throwable $e) {
    if ($con && $con->inTransaction()) {
        $con->rollBack();
    }
    http_response_code(500);
    $out['message'] = 'Error interno del servidor';
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);