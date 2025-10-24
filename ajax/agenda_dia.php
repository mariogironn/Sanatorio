<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once '../config/connection.php';
header('Content-Type: application/json; charset=utf-8');

$out = ['success'=>false, 'agenda'=>[], 'extras'=>[], 'message'=>''];

try {
  $medico_id = (int)($_GET['medico_id'] ?? 0);
  $fecha     = $_GET['fecha'] ?? '';
  if ($medico_id <= 0 || !$fecha) throw new Exception('Parámetros inválidos');

  $dow = (int)(new DateTime($fecha))->format('N'); // 1..7

  /* ===== Horarios del día ===== */
  $qH = $con->prepare("
    SELECT id_horario, hora_inicio, hora_fin, intervalo_minutos
    FROM horarios_medicos
    WHERE medico_id = ? AND dia_semana = ? AND estado='activo'
      AND (vigente_desde IS NULL OR vigente_desde <= ?)
      AND (vigente_hasta IS NULL OR vigente_hasta >= ?)
  ");
  $qH->execute([$medico_id, $dow, $fecha, $fecha]);
  $horarios = $qH->fetchAll(PDO::FETCH_ASSOC);

  /* ===== Bloqueos del día ===== */
  $qB = $con->prepare("SELECT hora_inicio, hora_fin FROM horarios_bloqueos WHERE medico_id=? AND fecha=?");
  $qB->execute([$medico_id, $fecha]);
  $bloqs = $qB->fetchAll(PDO::FETCH_ASSOC);

  /* ===== Citas del día (detección flexible de columnas) ===== */
  $citas = [];
  if ($con->query("SHOW TABLES LIKE 'citas_medicas'")->rowCount() > 0) {
    $cols = $con->query("SHOW COLUMNS FROM citas_medicas")->fetchAll(PDO::FETCH_COLUMN);
    // columnas más comunes en tus módulos
    $colM = in_array('medico_id',$cols) ? 'medico_id' : (in_array('id_medico',$cols) ? 'id_medico' : (in_array('doctor_id',$cols) ? 'doctor_id' : null));
    $colP = in_array('paciente_id',$cols) ? 'paciente_id' : (in_array('id_paciente',$cols) ? 'id_paciente' : null);
    $colF = in_array('fecha_cita',$cols) ? 'fecha_cita' : (in_array('fecha',$cols) ? 'fecha' : null);
    $colH = in_array('hora_cita',$cols)  ? 'hora_cita'  : (in_array('hora',$cols)  ? 'hora'  : null);
    $colE = in_array('estado',$cols)     ? 'estado'     : (in_array('estatus',$cols) ? 'estatus' : null);

    if ($colM && $colP && $colF && $colH) {
      // nombre del paciente (nombres+apellidos o nombre)
      $pacCols = $con->query("SHOW COLUMNS FROM pacientes")->fetchAll(PDO::FETCH_COLUMN);
      $np = "COALESCE(CONCAT(p.nombres,' ',p.apellidos), p.nombre, p.nombre_mostrar, '')";
      if (!in_array('nombres',$pacCols) && in_array('nombre',$pacCols)) $np = "COALESCE(p.nombre, '')";

      $sqlC = "
        SELECT TIME_FORMAT(c.$colH, '%H:%i') AS hora,
               $np AS paciente,
               ".($colE ? "c.$colE" : "'pendiente'")." AS estado
        FROM citas_medicas c
        JOIN pacientes p ON p.id = c.$colP OR p.id_paciente = c.$colP
        WHERE c.$colM = :m AND c.$colF = :f
      ";
      $qc = $con->prepare($sqlC);
      $qc->execute([':m'=>$medico_id, ':f'=>$fecha]);
      $citas = $qc->fetchAll(PDO::FETCH_ASSOC);
    }
  }

  // indexar citas por hora
  $mapCitas = [];
  foreach ($citas as $c) $mapCitas[substr($c['hora'],0,5)] = $c;

  /* ===== Generar slots según horarios ===== */
  $agenda = [];
  foreach ($horarios as $h) {
    $ini  = new DateTime("$fecha ".$h['hora_inicio']);
    $fin  = new DateTime("$fecha ".$h['hora_fin']);
    $step = max(5, (int)$h['intervalo_minutos']);

    for ($t=$ini; $t < $fin; $t=(clone $t)->modify("+$step minutes")) {
      $hora = $t->format('H:i');
      $estado = 'Libre';
      $paciente = '';

      // Bloqueo
      foreach ($bloqs as $b) {
        $bi = new DateTime("$fecha ".$b['hora_inicio']);
        $bf = new DateTime("$fecha ".$b['hora_fin']);
        if ($t >= $bi && $t < $bf) { $estado='Bloqueo'; break; }
      }

      // Cita en slot
      if ($estado==='Libre' && isset($mapCitas[$hora])) {
        $estado = 'Reservado';
        $paciente = $mapCitas[$hora]['paciente'] ?? '';
      }

      $agenda[] = ['hora'=>$hora, 'paciente'=>$paciente, 'estado'=>$estado];
    }
  }

  // ===== Citas fuera de horario (para mostrarlas igual) =====
  $horasAgenda = array_column($agenda, 'hora');
  $extras = [];
  foreach ($citas as $c) {
    $h = substr($c['hora'],0,5);
    if (!in_array($h, $horasAgenda, true)) {
      $extras[] = ['hora'=>$h, 'paciente'=>$c['paciente'] ?? '', 'estado'=>'Fuera de horario'];
    }
  }

  // ordenar
  usort($agenda, fn($a,$b)=>strcmp($a['hora'],$b['hora']));
  usort($extras, fn($a,$b)=>strcmp($a['hora'],$b['hora']));

  $out['success']=true;
  $out['agenda']=$agenda;
  $out['extras']=$extras;
} catch (Throwable $e) {
  $out['message']=$e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
