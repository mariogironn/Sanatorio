<?php
// ver_detalle_medicina.php — Detalle imprimible de una medicina
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: medicinas.php'); exit; }

// ===== Medicina
$med = null;
try {
  $stmt = $con->prepare("
    SELECT m.*,
           (SELECT COUNT(*) FROM paciente_medicinas pm
             WHERE pm.medicina_id = m.id AND pm.estado = 'activo') AS pacientes_activos
      FROM medicamentos m
     WHERE m.id = :id
     LIMIT 1
  ");
  $stmt->execute([':id'=>$id]);
  $med = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $med = null; }

if (!$med) { header('Location: medicinas.php'); exit; }

// ===== Pacientes que usan la medicina
$rows = [];
try {
  $q = $con->prepare("
    SELECT p.nombre AS paciente,
           u.nombre_mostrar AS medico,
           pm.dosis, pm.frecuencia, pm.motivo_prescripcion,
           pm.duracion_tratamiento, pm.fecha_asignacion
      FROM paciente_medicinas pm
      JOIN pacientes p ON p.id_paciente = pm.paciente_id
 LEFT JOIN usuarios  u ON u.id = pm.usuario_id
     WHERE pm.medicina_id = :id AND pm.estado = 'activo'
  ORDER BY pm.fecha_asignacion DESC
  ");
  $q->execute([':id'=>$id]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $rows = []; }

// Helpers
$nombre   = (string)$med['nombre_medicamento'];
$generico = (string)($med['nombre_generico'] ?? '');
$act      = (int)($med['stock_actual'] ?? 0);
$min      = (int)($med['stock_minimo'] ?? 0);
$tipo     = (string)($med['tipo_medicamento'] ?? 'no_controlado');
$badgeTipo= $tipo === 'controlado' ? 'warning' : 'success'; // verde para no_controlado
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
    .header-card{border-left:4px solid #007bff}
    .kv-line{margin:.4rem 0;font-size:1.05rem}
    .kv-line strong{min-width:180px;display:inline-block}
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
          <a href="agregar_medicina.php?edit=<?= $id ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-edit"></i> Editar
          </a>
          <button class="btn btn-secondary btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir página
          </button>
          <a href="medicinas.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>
      </div>
    </section>

    <section class="content">
      <!-- Encabezado -->
      <div class="card header-card">
        <div class="card-body">
          <h5 class="mb-3">
            <span class="badge badge-primary"><?= htmlspecialchars($nombre) ?></span>
            <?php if ($generico): ?>
              <small class="text-muted ml-2"><?= htmlspecialchars($generico) ?></small>
            <?php endif; ?>
          </h5>

          <div class="kv-line">
            <strong>Principio Activo:</strong>
            <?= htmlspecialchars($med['principio_activo'] ?? '—') ?>
          </div>

          <div class="kv-line">
            <strong>Stock Actual:</strong>
            <?= $act ?> unidades
          </div>

          <div class="kv-line">
            <strong>Stock Mínimo:</strong>
            <?= $min ?> unidades
          </div>

          <div class="kv-line">
            <strong>Tipo:</strong>
            <span class="badge badge-<?= $badgeTipo ?>">
              <?= $tipo === 'controlado' ? 'controlado' : 'no_controlado' ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Tabla de pacientes -->
      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="fas fa-user-md"></i> Pacientes que usan esta medicina</h3>
        </div>
        <div class="card-body table-responsive">
          <table id="tblPac" class="table table-striped table-bordered">
            <thead class="bg-light">
              <tr>
                <th class="text-center">#</th>
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
              <?php $i=0; foreach($rows as $r): $i++; ?>
              <tr>
                <td class="text-center"><?= $i ?></td>
                <td><?= htmlspecialchars($r['paciente']) ?></td>
                <td><?= htmlspecialchars($r['medico'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['dosis']) ?></td>
                <td><?= htmlspecialchars($r['frecuencia']) ?></td>
                <td><?= htmlspecialchars($r['motivo_prescripcion'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['duracion_tratamiento'] ?? '') ?></td>
                <td><?= date('d/m/Y h:i a', strtotime($r['fecha_asignacion'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>

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
    $('#tblPac').DataTable({
      responsive:true,
      dom:'<"row mb-2"<"col-md-6"l><"col-md-6 text-right"B>>rtip',
      buttons:[
        {extend:'copyHtml5', text:'Copiar',  className:'btn btn-sm btn-secondary'},
        {extend:'csvHtml5',  text:'CSV',     className:'btn btn-sm btn-info'},
        {extend:'excelHtml5',text:'Excel',   className:'btn btn-sm btn-success'},
        {extend:'pdfHtml5',  text:'PDF',     className:'btn btn-sm btn-danger', orientation:'landscape', pageSize:'LETTER'},
        {extend:'print',     text:'Imprimir',className:'btn btn-sm btn-dark'},
        {extend:'colvis',    text:'Columnas',className:'btn btn-sm btn-warning'}
      ],
      language:{
        sLengthMenu:"Mostrar _MENU_", sSearch:"Buscar:", sZeroRecords:"No se encontraron resultados",
        sInfo:"Mostrando _START_ a _END_ de _TOTAL_", sInfoEmpty:"Mostrando 0 a 0 de 0",
        sInfoFiltered:"(filtrado de _MAX_)",
        oPaginate:{sFirst:"Primero",sLast:"Último",sNext:"Siguiente",sPrevious:"Anterior"}
      },
      order:[[7,'desc']]
    });
  });
</script>
</body>
</html>
