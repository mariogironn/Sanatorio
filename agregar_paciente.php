<?php
// agregar_paciente.php - FORMULARIO PARA CREAR PACIENTE
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

// Auditoría
require_once __DIR__ . '/common_service/auditoria_service.php';
$haveHelpers = @include_once __DIR__ . '/common_service/audit_helpers.php';

$message = '';

// Detectar PK de pacientes
$PKP = 'id_paciente';
try {
    $ck = $con->query("SHOW COLUMNS FROM pacientes LIKE 'id_paciente'");
    if (!$ck || $ck->rowCount() === 0) { $PKP = 'id'; }
} catch (Throwable $e) { /* noop */ }

// Helper para fecha
function parseBirthDateFlexible(string $s): ?string {
    $s = trim($s);
    if ($s === '') return null;
    
    $dt = DateTime::createFromFormat('d/m/Y', $s);
    if ($dt && $dt->format('d/m/Y') === $s) return $dt->format('Y-m-d');
    
    $dt = DateTime::createFromFormat('m/d/Y', $s);
    if ($dt && $dt->format('m/d/Y') === $s) return $dt->format('Y-m-d');
    
    $dt = DateTime::createFromFormat('Y-m-d', $s);
    if ($dt && $dt->format('Y-m-d') === $s) return $dt->format('Y-m-d');
    
    return null;
}

// Procesar formulario
if (isset($_POST['save_Patient'])) {
    $patientName = trim($_POST['nombre'] ?? '');
    $address = trim($_POST['direccion'] ?? '');
    $cnic = trim($_POST['dpi'] ?? '');
    $dateBirthIn = trim($_POST['fecha_nacimiento'] ?? '');
    $phoneNumber = trim($_POST['telefono'] ?? '');
    $gender = $_POST['genero'] ?? '';
    $bloodType = trim($_POST['tipo_sangre'] ?? '');
    $antecedentesPersonales = trim($_POST['antecedentes_personales'] ?? '');
    $antecedentesFamiliares = trim($_POST['antecedentes_familiares'] ?? '');

    // Formatear datos
    $patientName = ucwords(strtolower($patientName));
    $address = ucwords(strtolower($address));
    $dateBirth = parseBirthDateFlexible($dateBirthIn);

    // Validaciones
    if ($patientName !== '' && $address !== '' && $cnic !== '' && $dateBirth && $phoneNumber !== '' && $gender !== '') {
        $sql = "INSERT INTO pacientes 
                (nombre, direccion, dpi, fecha_nacimiento, telefono, genero, 
                 tipo_sangre, antecedentes_personales, antecedentes_familiares, estado) 
                VALUES 
                (:n, :d, :dpi, :fn, :t, :g, :ts, :ap, :af, 'activo')";
        
        try {
            $con->beginTransaction();
            $stmt = $con->prepare($sql);
            $stmt->execute([
                ':n'   => $patientName,
                ':d'   => $address,
                ':dpi' => $cnic,
                ':fn'  => $dateBirth,
                ':t'   => $phoneNumber,
                ':g'   => $gender,
                ':ts'  => $bloodType,
                ':ap'  => $antecedentesPersonales,
                ':af'  => $antecedentesFamiliares
            ]);
            
            $newId = (int)$con->lastInsertId();
            $con->commit();

            // Auditoría
            try {
                if ($newId <= 0) {
                    $aux = $con->prepare("SELECT `$PKP` AS pk FROM pacientes WHERE dpi = :dpi ORDER BY `$PKP` DESC LIMIT 1");
                    $aux->execute([':dpi' => $cnic]);
                    $rowAux = $aux->fetch(PDO::FETCH_ASSOC);
                    if ($rowAux && isset($rowAux['pk'])) { $newId = (int)$rowAux['pk']; }
                }

                if (function_exists('audit_row')) {
                    $despues = audit_row($con, 'pacientes', $PKP, $newId);
                } else {
                    $sf = $con->prepare("SELECT * FROM pacientes WHERE `$PKP` = :i");
                    $sf->execute([':i'=>$newId]);
                    $despues = $sf->fetch(PDO::FETCH_ASSOC) ?: [$PKP=>$newId];
                }

                audit_create($con, 'Pacientes', 'pacientes', $newId, $despues, 'activo');
            } catch (Throwable $eAud) {
                error_log('AUDITORIA CREATE pacientes: '.$eAud->getMessage());
            }

            $message = 'Paciente agregado exitosamente.';
            header("Location: congratulation.php?goto_page=pacientes.php&message=".$message);
            exit;
            
        } catch (PDOException $ex) {
            if ($con->inTransaction()) { $con->rollBack(); }
            $message = 'Error al guardar el paciente: ' . $ex->getMessage();
        }
    } else {
        $message = 'Completa todos los campos obligatorios.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <title>Agregar Nuevo Paciente</title>
    <style>
        .required-field::after { content: " *"; color: #dc3545; }
        .form-section { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .form-section h5 { color: #495057; border-bottom: 2px solid #007bff; padding-bottom: 8px; }
        .btn-icon { 
            width: 35px; 
            height: 35px; 
            padding: 0; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
    <?php include './config/header.php'; include './config/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6">
                        <h1><i class="fas fa-user-plus"></i> Agregar Nuevo Paciente</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="pacientes.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver a la Lista
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="card card-outline card-primary rounded-0 shadow">
                <div class="card-header">
                    <h3 class="card-title">Formulario de Registro</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-warning"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form method="post" autocomplete="off">
                        <!-- SECCIÓN: DATOS PERSONALES -->
                        <div class="form-section">
                            <h5><i class="fas fa-user"></i> Datos Personales</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required-field">Nombre Completo</label>
                                        <input type="text" name="nombre" required class="form-control form-control-sm rounded-0" 
                                               placeholder="Ej: Juan Pérez García" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required-field">DPI</label>
                                        <input type="text" name="dpi" required class="form-control form-control-sm rounded-0" 
                                               placeholder="Ej: 1234567890101" value="<?php echo htmlspecialchars($_POST['dpi'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required-field">Fecha de Nacimiento</label>
                                        <div class="input-group date" id="fecha_nacimiento" data-target-input="nearest">
                                            <input type="text" class="form-control form-control-sm rounded-0 datetimepicker-input" 
                                                   data-target="#fecha_nacimiento" name="fecha_nacimiento" required 
                                                   autocomplete="off" placeholder="DD/MM/AAAA" value="<?php echo htmlspecialchars($_POST['fecha_nacimiento'] ?? ''); ?>">
                                            <div class="input-group-append" data-target="#fecha_nacimiento" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fas fa-calendar-alt"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required-field">Género</label>
                                        <select class="form-control form-control-sm rounded-0" name="genero" required>
                                            <?php echo getGender($_POST['genero'] ?? ''); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN: DATOS MÉDICOS -->
                        <div class="form-section">
                            <h5><i class="fas fa-heartbeat"></i> Datos Médicos</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tipo de Sangre</label>
                                        <select class="form-control form-control-sm rounded-0" name="tipo_sangre">
                                            <option value="">Seleccionar</option>
                                            <option value="A+" <?php echo ($_POST['tipo_sangre'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                            <option value="A-" <?php echo ($_POST['tipo_sangre'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                            <option value="B+" <?php echo ($_POST['tipo_sangre'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                            <option value="B-" <?php echo ($_POST['tipo_sangre'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                            <option value="AB+" <?php echo ($_POST['tipo_sangre'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                            <option value="AB-" <?php echo ($_POST['tipo_sangre'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                            <option value="O+" <?php echo ($_POST['tipo_sangre'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                            <option value="O-" <?php echo ($_POST['tipo_sangre'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Antecedentes Personales</label>
                                        <textarea name="antecedentes_personales" class="form-control form-control-sm rounded-0" rows="2" 
                                                  placeholder="Ej: Diabetes tipo 2, Hipertensión, Cirugías previas..."><?php echo htmlspecialchars($_POST['antecedentes_personales'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label>Antecedentes Familiares</label>
                                        <textarea name="antecedentes_familiares" class="form-control form-control-sm rounded-0" rows="2" 
                                                  placeholder="Ej: Padre con diabetes, Madre con hipertensión..."><?php echo htmlspecialchars($_POST['antecedentes_familiares'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN: CONTACTO -->
                        <div class="form-section">
                            <h5><i class="fas fa-address-book"></i> Información de Contacto</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required-field">Dirección</label>
                                        <input type="text" name="direccion" required class="form-control form-control-sm rounded-0" 
                                               placeholder="Ej: 12 Avenida A, Zona 1" value="<?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required-field">Teléfono</label>
                                        <input type="text" name="telefono" required class="form-control form-control-sm rounded-0" 
                                               placeholder="Ej: 5555-1234" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-8"></div>
                            <div class="col-md-2">
                                <a href="pacientes.php" class="btn btn-secondary btn-sm btn-block">Cancelar</a>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="save_Patient" class="btn btn-primary btn-sm btn-block">
                                    <i class="fas fa-save"></i> Guardar
                                </button>
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
    
    $(function () {
        $('#fecha_nacimiento').datetimepicker({
            format: 'DD/MM/YYYY',
            locale: 'es',
            maxDate: new Date()
        });
    });
</script>
</body>
</html>