<?php
// Responderá filas <tr>...</tr>
header('Content-Type: text/html; charset=UTF-8');

include '../config/connection.php';

// ====== 1) Tomar y validar parámetros ======
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$from   = isset($_GET['from']) ? $_GET['from'] : '';
$to     = isset($_GET['to'])   ? $_GET['to']   : '';

// dd/mm/yyyy -> yyyy-mm-dd
function toMysqlDate($dmy) {
  $a = explode('/', $dmy);
  return (count($a) === 3) ? ($a[2] . '-' . $a[0] . '-' . $a[1]) : '';
}
$fromMysql = toMysqlDate($from);
$toMysql   = toMysqlDate($to);

// Rango completo del día
$fromDT = $fromMysql . ' 00:00:00';
$toDT   = $toMysql   . ' 23:59:59';

// ====== 2) Consulta segura (prepared) ======
$sql = "SELECT IFNULL(`insertion_date_time`, '') AS insertion_date_time,
               `description`
        FROM `interaction_histories`
        WHERE `user_id` = :uid
          AND `insertion_date_time` BETWEEN :from AND :to
        ORDER BY `insertion_date_time` ASC";

try {
  $stmt = $con->prepare($sql);
  $stmt->bindParam(':uid',  $userId,  PDO::PARAM_INT);
  $stmt->bindParam(':from', $fromDT,  PDO::PARAM_STR);
  $stmt->bindParam(':to',   $toDT,    PDO::PARAM_STR);
  $stmt->execute();
} catch (PDOException $ex) {
  echo '<tr><td colspan="3">Error: ' . htmlspecialchars($ex->getMessage()) . '</td></tr>';
  exit;
}

// ====== 3) Construir las filas ======
$data   = '';
$serial = 0;

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $serial++;
  $fecha = htmlspecialchars($r['insertion_date_time']);
  $desc  = htmlspecialchars($r['description']);

  $data .= '<tr>';
  $data .= '<td>' . $serial . '</td>';
  $data .= '<td>' . $fecha   . '</td>';
  $data .= '<td>' . $desc    . '</td>';
  $data .= '</tr>';
}

// Si no hubo resultados
if ($serial === 0) {
  $data = '<tr><td colspan="3" class="text-center">No se encontraron registros.</td></tr>';
}

echo $data;

