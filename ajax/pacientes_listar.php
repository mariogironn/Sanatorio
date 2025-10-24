<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$out = ['success'=>false,'data'=>[],'message'=>''];
try{
  // Ajusta nombres de tabla/columnas a tus reales
  $sql = "SELECT id AS id_paciente,
                 CONCAT(COALESCE(nombres,''),' ',COALESCE(apellidos,'')) AS nombre_completo
          FROM pacientes
          WHERE estado IN ('activo','1') OR estado IS NULL
          ORDER BY nombre_completo";
  $stmt = $con->query($sql);
  $out['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $out['success'] = true;
}catch(Throwable $e){ $out['message']=$e->getMessage(); }
echo json_encode($out, JSON_UNESCAPED_UNICODE);
