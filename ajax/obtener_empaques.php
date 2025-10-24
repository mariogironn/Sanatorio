<?php
// ajax/obtener_empaques.php
// Devuelve <option> de empaques para una medicina.
// Acepta: GET/POST  medicine_id  |  id_medicamento
// Salida: HTML <option value="id_detalle">Etiqueta de empaque</option>

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';

try {
  // 1) Tomar el id de la medicina (aceptamos ambos nombres)
  $mid = 0;
  if (isset($_GET['medicine_id']))             $mid = (int)$_GET['medicine_id'];
  elseif (isset($_POST['medicine_id']))        $mid = (int)$_POST['medicine_id'];
  elseif (isset($_GET['id_medicamento']))      $mid = (int)$_GET['id_medicamento'];
  elseif (isset($_POST['id_medicamento']))     $mid = (int)$_POST['id_medicamento'];

  if ($mid <= 0) { echo '<option value="">(selecciona una medicina)</option>'; exit; }

  // 2) Traer empaques desde detalles_medicina para esa medicina
  $sql = "SELECT id, empaque
          FROM detalles_medicina
          WHERE id_medicamento = :m
          ORDER BY empaque ASC";
  $st  = $con->prepare($sql);
  $st->execute([':m' => $mid]);

  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // 3) Responder HTML <option> (lo que espera el select #packing)
  if (!$rows) {
    echo '<option value="">(sin empaques)</option>';
    exit;
  }

  echo '<option value="">Selecciona Paquete</option>';
  foreach ($rows as $r) {
    $id  = (int)$r['id'];
    $txt = htmlspecialchars((string)$r['empaque'], ENT_QUOTES, 'UTF-8');
    echo '<option value="'.$id.'">'.$txt.'</option>';
  }

} catch (Throwable $e) {
  echo '<option value="">Error cargando empaques</option>';
}
