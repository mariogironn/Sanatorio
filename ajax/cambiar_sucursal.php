<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/plain; charset=UTF-8');

$sid = (int)($_POST['sucursal_id'] ?? 0);
if ($sid <= 0) { echo 'Sucursal inválida'; exit; }

// Por compatibilidad mantenemos ambas claves en sesión
$_SESSION['sucursal_activa']    = $sid;
$_SESSION['id_sucursal_activa'] = $sid;

echo 'OK';
