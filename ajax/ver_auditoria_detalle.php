<?php
// ajax/ver_auditoria_detalle.php
// Devuelve campos y un detalle en HTML (compatible con el modal SweetAlert2 y el nuevo modal de estilo "primera imagen")

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

/* ============================ Helpers ============================ */

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function format_dt($s){
  if (!$s) return '';
  try { $dt = new DateTime($s); return $dt->format('d/m/Y H:i:s'); } catch(Throwable $e){ return (string)$s; }
}

function estado_bits($estado){
  $s = strtolower(trim((string)$estado));
  $isActive = ($s==='activo' || $s==='1' || $s==='si' || $s==='true');
  return [$isActive ? 'Activo' : 'Inactivo', $isActive ? 'badge-success' : 'badge-secondary'];
}

function acc_text($a){
  $M=['CREATE'=>'Crear','UPDATE'=>'Actualizar','DELETE'=>'Eliminar','ACTIVAR'=>'Activar','DESACTIVAR'=>'Desactivar','GENERAR'=>'Generar'];
  return $M[strtoupper((string)$a)] ?? strtoupper((string)$a);
}

function abbrev_slug($tabla,$modulo){
  $base = trim((string)$tabla); if ($base==='') $base = trim((string)$modulo); if ($base==='') $base = 'AUD';
  $base = strtoupper(preg_replace('/[^A-Za-z]/','',$base));
  return substr($base,0,3) ?: 'AUD';
}

/* Etiquetas bonitas: id_paciente -> ID paciente, fecha_nacimiento -> Fecha de nacimiento */
function pretty_label($k){
  $map = [
    'id' => 'ID',
    'id_paciente' => 'ID paciente',
    'id_medicina' => 'ID medicina',
    'fecha_nacimiento' => 'Fecha de nacimiento',
    'genero' => 'Género',
    'dpi' => 'DPI',
    'telefono' => 'Teléfono',
    'nombre' => 'Nombre',
    'direccion' => 'Dirección',
    'estado' => 'Estado',
  ];
  $kl = strtolower($k);
  if (isset($map[$kl])) return $map[$kl];
  return mb_convert_case(str_replace('_',' ',(string)$k), MB_CASE_TITLE, 'UTF-8');
}

/* Campos que no deben mostrarse en el detalle */
function skip_keys(){ 
  return [
    'created_by','updated_by',
    'created_at','updated_at','creado_en','actualizado_en',
    'createdon','updatedon','created_on','updated_on',
    'fecha_creacion','fecha_actualizacion','deleted_at'
  ];
}
function should_skip($k){ return in_array(strtolower((string)$k), skip_keys(), true); }

/* Normaliza valores complejos a string */
function norm_val($v){
  if (is_array($v) || is_object($v)) return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  return (string)$v;
}

/* Listado clave-valor bonito */
function render_kv_list(array $arr){
  if (!$arr) return '<div class="text-muted">Sin datos.</div>';
  $rows='';
  foreach ($arr as $k=>$v){
    if (should_skip($k)) continue;
    $rows .= '<tr><th style="width:32%;font-weight:600">'.esc(pretty_label($k)).'</th><td>'.esc(norm_val($v)).'</td></tr>';
  }
  if ($rows==='') return '<div class="text-muted">Sin datos relevantes.</div>';
  return '<div class="table-responsive"><table class="table table-sm table-striped mb-0">'.$rows.'</table></div>';
}

/* Tabla de diferencias (solo campos que cambiaron) */
function render_diff(array $antes, array $despues){
  $keys = array_unique(array_merge(array_keys($antes), array_keys($despues)));
  $rows = '';
  foreach ($keys as $k){
    if (should_skip($k)) continue;
    $a = norm_val($antes[$k]  ?? '');
    $d = norm_val($despues[$k]?? '');
    if ($a === $d) continue;
    $rows .= '<tr>'
           . '<th style="width:28%;font-weight:600">'.esc(pretty_label($k)).'</th>'
           . '<td>'.esc($a).'</td>'
           . '<td>'.esc($d).'</td>'
           . '</tr>';
  }
  if ($rows==='') return '<div class="text-muted">No se detectaron cambios.</div>';
  return '<div class="table-responsive"><table class="table table-sm table-striped mb-0">'
       . '<thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead>'
       . '<tbody>'.$rows.'</tbody></table></div>';
}

/* ============================ Main ============================ */

try{
  $id = (int)($_GET['id'] ?? 0);
  if ($id<=0) throw new Exception('ID inválido');

  if (!($con instanceof PDO)) {
    echo json_encode(['error'=>'Conexión no reconocida (usa PDO).']); exit;
  }
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Resolver columnas de usuarios (nombre visible y PK)
  $uCols = [];
  try { $uCols = $con->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN); } catch(Throwable $e){}
  $disp = null; $pk = 'id';
  if ($uCols) {
    $has = function($c) use ($uCols){ return in_array($c,$uCols,true); };
    if     ($has('nombre_mostrar'))               $disp = 'u.nombre_mostrar';
    elseif ($has('nombre_completo'))              $disp = 'u.nombre_completo';
    elseif ($has('nombre'))                       $disp = 'u.nombre';
    elseif ($has('name'))                         $disp = 'u.name';
    elseif ($has('nombres') && $has('apellidos')) $disp = "CONCAT_WS(' ',u.nombres,u.apellidos)";
    elseif ($has('usuario'))                      $disp = 'u.usuario';
    elseif ($has('username'))                     $disp = 'u.username';
    elseif ($has('login'))                        $disp = 'u.login';
    elseif ($has('email'))                        $disp = 'u.email';
    if (!$has('id') && $has('id_usuario')) $pk = 'id_usuario';
  }
  $userSel = $disp ? "$disp AS usuario" : "CONCAT('#',a.usuario_id) AS usuario";
  $userJoin= $disp ? "LEFT JOIN usuarios u ON u.`$pk` = a.usuario_id" : "";

  // Fila de auditoría
  $sql = "SELECT a.id, a.modulo, a.tabla, a.id_registro, a.accion, a.estado_resultante,
                 a.usuario_id, $userSel, a.antes_json, a.despues_json, a.creado_en
          FROM auditoria a
          $userJoin
          WHERE a.id=? LIMIT 1";
  $st  = $con->prepare($sql);
  $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) throw new Exception('No encontrado');

  // Roles del usuario (opcional)
  $roles = '';
  try{
    $stR = $con->prepare("SELECT GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', ')
                            FROM usuario_rol ur
                            JOIN roles r ON r.id_rol = ur.id_rol
                           WHERE ur.id_usuario = ?");
    $stR->execute([(int)$r['usuario_id']]);
    $roles = (string)$stR->fetchColumn();
  } catch(Throwable $e){ $roles=''; }

  $accion    = (string)$r['accion'];
  $accionFmt = acc_text($accion);

  // Estado: usar estado_resultante o despues_json.estado
  $estado = $r['estado_resultante'];
  $Djson  = $r['despues_json'] ? json_decode($r['despues_json'], true) : null;
  if (!$estado && is_array($Djson) && array_key_exists('estado',$Djson)) $estado = $Djson['estado'];
  list($estadoText,$estadoBadge) = estado_bits($estado);

  // Construir detalle
  $Ajson = $r['antes_json']   ? json_decode($r['antes_json'], true)   : null;
  $Djson = $r['despues_json'] ? json_decode($r['despues_json'], true) : null;
  $A = is_array($Ajson) ? $Ajson : [];
  $D = is_array($Djson) ? $Djson : [];

  $detalle = '';
  switch (strtoupper($accion)) {
    case 'CREATE':
      $detalle = '<div class="mb-2"><b>Registro creado</b></div>' . render_kv_list($D);
      break;
    case 'UPDATE':
      $detalle = '<div class="mb-2"><b>Cambios aplicados</b></div>' . render_diff($A, $D);
      break;
    case 'DELETE':
      $detalle = '<div class="mb-2"><b>Registro eliminado (valores previos)</b></div>' . render_kv_list($A);
      break;
    case 'ACTIVAR':
    case 'DESACTIVAR':
      $ant = is_array($A) && array_key_exists('estado',$A) ? $A['estado'] : null;
      $des = is_array($D) && array_key_exists('estado',$D) ? $D['estado'] : $estado;
      list($tA,) = estado_bits($ant);
      list($tD,) = estado_bits($des);
      $detalle = '<div><b>Estado:</b> '.esc($tA).' → <b>'.esc($tD).'</b></div>';
      break;
    default:
      $detalle = '<div class="text-muted">Sin detalle específico para esta acción.</div>';
      break;
  }

  // ID legible
  $slug = abbrev_slug($r['tabla'], $r['modulo']);
  $registroFmt = $slug . '-' . ( $r['id_registro'] ?: $r['id'] );

  // Resumen
  $resumen = sprintf('Acción "%s" realizada en el módulo %s por %s',
    $accionFmt, $r['modulo'] ?? '', ($r['usuario'] ?? ('#'.$r['usuario_id']))
  );

  // HTML imprimible / para PDF (auto-contenido)
  $print_html = '
  <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; max-width:900px; margin:auto;">
    <h2 style="margin:0 0 8px 0;font-weight:700;">Registro de Auditoría</h2>
    <div style="color:#666;margin-bottom:12px;">'.$resumen.' — '.esc(format_dt($r['creado_en'])).'</div>
    <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:12px;">
      <tr><th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Módulo</th><td style="padding:6px;border:1px solid #e5e7eb;">'.esc($r['modulo']).'</td></tr>
      <tr><th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Acción</th><td style="padding:6px;border:1px solid #e5e7eb;">'.esc($accionFmt).'</td></tr>
      <tr><th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Usuario</th><td style="padding:6px;border:1px solid #e5e7eb;">'.esc($r['usuario'] ?? ('#'.$r['usuario_id'])).'</td></tr>
      <tr><th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Rol</th><td style="padding:6px;border:1px solid #e5e7eb;">'.esc($roles ?: '—').'</td></tr>
      <tr><th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">ID de Registro</th><td style="padding:6px;border:1px solid #e5e7eb;">'.esc($registroFmt).'</td></tr>
      <tr><th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Estado</th><td style="padding:6px;border:1px solid #e5e7eb;">'.esc($estadoText).'</td></tr>
      <tr><th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Fecha</th><td style="padding:6px;border:1px solid #e5e7eb;">'.esc(format_dt($r['creado_en'])).'</td></tr>
    </table>
    <h3 style="margin:18px 0 8px 0;">Detalles</h3>
    '.$detalle.'
  </div>';

  // ===== Salida JSON (mantengo campos existentes y agrego alias para el nuevo modal) =====
  echo json_encode([
    'id'                => (int)$r['id'],
    'modulo'            => $r['modulo'],
    'tabla'             => $r['tabla'],
    'id_registro'       => $r['id_registro'],
    'registro_fmt'      => $registroFmt,
    'accion'            => $accion,
    'accion_fmt'        => $accionFmt,
    'usuario'           => $r['usuario'] ?? ('#'.$r['usuario_id']),
    'usuario_nombre'    => $r['usuario'] ?? ('#'.$r['usuario_id']), // alias
    'usuario_rol'       => $roles ?: '—',

    // Estado
    'estado_text'       => $estadoText,
    'estado_badge'      => $estadoBadge,
    'estado'            => $estado, // alias útil
    'estado_resultante' => $r['estado_resultante'],

    // Fechas
    'creado_en'         => $r['creado_en'],
    'creado_en_fmt'     => format_dt($r['creado_en']),
    'fecha_hora'        => $r['creado_en'], // alias
    'datetime'          => $r['creado_en'], // alias

    // Detalle en HTML (para usos previos)
    'detalle_html'      => $detalle,

    // JSON de cambios (compatibilidad)
    'antes_json'        => $Ajson,
    'despues_json'      => $Djson,

    // Alias que el nuevo modal acepta para "Valores previos"
    'previos'           => $A,
    'valores_previos'   => $A,
    // (opcional) más alias si los usas en otros lugares:
    // 'valores'        => $A, 'old' => $A, 'before' => $A, 'anteriores' => $A, 'previous' => $A,

    'resumen'           => esc($resumen),
    'print_html'        => $print_html
  ], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e){
  echo json_encode(['error'=>'ver_auditoria_detalle.php: '.$e->getMessage()]);
}
