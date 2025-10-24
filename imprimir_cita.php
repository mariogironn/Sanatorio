<?php
// imprimir_cita.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';

// (opcional) protege si tu app usa sesión
if (empty($_SESSION['user_id'])) { /* puedes redirigir si quieres */ }

$id = (int)($_GET['id'] ?? $_GET['id_cita'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo "ID de cita inválido.";
  exit;
}

$stmt = $con->prepare("
  SELECT c.id_cita, c.fecha, c.hora, c.motivo, c.estado,
         p.nombre AS paciente,
         u.nombre_mostrar AS medico
  FROM citas_medicas c
  JOIN pacientes p   ON p.id_paciente = c.paciente_id
  LEFT JOIN usuarios u ON u.id = c.medico_id
  WHERE c.id_cita = :id
  LIMIT 1
");
$stmt->execute([':id'=>$id]);
$cita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cita) {
  http_response_code(404);
  echo "La cita #{$id} no existe.";
  exit;
}

// Helper simple para fecha en español
function fecha_es($ymd) {
  $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  [$y,$m,$d] = explode('-', $ymd);
  $m = (int)$m; $d = (int)$d;
  return $d.' de '.$meses[$m].' de '.$y;
}

$fecha = htmlspecialchars(fecha_es($cita['fecha']));
$hora  = htmlspecialchars(substr($cita['hora'] ?? '', 0, 5));
$estado= htmlspecialchars(strtoupper($cita['estado']));
$pac   = htmlspecialchars($cita['paciente'] ?? '—');
$med   = htmlspecialchars($cita['medico']   ?? '—');
$mot   = htmlspecialchars($cita['motivo']   ?? '—');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Imprimir Cita #<?php echo (int)$cita['id_cita']; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Puedes usar tu css global si quieres -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    @media print {.no-print{display:none!important}}
    body{background:#f7f9fb}
    .sheet{
      max-width: 800px; margin:24px auto; background:#fff;
      border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,.06);
    }
    .sheet-header{
      background:linear-gradient(120deg,#2c3e50,#3498db); color:#fff;
      padding:16px 20px; border-radius:8px 8px 0 0;
    }
    .badge-estado{font-size:.8rem}
    .row-line{padding:8px 0; border-bottom:1px dashed #e9ecef}
    .row-line:last-child{border-bottom:0}
  </style>
</head>
<body>
  <div class="sheet">
    <div class="sheet-header d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-0"><i class="fas fa-calendar-check"></i> Cita #<?php echo (int)$cita['id_cita']; ?></h4>
        <small>Sanatorio La Esperanza</small>
      </div>
      <span class="badge badge-light badge-estado"><?php echo $estado; ?></span>
    </div>

    <div class="p-4">
      <div class="row-line"><strong>Paciente:</strong> <?php echo $pac; ?></div>
      <div class="row-line"><strong>Médico:</strong> <?php echo $med; ?></div>
      <div class="row-line"><strong>Fecha y hora:</strong> <?php echo $fecha.' '.$hora; ?></div>
      <div class="row-line"><strong>Motivo:</strong> <?php echo $mot; ?></div>

      <div class="mt-4">
        <small class="text-muted">Generado el <?php echo date('d/m/Y H:i'); ?></small>
      </div>

      <div class="mt-3 no-print">
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
        <button class="btn btn-outline-secondary" onclick="window.close()">Cerrar</button>
      </div>
    </div>
  </div>

  <script defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
</body>
</html>
