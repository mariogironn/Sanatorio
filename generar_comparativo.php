<?php
// generar_comparativo.php - FORMULARIO PARA CONFIGURAR COMPARACIÓN
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

// Obtener datos para los selectores
$pacientes = [];
$enfermedades = [];
$sucursales = [];

try {
    // Pacientes activos
    $queryPacientes = "SELECT id_paciente, nombre, genero, tipo_sangre, 
                              TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) as edad
                       FROM pacientes 
                       WHERE estado = 'activo'
                       ORDER BY nombre ASC";
    $stmtPacientes = $con->prepare($queryPacientes);
    $stmtPacientes->execute();
    $pacientes = $stmtPacientes->fetchAll(PDO::FETCH_ASSOC);

    // Enfermedades únicas
    $queryEnfermedades = "SELECT DISTINCT enfermedad 
                         FROM prescripciones 
                         WHERE enfermedad IS NOT NULL AND enfermedad != ''
                         ORDER BY enfermedad ASC";
    $stmtEnfermedades = $con->prepare($queryEnfermedades);
    $stmtEnfermedades->execute();
    $enfermedades = $stmtEnfermedades->fetchAll(PDO::FETCH_COLUMN);

    // Sucursales
    $querySucursales = "SELECT DISTINCT sucursal 
                       FROM prescripciones 
                       WHERE sucursal IS NOT NULL AND sucursal != ''
                       ORDER BY sucursal ASC";
    $stmtSucursales = $con->prepare($querySucursales);
    $stmtSucursales->execute();
    $sucursales = $stmtSucursales->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $ex) {
    echo "Error: " . $ex->getMessage();
    exit;
}

// Tipo de comparación desde URL
$tipo_comparacion = $_GET['tipo'] ?? 'personalizada';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <title>Generar Comparativo de Pacientes</title>
    <style>
        .comparacion-option { border: 2px solid transparent; transition: all 0.3s ease; cursor: pointer; }
        .comparacion-option:hover { border-color: #007bff; transform: translateY(-2px); }
        .comparacion-option.selected { border-color: #28a745; background-color: #f8fff9; }
        .paciente-item { border-left: 4px solid #17a2b8; margin-bottom: 10px; }
        .badge-edad { background: #e83e8c; }
        .badge-genero { background: #6f42c1; }
        .badge-sangre { background: #dc3545; }
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
                        <h1><i class="fas fa-balance-scale"></i> Generar Comparativo</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="reporte_comparativo.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <form id="formComparativo" action="ver_comparativo.php" method="GET" target="_blank">
                    
                    <!-- Tipo de Comparación -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cogs"></i> Tipo de Comparación</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card comparacion-option <?php echo $tipo_comparacion == 'personalizada' ? 'selected' : ''; ?>" 
                                         onclick="seleccionarTipo('personalizada')">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user-friends fa-2x text-primary mb-2"></i>
                                            <h5>Personalizada</h5>
                                            <p class="text-muted small">Selecciona pacientes específicos</p>
                                            <input type="radio" name="tipo" value="personalizada" 
                                                   <?php echo $tipo_comparacion == 'personalizada' ? 'checked' : ''; ?> hidden>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card comparacion-option <?php echo $tipo_comparacion == 'edad' ? 'selected' : ''; ?>" 
                                         onclick="seleccionarTipo('edad')">
                                        <div class="card-body text-center">
                                            <i class="fas fa-birthday-cake fa-2x text-success mb-2"></i>
                                            <h5>Por Edad</h5>
                                            <p class="text-muted small">Compara por rangos de edad</p>
                                            <input type="radio" name="tipo" value="edad" 
                                                   <?php echo $tipo_comparacion == 'edad' ? 'checked' : ''; ?> hidden>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card comparacion-option <?php echo $tipo_comparacion == 'genero' ? 'selected' : ''; ?>" 
                                         onclick="seleccionarTipo('genero')">
                                        <div class="card-body text-center">
                                            <i class="fas fa-venus-mars fa-2x text-info mb-2"></i>
                                            <h5>Por Género</h5>
                                            <p class="text-muted small">Compara hombres vs mujeres</p>
                                            <input type="radio" name="tipo" value="genero" 
                                                   <?php echo $tipo_comparacion == 'genero' ? 'checked' : ''; ?> hidden>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card comparacion-option <?php echo $tipo_comparacion == 'sangre' ? 'selected' : ''; ?>" 
                                         onclick="seleccionarTipo('sangre')">
                                        <div class="card-body text-center">
                                            <i class="fas fa-tint fa-2x text-danger mb-2"></i>
                                            <h5>Por Sangre</h5>
                                            <p class="text-muted small">Compara por tipo sanguíneo</p>
                                            <input type="radio" name="tipo" value="sangre" 
                                                   <?php echo $tipo_comparacion == 'sangre' ? 'checked' : ''; ?> hidden>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración Específica por Tipo -->
                    <div id="config-personalizada" class="config-section">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-users"></i> Seleccionar Pacientes</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($pacientes as $paciente): ?>
                                    <div class="col-md-6">
                                        <div class="card paciente-item">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($paciente['nombre']); ?></h6>
                                                        <div class="mb-1">
                                                            <?php if ($paciente['edad']): ?>
                                                                <span class="badge badge-edad">Edad: <?php echo $paciente['edad']; ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($paciente['genero']): ?>
                                                                <span class="badge badge-genero"><?php echo $paciente['genero']; ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($paciente['tipo_sangre']): ?>
                                                                <span class="badge badge-sangre"><?php echo $paciente['tipo_sangre']; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input paciente-check" 
                                                               name="pacientes[]" value="<?php echo $paciente['id_paciente']; ?>"
                                                               id="paciente-<?php echo $paciente['id_paciente']; ?>">
                                                        <label class="form-check-label" for="paciente-<?php echo $paciente['id_paciente']; ?>"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="seleccionarTodos()">
                                        <i class="fas fa-check-double"></i> Seleccionar Todos
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deseleccionarTodos()">
                                        <i class="fas fa-times"></i> Deseleccionar Todos
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="config-edad" class="config-section" style="display: none;">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-birthday-cake"></i> Rango de Edades</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Edad Mínima</label>
                                            <input type="number" class="form-control" name="edad_min" min="0" max="120" value="18">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Edad Máxima</label>
                                            <input type="number" class="form-control" name="edad_max" min="0" max="120" value="65">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Agrupar por:</label>
                                    <select class="form-control" name="grupo_edad">
                                        <option value="5">Cada 5 años</option>
                                        <option value="10">Cada 10 años</option>
                                        <option value="15">Cada 15 años</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="config-genero" class="config-section" style="display: none;">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-venus-mars"></i> Configuración por Género</h3>
                            </div>
                            <div class="card-body">
                                <p>Se compararán automáticamente todos los pacientes por género.</p>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Se analizarán las diferencias entre pacientes masculinos y femeninos.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="config-sangre" class="config-section" style="display: none;">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-tint"></i> Tipos Sanguíneos a Comparar</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="sangre[]" value="A+" checked>
                                            <label class="form-check-label">A+</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="sangre[]" value="A-" checked>
                                            <label class="form-check-label">A-</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="sangre[]" value="B+" checked>
                                            <label class="form-check-label">B+</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="sangre[]" value="B-" checked>
                                            <label class="form-check-label">B-</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="sangre[]" value="AB+" checked>
                                            <label class="form-check-label">AB+</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="sangre[]" value="AB-" checked>
                                            <label class="form-check-label">AB-</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="sangre[]" value="O+" checked>
                                            <label class="form-check-label">O+</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="sangre[]" value="O-" checked>
                                            <label class="form-check-label">O-</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros Adicionales -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-filter"></i> Filtros Adicionales</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Fecha Desde</label>
                                        <input type="date" class="form-control" name="fecha_desde" 
                                               value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Fecha Hasta</label>
                                        <input type="date" class="form-control" name="fecha_hasta" 
                                               value="<?php echo date('Y-m-t'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Sucursal</label>
                                        <select class="form-control" name="sucursal">
                                            <option value="">Todas las sucursales</option>
                                            <?php foreach ($sucursales as $sucursal): ?>
                                                <option value="<?php echo htmlspecialchars($sucursal); ?>">
                                                    <?php echo htmlspecialchars($sucursal); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="row">
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-chart-bar"></i> Generar Comparativo
                            </button>
                            <a href="reporte_comparativo.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<script>
    // Mostrar/ocultar configuraciones según tipo
    function seleccionarTipo(tipo) {
        // Remover selección anterior
        document.querySelectorAll('.comparacion-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        // Agregar selección nueva
        event.currentTarget.classList.add('selected');
        
        // Marcar radio button
        document.querySelector(`input[value="${tipo}"]`).checked = true;
        
        // Mostrar configuración correspondiente
        document.querySelectorAll('.config-section').forEach(section => {
            section.style.display = 'none';
        });
        
        document.getElementById(`config-${tipo}`).style.display = 'block';
    }

    // Seleccionar/deseleccionar todos los pacientes
    function seleccionarTodos() {
        document.querySelectorAll('.paciente-check').forEach(checkbox => {
            checkbox.checked = true;
        });
    }

    function deseleccionarTodos() {
        document.querySelectorAll('.paciente-check').forEach(checkbox => {
            checkbox.checked = false;
        });
    }

    // Inicializar según tipo actual
    document.addEventListener('DOMContentLoaded', function() {
        const tipoActual = '<?php echo $tipo_comparacion; ?>';
        seleccionarTipo(tipoActual);
    });

    // Validación del formulario
    document.getElementById('formComparativo').addEventListener('submit', function(e) {
        const tipo = document.querySelector('input[name="tipo"]:checked').value;
        
        if (tipo === 'personalizada') {
            const pacientesSeleccionados = document.querySelectorAll('.paciente-check:checked');
            if (pacientesSeleccionados.length < 2) {
                e.preventDefault();
                alert('Debe seleccionar al menos 2 pacientes para comparar.');
                return;
            }
        }
        
        // Si todo está bien, el formulario se envía
    });
</script>
</body>
</html>