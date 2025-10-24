<?php
// ==========================================================
// editar_paciente.php  (ACTUALIZADO con campos médicos)
// ==========================================================
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

include './config/connection.php';
include './common_service/common_functions.php';

// Auditoría
require_once __DIR__ . '/common_service/auditoria_service.php';
$haveHelpers = @include_once __DIR__ . '/common_service/audit_helpers.php';

// -------- helpers --------
function parseBirthDateFlexible(string $s): ?string {
  $s = trim($s);
  if ($s === '') return null;
  foreach (['d/m/Y','m/d/Y','Y-m-d'] as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $s);
    if ($dt && $dt->format($fmt) === $s) {
      return $dt->format('Y-m-d');
    }
  }
  // último intento: sin comparador estricto
  foreach (['d/m/Y','m/d/Y','Y-m-d'] as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $s);
    if ($dt instanceof DateTime) return $dt->format('Y-m-d');
  }
  return null;
}

// ==========================================================
// POST: guardar cambios
// ==========================================================
if (isset($_POST['save_Patient'])) {
  $id = (int)($_POST['hidden_id'] ?? 0);

  // cargar original
  $st = $con->prepare("SELECT id_paciente, nombre, direccion, dpi, fecha_nacimiento, telefono, genero, tipo_sangre, antecedentes_personales, antecedentes_familiares, estado
                       FROM pacientes WHERE id_paciente = :id LIMIT 1");
  $st->execute([':id'=>$id]);
  $orig = $st->fetch(PDO::FETCH_ASSOC);
  if (!$orig) {
    header("Location: congratulation.php?goto_page=pacientes.php&message=Paciente no existe");
    exit;
  }

  // nuevos valores
  $nombre   = ucwords(strtolower(trim($_POST['nombre']    ?? '')));
  $direccion= ucwords(strtolower(trim($_POST['direccion'] ?? '')));
  $dpi      = trim($_POST['dpi'] ?? '');
  $tel      = trim($_POST['telefono'] ?? '');
  $generoIn = $_POST['genero'] ?? '';
  $genero   = ($generoIn === '' ? (string)$orig['genero'] : $generoIn);
  $tipo_sangre = trim($_POST['tipo_sangre'] ?? '');
  $antecedentes_personales = trim($_POST['antecedentes_personales'] ?? '');
  $antecedentes_familiares = trim($_POST['antecedentes_familiares'] ?? '');
  $estado = $_POST['estado'] ?? 'activo';

  $fechaIn  = trim($_POST['fecha_nacimiento'] ?? '');
  $fn       = parseBirthDateFlexible($fechaIn);

  // validaciones mínimas
  if ($id <= 0 || $nombre==='' || $direccion==='' || $dpi==='' || !$fn || $tel==='') {
    header("Location: congratulation.php?goto_page=pacientes.php&message=Datos incompletos o fecha inválida");
    exit;
  }

  // SET dinámico + compare con original
  $sets = []; $p = [':id'=>$id];

  if ($nombre    !== (string)$orig['nombre'])             { $sets[]='nombre=:n';             $p[':n']=$nombre; }
  if ($direccion !== (string)$orig['direccion'])          { $sets[]='direccion=:d';          $p[':d']=$direccion; }
  if ($dpi       !== (string)$orig['dpi'])                { $sets[]='dpi=:dpi';              $p[':dpi']=$dpi; }
  $origFn = $orig['fecha_nacimiento'] ? (new DateTime($orig['fecha_nacimiento']))->format('Y-m-d') : null;
  if ($fn        !== $origFn)                             { $sets[]='fecha_nacimiento=:fn';  $p[':fn']=$fn; }
  if ($tel       !== (string)$orig['telefono'])           { $sets[]='telefono=:t';           $p[':t']=$tel; }
  if ($genero    !== (string)$orig['genero'])             { $sets[]='genero=:g';             $p[':g']=$genero; }
  if ($tipo_sangre !== (string)$orig['tipo_sangre'])      { $sets[]='tipo_sangre=:ts';       $p[':ts']=$tipo_sangre; }
  if ($antecedentes_personales !== (string)$orig['antecedentes_personales']) { $sets[]='antecedentes_personales=:ap'; $p[':ap']=$antecedentes_personales; }
  if ($antecedentes_familiares !== (string)$orig['antecedentes_familiares']) { $sets[]='antecedentes_familiares=:af'; $p[':af']=$antecedentes_familiares; }
  if ($estado    !== (string)$orig['estado'])             { $sets[]='estado=:e';             $p[':e']=$estado; }

  if (empty($sets)) {
    header("Location: congratulation.php?goto_page=pacientes.php&message=Sin cambios");
    exit;
  }

  // snapshot antes (best-effort)
  try {
    $antes = ($haveHelpers && function_exists('audit_row'))
      ? audit_row($con, 'pacientes', 'id_paciente', $id)
      : $orig;
  } catch (Throwable $e) { $antes = $orig; }

  // UPDATE
  $sql = "UPDATE pacientes SET ".implode(', ',$sets)." WHERE id_paciente=:id";
  try {
    $con->beginTransaction();
    $up = $con->prepare($sql);
    $up->execute($p);
    $con->commit();
  } catch (PDOException $e) {
    if ($con->inTransaction()) $con->rollBack();
    header("Location: congratulation.php?goto_page=pacientes.php&message=Error al actualizar");
    exit;
  }

  // snapshot después + auditoría
  try {
    $despues = ($haveHelpers && function_exists('audit_row'))
      ? audit_row($con, 'pacientes', 'id_paciente', $id)
      : (function($c,$id){ $q=$c->prepare("SELECT * FROM pacientes WHERE id_paciente=?"); $q->execute([$id]); return $q->fetch(PDO::FETCH_ASSOC); })($con,$id);

    audit_update($con, 'Pacientes', 'pacientes', $id, $antes, $despues, $estado);
  } catch (Throwable $eAud) {
    error_log('AUDITORIA UPDATE pacientes: '.$eAud->getMessage());
  }

  header("Location: congratulation.php?goto_page=pacientes.php&message=Paciente actualizado correctamente");
  exit;
}

// ==========================================================
// GET: cargar datos a editar
// ==========================================================
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: pacientes.php"); exit; }

$st = $con->prepare("
  SELECT id_paciente, nombre, direccion, dpi, fecha_nacimiento, telefono, genero, tipo_sangre, antecedentes_personales, antecedentes_familiares, estado
  FROM pacientes WHERE id_paciente = :id LIMIT 1
");
$st->execute([':id'=>$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { header("Location: pacientes.php"); exit; }

// fecha para UI
$fechaUI = '';
if (!empty($row['fecha_nacimiento'])) {
  $dt = new DateTime($row['fecha_nacimiento']);
  $fechaUI = $dt->format('d/m/Y'); // DD/MM/YYYY
}
$generoActual = (string)$row['genero'];
$estadoActual = (string)$row['estado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Editar Paciente</title>
  <link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <style>
    .required-field::after { content: " *"; color: #dc3545; }
    .form-section { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    .form-section h5 { color: #495057; border-bottom: 2px solid #007bff; padding-bottom: 8px; }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2 align-items-center">
          <div class="col-sm-6"><h1>Editar Información de Paciente</h1></div>
          <div class="col-sm-6 text-right">
            <a href="pacientes.php" class="btn btn-secondary btn-sm">
              <i class="fas fa-arrow-left"></i> Volver a Total Pacientes
            </a>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary rounded-0 shadow">
        <div class="card-header">
          <h3 class="card-title">Formulario de Edición</h3>
          <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
        </div>

        <div class="card-body">
          <form method="post" autocomplete="off">
            <input type="hidden" name="hidden_id" value="<?php echo (int)$row['id_paciente']; ?>">
            
            <!-- SECCIÓN: DATOS PERSONALES -->
            <div class="form-section">
              <h5><i class="fas fa-user"></i> Datos Personales</h5>
              <div class="row">
                <div class="col-lg-4">
                  <div class="form-group">
                    <label class="required-field">Nombre</label>
                    <input type="text" name="nombre" required class="form-control form-control-sm rounded-0"
                           value="<?php echo htmlspecialchars($row['nombre']); ?>">
                  </div>
                </div>
                <div class="col-lg-4">
                  <div class="form-group">
                    <label class="required-field">Dirección</label>
                    <input type="text" name="direccion" required class="form-control form-control-sm rounded-0"
                           value="<?php echo htmlspecialchars($row['direccion']); ?>">
                  </div>
                </div>
                <div class="col-lg-4">
                  <div class="form-group">
                    <label class="required-field">DPI</label>
                    <input type="text" name="dpi" required class="form-control form-control-sm rounded-0"
                           value="<?php echo htmlspecialchars($row['dpi']); ?>">
                  </div>
                </div>

                <div class="col-lg-4">
                  <div class="form-group">
                    <label class="required-field">Fecha de Nacimiento</label>
                    <div class="input-group date" id="fecha_nacimiento" data-target-input="nearest">
                      <input type="text"
                             name="fecha_nacimiento"
                             value="<?php echo htmlspecialchars($fechaUI); ?>"
                             class="form-control form-control-sm rounded-0 datetimepicker-input"
                             data-target="#fecha_nacimiento" autocomplete="off" required>
                      <div class="input-group-append" data-target="#fecha_nacimiento" data-toggle="datetimepicker">
                        <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-lg-4">
                  <div class="form-group">
                    <label class="required-field">Teléfono</label>
                    <input type="text" name="telefono" required class="form-control form-control-sm rounded-0"
                           value="<?php echo htmlspecialchars($row['telefono']); ?>">
                  </div>
                </div>

                <div class="col-lg-4">
                  <div class="form-group">
                    <label class="required-field">Género</label>
                    <select class="form-control form-control-sm rounded-0" name="genero" required>
                      <?php
                        $val = trim($generoActual);
                        if ($val !== '') {
                          $known = ['Masculino','Femenino','Otro','Mascul','Femen','No especificado'];
                          if (!in_array($val, $known, true)) {
                            echo '<option value="'.htmlspecialchars($val,ENT_QUOTES,'UTF-8').'" selected>'.
                                  htmlspecialchars($val,ENT_QUOTES,'UTF-8').'</option>';
                          }
                        }
                        echo getGender($generoActual);
                      ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECCIÓN: DATOS MÉDICOS -->
            <div class="form-section">
              <h5><i class="fas fa-heartbeat"></i> Datos Médicos</h5>
              <div class="row">
                <div class="col-lg-4">
                  <div class="form-group">
                    <label>Tipo de Sangre</label>
                    <select class="form-control form-control-sm rounded-0" name="tipo_sangre">
                      <option value="">Seleccionar</option>
                      <option value="A+" <?php echo ($row['tipo_sangre'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                      <option value="A-" <?php echo ($row['tipo_sangre'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                      <option value="B+" <?php echo ($row['tipo_sangre'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                      <option value="B-" <?php echo ($row['tipo_sangre'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                      <option value="AB+" <?php echo ($row['tipo_sangre'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                      <option value="AB-" <?php echo ($row['tipo_sangre'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                      <option value="O+" <?php echo ($row['tipo_sangre'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                      <option value="O-" <?php echo ($row['tipo_sangre'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                    </select>
                  </div>
                </div>
                
                <div class="col-lg-4">
                  <div class="form-group">
                    <label>Estado</label>
                    <select class="form-control form-control-sm rounded-0" name="estado">
                      <option value="activo" <?php echo ($estadoActual === 'activo') ? 'selected' : ''; ?>>Activo</option>
                      <option value="inactivo" <?php echo ($estadoActual === 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-lg-6">
                  <div class="form-group">
                    <label>Antecedentes Personales</label>
                    <textarea name="antecedentes_personales" class="form-control form-control-sm rounded-0" rows="3" 
                              placeholder="Ej: Diabetes tipo 2, Hipertensión, Cirugías previas..."><?php echo htmlspecialchars($row['antecedentes_personales'] ?? ''); ?></textarea>
                  </div>
                </div>
                
                <div class="col-lg-6">
                  <div class="form-group">
                    <label>Antecedentes Familiares</label>
                    <textarea name="antecedentes_familiares" class="form-control form-control-sm rounded-0" rows="3" 
                              placeholder="Ej: Padre con diabetes, Madre con hipertensión..."><?php echo htmlspecialchars($row['antecedentes_familiares'] ?? ''); ?></textarea>
                  </div>
                </div>
              </div>
            </div>

            <div class="clearfix my-2"></div>
            <div class="row">
              <div class="col-lg-10"></div>
              <div class="col-lg-2">
                <button type="submit" name="save_Patient" class="btn btn-primary btn-sm btn-flat btn-block">Guardar Cambios</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<script src="plugins/moment/moment.min.js"></script>
<script src="plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script>
  showMenuSelected("#mnu_patients", "#mi_patients");
  if (typeof moment!=='undefined') moment.locale('es');
  // Forzamos DD/MM/YYYY para coincidir con el parser PHP
  $('#fecha_nacimiento').datetimepicker({ format: 'DD/MM/YYYY', locale: 'es' });
</script>
</body>
</html>