<?php
// ajax/cargar_medicos_clinicos.php
// Devuelve la lista de usuarios con rol clínico (médico/doctor/enfermero/a)
// Formato: { success:true, items:[{id, text}], sugerido:<id|null> }

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connection.php';

$out = ['success' => false, 'items' => [], 'sugerido' => null];

try {
    // Roles clínicos aceptados (en BD tienes 'medico' y 'Doctor')
    $rolesClinicos = ['medico','doctor','enfermero','enfermera'];

    // Query: usuarios ACTIVO + alguno de los roles clínicos
    $sql = "
        SELECT DISTINCT u.id, u.nombre_mostrar AS text
        FROM usuarios u
        INNER JOIN usuario_rol ur  ON ur.id_usuario = u.id
        INNER JOIN roles r         ON r.id_rol = ur.id_rol
        WHERE u.estado = 'ACTIVO'
          AND LOWER(r.nombre) IN (" . implode(',', array_fill(0, count($rolesClinicos), '?')) . ")
        ORDER BY u.nombre_mostrar
    ";
    $stmt = $con->prepare($sql);
    foreach ($rolesClinicos as $k => $rol) {
        $stmt->bindValue($k + 1, strtolower($rol));
    }
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out['items'] = $items;
    $out['success'] = true;

    // Sugerir como seleccionado al usuario logueado si él mismo es clínico
    $sugerido = null;
    if (!empty($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $sqlSug = "
            SELECT 1
            FROM usuarios u
            INNER JOIN usuario_rol ur ON ur.id_usuario = u.id
            INNER JOIN roles r        ON r.id_rol = ur.id_rol
            WHERE u.id = ?
              AND u.estado = 'ACTIVO'
              AND LOWER(r.nombre) IN (" . implode(',', array_fill(0, count($rolesClinicos), '?')) . ")
            LIMIT 1
        ";
        $st = $con->prepare($sqlSug);
        $st->bindValue(1, $uid, PDO::PARAM_INT);
        $i = 2;
        foreach ($rolesClinicos as $rol) { $st->bindValue($i++, strtolower($rol)); }
        $st->execute();
        if ($st->fetchColumn()) {
            $sugerido = $uid;
        }
    }
    $out['sugerido'] = $sugerido;

} catch (Throwable $e) {
    // No exponemos el error al cliente para no romper el modal;
    // simplemente devolvemos lista vacía y success=false
    $out['success'] = false;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
