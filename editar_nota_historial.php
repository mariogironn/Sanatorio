<?php
// editar_nota_historial.php - EDITAR NOTAS DEL HISTORIAL (UPDATE)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// Verificar sesión y permisos
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include './config/connection.php';
include './common_service/common_functions.php';

$id_nota = $_GET['id'] ?? 0;

// Validar ID
if (!$id_nota || !is_numeric($id_nota)) {
    header('Location: historial_paciente.php');
    exit;
}

// Obtener datos de la nota
$nota = null;
$paciente = null;

try {
    $query = "SELECT hn.*, p.id_paciente, p.nombre as paciente_nombre
              FROM historial_notas hn
              INNER JOIN pacientes p ON hn.id_paciente = p.id_paciente
              WHERE hn.id_nota = ?";
    $stmt = $con->prepare($query);
    $stmt->execute([$id_nota]);
    $nota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nota) {
        $_SESSION['error_message'] = 'La nota no existe';
        header('Location: historial_paciente.php');
        exit;
    }
    
    $paciente = [
        'id_paciente' => $nota['id_paciente'],
        'nombre' => $nota['paciente_nombre']
    ];
    
} catch (PDOException $ex) {
    $_SESSION['error_message'] = "Error al cargar la nota: " . $ex->getMessage();
    header('Location: historial_paciente.php');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? 'nota';
    $fecha_nota = $_POST['fecha_nota'] ?? date('Y-m-d');
    
    // Validaciones básicas
    if (empty($titulo) || empty($descripcion)) {
        $error_message = "El título y la descripción son obligatorios";
    } else {
        try {
            $query = "UPDATE historial_notas 
                      SET titulo = ?, descripcion = ?, tipo = ?, fecha_nota = ?, actualizado_en = NOW()
                      WHERE id_nota = ?";
            $stmt = $con->prepare($query);
            $stmt->execute([
                $titulo,
                $descripcion,
                $tipo,
                $fecha_nota,
                $id_nota
            ]);
            
            $_SESSION['success_message'] = 'Nota actualizada correctamente';
            header("Location: ver_historial_completo.php?id=" . $paciente['id_paciente']);
            exit;
            
        } catch (PDOException $ex) {
            $error_message = "Error al actualizar la nota: " . $ex->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <title>Editar Nota del Historial - <?php echo htmlspecialchars($paciente['nombre']); ?></title>
    <style>
        .form-nota { max-width: 800px; margin: 0 auto; }
        .paciente-info { background: linear-gradient(135deg, #ffc107, #ff8c00); color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .required:after { content: " *"; color: red; }
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
                        <h1><i class="fas fa-edit"></i> Editar Nota del Historial</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="ver_historial_completo.php?id=<?php echo $paciente['id_paciente']; ?>" class="btn btn-secondary btn-sm">
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
                    <p>Editando nota del historial médico</p>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Formulario -->
                <div class="card form-nota">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit"></i> Editar Nota Médica</h3>
                    </div>
                    <form method="POST" action="">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required">Título de la Nota</label>
                                        <input type="text" class="form-control" name="titulo" 
                                               placeholder="Ej: Control de presión arterial" required
                                               value="<?php echo htmlspecialchars($nota['titulo']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="required">Tipo de Nota</label>
                                        <select class="form-control" name="tipo" required>
                                            <option value="nota" <?php echo $nota['tipo'] == 'nota' ? 'selected' : ''; ?>>Nota General</option>
                                            <option value="observacion" <?php echo $nota['tipo'] == 'observacion' ? 'selected' : ''; ?>>Observación</option>
                                            <option value="seguimiento" <?php echo $nota['tipo'] == 'seguimiento' ? 'selected' : ''; ?>>Seguimiento</option>
                                            <option value="recordatorio" <?php echo $nota['tipo'] == 'recordatorio' ? 'selected' : ''; ?>>Recordatorio</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="required">Fecha de la Nota</label>
                                        <input type="date" class="form-control" name="fecha_nota" 
                                               value="<?php echo $nota['fecha_nota']; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Descripción Detallada</label>
                                <textarea class="form-control" name="descripcion" rows="8" 
                                          placeholder="Describa la nota médica, observaciones, recomendaciones, etc..." required><?php echo htmlspecialchars($nota['descripcion']); ?></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Información de la nota:</strong><br>
                                • <strong>Creada:</strong> <?php echo date('d/m/Y H:i', strtotime($nota['creado_en'])); ?><br>
                                <?php if ($nota['actualizado_en']): ?>
                                • <strong>Última modificación:</strong> <?php echo date('d/m/Y H:i', strtotime($nota['actualizado_en'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Actualizar Nota
                            </button>
                            <a href="ver_historial_completo.php?id=<?php echo $paciente['id_paciente']; ?>" class="btn btn-secondary">
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