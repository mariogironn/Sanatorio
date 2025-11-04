<?php
// ver_detalle_medicina.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: medicinas.php'); exit; }

// --------- Cabecera de medicina (usa la VISTA correcta) ---------
$med = null;
try {
  $st = $con->prepare("
    SELECT  vm.med_id,
            vm.nombre_medicamento, vm.nombre_generico, vm.principio_activo,
            vm.concentracion, vm.tipo_medicamento,
            vm.stock_actual, vm.stock_minimo,
            vm.presentacion, vm.laboratorio, vm.categoria, vm.descripcion
    FROM v_medicamentos_meta vm
    WHERE vm.med_id = :id
    LIMIT 1
  ");
  $st->execute([':id'=>$id]);
  $med = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $med = null; }

// --------- Pacientes que usan esta medicina (vista) ---------
$pac = [];
try {
  $st = $con->prepare("
    SELECT id, paciente, medico, dosis, frecuencia,
           motivo_diagnostico, duracion, fecha
    FROM v_medicamento_pacientes
    WHERE med_id = :id
    ORDER BY fecha DESC
  ");
  $st->execute([':id'=>$id]);
  $pac = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $pac = []; }

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <!-- DataTables Buttons (Bootstrap 4) -->
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
  <title>Detalles de Medicina</title>
  <style>
    .info-box{border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem}
    .pill{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600}
    .pill--ok{background:#e6f4ea;color:#1e7e34}
    .pill--warn{background:#fff3cd;color:#856404}
    
    /* Estilos para impresión */
    @media print {
      .no-print { display: none !important; }
      body { background: white !important; }
      .content-wrapper { margin: 0 !important; padding: 0 !important; }
      .card { border: 1px solid #000 !important; box-shadow: none !important; }
      .info-box { border: 1px solid #000 !important; }
      .container-fluid { max-width: 100% !important; padding: 0 20px !important; }
      .table { width: 100% !important; font-size: 12px !important; }
      .card-body { padding: 15px !important; }
      .badge, .pill { border: 1px solid #000 !important; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-info-circle"></i> Detalles de Medicina</h1>
        <div>
          <!-- Solo botón de imprimir -->
          <button class="btn btn-dark btn-sm no-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir Todo
          </button>
          <a href="medicinas.php" class="btn btn-secondary btn-sm no-print">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary">
        <div class="card-body">
        <?php if(!$med): ?>
          <div class="alert alert-warning mb-0">No se encontró la medicina solicitada.</div>
        <?php else: ?>
          <div class="row">
            <div class="col-md-12">
              <span class="badge badge-primary">
                <?= htmlspecialchars($med['nombre_medicamento']) ?>
              </span>
            </div>
          </div>
          <br>
          <div class="row info-box">
            <div class="col-md-6">
              <div><b>Principio Activo:</b> <?= htmlspecialchars($med['principio_activo'] ?: '—') ?></div>
              <div><b>Presentación:</b> <?= htmlspecialchars($med['presentacion'] ?: '—') ?></div>
              <div><b>Laboratorio:</b> <?= htmlspecialchars($med['laboratorio'] ?: '—') ?></div>
            </div>
            <div class="col-md-6">
              <div><b>Disponible:</b> <?= (int)$med['stock_actual'] ?> unidades</div>
              <div><b>Stock mínimo:</b> <?= (int)$med['stock_minimo'] ?></div>
              <div><b>Pacientes Activos:</b> <?= count($pac) ?></div>
              <div><b>Tipo:</b>
                <span class="pill <?= ($med['tipo_medicamento']==='no_controlado'?'pill--ok':'pill--warn') ?>">
                  <?= htmlspecialchars($med['tipo_medicamento']) ?>
                </span>
              </div>
            </div>
          </div>
        <?php endif; ?>
        </div>
      </div>

      <div class="card card-outline card-secondary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-users"></i> Pacientes que usan esta medicina</h3>
        </div>
        <div class="card-body table-responsive">
          <table id="tblPac" class="table table-striped table-bordered">
            <thead>
              <tr>
                <th>#</th>
                <th>Paciente</th>
                <th>Médico</th>
                <th>Dosis</th>
                <th>Frecuencia</th>
                <th>Motivo/Diagnóstico</th>
                <th>Duración</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $i=1;
              foreach($pac as $r):
                $ts = strtotime($r['fecha'] ?? '');
                $fecha = $ts ? date('d/m/Y h:i a', $ts) : '—';
              ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($r['paciente']) ?></td>
                <td><?= htmlspecialchars($r['medico']) ?></td>
                <td><?= htmlspecialchars($r['dosis']) ?></td>
                <td><?= htmlspecialchars($r['frecuencia']) ?></td>
                <td><?= htmlspecialchars($r['motivo_diagnostico']) ?></td>
                <td><?= htmlspecialchars($r['duracion']) ?></td>
                <td><?= $fecha ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php if (!$pac): ?>
            <em>No hay pacientes registrados para esta medicina.</em>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/data_tables_js.php'; ?>
<?php include './config/site_js_links.php'; ?>

<!-- DataTables Buttons deps -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

<script>
$(function(){
  $("#tblPac").DataTable({
    responsive: true,
    dom: '<"row mb-2"<"col-md-6"l><"col-md-6 text-right"B>>rtip',
    buttons: [
      {extend:'copyHtml5', text:'Copiar', className:'btn btn-sm btn-secondary'},
      {extend:'csvHtml5', text:'CSV', className:'btn btn-sm btn-info'},
      {extend:'excelHtml5', text:'Excel', className:'btn btn-sm btn-success'},
      {extend:'pdfHtml5', text:'PDF', className:'btn btn-sm btn-danger', orientation:'landscape', pageSize:'LETTER'},
      {extend:'print', text:'Imprimir Tabla', className:'btn btn-sm btn-dark'},
      {extend:'colvis', text:'Columnas', className:'btn btn-sm btn-warning'}
    ],
    language: {
      sLengthMenu: "Mostrar _MENU_",
      sSearch: "Buscar:",
      sZeroRecords: "No se encontraron resultados",
      sInfo: "Mostrando _START_ a _END_ de _TOTAL_",
      sInfoEmpty: "Mostrando 0 a 0 de 0",
      sInfoFiltered: "(filtrado de _MAX_)",
      oPaginate: {
        sFirst: "Primero",
        sLast: "Último", 
        sNext: "Siguiente",
        sPrevious: "Anterior"
      }
    },
    order: [[7, 'desc']]
  });
});
</script>
</body>
</html>