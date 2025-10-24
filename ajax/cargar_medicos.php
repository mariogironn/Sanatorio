<?php
session_start();
require_once '../config/connection.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $query = "SELECT u.id, u.nombre_mostrar 
              FROM usuarios u 
              INNER JOIN usuario_rol ur ON u.id = ur.id_usuario 
              INNER JOIN roles r ON ur.id_rol = r.id_rol 
              WHERE r.nombre = 'medico' AND u.estado = 'ACTIVO' 
              ORDER BY u.nombre_mostrar";
    $stmt = $con->query($query);
    
    $options = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $options .= "<option value='{$row['id']}'>{$row['nombre_mostrar']}</option>";
    }
    
    echo $options;
} catch (PDOException $e) {
    echo '<option value="">Error al cargar médicos</option>';
}
?>