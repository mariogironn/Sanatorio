<?php
session_start();
require_once '../config/connection.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $query = "SELECT id_paciente, nombre FROM pacientes WHERE estado = 'activo' ORDER BY nombre";
    $stmt = $con->query($query);
    
    $options = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $options .= "<option value='{$row['id_paciente']}'>{$row['nombre']}</option>";
    }
    
    echo $options;
} catch (PDOException $e) {
    echo '<option value="">Error al cargar pacientes</option>';
}
?>