<?php
// editar_prescripcion.php - Editar prescripción
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

$id_prescripcion = $_GET['id'] ?? 0;

// Obtener datos de la prescripción
$prescripcion = null;
$medicinas = [];

try {
    // Datos principales
    $query = "SELECT p.*, pac.nombre as paciente_nombre
              FROM prescripciones p
              INNER JOIN pacientes pac ON p.id_paciente = pac.id_paciente
              WHERE p.id_prescripcion = ?";
    
    $stmt = $con->prepare($query);
    $stmt->execute([$id_prescripcion]);
    $prescripcion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prescripcion) {
        header('Location: nueva_prescripcion.php');
        exit;
    }

    // Medicinas de la prescripción
    $queryMedicinas = "SELECT dp.*, m.nombre_medicamento 
                       FROM detalle_prescripciones dp
                       INNER JOIN medicamentos m ON dp.id_medicamento = m.id
                       WHERE dp.id_prescripcion = ?
                       ORDER BY dp.id_detalle ASC";
    
    $stmtMedicinas = $con->prepare($queryMedicinas);
    $stmtMedicinas->execute([$id_prescripcion]);
    $medicinas = $stmtMedicinas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    echo $ex->getMessage();
    exit;
}

// Obtener listas para los selectores
$pacientes = [];
$medicamentos = [];
$sucursales = [];

try {
    $queryPacientes = "SELECT id_paciente, nombre FROM pacientes WHERE estado = 'activo' ORDER BY nombre ASC";
    $stmtPacientes = $con->prepare($queryPacientes);
    $stmtPacientes->execute();
    $pacientes = $stmtPacientes->fetchAll(PDO::FETCH_ASSOC);

    $queryMedicamentos = "SELECT id, nombre_medicamento FROM medicamentos ORDER BY nombre_medicamento ASC";
    $stmtMedicamentos = $con->prepare($queryMedicamentos);
    $stmtMedicamentos->execute();
    $medicamentos = $stmtMedicamentos->fetchAll(PDO::FETCH_ASSOC);

    $querySucursales = "SELECT id, nombre FROM sucursales WHERE estado = 1 ORDER BY nombre ASC";
    $stmtSucursales = $con->prepare($querySucursales);
    $stmtSucursales->execute();
    $sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    echo $ex->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <title>Editar Prescripción</title>
    <style>
        .medicine-item { border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-bottom: 10px; background-color: #f8f9fa; }
        .btn-remove-medicine { margin-top: 25px; }
        .required-field::after { content: " *"; color: #dc3545; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
    <?php include './config/header.php'; include './config/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1><i class="fas fa-edit"></i> Editar Prescripción</h1>
                    </div>
                    <div class="col-sm-6">
                        <a href="nueva_prescripcion.php" class="btn btn-secondary btn-sm float-right">
                            <i class="fas fa-arrow-left"></i> Volver a la Lista
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if ($prescripcion): ?>
                <form id="formPrescripcion" action="ajax/actualizar_prescripcion.php" method="POST">
                    <input type="hidden" name="id_prescripcion" value="<?php echo $prescripcion['id_prescripcion']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Datos del Paciente -->
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-user-injured"></i> Datos del Paciente</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="required-field">Paciente</label>
                                        <select class="form-control" name="id_paciente" required>
                                            <option value="">-- Seleccione un paciente --</option>
                                            <?php foreach ($pacientes as $paciente): ?>
                                                <option value="<?php echo $paciente['id_paciente']; ?>" 
                                                    <?php echo ($prescripcion['id_paciente'] == $paciente['id_paciente']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($paciente['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="required-field">Fecha de Visita</label>
                                                <input type="date" class="form-control" name="fecha_visita" 
                                                       value="<?php echo $prescripcion['fecha_visita']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Próxima Visita</label>
                                                <input type="date" class="form-control" name="proxima_visita" 
                                                       value="<?php echo $prescripcion['proxima_visita']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Signos Vitales -->
                            <div class="card card-info mt-3">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-heartbeat"></i> Signos Vitales</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Peso (kg)</label>
                                                <input type="text" class="form-control" name="peso" 
                                                       value="<?php echo htmlspecialchars($prescripcion['peso']); ?>"
                                                       placeholder="Ej: 70.5">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Presión Arterial</label>
                                                <input type="text" class="form-control" name="presion" 
                                                       value="<?php echo htmlspecialchars($prescripcion['presion']); ?>"
                                                       placeholder="Ej: 120/80">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <!-- Diagnóstico y Sucursal -->
                            <div class="card card-warning">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-stethoscope"></i> Diagnóstico</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="required-field">Enfermedad/Diagnóstico</label>
                                        <input type="text" class="form-control" name="enfermedad" 
                                               value="<?php echo htmlspecialchars($prescripcion['enfermedad']); ?>"
                                               required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="required-field">Sucursal</label>
                                        <select class="form-control" name="sucursal" required>
                                            <option value="">-- Seleccione sucursal --</option>
                                            <?php foreach ($sucursales as $sucursal): ?>
                                                <option value="<?php echo htmlspecialchars($sucursal['nombre']); ?>" 
                                                    <?php echo ($prescripcion['sucursal'] == $sucursal['nombre']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Estado</label>
                                        <select class="form-control" name="estado">
                                            <option value="activa" <?php echo ($prescripcion['estado'] == 'activa') ? 'selected' : ''; ?>>Activa</option>
                                            <option value="completada" <?php echo ($prescripcion['estado'] == 'completada') ? 'selected' : ''; ?>>Completada</option>
                                            <option value="pendiente" <?php echo ($prescripcion['estado'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Medicinas -->
                            <div class="card card-success mt-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3 class="card-title"><i class="fas fa-pills"></i> Medicinas Recetadas</h3>
                                    <button type="button" class="btn btn-sm btn-primary" id="btnAddMedicine">
                                        <i class="fas fa-plus"></i> Agregar Medicina
                                    </button>
                                </div>
                                <div class="card-body" id="medicinesContainer">
                                    <?php if (empty($medicinas)): ?>
                                        <div class="text-muted text-center" id="noMedicinesMessage">
                                            No hay medicinas agregadas. Haga clic en "Agregar Medicina".
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($medicinas as $index => $medicina): ?>
                                        <div class="medicine-item" data-medicine-index="<?php echo $index; ?>">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="required-field">Medicina</label>
                                                        <select class="form-control medicine-select" name="medicinas[<?php echo $index; ?>][id_medicamento]" required>
                                                            <option value="">-- Seleccione medicina --</option>
                                                            <?php foreach ($medicamentos as $med): ?>
                                                                <option value="<?php echo $med['id']; ?>" 
                                                                    <?php echo ($medicina['id_medicamento'] == $med['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($med['nombre_medicamento']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label class="required-field">Caja de medicamentos</label>
                                                        <input type="text" class="form-control" name="medicinas[<?php echo $index; ?>][empaque]" 
                                                               value="<?php echo htmlspecialchars($medicina['empaque']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label class="required-field">Cantidad</label>
                                                        <input type="number" class="form-control" name="medicinas[<?php echo $index; ?>][cantidad]" 
                                                               value="<?php echo htmlspecialchars($medicina['cantidad']); ?>" min="1" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label class="required-field">Dosis</label>
                                                        <input type="text" class="form-control" name="medicinas[<?php echo $index; ?>][dosis]" 
                                                               value="<?php echo htmlspecialchars($medicina['dosis']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-danger btn-remove-medicine" 
                                                            onclick="removeMedicine(<?php echo $index; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Actualizar Prescripción
                            </button>
                            <a href="nueva_prescripcion.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> No se encontró la prescripción solicitada.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Template para nueva medicina -->
<script id="medicineTemplate" type="text/template">
<div class="medicine-item" data-medicine-index="{{index}}">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label class="required-field">Medicina</label>
                <select class="form-control medicine-select" name="medicinas[{{index}}][id_medicamento]" required>
                    <option value="">-- Seleccione medicina --</option>
                    <?php foreach ($medicamentos as $med): ?>
                        <option value="<?php echo $med['id']; ?>"><?php echo htmlspecialchars($med['nombre_medicamento']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label class="required-field">Empaque</label>
                <input type="text" class="form-control" name="medicinas[{{index}}][empaque]" placeholder="Ej: 10" required>
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label class="required-field">Cantidad</label>
                <input type="number" class="form-control" name="medicinas[{{index}}][cantidad]" min="1" value="1" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="required-field">Dosis</label>
                <input type="text" class="form-control" name="medicinas[{{index}}][dosis]" placeholder="Ej: 1 cada 8 horas" required>
            </div>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-remove-medicine" onclick="removeMedicine({{index}})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</div>
</script>

<script>
let medicineCounter = <?php echo count($medicinas); ?>;

// Agregar nueva medicina
document.getElementById('btnAddMedicine').addEventListener('click', function() {
    const container = document.getElementById('medicinesContainer');
    const noMedicinesMessage = document.getElementById('noMedicinesMessage');
    
    // Ocultar mensaje de no medicinas
    if (noMedicinesMessage) {
        noMedicinesMessage.style.display = 'none';
    }
    
    // Crear nueva medicina
    const template = document.getElementById('medicineTemplate').innerHTML;
    const newMedicine = template.replace(/{{index}}/g, medicineCounter);
    
    const div = document.createElement('div');
    div.innerHTML = newMedicine;
    container.appendChild(div.firstElementChild);
    
    medicineCounter++;
});

// Remover medicina
function removeMedicine(index) {
    const medicineItem = document.querySelector(`[data-medicine-index="${index}"]`);
    if (medicineItem) {
        medicineItem.remove();
    }
    
    // Mostrar mensaje si no hay medicinas
    const container = document.getElementById('medicinesContainer');
    if (container.children.length === 0) {
        const noMedMsg = document.createElement('div');
        noMedMsg.className = 'text-muted text-center';
        noMedMsg.id = 'noMedicinesMessage';
        noMedMsg.textContent = 'No hay medicinas agregadas. Haga clic en "Agregar Medicina".';
        container.appendChild(noMedMsg);
    }
}

// Envío del formulario
document.getElementById('formPrescripcion').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validar que hay al menos una medicina
    const medicineItems = document.querySelectorAll('.medicine-item');
    if (medicineItems.length === 0) {
        Swal.fire('Error', 'Debe agregar al menos una medicina', 'error');
        return;
    }
    
    const formData = new FormData(this);
    
    Swal.fire({
        title: '¿Actualizar prescripción?',
        text: '¿Está seguro de actualizar esta prescripción?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Éxito', 'Prescripción actualizada correctamente', 'success')
                    .then(() => {
                        window.location.href = 'nueva_prescripcion.php';
                    });
                } else {
                    Swal.fire('Error', data.message || 'Error al actualizar', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Error de conexión', 'error');
            });
        }
    });
});
</script>
</body>
</html>