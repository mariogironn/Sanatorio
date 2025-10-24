<?php
session_start();
require_once '../config/connection.php';
header('Content-Type: text/html; charset=utf-8');

try {
  $sql = "
    SELECT m.id_medico, u.nombre_mostrar, m.colegiado
    FROM medicos m
    JOIN usuarios u      ON u.id = m.usuario_id
    JOIN usuario_rol ur  ON ur.id_usuario = u.id
    JOIN roles r         ON r.id_rol      = ur.id_rol
    WHERE LOWER(r.nombre) IN ('medico','doctor','enfermero','enfermera')
    ORDER BY u.nombre_mostrar ASC
  ";
  $stmt = $con->query($sql);
  $opts = '';
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id  = (int)$row['id_medico'];
    $nm  = htmlspecialchars($row['nombre_mostrar'] ?? '', ENT_QUOTES, 'UTF-8');
    $col = htmlspecialchars($row['colegiado'] ?? '', ENT_QUOTES, 'UTF-8');
    $txt = $nm.($col ? " - Colegiado: {$col}" : "");
    $opts .= "<option value=\"{$id}\">{$txt}</option>";
  }
  echo $opts;
} catch (Throwable $e){ echo '<option value="">Error</option>'; }
