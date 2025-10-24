<?php
include './config/connection.php';
include './common_service/common_functions.php';

$date = date('Y-m-d');

$year =  date('Y');
$month =  date('m');

$queryToday = "SELECT count(*) as `today` 
  from `visitas_pacientes` 
  where `fecha_visita` = '$date';";

$queryWeek = "SELECT count(*) as `week` 
  from `visitas_pacientes` 
  where YEARWEEK(`fecha_visita`) = YEARWEEK('$date');";

$queryYear = "SELECT count(*) as `year` 
  from `visitas_pacientes` 
  where YEAR(`fecha_visita`) = YEAR('$date');";

$queryMonth = "SELECT count(*) as `month` 
  from `visitas_pacientes` 
  where YEAR(`fecha_visita`) = $year and 
  MONTH(`fecha_visita`) = $month;";

$todaysCount = 0;
$currentWeekCount = 0;
$currentMonthCount = 0;
$currentYearCount = 0;

try {
  $stmtToday = $con->prepare($queryToday);
  $stmtToday->execute();
  $r = $stmtToday->fetch(PDO::FETCH_ASSOC);
  $todaysCount = $r['today'];

  $stmtWeek = $con->prepare($queryWeek);
  $stmtWeek->execute();
  $r = $stmtWeek->fetch(PDO::FETCH_ASSOC);
  $currentWeekCount = $r['week'];

  $stmtYear = $con->prepare($queryYear);
  $stmtYear->execute();
  $r = $stmtYear->fetch(PDO::FETCH_ASSOC);
  $currentYearCount = $r['year'];

  $stmtMonth = $con->prepare($queryMonth);
  $stmtMonth->execute();
  $r = $stmtMonth->fetch(PDO::FETCH_ASSOC);
  $currentMonthCount = $r['month'];
} catch (PDOException $ex) {
  echo $ex->getMessage();
  echo $ex->getTraceAsString();
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Sanatorio La Esperanza</title>
  <style>
    .dark-mode .bg-fuchsia,
    .dark-mode .bg-maroon {
      color: #fff !important;
    }
  </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
  <!-- Site wrapper -->
  <div class="wrapper">
    <!-- Navbar -->
    <?php include './config/header.php'; ?>
    <?php include './config/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

      <!-- NUEVO CONTENIDO -->
      <section class="content pt-4">
        <div class="container-fluid text-center">

          <!-- Logo -->
          <div class="mb-3">
            <img src="user_images/sanatorio.png" alt="Sanatorio" width="180">
          </div>

          <!-- Breadcrumb -->
          <div class="text-muted mb-3">
            Home &nbsp;/&nbsp; <span class="text-primary">Dashboard</span>
          </div>

          <!-- Derechos reservados -->
          <div class="text-center mt-4" style="font-size: 14px; color: black;">
            <strong style="color: #007bff;">LA ESPERANZA</strong> © 2025<br>
            Todos los Derechos Reservados.<br>
            Versión 1.0.0 
          </div>

        </div>
      </section>

      <!-- CONTENIDO ORIGINAL COMENTADO -->
      <!--
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1>Pacientes</h1>
            </div>
          </div>
        </div>
      </section>

      <section class="content">
        <div class="container-fluid">
          <div class="row">
            <div class="col-lg-3 col-6">
              <div class="small-box bg-maroon">
                <div class="inner">
                  <h3><?php echo $todaysCount; ?></h3>
                  <p>Hoy</p>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-6">
              <div class="small-box bg-maroon">
                <div class="inner">
                  <h3><?php echo $currentWeekCount; ?></h3>
                  <p>Esta Semana</p>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-6">
              <div class="small-box bg-maroon text-reset">
                <div class="inner">
                  <h3><?php echo $currentMonthCount; ?></h3>
                  <p>Este Mes</p>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-6">
              <div class="small-box bg-maroon text-reset">
                <div class="inner">
                  <h3><?php echo $currentYearCount; ?></h3>
                  <p>Este Año</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
      -->

    </div>
    <!-- /.content-wrapper -->

    <?php include './config/footer.php'; ?>
    <!-- /.control-sidebar -->
  </div>
  <!-- ./wrapper -->

  <?php include './config/site_js_links.php'; ?>
  <script>
    $(function() {
      showMenuSelected("#mnu_dashboard", "");
    });
  </script>

</body>

</html>
