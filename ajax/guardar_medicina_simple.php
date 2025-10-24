<?php
// ajax/guardar_medicina_simple.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';
require_once '../common_service/common_functions.php';
require_once '../common_service/auditoria_service.php';

$res = ['success'=>false,'message'=>'Error desconocido'];

try{
  if($_SERVER['REQUEST_METHOD']!=='POST'){ throw new Exception('Método no permitido'); }

  $nombre  = trim($_POST['nombre_medicamento'] ?? '');
  $pact    = trim($_POST['principio_activo'] ?? '');
  $stock   = (int)($_POST['stock_actual'] ?? 0);
  $smin    = (int)($_POST['stock_minimo'] ?? 0);

  if($nombre===''){ throw new Exception('El nombre comercial es requerido'); }
  if($pact===''){ throw new Exception('El principio activo es requerido'); }

  // evitar duplicado simple por nombre comercial
  $chk = $con->prepare("SELECT id FROM medicamentos WHERE nombre_medicamento = :n LIMIT 1");
  $chk->execute([':n'=>$nombre]);
  if($chk->fetch()){ throw new Exception('Ya existe una medicina con ese nombre.'); }

  $uid = (int)($_SESSION['user_id'] ?? 0);
  if($uid<=0){ throw new Exception('Sesión inválida.'); }

  $con->beginTransaction();

  $sql = "INSERT INTO medicamentos
            (nombre_medicamento, principio_activo, stock_actual, stock_minimo, tipo_medicamento, estado)
          VALUES
            (:n, :pa, :sa, :sm, 'no_controlado', 'activo')";
  $st = $con->prepare($sql);
  $st->execute([
    ':n'=>$nombre, ':pa'=>$pact, ':sa'=>$stock, ':sm'=>$smin
  ]);

  $newId = (int)$con->lastInsertId();

  // auditoría
  try{
    $after = [
      'id'=>$newId,
      'nombre_medicamento'=>$nombre,
      'principio_activo'=>$pact,
      'stock_actual'=>$stock,
      'stock_minimo'=>$smin,
      'tipo_medicamento'=>'no_controlado',
      'estado'=>'activo'
    ];
    audit_create($con, 'Medicinas', 'medicamentos', $newId, $after, 'activo');
  }catch(Throwable $a){ error_log('AUD new med: '.$a->getMessage()); }

  $con->commit();

  $res = ['success'=>true,'message'=>'Medicina creada','id'=>$newId];

}catch(Throwable $e){
  if($con->inTransaction()) $con->rollBack();
  $res['message'] = $e->getMessage();
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
