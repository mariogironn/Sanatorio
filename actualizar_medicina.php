<?php
// Conexión PDO ($con)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';

$message = '';

// === Auditoría ===
require_once __DIR__ . '/common_service/auditoria_service.php';
$haveHelpers = @include_once __DIR__ . '/common_service/audit_helpers.php';

// ===== Guardar (POST) =====
if (isset($_POST['save_medicine'])) {
  $id = (int)($_POST['hidden_id'] ?? 0);
  $medicineName = trim($_POST['medicine_name'] ?? '');
  $medicineName = ucwords(strtolower($medicineName));

  if ($id <= 0) {
    header("Location: medicinas.php");
    exit;
  }

  if ($medicineName !== '') {
    try {
      // Traer registro original para comparar
      $stOrig = $con->prepare("SELECT id, nombre_medicamento FROM medicamentos WHERE id = :id LIMIT 1");
      $stOrig->execute([':id' => $id]);
      $orig = $stOrig->fetch(PDO::FETCH_ASSOC);
      if (!$orig) {
        header("Location: medicinas.php");
        exit;
      }

      // Si no hay cambios, solo mensaje amable
      if ($medicineName === (string)$orig['nombre_medicamento']) {
        $message = "Sin cambios";
      } else {
        // Validar duplicado con otro ID
        $ck = $con->prepare("SELECT 1 FROM medicamentos WHERE nombre_medicamento = :n AND id <> :id LIMIT 1");
        $ck->execute([':n' => $medicineName, ':id' => $id]);
        if ($ck->fetchColumn()) {
          $message = "Este nombre de medicamento ya existe. Por favor elige otro.";
        } else {

          // === AUDITORÍA: snapshot ANTES (mejor esfuerzo)
          $antes = null;
          try {
            if ($haveHelpers && function_exists('audit_row')) {
              $antes = audit_row($con, 'medicamentos', 'id', $id);
            }
            if (!$antes) {
              $sf = $con->prepare("SELECT * FROM medicamentos WHERE id = :id");
              $sf->execute([':id' => $id]);
              $antes = $sf->fetch(PDO::FETCH_ASSOC) ?: ['id' => $id, 'nombre_medicamento' => $orig['nombre_medicamento']];
            }
          } catch (Throwable $e) {
            $antes = ['id' => $id, 'nombre_medicamento' => $orig['nombre_medicamento']];
          }

          // UPDATE
          $con->beginTransaction();
          $stmt = $con->prepare("
            UPDATE medicamentos
               SET nombre_medicamento = :nombre
             WHERE id = :id
          ");
          $stmt->execute([':nombre' => $medicineName, ':id' => $id]);
          $con->commit();

          $message = "Registro actualizado correctamente";

          // === AUDITORÍA: snapshot DESPUÉS + audit_update (estado_resultante = 'activo')
          try {
            $despues = null;
            if ($haveHelpers && function_exists('audit_row')) {
              $despues = audit_row($con, 'medicamentos', 'id', $id);
            }
            if (!$despues) {
              $sf2 = $con->prepare("SELECT * FROM medicamentos WHERE id = :id");
              $sf2->execute([':id' => $id]);
              $despues = $sf2->fetch(PDO::FETCH_ASSOC) ?: ['id' => $id, 'nombre_medicamento' => $medicineName];
            }
            // ✅ marcamos 'activo' para que se vea en la columna Estado
            audit_update($con, 'Medicinas', 'medicamentos', $id, $antes, $despues, 'activo');
          } catch (Throwable $eAud) {
            error_log('AUDITORIA UPDATE medicamentos: '.$eAud->getMessage());
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
    $message = 'El nombre no puede estar vacío.';
  }

  header("Location: congratulation.php?goto_page=medicinas.php&message=" . urlencode($message));
  exit;
}

// ===== Cargar (GET) =====
try {
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) { header("Location: medicinas.php"); exit; }

  $stmt = $con->prepare("
    SELECT id, nombre_medicamento
      FROM medicamentos
     WHERE id = :id
     LIMIT 1
  ");
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    header("Location: medicinas.php");
    exit;
  }
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
  <title>Actualizar Medicina</title>
  <!-- (Opcional) DataTables si lo usaras acá -->
  <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
  <div class="wrapper">
    <?php include './config/header.php'; include './config/sidebar.php'; ?>

    <div class="content-wrapper">
      <!-- Título -->
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
              <h1>Medicinas</h1>
            </div>
            <div class="col-sm-6 text-right">
              <a href="medicinas.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Volver a Agregar Medicina
              </a>
            </div>
          </div>
        </div>
      </section>

      <!-- Formulario -->
      <section class="content">
        <div class="card card-outline card-primary rounded-0 shadow">
          <div class="card-header">
            <h3 class="card-title">Actualizar Medicina</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Colapsar">
                <i class="fas fa-minus"></i>
              </button>
            </div>
          </div>

        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="row">
              <input type="hidden" name="hidden_id" id="hidden_id" value="<?php echo htmlspecialchars($row['id']); ?>" />
              <div class="col-lg-4 col-md-4 col-sm-4 col-xs-10">
                <label>Nombre de Medicina</label>
                <input
                  type="text"
                  id="medicine_name"
                  name="medicine_name"
                  required
                  class="form-control form-control-sm rounded-0"
                  value="<?php echo htmlspecialchars($row['nombre_medicamento']); ?>" />
              </div>
              <div class="col-lg-1 col-md-2 col-sm-2 col-xs-2">
                <label>&nbsp;</label>
                <button type="submit" id="save_medicine" name="save_medicine" class="btn btn-primary btn-sm btn-flat btn-block">
                  Actualizar
                </button>
              </div>
            </div>
          </form>
        </div>

        </div>
      </section>
    </div>

    <?php
      include './config/footer.php';
      $message = '';
      if (isset($_GET['message'])) { $message = $_GET['message']; }
    ?>
  </div>

  <?php include './config/site_js_links.php'; ?>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    var message = '<?php echo isset($message) ? htmlspecialchars($message, ENT_QUOTES, "UTF-8") : ''; ?>';
    if (message !== '') {
      var lower = message.toLowerCase();
      var icono = (lower.includes('error') || lower.includes('existe') || lower.includes('vacío')) ? 'error' : 'success';

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
