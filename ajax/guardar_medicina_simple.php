<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../config/connection.php';

$out = ['success'=>false,'message'=>'Error desconocido'];

try{
  if($_SERVER['REQUEST_METHOD']!=='POST'){ throw new Exception('Método no permitido'); }

  $id      = (int)($_POST['id'] ?? 0);               // hidden #medicina_id
  $nombre  = trim($_POST['nombre_medicamento'] ?? '');
  $pact    = trim($_POST['principio_activo'] ?? '');
  $stock   = (int)($_POST['stock_actual'] ?? 0);
  $smin    = (int)($_POST['stock_minimo'] ?? 0);

  // meta opcional (inputs del modal; si no existen, llegan vacíos)
  $pres    = trim($_POST['presentacion'] ?? '');
  $lab     = trim($_POST['laboratorio'] ?? '');
  $cat     = trim($_POST['categoria'] ?? '');
  $desc    = trim($_POST['descripcion'] ?? '');

  if($nombre==='') throw new Exception('El nombre comercial es requerido');
  if($pact==='')   throw new Exception('El principio activo es requerido');

  $uid = (int)($_SESSION['user_id'] ?? 0);
  if($uid<=0) throw new Exception('Sesión inválida');

  $con->beginTransaction();

  if($id>0){
    // actualizar
    $sql = "UPDATE medicamentos
               SET nombre_medicamento=:n, principio_activo=:pa,
                   stock_actual=:sa, stock_minimo=:sm
             WHERE id=:id";
    $st = $con->prepare($sql);
    $st->execute([':n'=>$nombre, ':pa'=>$pact, ':sa'=>$stock, ':sm'=>$smin, ':id'=>$id]);
    $targetId = $id;
  }else{
    // crear
    // evitar duplicado simple por nombre
    $chk = $con->prepare("SELECT id FROM medicamentos WHERE nombre_medicamento=:n LIMIT 1");
    $chk->execute([':n'=>$nombre]);
    if($chk->fetch()) throw new Exception('Ya existe una medicina con ese nombre');

    $sql = "INSERT INTO medicamentos
              (nombre_medicamento, principio_activo, stock_actual, stock_minimo, tipo_medicamento, estado)
            VALUES
              (:n, :pa, :sa, :sm, 'no_controlado', 'activo')";
    $st = $con->prepare($sql);
    $st->execute([':n'=>$nombre, ':pa'=>$pact, ':sa'=>$stock, ':sm'=>$smin]);
    $targetId = (int)$con->lastInsertId();
  }

  // upsert en medicamentos_meta con detección de FK
  $cands = ['med_id','medicamento_id','id_medicamento','idMedicamento','medicina_id','id_medicina'];
  $place = implode(',', array_fill(0,count($cands),'?'));
  $q = $con->prepare(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='medicamentos_meta'
        AND COLUMN_NAME IN ($place) LIMIT 1");
  $q->execute($cands);
  $fkcol = $q->fetchColumn();

  if($fkcol){
    $sel = $con->prepare("SELECT 1 FROM medicamentos_meta WHERE $fkcol=:id LIMIT 1");
    $sel->execute([':id'=>$targetId]);
    if($sel->fetch()){
      $up = $con->prepare("UPDATE medicamentos_meta
                              SET presentacion=:pr, laboratorio=:la, categoria=:ca, descripcion=:de
                            WHERE $fkcol=:id");
      $up->execute([':pr'=>$pres,':la'=>$lab,':ca'=>$cat,':de'=>$desc,':id'=>$targetId]);
    }else{
      $ins = $con->prepare("INSERT INTO medicamentos_meta
                              ($fkcol,presentacion,laboratorio,categoria,descripcion)
                            VALUES (:id,:pr,:la,:ca,:de)");
      $ins->execute([':id'=>$targetId,':pr'=>$pres,':la'=>$lab,':ca'=>$cat,':de'=>$desc]);
    }
  }

  // datos para refrescar la fila
  $pacAct = (int)$con->prepare("
      SELECT COUNT(*) FROM paciente_medicinas
       WHERE medicina_id=:id AND estado='activo'
    ")->execute([':id'=>$targetId]) ?: 0;
  $p = $con->prepare("
      SELECT m.id, m.nombre_medicamento, m.principio_activo, m.stock_actual, m.stock_minimo,
             COALESCE(mm.presentacion,'') AS presentacion,
             COALESCE(mm.laboratorio,'')  AS laboratorio,
             COALESCE(mm.categoria,'')    AS categoria,
             COALESCE(mm.descripcion,'')  AS descripcion
        FROM medicamentos m
   LEFT JOIN medicamentos_meta mm ON mm.$fkcol = m.id
       WHERE m.id=:id
       LIMIT 1");
  $p->execute([':id'=>$targetId]);
  $row = $p->fetch(PDO::FETCH_ASSOC);

  $con->commit();

  $out = [
    'success'=>true,
    'message'=> ($id>0?'Medicina actualizada':'Medicina creada'),
    'id'=>$targetId,
    'data'=> $row + ['pacientes_activos'=>$pacAct]
  ];

}catch(Throwable $e){
  if($con->inTransaction()) $con->rollBack();
  $out['message'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
