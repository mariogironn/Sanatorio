<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';
header('Content-Type: text/html; charset=UTF-8');

try {
    // Mostrar TODOS los clínicos: médico, doctor, enfermero/a
    $sql = "
        SELECT DISTINCT
               u.id,
               COALESCE(u.nombre_mostrar, u.usuario) AS nombre
          FROM usuarios u
          JOIN usuario_rol ur ON ur.id_usuario = u.id
          JOIN roles r        ON r.id_rol = ur.id_rol
         WHERE r.nombre IN ('medico','doctor','enfermero','enfermera')
           AND (u.estado = 1 OR UPPER(u.estado) = 'ACTIVO')
         ORDER BY nombre ASC
    ";

    $stmt = $con->query($sql);

    $out = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id  = (int)$row['id'];
        $nom = htmlspecialchars($row['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $out .= "<option value=\"{$id}\">{$nom}</option>";
    }

    echo $out !== '' ? $out : '<option value="">(Sin médicos clínicos)</option>';

} catch (Throwable $e) {
    echo '<option value="">Error al cargar médicos</option>';
}
