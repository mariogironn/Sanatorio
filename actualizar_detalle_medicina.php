<?php
// actualizar_medicina_detalle.php

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

// === Auditoría ===
require_once __DIR__ . '/common_service/auditoria_service.php';
$haveHelpers = @include_once __DIR__ . '/common_service/audit_helpers.php';

$message = '';

// =================== POST: Guardar cambios ===================
if (isset($_POST['submit'])) {
  $id_detalle     = (int)($_POST['hidden_id'] ?? 0);
  $id_medicamento = (int)($_POST['medicine'] ?? 0);
  $empaque        = trim($_POST['packing'] ?? '');

  if ($id_detalle <= 0) {
    header("location:detalles_medicina.php");
    exit;
  }

  if ($id_medicamento > 0 && $empaque !== '') {
    try {
      // Traer original
      $stOrig = $con->prepare("
        SELECT d.id, d.id_medicamento, d.empaque
          FROM detalles_medicina d
         WHERE d.id = :id
         LIMIT 1
      ");
      $stOrig->execute([':id' => $id_detalle]);
      $orig = $stOrig->fetch(PDO::FETCH_ASSOC);

      if (!$orig) {
        $message = 'El detalle solicitado no existe.';
      } else {
        $noChange = ((int)$orig['id_medicamento'] === $id_medicamento) && ((string)$orig['empaque'] === $empaque);

        if ($noChange) {
          $message = 'Sin cambios.';
        } else {
          // Duplicado (mismo medicamento + empaque en otro registro)
          $ck = $con->prepare("
            SELECT 1
              FROM detalles_medicina
             WHERE id_medicamento = :idm
               AND empaque = :emp
               AND id <> :id
             LIMIT 1
          ");
          $ck->execute([':idm' => $id_medicamento, ':emp' => $empaque, ':id' => $id_detalle]);
          if ($ck->fetchColumn()) {
            $message = 'Ya existe ese empaque para la medicina seleccionada.';
          } else {

            // ===== AUDITORÍA: snapshot ANTES (mejor esfuerzo) =====
            $antes = null;
            try {
              if ($haveHelpers && function_exists('audit_row')) {
                $antes = audit_row($con, 'detalles_medicina', 'id', $id_detalle);
              }
              if (!$antes) {
                $sfA = $con->prepare("SELECT * FROM detalles_medicina WHERE id = :id");
                $sfA->execute([':id' => $id_detalle]);
                $antes = $sfA->fetch(PDO::FETCH_ASSOC) ?: ['id' => $id_detalle];
              }
            } catch (Throwable $e) {
              $antes = ['id' => $id_detalle];
            }

            // UPDATE
            $con->beginTransaction();
            $up = $con->prepare("
              UPDATE detalles_medicina
                 SET id_medicamento = :idm,
                     empaque        = :emp
               WHERE id = :id
            ");
            $up->execute([':idm' => $id_medicamento, ':emp' => $empaque, ':id' => $id_detalle]);
            $con->commit();

            $message = 'Información de la medicina actualizada correctamente.';

            // ===== AUDITORÍA: snapshot DESPUÉS + audit_update (estado_resultante = 'activo') =====
            try {
              $despues = null;
              if ($haveHelpers && function_exists('audit_row')) {
                $despues = audit_row($con, 'detalles_medicina', 'id', $id_detalle);
              }
              if (!$despues) {
                $sfD = $con->prepare("SELECT * FROM detalles_medicina WHERE id = :id");
                $sfD->execute([':id' => $id_detalle]);
                $despues = $sfD->fetch(PDO::FETCH_ASSOC) ?: ['id' => $id_detalle, 'id_medicamento'=>$id_medicamento, 'empaque'=>$empaque];
              }

              // Módulo: "Medicinas", Tabla: "detalles_medicina"
              audit_update($con, 'Medicinas', 'detalles_medicina', $id_detalle, $antes, $despues, 'activo');
            } catch (Throwable $eAud) {
              error_log('AUDITORIA UPDATE detalles_medicina: '.$eAud->getMessage());
            }
          }
        }
      }
    } catch (PDOException $ex) {
      if ($con->inTransaction()) $con->rollBack();
      echo $ex->getMessage();
      echo $ex->getTraceAsString();
      exit;
    }
  } else {
    $message = 'Completa todos los campos.';
  }

  // Nota: tu ruta de edición es actualizar_detalle_medicina.php (la conservamos tal cual)
  header("location:actualizar_detalle_medicina.php?medicine_detail_id={$id_detalle}&message=" . urlencode($message));
  exit;
}

// =================== GET: Cargar datos para edición ===================
$id_detalle = (int)($_GET['medicine_detail_id'] ?? 0);
if ($id_detalle <= 0) {
  header("location:detalles_medicina.php");
  exit;
}

try {
  $st = $con->prepare("
    SELECT d.id, d.id_medicamento, d.empaque
      FROM detalles_medicina d
     WHERE d.id = :id
     LIMIT 1
  ");
  $st->execute([':id' => $id_detalle]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    header("location:detalles_medicina.php");
    exit;
  }

  $id_medicamento = (int)$row['id_medicamento'];
  $empaque        = (string)$row['empaque'];
  $medicinas = getMedicamentos($con, $id_medicamento);
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
  <?php include './config/data_tables_css.php'; ?>
  <title>Actualizar Información de Medicina</title>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
  <div class="wrapper">
    <?php include './config/header.php'; include './config/sidebar.php'; ?>

    <div class="content-wrapper">
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="mb-0">Información de Medicina</h1>
            </div>
            <div class="col-sm-6 text-right">
              <a href="detalles_medicina.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Volver a Información
              </a>
            </div>
          </div>
        </div>
      </section>

      <section class="content">
        <div class="card card-outline card-primary rounded-0 shadow">
          <div class="card-header">
            <h3 class="card-title">Actualizar Información de Medicina</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Colapsar">
                <i class="fas fa-minus"></i>
              </button>
            </div>
          </div>
          <div class="card-body">
            <form method="post" autocomplete="off">
              <input type="hidden" name="hidden_id" value="<?php echo (int)$id_detalle; ?>" />
              <div class="row">
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                  <label>Seleccionar Medicina</label>
                  <select id="medicine" name="medicine" class="form-control form-control-sm rounded-0" required>
                    <?php echo $medicinas; ?>
                  </select>
                </div>
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                  <label>Empaque</label>
                  <input id="packing" name="packing" class="form-control form-control-sm rounded-0" required
                         value="<?php echo htmlspecialchars($empaque, ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-lg-1 col-md-2 col-sm-4 col-xs-12">
                  <label>&nbsp;</label>
                  <button type="submit" name="submit" class="btn btn-primary btn-sm btn-flat btn-block">Actualizar</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>
    </div>

    <?php
      include './config/footer.php';
      $message = $_GET['message'] ?? '';
    ?>
  </div>

  <?php include './config/site_js_links.php'; ?>
  <?php include './config/data_tables_js.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    showMenuSelected("#mnu_medicines", "#mi_medicine_details");
    var message = '<?php echo htmlspecialchars($message ?? "", ENT_QUOTES, "UTF-8"); ?>';
    if (message !== '') {
      var lower = message.toLowerCase();
      var icono = (lower.includes('error') || lower.includes('existe') || lower.includes('completa')) ? 'error' : 'success';
      Swal.fire({
        title: 'Mensaje',
        text: message,
        icon: icono,
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '#3085d6'
      });
    }
  </script>
</body>
</html>
