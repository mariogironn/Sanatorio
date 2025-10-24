<?php
// reportes_historial.php - REPORTES GENERALES Y ESTADÍSTICAS
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

// Parámetros de filtro
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes actual
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-t');  // Último día del mes actual
$sucursal = $_GET['sucursal'] ?? '';

// Obtener estadísticas generales
$estadisticas = [];
$enfermedades_comunes = [];
$sucursales_stats = [];
$evolucion_mensual = [];

try {
    // Estadísticas generales
    $queryStats = "SELECT 
                    COUNT(DISTINCT p.id_paciente) as total_pacientes,
                    COUNT(DISTINCT pr.id_prescripcion) as total_prescripciones,
                    COUNT(DISTINCT pr.enfermedad) as enfermedades_diferentes,
                    AVG(CAST(REPLACE(pr.peso, ' kg', '') AS DECIMAL(10,2))) as peso_promedio,
                    COUNT(DISTINCT CASE WHEN pr.proxima_visita >= CURDATE() THEN pr.id_prescripcion END) as proximas_visitas
                   FROM pacientes p
                   LEFT JOIN prescripciones pr ON p.id_paciente = pr.id_paciente 
                   WHERE p.estado = 'activo'
                   AND pr.estado != 'cancelada'
                   AND pr.fecha_visita BETWEEN ? AND ?
                   " . ($sucursal ? " AND pr.sucursal = ?" : "");
    
    $stmtStats = $con->prepare($queryStats);
    $params = [$fecha_desde, $fecha_hasta];
    if ($sucursal) $params[] = $sucursal;
    $stmtStats->execute($params);
    $estadisticas = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Enfermedades más comunes
    $queryEnfermedades = "SELECT enfermedad, COUNT(*) as total
                         FROM prescripciones 
                         WHERE fecha_visita BETWEEN ? AND ?
                         AND estado != 'cancelada'
                         " . ($sucursal ? " AND sucursal = ?" : "") . "
                         GROUP BY enfermedad 
                         ORDER BY total DESC 
                         LIMIT 10";
    
    $stmtEnfermedades = $con->prepare($queryEnfermedades);
    $params = [$fecha_desde, $fecha_hasta];
    if ($sucursal) $params[] = $sucursal;
    $stmtEnfermedades->execute($params);
    $enfermedades_comunes = $stmtEnfermedades->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas por sucursal
    $querySucursales = "SELECT sucursal, COUNT(*) as total_prescripciones,
                               COUNT(DISTINCT id_paciente) as pacientes_unicos
                        FROM prescripciones 
                        WHERE fecha_visita BETWEEN ? AND ?
                        AND estado != 'cancelada'
                        GROUP BY sucursal 
                        ORDER BY total_prescripciones DESC";
    
    $stmtSucursales = $con->prepare($querySucursales);
    $stmtSucursales->execute([$fecha_desde, $fecha_hasta]);
    $sucursales_stats = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);

    // Evolución mensual (últimos 6 meses)
    $queryEvolucion = "SELECT 
                        DATE_FORMAT(fecha_visita, '%Y-%m') as mes,
                        COUNT(*) as total_prescripciones,
                        COUNT(DISTINCT id_paciente) as pacientes_unicos
                       FROM prescripciones 
                       WHERE fecha_visita >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                       AND estado != 'cancelada'
                       " . ($sucursal ? " AND sucursal = ?" : "") . "
                       GROUP BY mes 
                       ORDER BY mes DESC 
                       LIMIT 6";
    
    $stmtEvolucion = $con->prepare($queryEvolucion);
    $params = [];
    if ($sucursal) $params[] = $sucursal;
    $stmtEvolucion->execute($params);
    $evolucion_mensual = $stmtEvolucion->fetchAll(PDO::FETCH_ASSOC);

    // Lista de sucursales para el filtro
    $querySucursalesList = "SELECT DISTINCT sucursal FROM prescripciones ORDER BY sucursal";
    $stmtSucursalesList = $con->prepare($querySucursalesList);
    $stmtSucursalesList->execute();
    $sucursales_list = $stmtSucursalesList->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $ex) {
    echo "Error: " . $ex->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <title>Reportes Generales de Historial</title>
    <style>
        .stat-card { border-left: 4px solid #007bff; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .chart-container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .enfermedad-item { padding: 8px 12px; margin-bottom: 5px; background: #f8f9fa; border-radius: 3px; border-left: 3px solid #28a745; }
        .progress-bar-custom { height: 20px; background: #e9ecef; border-radius: 3px; overflow: hidden; }
        .progress-value { height: 100%; background: linear-gradient(90deg, #007bff, #0056b3); }
        .filter-form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .table-stats th { background: #343a40; color: white; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
    <?php include './config/header.php'; include './config/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6">
                        <h1><i class="fas fa-chart-bar"></i> Reportes Generales</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <button onclick="window.print()" class="btn btn-secondary btn-sm">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                        <button onclick="exportToExcel()" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Exportar Excel
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <!-- Filtros -->
            <div class="card filter-form">
                <div class="card-body">
                    <form method="GET" action="" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label for="fecha_desde" class="mr-2">Desde:</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                   value="<?php echo htmlspecialchars($fecha_desde); ?>">
                        </div>
                        <div class="form-group mr-3 mb-2">
                            <label for="fecha_hasta" class="mr-2">Hasta:</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                   value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                        </div>
                        <div class="form-group mr-3 mb-2">
                            <label for="sucursal" class="mr-2">Sucursal:</label>
                            <select class="form-control" id="sucursal" name="sucursal">
                                <option value="">Todas las sucursales</option>
                                <?php foreach ($sucursales_list as $suc): ?>
                                    <option value="<?php echo htmlspecialchars($suc); ?>" 
                                        <?php echo $sucursal == $suc ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($suc); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <a href="reportes_historial.php" class="btn btn-secondary mb-2 ml-2">
                            <i class="fas fa-redo"></i> Limpiar
                        </a>
                    </form>
                </div>
            </div>

            <!-- Estadísticas Principales -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?php echo (int)$estadisticas['total_pacientes']; ?></h3>
                            <p class="text-muted mb-0">Pacientes Atendidos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h3 class="text-success"><?php echo (int)$estadisticas['total_prescripciones']; ?></h3>
                            <p class="text-muted mb-0">Total Prescripciones</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h3 class="text-info"><?php echo (int)$estadisticas['enfermedades_diferentes']; ?></h3>
                            <p class="text-muted mb-0">Enfermedades Diferentes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h3 class="text-warning"><?php echo number_format($estadisticas['peso_promedio'] ?? 0, 1); ?></h3>
                            <p class="text-muted mb-0">Peso Promedio (kg)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h3 class="text-danger"><?php echo (int)$estadisticas['proximas_visitas']; ?></h3>
                            <p class="text-muted mb-0">Próximas Visitas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h3 class="text-dark">
                                <?php echo $estadisticas['total_prescripciones'] > 0 ? 
                                    number_format($estadisticas['total_prescripciones'] / max(1, $estadisticas['total_pacientes']), 1) : '0'; ?>
                            </h3>
                            <p class="text-muted mb-0">Promedio por Paciente</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Enfermedades más comunes -->
                <div class="col-md-6">
                    <div class="card chart-container">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-stethoscope"></i> Enfermedades Más Comunes</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($enfermedades_comunes)): ?>
                                <p class="text-muted text-center">No hay datos para mostrar.</p>
                            <?php else: ?>
                                <?php 
                                $max_count = max(array_column($enfermedades_comunes, 'total'));
                                foreach ($enfermedades_comunes as $enfermedad): 
                                    $percentage = $max_count > 0 ? ($enfermedad['total'] / $max_count) * 100 : 0;
                                ?>
                                <div class="enfermedad-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($enfermedad['enfermedad']); ?></span>
                                        <span class="badge badge-primary"><?php echo $enfermedad['total']; ?></span>
                                    </div>
                                    <div class="progress-bar-custom mt-1">
                                        <div class="progress-value" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas por Sucursal -->
                <div class="col-md-6">
                    <div class="card chart-container">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-hospital"></i> Actividad por Sucursal</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($sucursales_stats)): ?>
                                <p class="text-muted text-center">No hay datos para mostrar.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Sucursal</th>
                                                <th>Prescripciones</th>
                                                <th>Pacientes Únicos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sucursales_stats as $sucursal_stat): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sucursal_stat['sucursal']); ?></td>
                                                <td class="text-center">
                                                    <span class="badge badge-primary"><?php echo $sucursal_stat['total_prescripciones']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-success"><?php echo $sucursal_stat['pacientes_unicos']; ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Evolución Mensual -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card chart-container">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-line"></i> Evolución Mensual (Últimos 6 Meses)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="evolucionChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumen del Período -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-file-alt"></i> Resumen del Período</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Período analizado:</strong> 
                                        <?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>
                                    </p>
                                    <p><strong>Sucursal:</strong> 
                                        <?php echo $sucursal ? htmlspecialchars($sucursal) : 'Todas las sucursales'; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                                    <p><strong>Total de registros analizados:</strong> <?php echo (int)$estadisticas['total_prescripciones']; ?></p>
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
    // Gráfico de evolución mensual
    const evolucionCtx = document.getElementById('evolucionChart').getContext('2d');
    const evolucionChart = new Chart(evolucionCtx, {
        type: 'line',
        data: {
            labels: [<?php 
                $labels = [];
                foreach (array_reverse($evolucion_mensual) as $mes) {
                    $labels[] = "'" . date('M Y', strtotime($mes['mes'] . '-01')) . "'";
                }
                echo implode(', ', $labels);
            ?>],
            datasets: [
                {
                    label: 'Prescripciones',
                    data: [<?php 
                        $data = [];
                        foreach (array_reverse($evolucion_mensual) as $mes) {
                            $data[] = $mes['total_prescripciones'];
                        }
                        echo implode(', ', $data);
                    ?>],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Pacientes Únicos',
                    data: [<?php 
                        $data = [];
                        foreach (array_reverse($evolucion_mensual) as $mes) {
                            $data[] = $mes['pacientes_unicos'];
                        }
                        echo implode(', ', $data);
                    ?>],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Evolución de Actividad Médica'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Exportar a Excel (función básica)
    function exportToExcel() {
        // Esta es una implementación básica - podrías usar una librería como SheetJS para más funcionalidad
        const html = document.documentElement.outerHTML;
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'reporte_historial_<?php echo date('Y-m-d'); ?>.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Menu activo
    showMenuSelected("#mnu_patients", "#mi_patient_history");
</script>
</body>
</html>