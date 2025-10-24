<?php
// editar_comparativo.php - EDITAR COMPARACIONES GUARDADAS (UPDATE)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

$id_comparacion = $_GET['id'] ?? 0;

// Obtener datos de la comparación
$comparacion = null;

try {
    $query = "SELECT * FROM comparaciones_pacientes WHERE id = ?";
    $stmt = $con->prepare($query);
    $stmt->execute([$id_comparacion]);
    $comparacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comparacion) {
        header('Location: reporte_comparativo.php');
        exit;
    }
    
    // Decodificar parámetros JSON
    $parametros = json_decode($comparacion['parametros'], true);
    
} catch (PDOException $ex) {
    echo "Error: " . $ex->getMessage();
    exit;
}

// Obtener datos para los selectores
$pacientes = [];
try {
    $queryPacientes = "SELECT id_paciente, nombre FROM pacientes WHERE estado = 'activo' ORDER BY nombre ASC";
    $stmtPacientes = $con->prepare($queryPacientes);
    $stmtPacientes->execute();
    $pacientes = $stmtPacientes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    // Manejar error
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $tipo = $_POST['tipo'] ?? 'personalizada';
    
    // Recoger parámetros según el tipo
    $nuevos_parametros = [];
    switch ($tipo) {
        case 'personalizada':
            $nuevos_parametros['pacientes'] = $_POST['pacientes'] ?? [];
            break;
        case 'edad':
            $nuevos_parametros['edad_min'] = $_POST['edad_min'] ?? 18;
            $nuevos_parametros['edad_max'] = $_POST['edad_max'] ?? 65;
            $nuevos_parametros['grupo_edad'] = $_POST['grupo_edad'] ?? 10;
            break;
        case 'sangre':
            $nuevos_parametros['sangre'] = $_POST['sangre'] ?? [];
            break;
    }
    
    $nuevos_parametros['fecha_desde'] = $_POST['fecha_desde'] ?? date('Y-m-01');
    $nuevos_parametros['fecha_hasta'] = $_POST['fecha_hasta'] ?? date('Y-m-t');
    $nuevos_parametros['sucursal'] = $_POST['sucursal'] ?? '';
    
    try {
        $query = "UPDATE comparaciones_pacientes 
                  SET nombre = ?, tipo = ?, parametros = ?, actualizado_en = NOW()
                  WHERE id = ?";
        $stmt = $con->prepare($query);
        $stmt->execute([
            $nombre,
            $tipo,
            json_encode($nuevos_parametros),
            $id_comparacion
        ]);
        
        $_SESSION['success_message'] = 'Comparación actualizada correctamente';
        header("Location: reporte_comparativo.php");
        exit;
        
    } catch (PDOException $ex) {
        $error_message = "Error al actualizar la comparación: " . $ex->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <title>Editar Comparación</title>
    <style>
        .comparacion-option { border: 2px solid transparent; transition: all 0.3s ease; cursor: pointer; }
        .comparacion-option:hover { border-color: #007bff; }
        .comparacion-option.selected { border-color: #28a745; background-color: #f8fff9; }
        .config-section { display: none; }
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
                        <h1><i class="fas fa-edit"></i> Editar Comparación</h1>
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
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle"></i> Información Básica</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="required">Nombre de la Comparación</label>
                                <input type="text" class="form-control" name="nombre" 
                                       value="<?php echo htmlspecialchars($comparacion['nombre']); ?>" 
                                       placeholder="Ej: Comparación Pacientes Crónicos" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Tipo de Comparación</label>
                                <select class="form-control" name="tipo" id="tipoComparacion" required>
                                    <option value="personalizada" <?php echo $comparacion['tipo'] == 'personalizada' ? 'selected' : ''; ?>>Personalizada</option>
                                    <option value="edad" <?php echo $comparacion['tipo'] == 'edad' ? 'selected' : ''; ?>>Por Edad</option>
                                    <option value="genero" <?php echo $comparacion['tipo'] == 'genero' ? 'selected' : ''; ?>>Por Género</option>
                                    <option value="sangre" <?php echo $comparacion['tipo'] == 'sangre' ? 'selected' : ''; ?>>Por Tipo de Sangre</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración Personalizada -->
                    <div id="config-personalizada" class="config-section card mb-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-users"></i> Seleccionar Pacientes</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($pacientes as $paciente): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="pacientes[]" 
                                               value="<?php echo $paciente['id_paciente']; ?>"
                                               id="paciente-<?php echo $paciente['id_paciente']; ?>"
                                               <?php echo in_array($paciente['id_paciente'], $parametros['pacientes'] ?? []) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="paciente-<?php echo $paciente['id_paciente']; ?>">
                                            <?php echo htmlspecialchars($paciente['nombre']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración por Edad -->
                    <div id="config-edad" class="config-section card mb-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-birthday-cake"></i> Rango de Edades</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Edad Mínima</label>
                                        <input type="number" class="form-control" name="edad_min" 
                                               value="<?php echo $parametros['edad_min'] ?? 18; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Edad Máxima</label>
                                        <input type="number" class="form-control" name="edad_max" 
                                               value="<?php echo $parametros['edad_max'] ?? 65; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Agrupar por</label>
                                        <select class="form-control" name="grupo_edad">
                                            <option value="5" <?php echo ($parametros['grupo_edad'] ?? 10) == 5 ? 'selected' : ''; ?>>5 años</option>
                                            <option value="10" <?php echo ($parametros['grupo_edad'] ?? 10) == 10 ? 'selected' : ''; ?>>10 años</option>
                                            <option value="15" <?php echo ($parametros['grupo_edad'] ?? 10) == 15 ? 'selected' : ''; ?>>15 años</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración por Sangre -->
                    <div id="config-sangre" class="config-section card mb-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-tint"></i> Tipos Sanguíneos</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                $tipos_sangre = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                foreach ($tipos_sangre as $tipo_sangre): 
                                ?>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="sangre[]" 
                                               value="<?php echo $tipo_sangre; ?>"
                                               <?php echo in_array($tipo_sangre, $parametros['sangre'] ?? $tipos_sangre) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo $tipo_sangre; ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros Comunes -->
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
                                               value="<?php echo $parametros['fecha_desde'] ?? date('Y-m-01'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Fecha Hasta</label>
                                        <input type="date" class="form-control" name="fecha_hasta" 
                                               value="<?php echo $parametros['fecha_hasta'] ?? date('Y-m-t'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Sucursal</label>
                                        <input type="text" class="form-control" name="sucursal" 
                                               value="<?php echo htmlspecialchars($parametros['sucursal'] ?? ''); ?>"
                                               placeholder="Dejar vacío para todas">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Actualizar Comparación
                        </button>
                        <a href="reporte_comparativo.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<script>
    // Mostrar configuración según tipo seleccionado
    function mostrarConfiguracion(tipo) {
        // Ocultar todas las configuraciones
        document.querySelectorAll('.config-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Mostrar la configuración correspondiente
        const configSection = document.getElementById(`config-${tipo}`);
        if (configSection) {
            configSection.style.display = 'block';
        }
    }
    
    // Cambiar configuración cuando cambie el tipo
    document.getElementById('tipoComparacion').addEventListener('change', function() {
        mostrarConfiguracion(this.value);
    });
    
    // Inicializar con el tipo actual
    document.addEventListener('DOMContentLoaded', function() {
        mostrarConfiguracion('<?php echo $comparacion['tipo']; ?>');
    });
</script>
</body>
</html>