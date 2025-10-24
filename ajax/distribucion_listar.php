<?php
// ajax/distribucion_listar.php
// Lista de personal programado (DP -> AP -> Disponibles) + conteo de pacientes por médico
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

function estado_badge($estado){
  $s = strtolower(trim((string)$estado));
  if ($s==='' || $s==='activo' || $s==='1' || $s==='si' || $s==='true') return '<span class="badge badge-success badge-estado">Activo</span>';
  if ($s==='en pausa' || $s==='pausa') return '<span class="badge badge-warning badge-estado">En pausa</span>';
  return '<span class="badge badge-secondary badge-estado">Inactivo</span>';
}
function normalizar_fecha($f){
  $f = trim((string)$f);
  if ($f === '') return date('Y-m-d');
  if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $f)) { [$d,$m,$y] = explode('/', $f); return "$y-$m-$d"; }
  if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $f)) return $f;
  return date('Y-m-d');
}
function rol_icon_class($rol){
  $n = strtolower((string)$rol);
  if (strpos($n,'médico')!==false || strpos($n,'medico')!==false) return 'fas fa-user-md';
  if (strpos($n,'recepcion')!==false) return 'fas fa-concierge-bell';
  if (strpos($n,'enfermer')!==false) return 'fas fa-briefcase-medical';
  if (strpos($n,'admin')!==false) return 'fas fa-user-shield';
  return 'fas fa-id-badge';
}
/** Nombre del día (es-ES) desde 'YYYY-MM-DD' */
function nombre_dia_es($ymd){
  $ymd = trim((string)$ymd);
  $ts = strtotime($ymd);
  if (!$ts) return '—';
  $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  return $dias[(int)date('w', $ts)];
}

try{
  if (!($con instanceof PDO)) throw new Exception('Sin conexión PDO');
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $userId  = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
  $sucReq  = isset($_REQUEST['sucursal_id']) ? (int)$_REQUEST['sucursal_id']
           : (isset($_REQUEST['id_sucursal']) ? (int)$_REQUEST['id_sucursal'] : 0);
  $fecha   = normalizar_fecha($_REQUEST['fecha'] ?? '');

  // === Sucursales visibles para el usuario ===
  $visibles = [];
  try {
    $st = $con->prepare("SELECT s.id
                           FROM usuario_sucursal us
                           JOIN sucursales s ON s.id = us.id_sucursal AND s.estado = 1
                          WHERE us.id_usuario = :u
                          ORDER BY s.nombre");
    $st->execute([':u'=>$userId]);
    $visibles = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN,0));

    if (empty($visibles)) {
      $qa = $con->prepare("SELECT 1
                             FROM usuario_rol ur JOIN roles r ON r.id_rol = ur.id_rol
                            WHERE ur.id_usuario = :u
                              AND UPPER(r.nombre) IN ('ADMIN','ADMINISTRADOR','PROPIETARIO','SUPERADMIN','OWNER')
                            LIMIT 1");
      $qa->execute([':u'=>$userId]);
      if ($qa->fetchColumn()) {
        $rs = $con->query("SELECT id FROM sucursales WHERE estado=1");
        $visibles = array_map('intval', $rs->fetchAll(PDO::FETCH_COLUMN,0));
      }
    }
  } catch (Throwable $e) { }

  if ($sucReq > 0) { $visibles = array_values(array_intersect($visibles, [$sucReq])); }
  if (empty($visibles)) { echo json_encode(['data'=>[]]); exit; }

  // helper IN (?, ?, ...)
  $ph = implode(',', array_fill(0, count($visibles), '?'));

  // === ¿Existe la columna 'cupos' en distribucion_personal? ===
  $hasCupos = false;
  try{
    $ck = $con->query("SHOW COLUMNS FROM distribucion_personal LIKE 'cupos'");
    if ($ck && $ck->rowCount() > 0) $hasCupos = true;
  }catch(Throwable $e){}

  // === Conteo de pacientes por (medico_id, id_sucursal) en fecha (sólo sucursales visibles) ===
  $pacMap = [];
  try {
    $sqlP = "SELECT medico_id, id_sucursal, COUNT(*) AS cnt
               FROM visitas_pacientes
              WHERE DATE(fecha_visita) = ? AND id_sucursal IN ($ph)
              GROUP BY medico_id, id_sucursal";
    $stP = $con->prepare($sqlP);
    $stP->execute(array_merge([$fecha], $visibles));
    while ($r = $stP->fetch(PDO::FETCH_ASSOC)) {
      $pacMap[$r['medico_id'].'|'.$r['id_sucursal']] = (int)$r['cnt'];
    }
  } catch (Throwable $e) { /* tabla opcional */ }

  $data = [];

  // === 1) distribucion_personal (principal) ===
  $rowsDP = [];
  try {
    $sqlDP = "SELECT dp.id_distribucion, dp.id_usuario, dp.id_sucursal, dp.fecha,
                     dp.hora_entrada, dp.hora_salida, dp.id_rol, dp.estado".
                     ($hasCupos ? ", dp.cupos" : "").",
                     u.nombre_mostrar AS usuario_nom, u.usuario AS usuario_login, u.imagen_perfil,
                     s.nombre AS sucursal_nom,
                     COALESCE(r.nombre,
                       (SELECT r2.nombre FROM usuario_rol ur2
                         JOIN roles r2 ON r2.id_rol = ur2.id_rol
                        WHERE ur2.id_usuario = dp.id_usuario
                        ORDER BY r2.nombre LIMIT 1)
                     ) AS rol_nom
                FROM distribucion_personal dp
                JOIN usuarios u   ON u.id = dp.id_usuario
                JOIN sucursales s ON s.id = dp.id_sucursal
           LEFT JOIN roles r      ON r.id_rol = dp.id_rol
               WHERE dp.fecha = ? AND dp.id_sucursal IN ($ph)
            ORDER BY s.nombre, rol_nom, u.nombre_mostrar";
    $st = $con->prepare($sqlDP);
    $st->execute(array_merge([$fecha], $visibles));
    $rowsDP = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { }

  if (!empty($rowsDP)) {
    foreach ($rowsDP as $r) {
      $key      = $r['id_usuario'].'|'.$r['id_sucursal'];
      $esMedico = preg_match('/m[ée]dico/i', (string)$r['rol_nom']);

      // pacientes reales sólo de referencia; prioridad a cupos si existe
      $pac = $esMedico ? (int)($pacMap[$key] ?? 0) : '—';

      $he = $r['hora_entrada'] ? substr($r['hora_entrada'],0,5) : '—';
      $hs = $r['hora_salida']  ? substr($r['hora_salida'],0,5)  : '—';

      $img = !empty($r['imagen_perfil']) ? 'user_images/'.$r['imagen_perfil'] : 'user_images/default-user.png';

      $data[] = [
        // presentación
        'sucursal'      => $r['sucursal_nom'],
        'sucursal_icon' => 'fas fa-store',
        'sucursal_html' => '<div class="cell-inline"><i class="fas fa-store"></i><span>'.htmlspecialchars($r['sucursal_nom']).'</span></div>',
        'rol'           => $r['rol_nom'] ?: '—',
        'rol_icon'      => rol_icon_class($r['rol_nom']),
        'usuario'       => $r['usuario_nom'] ?: $r['usuario_login'],
        'foto'          => $img,
        'fecha'         => $r['fecha'],
        'dia_laboral'   => nombre_dia_es($r['fecha']),
        'horario'       => $he.' - '.$hs,
        'pacientes'     => $pac,
        'cupos'         => $hasCupos ? ( (isset($r['cupos']) && $r['cupos']!=='') ? (int)$r['cupos'] : null ) : null,
        'estado'        => $r['estado'],
        'estado_badge'  => estado_badge($r['estado']),
        // edición
        'id'           => (int)$r['id_distribucion'],
        'origen'       => 'DP',
        'can_edit'     => true,
        'id_usuario'   => (int)$r['id_usuario'],
        'id_sucursal'  => (int)$r['id_sucursal'],
        'hora_entrada' => $r['hora_entrada'] ? substr($r['hora_entrada'],0,5) : '',
        'hora_salida'  => $r['hora_salida']  ? substr($r['hora_salida'],0,5)  : '',
        'id_rol'       => isset($r['id_rol']) ? (int)$r['id_rol'] : null
      ];
    }

    echo json_encode(['data'=>$data], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // === 2) asignacion_personal (fallback) ===
  $rowsAP = [];
  try {
    $sqlAP = "SELECT ap.id, ap.id_usuario, ap.id_sucursal, ap.fecha,
                     ap.hora_inicio, ap.hora_fin, ap.estado,
                     u.nombre_mostrar AS usuario_nom, u.usuario AS usuario_login, u.imagen_perfil,
                     s.nombre AS sucursal_nom,
                     (SELECT r.nombre FROM usuario_rol ur
                       JOIN roles r ON r.id_rol = ur.id_rol
                      WHERE ur.id_usuario = ap.id_usuario
                      ORDER BY r.nombre LIMIT 1) AS rol_nom
                FROM asignacion_personal ap
                JOIN usuarios u   ON u.id = ap.id_usuario
                JOIN sucursales s ON s.id = ap.id_sucursal
               WHERE ap.fecha = ? AND ap.id_sucursal IN ($ph)
            ORDER BY s.nombre, rol_nom, u.nombre_mostrar";
    $st = $con->prepare($sqlAP);
    $st->execute(array_merge([$fecha], $visibles));
    $rowsAP = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { }

  if (!empty($rowsAP)) {
    foreach ($rowsAP as $r) {
      $key      = $r['id_usuario'].'|'.$r['id_sucursal'];
      $esMedico = preg_match('/m[ée]dico/i', (string)$r['rol_nom']);
      $pac      = $esMedico ? (int)($pacMap[$key] ?? 0) : '—';

      $he = $r['hora_inicio'] ? substr($r['hora_inicio'],0,5) : '—';
      $hs = $r['hora_fin']    ? substr($r['hora_fin'],0,5)    : '—';

      $img = !empty($r['imagen_perfil']) ? 'user_images/'.$r['imagen_perfil'] : 'user_images/default-user.png';

      $data[] = [
        'sucursal'      => $r['sucursal_nom'],
        'sucursal_icon' => 'fas fa-store',
        'sucursal_html' => '<div class="cell-inline"><i class="fas fa-store"></i><span>'.htmlspecialchars($r['sucursal_nom']).'</span></div>',
        'rol'           => $r['rol_nom'] ?: '—',
        'rol_icon'      => rol_icon_class($r['rol_nom']),
        'usuario'       => $r['usuario_nom'] ?: $r['usuario_login'],
        'foto'          => $img,
        'fecha'         => $r['fecha'],
        'dia_laboral'   => nombre_dia_es($r['fecha']),
        'horario'       => $he.' - '.$hs,
        'pacientes'     => $pac,
        'cupos'         => null,
        'estado'        => $r['estado'],
        'estado_badge'  => estado_badge($r['estado']),
        'id'           => (int)$r['id'],
        'origen'       => 'AP',
        'can_edit'     => false,
        'id_usuario'   => (int)$r['id_usuario'],
        'id_sucursal'  => (int)$r['id_sucursal'],
        'hora_entrada' => $r['hora_inicio'] ? substr($r['hora_inicio'],0,5) : '',
        'hora_salida'  => $r['hora_fin']    ? substr($r['hora_fin'],0,5)    : '',
        'id_rol'       => null
      ];
    }

    echo json_encode(['data'=>$data], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // === 3) Sin programación: personal disponible por acceso ===
  $sqlDisp = "SELECT u.id, u.nombre_mostrar, u.usuario, u.imagen_perfil,
                     s.id AS id_suc, s.nombre AS sucursal,
                     (SELECT r.nombre FROM usuario_rol ur
                       JOIN roles r ON r.id_rol=ur.id_rol
                      WHERE ur.id_usuario=u.id ORDER BY r.nombre LIMIT 1) AS rol
                FROM usuario_sucursal us
                JOIN usuarios u   ON u.id = us.id_usuario
                JOIN sucursales s ON s.id = us.id_sucursal
               WHERE s.id IN ($ph)
            ORDER BY s.nombre, u.nombre_mostrar";
  $st = $con->prepare($sqlDisp);
  $st->execute($visibles);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $img = !empty($r['imagen_perfil']) ? 'user_images/'.$r['imagen_perfil'] : 'user_images/default-user.png';
    $data[] = [
      'sucursal'      => $r['sucursal'],
      'sucursal_icon' => 'fas fa-store',
      'sucursal_html' => '<div class="cell-inline"><i class="fas fa-store"></i><span>'.htmlspecialchars($r['sucursal']).'</span></div>',
      'rol'           => $r['rol'] ?: '—',
      'rol_icon'      => rol_icon_class($r['rol']),
      'usuario'       => $r['nombre_mostrar'] ?: $r['usuario'],
      'foto'          => $img,
      'fecha'         => $fecha,                         // usa fecha del filtro
      'dia_laboral'   => nombre_dia_es($fecha),         // día desde la fecha del filtro
      'horario'       => '—',
      'pacientes'     => (preg_match('/m[ée]dico/i', (string)$r['rol']) ? 0 : '—'),
      'cupos'         => null,
      'estado'       => null,
      'estado_badge' => '<span class="badge badge-secondary badge-estado">—</span>',
      'id'           => (int)$r['id'],
      'origen'       => 'DISP',
      'can_edit'     => false,
      'id_usuario'   => (int)$r['id'],
      'id_sucursal'  => (int)$r['id_suc'],
      'hora_entrada' => '',
      'hora_salida'  => '',
      'id_rol'       => null
    ];
  }

  echo json_encode(['data'=>$data], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e){
  echo json_encode(['data'=>[], 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
