<?php
// ajax/tratamiento_detalle.php
// Devuelve el tratamiento para prellenar el modal de edición.
// Respuesta esperada por el front:
// { ok:true, id, id_diagnostico, id_medico, fecha_inicio, duracion_estimada, estado, instrucciones, meds:[], medico }

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$res = ['ok' => false];

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // t.id_medico referencia a medicos.id_medico; usuarios.id = medicos.id_medico
    $sql = "
        SELECT
            t.id,
            t.id_diagnostico,
            t.id_medico,
            t.fecha_inicio,
            t.duracion           AS duracion_estimada,
            t.estado,
            t.instrucciones,
            u.nombre_mostrar     AS medico,
            GROUP_CONCAT(tm.id_medicamento) AS meds
        FROM tratamientos t
        LEFT JOIN tratamiento_medicamentos tm ON tm.id_tratamiento = t.id
        LEFT JOIN medicos md  ON md.id_medico = t.id_medico
        LEFT JOIN usuarios u  ON u.id = md.id_medico
        WHERE t.id = :id
        GROUP BY t.id, t.id_diagnostico, t.id_medico, t.fecha_inicio, t.duracion, t.estado, t.instrucciones, u.nombre_mostrar
        LIMIT 1
    ";

    $st = $con->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Tratamiento no encontrado');
    }

    // Normalizar meds como arreglo de enteros
    $meds = [];
    if (!empty($row['meds'])) {
        foreach (explode(',', $row['meds']) as $m) {
            $m = (int)trim($m);
            if ($m > 0) { $meds[] = $m; }
        }
    }
    $row['meds'] = $meds;

    $res = array_merge(['ok' => true], $row);

} catch (Throwable $e) {
    $res = ['ok' => false, 'error' => $e->getMessage()];
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
