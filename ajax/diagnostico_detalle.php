<?php
// ajax/diagnostico_detalle.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connection.php';

$out = ['success' => false];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        throw new Exception('Método no permitido');
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        throw new Exception('ID inválido');
    }

    // Intento #1: con columnas fecha y gravedad (si existen)
    try {
        $sql = "SELECT 
                    d.id,
                    d.id_paciente,
                    d.id_enfermedad,
                    d.id_medico,
                    DATE_FORMAT(d.fecha, '%Y-%m-%d') AS fecha,
                    d.sintomas,
                    d.observaciones,
                    d.gravedad
                FROM diagnosticos d
                WHERE d.id = ?";
        $q = $con->prepare($sql);
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Intento #2: esquema mínimo (por si tu tabla no tiene fecha/gravedad)
        $sql = "SELECT 
                    d.id,
                    d.id_paciente,
                    d.id_enfermedad,
                    d.id_medico,
                    '' AS fecha,
                    d.sintomas,
                    d.observaciones,
                    '' AS gravedad
                FROM diagnosticos d
                WHERE d.id = ?";
        $q = $con->prepare($sql);
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row) {
        http_response_code(404);
        throw new Exception('Diagnóstico no encontrado');
    }

    $out = $row;
    $out['success'] = true;

} catch (Throwable $e) {
    if (!http_response_code()) { http_response_code(400); }
    $out['message'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
