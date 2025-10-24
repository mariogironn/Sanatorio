<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$res=['success'=>false,'slots'=>[],'message'=>''];
try{
  $medico_id = (int)($_GET['medico_id'] ?? 0);
  $fecha     = $_GET['fecha'] ?? '';
  if($medico_id<=0 || !$fecha) throw new Exception('Parámetros inválidos');

  $dow = (int)date('N', strtotime($fecha)); // 1..7

  // 1) Horarios semanales vigentes
  $sql = "SELECT hora_inicio, hora_fin
          FROM horarios_medicos
          WHERE medico_id=? AND dia_semana=? AND estado='activo'
            AND (vigente_desde IS NULL OR vigente_desde <= ?)
            AND (vigente_hasta IS NULL OR vigente_hasta >= ?)";
  $st = $con->prepare($sql);
  $st->execute([$medico_id,$dow,$fecha,$fecha]);
  $horarios = $st->fetchAll(PDO::FETCH_ASSOC);

  // 2) Ausencias (bloqueos) del día (desde ausencias_medicos)
  $sqlA = "SELECT hora_desde AS hora_inicio, hora_hasta AS hora_fin
           FROM ausencias_medicos WHERE id_usuario_medico=? AND fecha_ausencia=?";
  $stA = $con->prepare($sqlA); $stA->execute([$medico_id,$fecha]);
  $bloqs = $stA->fetchAll(PDO::FETCH_ASSOC);

  // 3) (Opcional) Citas del día para pintar nombre de paciente si existen
  $citas = [];
  try{
    $hasTbl = $con->query("SHOW TABLES LIKE 'citas_medicas'")->rowCount() > 0;
    if($hasTbl){
      $cols = $con->query("SHOW COLUMNS FROM citas_medicas")->fetchAll(PDO::FETCH_COLUMN);
      $colM = in_array('medico_id',$cols)?'medico_id':(in_array('id_medico',$cols)?'id_medico':null);
      $colF = in_array('fecha',$cols)?'fecha':(in_array('fecha_cita',$cols)?'fecha_cita':null);
      $colH = in_array('hora',$cols)?'hora':(in_array('hora_cita',$cols)?'hora_cita':null);
      $colP = in_array('paciente_nombre',$cols)?'paciente_nombre':null; // si lo tienes
      if($colM && $colF && $colH){
        $sqlC = "SELECT $colH AS hora, ".($colP?"$colP AS paciente":"NULL AS paciente")."
                 FROM citas_medicas WHERE $colM=? AND $colF=?";
        $stC = $con->prepare($sqlC); $stC->execute([$medico_id,$fecha]);
        $citas = $stC->fetchAll(PDO::FETCH_ASSOC);
      }
    }
  }catch(Throwable $e){ /* opcional, sin ruido */ }

  // 4) Construcción de slots (cada 30 min)
  function toMin($t){ list($h,$m)=explode(':',$t); return $h*60+$m; }
  function stepSlots($ini,$fin,$step=30){
    $out=[]; for($t=toMin($ini); $t<toMin($fin); $t+=$step){ $out[] = sprintf('%02d:%02d', floor($t/60), $t%60); } return $out;
  }

  $slots=[];
  foreach($horarios as $h){
    foreach(stepSlots($h['hora_inicio'],$h['hora_fin']) as $H){
      $slots[$H] = ['hora'=>$H,'disponible'=>true];
    }
  }

  // marcar bloqueos
  foreach($bloqs as $b){
    foreach(stepSlots($b['hora_inicio'],$b['hora_fin']) as $H){
      if(isset($slots[$H])) $slots[$H]['disponible']=false;
    }
  }

  // marcar citas si hay
  foreach($citas as $c){
    $H = substr($c['hora'],0,5);
    if(isset($slots[$H])){
      $slots[$H]['disponible']=false;
      if(!empty($c['paciente'])) $slots[$H]['paciente']=$c['paciente'];
    }else{
      // cita fuera del rango semanal -> igual la mostramos
      $slots[$H]=['hora'=>$H,'disponible'=>false,'paciente'=>$c['paciente']??null];
    }
  }

  ksort($slots);
  $res['slots']=array_values($slots);
  $res['success']=true;
}catch(Throwable $e){ $res['message']=$e->getMessage(); }
echo json_encode($res, JSON_UNESCAPED_UNICODE);
