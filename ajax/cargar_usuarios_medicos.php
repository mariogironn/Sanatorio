<?php
session_start();
require_once '../config/connection.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // Solo usuarios ACTIVOS con roles clínicos (médico/doctor/enfermero/enfermera)
    $sql = "
        SELECT DISTINCT u.id, u.nombre_mostrar
        FROM usuarios u
        JOIN usuario_rol ur ON ur.id_usuario = u.id
        JOIN roles r        ON r.id_rol      = ur.id_rol
        WHERE UPPER(u.estado) = 'ACTIVO'
          AND LOWER(r.nombre) IN ('medico','doctor','enfermero','enfermera')
        ORDER BY u.nombre_mostrar ASC
    ";

    $stmt = $con->query($sql);

    $options = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id  = (int)$row['id'];
        $txt = htmlspecialchars($row['nombre_mostrar'] ?? '', ENT_QUOTES, 'UTF-8');
        $options .= "<option value=\"{$id}\">{$txt}</option>";
    }

    echo $options;

} catch (PDOException $e) {
    echo '<option value="">Error al cargar usuarios</option>';
}
