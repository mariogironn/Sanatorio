<?php
// ajax/obtener_historial_paciente.php
// Devuelve SOLO filas <tr> con el historial de medicación de un paciente (HTML)

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../config/connection.php';

// -------- Parámetros --------
$patientId = isset($_GET['id_paciente']) ? (int)$_GET['id_paciente'] : 0;
if ($patientId <= 0) { echo ''; exit; } // sin filas; el front muestra el mensaje

// Sucursal seleccionada en el filtro (0 = Todas)
$branchFilter = (int)($_GET['id_sucursal'] ?? 0);

// Lista de sucursales permitidas (si manejas control de acceso)
$allowed = array_map('intval', $_SESSION['sucursales_ids'] ?? []);

// Determina el conjunto de sucursales a consultar
$filterIds = [];
if ($branchFilter > 0) {
  // Si seleccionó una en concreto, valida acceso (si hay lista)
  if (!empty($allowed) && !in_array($branchFilter, $allowed, true)) {
    echo ''; // sin filas si no tiene acceso
    exit;
  }
  $filterIds = [$branchFilter];
} else {
  // "Todas": si hay lista de permitidas, se limita a esa lista
  if (!empty($allowed)) {
    $filterIds = $allowed;
  }
}

// -------- Consulta --------
// NOTA: aquí vinculamos el historial a la visita específica usando h.id_visita_paciente
// para mostrar exactamente la fecha, enfermedad y sucursal correspondientes.
$sql = "
  SELECT
    h.id,
    h.cantidad,
    h.dosis,
    m.nombre_medicamento   AS med,
    d.empaque              AS paquete,
    pv.fecha_visita,
    pv.enfermedad,
    s.nombre               AS sucursal
  FROM historial_medicacion_paciente h
  JOIN detalles_medicina d   ON d.id = h.id_detalle_medicina
  JOIN medicamentos m        ON m.id = d.id_medicamento
  JOIN visitas_pacientes pv  ON pv.id = h.id_visita_paciente
  JOIN sucursales s          ON s.id = pv.id_sucursal
  WHERE pv.id_paciente = :pid
";

$params = [':pid' => $patientId];

// Aplica filtro de sucursal si corresponde
if (!empty($filterIds)) {
  // Construye placeholders seguros (:s0, :s1, ...)
  $inPh = [];
  foreach ($filterIds as $i => $sid) {
    $ph = ':s' . $i;
    $inPh[] = $ph;
    $params[$ph] = (int)$sid;
  }
  $sql .= " AND pv.id_sucursal IN (" . implode(',', $inPh) . ")";
}

$sql .= " ORDER BY pv.fecha_visita DESC, h.id DESC";

try {
  $stmt = $con->prepare($sql);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_INT);
  }
  $stmt->execute();

  $out = '';
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id    = (int)$r['id'];
    $fvRaw = $r['fecha_visita'] ?? '';
    $fv    = $fvRaw ? date('d-m-Y', strtotime($fvRaw)) : '';

    $out .= '<tr id="hist-row-'.$id.'">';
    $out .=   '<td class="p-1 text-center"></td>'; // N. Serie (lo rellena JS)
    $out .=   '<td class="p-1 text-center">'.htmlspecialchars($fv, ENT_QUOTES, 'UTF-8').'</td>';
    $out .=   '<td class="p-1 text-center">'.htmlspecialchars((string)($r['enfermedad'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
    $out .=   '<td class="p-1 text-center">'.htmlspecialchars((string)($r['med'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
    $out .=   '<td class="p-1 text-center">'.htmlspecialchars((string)($r['paquete'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
    $out .=   '<td class="p-1 text-center">'.htmlspecialchars((string)($r['cantidad'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
    $out .=   '<td class="p-1 text-center">'.htmlspecialchars((string)($r['dosis'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
    $out .=   '<td class="p-1 text-center">'.htmlspecialchars((string)($r['sucursal'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'; // NUEVA COLUMNA
    $out .=   '<td class="p-1 text-center">';
    $out .=     '<button type="button" class="btn btn-danger btn-sm btn-icon btn-del-hist"';
    $out .=             ' data-id="'.$id.'" title="Eliminar"><i class="fa fa-trash"></i></button>';
    $out .=   '</td>';
    $out .= '</tr>';
  }

  echo $out; // si no hay filas, responde vacío

} catch (PDOException $ex) {
  // Silencioso para no romper la tabla; podrías loguearlo si quieres.
  echo '';
}
