<?php
// agregar_nota_historial.php - AGREGAR NOTAS AL HISTORIAL (CREATE)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

$id_paciente = $_GET['id_paciente'] ?? 0;

// Obtener datos del paciente
$paciente = null;
try {
    $query = "SELECT id_paciente, nombre FROM pacientes WHERE id_paciente = ? AND estado = 'activo'";
    $stmt = $con->prepare($query);
    $stmt->execute([$id_paciente]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paciente) {
        header('Location: historial_paciente.php');
        exit;
    }
} catch (PDOException $ex) {
    echo "Error: " . $ex->getMessage();
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $tipo = $_POST['tipo'] ?? 'nota';
    $fecha_nota = $_POST['fecha_nota'] ?? date('Y-m-d');
    
    try {
        $query = "INSERT INTO historial_notas (id_paciente, titulo, descripcion, tipo, fecha_nota, creado_por) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $con->prepare($query);
        $stmt->execute([
            $id_paciente,
            $titulo,
            $descripcion,
            $tipo,
            $fecha_nota,
            $_SESSION['id'] ?? null
        ]);
        
        $_SESSION['success_message'] = 'Nota agregada correctamente al historial';
        header("Location: ver_historial_completo.php?id=$id_paciente");
        exit;
        
    } catch (PDOException $ex) {
        $error_message = "Error al guardar la nota: " . $ex->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <title>Agregar Nota al Historial</title>
    <style>
        .form-nota { max-width: 800px; margin: 0 auto; }
        .paciente-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
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
                        <h1><i class="fas fa-plus-circle"></i> Agregar Nota al Historial</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="ver_historial_completo.php?id=<?php echo $id_paciente; ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver al Historial
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <!-- Información del Paciente -->
                <div class="paciente-info">
                    <h3><i class="fas fa-user-injured"></i> <?php echo htmlspecialchars($paciente['nombre']); ?></h3>
                    <p>Agregando nueva nota al historial médico</p>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Formulario -->
                <div class="card form-nota">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit"></i> Nueva Nota Médica</h3>
                    </div>
                    <form method="POST" action="">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required">Título de la Nota</label>
                                        <input type="text" class="form-control" name="titulo" 
                                               placeholder="Ej: Control de presión arterial" required
                                               value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="required">Tipo de Nota</label>
                                        <select class="form-control" name="tipo" required>
                                            <option value="nota" <?php echo ($_POST['tipo'] ?? '') == 'nota' ? 'selected' : ''; ?>>Nota General</option>
                                            <option value="observacion" <?php echo ($_POST['tipo'] ?? '') == 'observacion' ? 'selected' : ''; ?>>Observación</option>
                                            <option value="seguimiento" <?php echo ($_POST['tipo'] ?? '') == 'seguimiento' ? 'selected' : ''; ?>>Seguimiento</option>
                                            <option value="recordatorio" <?php echo ($_POST['tipo'] ?? '') == 'recordatorio' ? 'selected' : ''; ?>>Recordatorio</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="required">Fecha de la Nota</label>
                                        <input type="date" class="form-control" name="fecha_nota" 
                                               value="<?php echo $_POST['fecha_nota'] ?? date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Descripción Detallada</label>
                                <textarea class="form-control" name="descripcion" rows="8" 
                                          placeholder="Describa la nota médica, observaciones, recomendaciones, etc..." required><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Tipos de notas:</strong><br>
                                • <strong>Nota General:</strong> Información médica general<br>
                                • <strong>Observación:</strong> Observaciones clínicas importantes<br>
                                • <strong>Seguimiento:</strong> Notas de seguimiento de tratamiento<br>
                                • <strong>Recordatorio:</strong> Recordatorios para próximas visitas
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Guardar Nota
                            </button>
                            <a href="ver_historial_completo.php?id=<?php echo $id_paciente; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
</body>
</html>