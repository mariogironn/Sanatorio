<?php
// reporte_comparativo.php - PANTALLA PRINCIPAL DE COMPARACIÓN (EN MÓDULO REPORTES)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

// Obtener comparaciones guardadas (si las hay)
$comparaciones = [];

try {
    $query = "SELECT * FROM comparaciones_pacientes ORDER BY creado_en DESC LIMIT 10";
    $stmt = $con->prepare($query);
    $stmt->execute();
    $comparaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    // La tabla puede no existir aún, es normal
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <?php include './config/data_tables_css.php'; ?>
    <title>Reporte Comparativo de Pacientes</title>
    <style>
        .comparacion-card { border-left: 4px solid #ffc107; }
        .btn-comparar { background: linear-gradient(135deg, #ffc107, #ff8c00); color: white; }
        .stat-badge { font-size: 0.9em; padding: 5px 10px; }
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
                        <h1><i class="fas fa-balance-scale"></i> Comparativo de Pacientes</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="generar_comparativo.php" class="btn btn-comparar btn-sm">
                            <i class="fas fa-plus-circle"></i> Nueva Comparación
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <!-- Tarjetas de Acción Rápida -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h5>Comparar por Edad</h5>
                            <a href="generar_comparativo.php?tipo=edad" class="btn btn-primary btn-sm">
                                Iniciar
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-venus-mars fa-2x text-success mb-2"></i>
                            <h5>Comparar por Género</h5>
                            <a href="generar_comparativo.php?tipo=genero" class="btn btn-success btn-sm">
                                Iniciar
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-tint fa-2x text-danger mb-2"></i>
                            <h5>Comparar por Sangre</h5>
                            <a href="generar_comparativo.php?tipo=sangre" class="btn btn-danger btn-sm">
                                Iniciar
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-stethoscope fa-2x text-warning mb-2"></i>
                            <h5>Comparar por Enfermedad</h5>
                            <a href="generar_comparativo.php?tipo=enfermedad" class="btn btn-warning btn-sm">
                                Iniciar
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comparaciones Recientes -->
            <div class="card comparacion-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Comparaciones Recientes</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($comparaciones)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> 
                            No hay comparaciones guardadas. 
                            <a href="generar_comparativo.php" class="alert-link">Crea la primera comparación</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Nombre de Comparación</th>
                                        <th>Tipo</th>
                                        <th>Pacientes</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comparaciones as $comparacion): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($comparacion['nombre']); ?></td>
                                        <td>
                                            <span class="badge stat-badge badge-<?php 
                                                echo $comparacion['tipo'] == 'edad' ? 'primary' : 
                                                     ($comparacion['tipo'] == 'genero' ? 'success' : 
                                                     ($comparacion['tipo'] == 'sangre' ? 'danger' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst($comparacion['tipo']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $comparacion['total_pacientes']; ?> pacientes</span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($comparacion['creado_en'])); ?></td>
                                        <td>
                                            <a href="ver_comparativo.php?id=<?php echo $comparacion['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                            <a href="imprimir_comparativo.php?id=<?php echo $comparacion['id']; ?>" 
                                               class="btn btn-sm btn-outline-success" target="_blank">
                                                <i class="fas fa-print"></i> Imprimir
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Enlaces Rápidos -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-link"></i> Accesos Directos</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <a href="historial_paciente.php" class="btn btn-outline-primary btn-lg">
                                        <i class="fas fa-history"></i><br>
                                        Historial Pacientes
                                    </a>
                                </div>
                                <div class="col-md-4 text-center">
                                    <a href="reportes_historial.php" class="btn btn-outline-success btn-lg">
                                        <i class="fas fa-chart-bar"></i><br>
                                        Reportes Generales
                                    </a>
                                </div>
                                <div class="col-md-4 text-center">
                                    <a href="generar_comparativo.php" class="btn btn-outline-warning btn-lg">
                                        <i class="fas fa-balance-scale"></i><br>
                                        Nueva Comparación
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<script>
    // Menu activo - necesitarás agregar este ítem en el sidebar de Reportes
    showMenuSelected("#mnu_reports", "#mi_reports_comparativo");
</script>
</body>
</html>