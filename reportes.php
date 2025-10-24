<?php
// Conexión y helpers
include './config/connection.php';
include './common_service/common_functions.php';

// ===== Sucursal activa (para filtrar reportes) =====
$branchId = (int)($_SESSION['sucursal_activa'] ?? ($_SESSION['id_sucursal_activa'] ?? 0));
$branchName = '';
if ($branchId > 0) {
  try {
    $st = $con->prepare("SELECT nombre FROM sucursales WHERE id = :id AND estado = 1");
    $st->execute([':id' => $branchId]);
    $branchName = (string)($st->fetchColumn() ?: '');
  } catch (Throwable $e) { /* no romper vista */ }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <title>Reportes</title>
</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
  <div class="wrapper">
    <?php include './config/header.php'; include './config/sidebar.php'; ?>

    <div class="content-wrapper">
      <!-- Título -->
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1><i class="fas fa-file-alt"></i> Reportes</h1>
            </div>
          </div>
          <?php if ($branchId > 0 && $branchName !== ''): ?>
          <div class="row">
            <div class="col-sm-6">
              <div class="alert alert-info py-1 mb-0">
                <i class="fas fa-store"></i> Sucursal activa: <strong><?php echo htmlspecialchars($branchName); ?></strong>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="content">
        <!-- Reporte: Visitas por rango de fechas -->
        <div class="card card-outline card-primary rounded-0 shadow">
          <div class="card-header">
            <h3 class="card-title">Visitas de pacientes entre dos fechas</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Colapsar">
                <i class="fas fa-minus"></i>
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <?php
                // Campos de fecha (helper)
                echo getDateTextBox('Desde', 'patients_from');
                echo getDateTextBox('Hasta', 'patients_to');
              ?>
              <div class="col-md-2">
                <label>&nbsp;</label>
                <button type="button" id="print_visits" class="btn btn-primary btn-sm btn-flat btn-block">
                  Generar PDF
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Reporte: Enfermedad por rango de fechas -->
        <div class="card card-outline card-primary rounded-0 shadow">
          <div class="card-header">
            <h3 class="card-title">Informe por enfermedad entre dos fechas</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Colapsar">
                <i class="fas fa-minus"></i>
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <label>Enfermedad</label>
                <input id="disease" class="form-control form-control-sm rounded-0" />
              </div>
              <?php
                echo getDateTextBox('Desde', 'disease_from');
                echo getDateTextBox('Hasta', 'disease_to');
              ?>
              <div class="col-md-2">
                <label>&nbsp;</label>
                <button type="button" id="print_diseases" class="btn btn-primary btn-sm btn-flat btn-block">
                  Generar PDF
                </button>
              </div>
            </div>
          </div>
        </div>

      </section>
    </div>

    <?php include './config/footer.php'; ?>
  </div>

  <?php include './config/site_js_links.php'; ?>
  <script src="plugins/moment/moment.min.js"></script>
  <script src="plugins/daterangepicker/daterangepicker.js"></script>
  <script src="plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>

  <script>
    // Marca menú activo
    showMenuSelected("#mnu_reports", "#mi_reports");

    // Sucursal activa (inyectada desde PHP)
    const __BRANCH_ID = <?php echo (int)$branchId; ?>;

    // Fallback sencillo si no existe showCustomMessage
    function _msg(txt){ if (typeof showCustomMessage==='function') showCustomMessage(txt); else alert(txt); }

    $(function() {
      // Datepickers
      $('#patients_from, #patients_to, #disease_from, #disease_to').datetimepicker({ format: 'L' });

      // PDF: visitas de pacientes
      $("#print_visits").click(function() {
        var from = $("#patients_from").val();
        var to   = $("#patients_to").val();

        if (!__BRANCH_ID) { _msg('Selecciona la sucursal activa (arriba a la derecha).'); return; }
        if (from !== '' && to !== '') {
          var url = "imprimir_visitas_pacientes.php?from=" + encodeURIComponent(from) +
                    "&to=" + encodeURIComponent(to) +
                    "&id_sucursal=" + encodeURIComponent(__BRANCH_ID);
          var win = window.open(url, "_blank");
          if (win) win.focus(); else _msg('Permita las ventanas emergentes');
        } else {
          _msg('Completa las dos fechas.');
        }
      });

      // PDF: enfermedades por rango
      $("#print_diseases").click(function() {
        var from    = $("#disease_from").val();
        var to      = $("#disease_to").val();
        var disease = $("#disease").val().trim();

        if (!__BRANCH_ID) { _msg('Selecciona la sucursal activa (arriba a la derecha).'); return; }
        if (from !== '' && to !== '' && disease !== '') {
          var url = "imprimir_enfermedades.php?from=" + encodeURIComponent(from) +
                    "&to=" + encodeURIComponent(to) +
                    "&disease=" + encodeURIComponent(disease) +
                    "&id_sucursal=" + encodeURIComponent(__BRANCH_ID);
          var win = window.open(url, "_blank");
          if (win) win.focus(); else _msg('Permita las ventanas emergentes');
        } else {
          _msg('Completa enfermedad y rango de fechas.');
        }
      });
    });
  </script>
</body>
</html>
