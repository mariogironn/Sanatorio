<?php
// ajax/listar_auditoria.php
// Server-side DataTables. Filtros en español para "Acción", módulo case-insensitive,
// JOIN/lookup de usuarios y extracción de roles (soporta múltiples roles).

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
ob_start();

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con = $pdo; elseif (isset($dbh)) $con = $dbh; }

function out_json($a){ if(ob_get_length())ob_end_clean(); echo json_encode($a,JSON_UNESCAPED_UNICODE); exit; }

// Mapa ES → ENUM
function map_accion_enum($txt){
  $m = [
    'CREAR'       => 'CREATE',
    'ACTUALIZAR'  => 'UPDATE',
    'ELIMINAR'    => 'DELETE',
    'ACTIVAR'     => 'ACTIVAR',
    'DESACTIVAR'  => 'DESACTIVAR',
    'GENERAR'     => 'GENERAR',
  ];
  $k = mb_strtoupper(trim((string)$txt), 'UTF-8');
  return $m[$k] ?? ($k !== '' ? $k : '');
}

// Derivar estado si viene NULL/'' en DB
function derive_estado(?string $estado, string $accion){
  $estado = is_string($estado) ? trim($estado) : '';
  if ($estado !== '') return strtolower($estado);
  $a = strtoupper(trim($accion));
  if ($a === 'DELETE' || $a === 'DESACTIVAR') return 'inactivo';
  if ($a === 'CREATE' || $a === 'UPDATE' || $a === 'ACTIVAR') return 'activo';
  return null;
}

try{
  $draw   = (int)($_GET['draw']  ?? 1);
  $start  = (int)($_GET['start'] ?? 0);
  $length = (int)($_GET['length']?? 10);

  $modulo     = trim($_GET['modulo']     ?? '');
  $accionRaw  = trim($_GET['accion']     ?? '');     // puede venir en español
  $usuario_id = trim($_GET['usuario_id'] ?? '');
  $desde      = trim($_GET['desde']      ?? '');
  $hasta      = trim($_GET['hasta']      ?? '');
  $search     = trim($_GET['search']['value'] ?? '');

  $accion = map_accion_enum($accionRaw); // NORMALIZAR ACCIÓN

  if (!($con instanceof PDO)) throw new Exception('Conexión no reconocida (usa PDO).');
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // === Resolver nombre visible de usuario (mejor esfuerzo) ===
  $uCols = [];
  try { $uCols = $con->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN); } catch(Throwable $e){}
  $disp = null;
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
  }
  $userSelect = $disp ? "$disp AS usuario" : "CONCAT('#',a.usuario_id) AS usuario";
  $userJoin   = $disp ? "LEFT JOIN usuarios u ON u.id = a.usuario_id" : "";

  // === WHERE ===
  $where = ["1=1"]; $p=[];

  if ($modulo !== '')     { $where[]="UPPER(a.modulo) = UPPER(?)";      $p[]=$modulo; }
  if ($accion !== '')     { $where[]="a.accion = ?";                    $p[]=$accion; }
  if ($usuario_id !== '') { $where[]="a.usuario_id = ?";                $p[]=(int)$usuario_id; }
  if ($desde !== '')      { $where[]="DATE(a.creado_en) >= ?";          $p[]=$desde; }
  if ($hasta !== '')      { $where[]="DATE(a.creado_en) <= ?";          $p[]=$hasta; }

  if ($search !== '') {
    $searchEnum = map_accion_enum($search);
    $parts = [];
    $parts[] = "a.modulo LIKE ?";
    $parts[] = "a.accion LIKE ?";
    $parts[] = "CAST(a.id_registro AS CHAR) LIKE ?";
    array_push($p, "%$search%", "%$search%", "%$search%");
    // Buscar por nombre visible del usuario (si hay join de usuarios)
    if ($disp) { $parts[] = "$disp LIKE ?"; $p[] = "%$search%"; }
    // Buscar por ROL (EXISTS sobre tablas de roles)
    $parts[] = "EXISTS (SELECT 1 FROM usuario_rol ur JOIN roles r ON r.id_rol = ur.id_rol
                        WHERE ur.id_usuario = a.usuario_id AND r.nombre LIKE ?)";
    $p[] = "%$search%";
    // Si el usuario escribió "eliminar/crear", incluye el ENUM
    if ($searchEnum && $searchEnum !== $search) {
      $parts[] = "a.accion LIKE ?"; $p[] = $searchEnum;
    }
    $where[] = '('.implode(' OR ', $parts).')';
  }

  $whereSql = implode(' AND ', $where);

  // Totales filtrados
  $stmt = $con->prepare("SELECT COUNT(*) FROM auditoria a $userJoin WHERE $whereSql");
  foreach ($p as $i=>$v) $stmt->bindValue($i+1,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
  $stmt->execute();
  $totalFiltered = (int)$stmt->fetchColumn();

  // Totales sin filtro
  $totalRecords  = (int)$con->query("SELECT COUNT(*) FROM auditoria")->fetchColumn();

  // Select de roles (soporta muchos → concatenados)
  $rolSelect = "(SELECT GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', ')
                 FROM usuario_rol ur
                 JOIN roles r ON r.id_rol = ur.id_rol
                WHERE ur.id_usuario = a.usuario_id) AS rol";

  // Datos
  $sql = "SELECT a.id, a.creado_en, a.modulo, a.accion, a.id_registro, a.estado_resultante,
                 $userSelect, $rolSelect
            FROM auditoria a
            $userJoin
           WHERE $whereSql
        ORDER BY a.creado_en DESC
           LIMIT ?, ?";
  $stmt = $con->prepare($sql);
  $pos=1; foreach ($p as $v) $stmt->bindValue($pos++, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
  $stmt->bindValue($pos++, $start,  PDO::PARAM_INT);
  $stmt->bindValue($pos++, $length, PDO::PARAM_INT);
  $stmt->execute();

  $data=[];
  while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
    $estado = derive_estado($r['estado_resultante'] ?? null, $r['accion'] ?? '');
    $data[] = [
      'id'                => (int)$r['id'],
      'creado_en'         => $r['creado_en'],
      'usuario'           => $r['usuario'] ?: '',
      'rol'               => $r['rol'] ?: '—',          // <<<<<< NUEVO CAMPO
      'modulo'            => $r['modulo'],
      'accion'            => $r['accion'],
      'id_registro'       => $r['id_registro'],
      'estado_resultante' => $estado
    ];
  }

  out_json([
    'draw'=>$draw,'recordsTotal'=>$totalRecords,'recordsFiltered'=>$totalFiltered,'data'=>$data
  ]);

} catch (Throwable $e){
  out_json([
    'draw'=>(int)($_GET['draw']??1),
    'recordsTotal'=>0,
    'recordsFiltered'=>0,
    'data'=>[],
    'error'=>'listar_auditoria.php: '.$e->getMessage()
  ]);
}
