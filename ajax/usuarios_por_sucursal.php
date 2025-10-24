 <?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';
if (!isset($con)) { if (isset($pdo)) $con=$pdo; elseif(isset($dbh)) $con=$dbh; }

try{
  if (!($con instanceof PDO)) throw new Exception('Sin conexión PDO');
  $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $suc = (int)($_GET['id_sucursal'] ?? $_POST['id_sucursal'] ?? 0);

  // Sucursales visibles para el usuario actual
  $uid = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
  $vis = [];
  $st  = $con->prepare("SELECT s.id
                          FROM usuario_sucursal us
                          JOIN sucursales s ON s.id=us.id_sucursal AND s.estado=1
                         WHERE us.id_usuario=:u");
  $st->execute([':u'=>$uid]);
  $vis = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN,0));

  // Si no tiene asignadas, permitir ver todas si es admin
  if (empty($vis)) {
    $qa = $con->prepare("SELECT 1
                           FROM usuario_rol ur JOIN roles r ON r.id_rol=ur.id_rol
                          WHERE ur.id_usuario=:u
                            AND UPPER(r.nombre) IN ('ADMIN','ADMINISTRADOR','PROPIETARIO','SUPERADMIN','OWNER')
                          LIMIT 1");
    $qa->execute([':u'=>$uid]);
    if ($qa->fetchColumn()){
      $rs=$con->query("SELECT id FROM sucursales WHERE estado=1");
      $vis=array_map('intval',$rs->fetchAll(PDO::FETCH_COLUMN,0));
    }
  }

  if ($suc>0) {
    // Si pide una sucursal específica que no está visible => vacío
    if (!in_array($suc,$vis,true)) { echo json_encode(['usuarios'=>[]]); exit; }

    $sql = "SELECT DISTINCT u.id, COALESCE(NULLIF(u.nombre_mostrar,''), u.usuario) AS nombre
              FROM usuario_sucursal us
              JOIN usuarios u ON u.id=us.id_usuario
             WHERE us.id_sucursal=:s
             ORDER BY nombre";
    $st = $con->prepare($sql);
    $st->execute([':s'=>$suc]);
  } else {
    // Sin sucursal seleccionada: si no hay visibles => vacío
    if (empty($vis)) { echo json_encode(['usuarios'=>[]]); exit; }

    $ph = implode(',', array_fill(0, count($vis), '?'));
    $sql = "SELECT DISTINCT u.id, COALESCE(NULLIF(u.nombre_mostrar,''), u.usuario) AS nombre
              FROM usuario_sucursal us
              JOIN usuarios u ON u.id=us.id_usuario
             WHERE us.id_sucursal IN ($ph)
             ORDER BY nombre";
    $st = $con->prepare($sql);
    $st->execute($vis);
  }

  echo json_encode(['usuarios'=>$st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e){
  echo json_encode(['usuarios'=>[], 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
