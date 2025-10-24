<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $p = [
    ':medico' => (int)$_POST['medico_id'],
    ':fecha'  => $_POST['fecha'],
    ':ini'    => $_POST['hora_inicio'],
    ':fin'    => $_POST['hora_fin'],
    ':motivo' => $_POST['motivo'] ?? '',
    ':uid'    => (int)($_SESSION['user_id'] ?? 0),
  ];

  $sql = "INSERT INTO ausencias_medicos
          (id_usuario_medico, fecha_ausencia, hora_desde, hora_hasta, motivo, created_by, created_at)
          VALUES (:medico, :fecha, :ini, :fin, :motivo, :uid, NOW())";
  $con->prepare($sql)->execute($p);

  echo json_encode(['success'=>true,'message'=>'Ausencia registrada']);
} catch(Exception $e){
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
