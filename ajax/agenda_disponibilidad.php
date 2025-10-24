<?php
// ajax/agenda_disponibilidad.php
// Genera la grilla semanal (Lun-Vie) a partir de distribucion_personal
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

function ymd($d){ return date('Y-m-d', strtotime($d)); }
function mondayOf($ymd){
  $ts = strtotime($ymd);
  $dow = (int)date('N',$ts); // 1..7 (1=Lunes)
  return date('Y-m-d', strtotime("-".($dow-1)." day", $ts));
}
function labelSemanaRango($iniYmd){
  $ini = strtotime($iniYmd);
  $fin = strtotime("+4 day", $ini);
  // Si el server no tiene locale ES, usa date():
  return 'Del '.date('d M Y',$ini).' al '.date('d M Y',$fin);
}

try{
  if (!($con instanceof PDO)) throw new Exception('Sin conexión PDO');
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $vista       = strtolower(trim($_GET['vista'] ?? 'sucursal')); // 'medico' | 'sucursal'
  $medico_id   = (int)($_GET['medico_id'] ?? 0);
  $sucursal_id = (int)($_GET['sucursal_id'] ?? 0);
  $week_input  = $_GET['week_start'] ?? $_GET['fecha'] ?? date('Y-m-d');
  $week_start  = mondayOf(ymd($week_input));
  $week_end    = date('Y-m-d', strtotime('+4 day', strtotime($week_start))); // Lun..Vie

  // ==== HORAS VISIBLES (ajústalas si quieres) ====
  $TIMES = ['08:00','09:00','10:00','11:00'];
  // Ejemplo cada hora 07–19:
  // $TIMES = []; for($h=7;$h<=19;$h++) $TIMES[] = str_pad($h,2,'0',STR_PAD_LEFT).':00';

  // Días de la semana (Lun..Vie)
  $dias = [];
  $labelsES = ['Lunes','Martes','Miércoles','Jueves','Viernes'];
  for($i=0;$i<5;$i++){
    $d = date('Y-m-d', strtotime("+$i day", strtotime($week_start)));
    $dias[] = ['date'=>$d, 'label'=>$labelsES[$i]];
  }

  // === Turnos activos en el rango ===
  $params = [':d1'=>$week_start, ':d2'=>$week_end];
  $filtro = '';
  if ($vista==='sucursal' && $sucursal_id>0){ $filtro .= ' AND dp.id_sucursal = :s '; $params[':s'] = $sucursal_id; }
  if ($vista==='medico'   && $medico_id>0)  { $filtro .= ' AND dp.id_usuario  = :m '; $params[':m'] = $medico_id; }

  $sql = "SELECT dp.id_distribucion, dp.id_usuario, dp.id_sucursal, dp.fecha,
                 dp.hora_entrada, dp.hora_salida, dp.estado, dp.cupos,
                 COALESCE(u.nombre_mostrar,u.usuario) AS usuario_nom,
                 s.nombre AS sucursal_nom
            FROM distribucion_personal dp
            JOIN usuarios u   ON u.id = dp.id_usuario
            JOIN sucursales s ON s.id = dp.id_sucursal
           WHERE dp.fecha BETWEEN :d1 AND :d2
             AND (dp.estado = 1 OR dp.estado = '1' OR dp.estado = 'activo')
                 $filtro
        ORDER BY dp.fecha, s.nombre, u.nombre_mostrar";
  $st = $con->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Indexar por fecha
  $byDate = [];
  foreach ($rows as $r){ $byDate[$r['fecha']][] = $r; }

  // === Construcción de grilla (times x days) ===
  $grid = [];
  for($i=0;$i<count($TIMES);$i++){
    $horaSlot = $TIMES[$i];
    $grid[$i] = [];
    for($j=0;$j<count($dias);$j++){
      $day = $dias[$j]['date'];

      // Estado por defecto según la VISTA
      if ($vista==='medico' && $medico_id>0){
        // Por médico: si no hay turno, está Libre (disponible = verde)
        $cell = ['state'=>'disponible','text'=>'Libre','medico_id'=>$medico_id];
      } else {
        // Por sucursal: si no hay nadie, No asignado (rojo)
        $cell = ['state'=>'no_asignado','text'=>'No asignado','sucursal_id'=>($sucursal_id?:null)];
      }

      if (!empty($byDate[$day])){
        if ($vista==='medico' && $medico_id>0){
          // ¿El médico tiene turno y cubre la hora?
          foreach ($byDate[$day] as $r){
            if ((int)$r['id_usuario'] !== $medico_id) continue;
            $he = substr($r['hora_entrada']??'',0,5);
            $hs = substr($r['hora_salida'] ??'',0,5);
            // Inclusivo-exclusivo: he <= slot < hs
            if ($he!=='' && $hs!=='' && $he <= $horaSlot && $horaSlot < $hs){
              $cup = is_null($r['cupos']) ? null : (int)$r['cupos'];
              if ($cup !== null && $cup <= 0){
                // Sin cupos => ocupado (ámbar)
                $cell = [
                  'state'=>'ocupado',
                  'text'=>$r['sucursal_nom'],   // se muestra sucursal; el front puede mostrar “Ocupado”
                  'sub'=>'Sin cupos',
                  'medico_id'=>$r['id_usuario'],
                  'sucursal_id'=>$r['id_sucursal']
                ];
              } else {
                // Con cupos o sin campo cupos => en_sucursal (azul)
                $cell = [
                  'state'=>'en_sucursal',
                  'text'=>$r['sucursal_nom'],
                  'sub'=>($cup !== null ? ('Cupos '.$cup) : null),
                  'medico_id'=>$r['id_usuario'],
                  'sucursal_id'=>$r['id_sucursal']
                ];
              }
              break;
            }
          }
        } else { // vista por sucursal
          $nombres = [];
          $sumCupos = 0; $tieneCupos = false;
          foreach ($byDate[$day] as $r){
            if ($sucursal_id>0 && (int)$r['id_sucursal'] !== $sucursal_id) continue;
            $he = substr($r['hora_entrada']??'',0,5);
            $hs = substr($r['hora_salida'] ??'',0,5);
            if ($he!=='' && $hs!=='' && $he <= $horaSlot && $horaSlot < $hs){
              $nombres[] = $r['usuario_nom'];
              if (!is_null($r['cupos'])) { $tieneCupos = true; $sumCupos += (int)$r['cupos']; }
            }
          }
          if (!empty($nombres)){
            if ($tieneCupos && $sumCupos <= 0){
              // Todos sin cupos => ocupado (ámbar)
              $cell = [
                'state'=>'ocupado',
                'text'=>$nombres[0],
                'sub'=>(count($nombres)>1? (count($nombres)-1).' más' : null),
                'sucursal_id'=>$sucursal_id
              ];
            } else {
              // Hay al menos un cupo disponible o no manejamos cupos => en_sucursal (azul)
              $cell = [
                'state'=>'en_sucursal',
                'text'=>$nombres[0],
                'sub'=>(count($nombres)>1? (count($nombres)-1).' más' : null),
                'sucursal_id'=>$sucursal_id
              ];
            }
          }
        }
      }

      $grid[$i][$j] = $cell;
    }
  }

  echo json_encode([
    'days'       => $dias,
    'times'      => $TIMES,
    'grid'       => $grid,
    'week_label' => labelSemanaRango($week_start)
  ], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e){
  echo json_encode(['error'=>$e->getMessage(), 'days'=>[], 'times'=>[], 'grid'=>[]], JSON_UNESCAPED_UNICODE);
}
