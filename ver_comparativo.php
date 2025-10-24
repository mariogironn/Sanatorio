<?php
// ver_comparativo.php - RESULTADOS DE COMPARACIÓN ENTRE PACIENTES
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';

// Obtener parámetros de comparación
$tipo = $_GET['tipo'] ?? 'personalizada';
$pacientes_seleccionados = $_GET['pacientes'] ?? [];
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-t');
$sucursal = $_GET['sucursal'] ?? '';

// Variables para resultados
$resultados = [];
$estadisticas_comparativas = [];
$pacientes_data = [];

try {
    // Obtener datos según el tipo de comparación
    switch ($tipo) {
        case 'personalizada':
            if (!empty($pacientes_seleccionados)) {
                $placeholders = str_repeat('?,', count($pacientes_seleccionados) - 1) . '?';
                $query = "SELECT 
                            p.id_paciente,
                            p.nombre,
                            p.genero,
                            p.tipo_sangre,
                            TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) as edad,
                            COUNT(DISTINCT pr.id_prescripcion) as total_visitas,
                            COUNT(DISTINCT pr.enfermedad) as enfermedades_diferentes,
                            MIN(pr.fecha_visita) as primera_visita,
                            MAX(pr.fecha_visita) as ultima_visita,
                            AVG(CAST(REPLACE(pr.peso, ' kg', '') AS DECIMAL(10,2))) as peso_promedio,
                            GROUP_CONCAT(DISTINCT pr.enfermedad) as enfermedades
                         FROM pacientes p
                         LEFT JOIN prescripciones pr ON p.id_paciente = pr.id_paciente 
                           AND pr.estado != 'cancelada'
                           AND pr.fecha_visita BETWEEN ? AND ?
                           " . ($sucursal ? " AND pr.sucursal = ?" : "") . "
                         WHERE p.id_paciente IN ($placeholders)
                         GROUP BY p.id_paciente
                         ORDER BY total_visitas DESC";
                
                $params = array_merge([$fecha_desde, $fecha_hasta], $pacientes_seleccionados);
                if ($sucursal) array_splice($params, 2, 0, [$sucursal]);
                
                $stmt = $con->prepare($query);
                $stmt->execute($params);
                $pacientes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'edad':
            $edad_min = $_GET['edad_min'] ?? 18;
            $edad_max = $_GET['edad_max'] ?? 65;
            $grupo_edad = $_GET['grupo_edad'] ?? 10;
            
            $query = "SELECT 
                        FLOOR(TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) / ?) * ? as grupo_edad,
                        COUNT(DISTINCT p.id_paciente) as total_pacientes,
                        COUNT(DISTINCT pr.id_prescripcion) as total_visitas,
                        AVG(CAST(REPLACE(pr.peso, ' kg', '') AS DECIMAL(10,2))) as peso_promedio,
                        COUNT(DISTINCT pr.enfermedad) as enfermedades_diferentes
                     FROM pacientes p
                     LEFT JOIN prescripciones pr ON p.id_paciente = pr.id_paciente 
                       AND pr.estado != 'cancelada'
                       AND pr.fecha_visita BETWEEN ? AND ?
                       " . ($sucursal ? " AND pr.sucursal = ?" : "") . "
                     WHERE TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN ? AND ?
                     GROUP BY grupo_edad
                     ORDER BY grupo_edad";
            
            $params = [$grupo_edad, $grupo_edad, $fecha_desde, $fecha_hasta, $edad_min, $edad_max];
            if ($sucursal) array_splice($params, 4, 0, [$sucursal]);
            
            $stmt = $con->prepare($query);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'genero':
            $query = "SELECT 
                        p.genero,
                        COUNT(DISTINCT p.id_paciente) as total_pacientes,
                        COUNT(DISTINCT pr.id_prescripcion) as total_visitas,
                        AVG(CAST(REPLACE(pr.peso, ' kg', '') AS DECIMAL(10,2))) as peso_promedio,
                        COUNT(DISTINCT pr.enfermedad) as enfermedades_diferentes,
                        GROUP_CONCAT(DISTINCT pr.enfermedad) as enfermedades_comunes
                     FROM pacientes p
                     LEFT JOIN prescripciones pr ON p.id_paciente = pr.id_paciente 
                       AND pr.estado != 'cancelada'
                       AND pr.fecha_visita BETWEEN ? AND ?
                       " . ($sucursal ? " AND pr.sucursal = ?" : "") . "
                     WHERE p.genero IS NOT NULL AND p.genero != ''
                     GROUP BY p.genero
                     ORDER BY total_visitas DESC";
            
            $params = [$fecha_desde, $fecha_hasta];
            if ($sucursal) $params[] = $sucursal;
            
            $stmt = $con->prepare($query);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'sangre':
            $tipos_sangre = $_GET['sangre'] ?? ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
            if (!empty($tipos_sangre)) {
                $placeholders = str_repeat('?,', count($tipos_sangre) - 1) . '?';
                $query = "SELECT 
                            p.tipo_sangre,
                            COUNT(DISTINCT p.id_paciente) as total_pacientes,
                            COUNT(DISTINCT pr.id_prescripcion) as total_visitas,
                            AVG(CAST(REPLACE(pr.peso, ' kg', '') AS DECIMAL(10,2))) as peso_promedio,
                            COUNT(DISTINCT pr.enfermedad) as enfermedades_diferentes,
                            GROUP_CONCAT(DISTINCT pr.enfermedad) as enfermedades_comunes
                         FROM pacientes p
                         LEFT JOIN prescripciones pr ON p.id_paciente = pr.id_paciente 
                           AND pr.estado != 'cancelada'
                           AND pr.fecha_visita BETWEEN ? AND ?
                           " . ($sucursal ? " AND pr.sucursal = ?" : "") . "
                         WHERE p.tipo_sangre IN ($placeholders)
                         GROUP BY p.tipo_sangre
                         ORDER BY total_visitas DESC";
                
                $params = array_merge([$fecha_desde, $fecha_hasta], $tipos_sangre);
                if ($sucursal) array_splice($params, 2, 0, [$sucursal]);
                
                $stmt = $con->prepare($query);
                $stmt->execute($params);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
    }

    // Calcular estadísticas comparativas
    if ($tipo === 'personalizada' && !empty($pacientes_data)) {
        $estadisticas_comparativas = [
            'max_visitas' => max(array_column($pacientes_data, 'total_visitas')),
            'min_visitas' => min(array_column($pacientes_data, 'total_visitas')),
            'avg_visitas' => array_sum(array_column($pacientes_data, 'total_visitas')) / count($pacientes_data),
            'max_enfermedades' => max(array_column($pacientes_data, 'enfermedades_diferentes')),
            'min_enfermedades' => min(array_column($pacientes_data, 'enfermedades_diferentes')),
            'total_pacientes' => count($pacientes_data)
        ];
    }

} catch (PDOException $ex) {
    die("Error: " . $ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultados de Comparación</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11pt; }
        }
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .header { background: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .result-card { background: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .comparison-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .comparison-table th, .comparison-table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        .comparison-table th { background: #343a40; color: white; }
        .comparison-table tr:nth-child(even) { background: #f8f9fa; }
        .stat-high { background: #d4edda !important; font-weight: bold; }
        .stat-low { background: #f8d7da !important; }
        .badge-comparison { font-size: 0.8em; padding: 4px 8px; }
        .progress-comparison { height: 20px; background: #e9ecef; border-radius: 3px; overflow: hidden; margin: 5px 0; }
        .progress-bar-comparison { height: 100%; background: linear-gradient(90deg, #007bff, #0056b3); }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Botones de Acción -->
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
            🖨️ Imprimir
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
            ❌ Cerrar
        </button>
        <a href="generar_comparativo.php" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">
            🔄 Nueva Comparación
        </a>
    </div>

    <!-- Encabezado -->
    <div class="header">
        <h1 style="text-align: center; color: #343a40; margin-bottom: 10px;">
            <i class="fas fa-balance-scale"></i> Resultados de Comparación
        </h1>
        <p style="text-align: center; color: #666;">
            Tipo: <strong><?php echo ucfirst($tipo); ?></strong> | 
            Período: <?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>
            <?php if ($sucursal): ?> | Sucursal: <strong><?php echo htmlspecialchars($sucursal); ?></strong><?php endif; ?>
        </p>
        <p style="text-align: center; color: #666; font-size: 0.9em;">
            Generado el: <?php echo date('d/m/Y H:i'); ?>
        </p>
    </div>

    <?php if ($tipo === 'personalizada' && !empty($pacientes_data)): ?>
    
    <!-- Comparación Personalizada -->
    <div class="result-card">
        <h3><i class="fas fa-users"></i> Comparación entre Pacientes Seleccionados</h3>
        
        <!-- Estadísticas Resumen -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;">
            <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; text-align: center;">
                <h4 style="margin: 0; color: #1976d2;"><?php echo $estadisticas_comparativas['total_pacientes']; ?></h4>
                <p style="margin: 0; font-size: 0.9em;">Total Pacientes</p>
            </div>
            <div style="background: #f3e5f5; padding: 15px; border-radius: 5px; text-align: center;">
                <h4 style="margin: 0; color: #7b1fa2;"><?php echo number_format($estadisticas_comparativas['avg_visitas'], 1); ?></h4>
                <p style="margin: 0; font-size: 0.9em;">Visitas Promedio</p>
            </div>
            <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; text-align: center;">
                <h4 style="margin: 0; color: #388e3c;"><?php echo $estadisticas_comparativas['max_visitas']; ?></h4>
                <p style="margin: 0; font-size: 0.9em;">Máx Visitas</p>
            </div>
            <div style="background: #ffebee; padding: 15px; border-radius: 5px; text-align: center;">
                <h4 style="margin: 0; color: #d32f2f;"><?php echo $estadisticas_comparativas['min_visitas']; ?></h4>
                <p style="margin: 0; font-size: 0.9em;">Mín Visitas</p>
            </div>
        </div>

        <!-- Tabla de Comparación -->
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Edad</th>
                    <th>Género</th>
                    <th>Tipo Sangre</th>
                    <th>Total Visitas</th>
                    <th>Enfermedades</th>
                    <th>Peso Promedio</th>
                    <th>Primera Visita</th>
                    <th>Última Visita</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes_data as $paciente): 
                    $porcentaje_visitas = $estadisticas_comparativas['max_visitas'] > 0 ? 
                        ($paciente['total_visitas'] / $estadisticas_comparativas['max_visitas']) * 100 : 0;
                ?>
                <tr>
                    <td style="text-align: left; font-weight: bold;">
                        <?php echo htmlspecialchars($paciente['nombre']); ?>
                    </td>
                    <td><?php echo $paciente['edad'] ?: 'N/A'; ?></td>
                    <td>
                        <span class="badge-comparison" style="background: #6f42c1; color: white;">
                            <?php echo htmlspecialchars($paciente['genero'] ?: 'N/A'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($paciente['tipo_sangre']): ?>
                            <span class="badge-comparison" style="background: #dc3545; color: white;">
                                <?php echo htmlspecialchars($paciente['tipo_sangre']); ?>
                            </span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td class="<?php echo $paciente['total_visitas'] == $estadisticas_comparativas['max_visitas'] ? 'stat-high' : 
                                       ($paciente['total_visitas'] == $estadisticas_comparativas['min_visitas'] ? 'stat-low' : ''); ?>">
                        <strong><?php echo $paciente['total_visitas']; ?></strong>
                        <div class="progress-comparison">
                            <div class="progress-bar-comparison" style="width: <?php echo $porcentaje_visitas; ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <span class="badge-comparison" style="background: #ffc107; color: black;">
                            <?php echo $paciente['enfermedades_diferentes']; ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $paciente['peso_promedio'] ? number_format($paciente['peso_promedio'], 1) . ' kg' : 'N/A'; ?>
                    </td>
                    <td>
                        <?php echo $paciente['primera_visita'] ? date('d/m/Y', strtotime($paciente['primera_visita'])) : 'N/A'; ?>
                    </td>
                    <td>
                        <?php echo $paciente['ultima_visita'] ? date('d/m/Y', strtotime($paciente['ultima_visita'])) : 'N/A'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif (in_array($tipo, ['edad', 'genero', 'sangre']) && !empty($resultados)): ?>
    
    <!-- Comparación por Grupos -->
    <div class="result-card">
        <h3>
            <i class="fas fa-<?php 
                echo $tipo == 'edad' ? 'birthday-cake' : 
                     ($tipo == 'genero' ? 'venus-mars' : 'tint'); 
            ?>"></i>
            Comparación por <?php echo ucfirst($tipo); ?>
        </h3>

        <table class="comparison-table">
            <thead>
                <tr>
                    <th><?php echo $tipo == 'edad' ? 'Rango Edad' : ($tipo == 'genero' ? 'Género' : 'Tipo Sangre'); ?></th>
                    <th>Total Pacientes</th>
                    <th>Total Visitas</th>
                    <th>Visitas por Paciente</th>
                    <th>Peso Promedio</th>
                    <th>Enfermedades Diferentes</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $max_visitas = max(array_column($resultados, 'total_visitas'));
                foreach ($resultados as $resultado): 
                    $visitas_por_paciente = $resultado['total_pacientes'] > 0 ? 
                        $resultado['total_visitas'] / $resultado['total_pacientes'] : 0;
                    $porcentaje_visitas = $max_visitas > 0 ? ($resultado['total_visitas'] / $max_visitas) * 100 : 0;
                ?>
                <tr>
                    <td style="font-weight: bold;">
                        <?php if ($tipo == 'edad'): ?>
                            <?php echo $resultado['grupo_edad'] . '-' . ($resultado['grupo_edad'] + ($_GET['grupo_edad'] ?? 10) - 1); ?> años
                        <?php else: ?>
                            <?php echo htmlspecialchars($resultado[$tipo]); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-comparison" style="background: #17a2b8; color: white;">
                            <?php echo $resultado['total_pacientes']; ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo $resultado['total_visitas']; ?></strong>
                        <div class="progress-comparison">
                            <div class="progress-bar-comparison" style="width: <?php echo $porcentaje_visitas; ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <span class="badge-comparison" style="background: #28a745; color: white;">
                            <?php echo number_format($visitas_por_paciente, 1); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $resultado['peso_promedio'] ? number_format($resultado['peso_promedio'], 1) . ' kg' : 'N/A'; ?>
                    </td>
                    <td>
                        <span class="badge-comparison" style="background: #ffc107; color: black;">
                            <?php echo $resultado['enfermedades_diferentes']; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    
    <div class="result-card" style="text-align: center; padding: 40px;">
        <h3 style="color: #dc3545;">No hay datos para mostrar</h3>
        <p>No se encontraron resultados para los criterios de comparación seleccionados.</p>
        <a href="generar_comparativo.php" class="no-print" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
            Intentar con otros parámetros
        </a>
    </div>

    <?php endif; ?>

    <!-- Gráfico Comparativo (solo para personalizada) -->
    <?php if ($tipo === 'personalizada' && !empty($pacientes_data) && count($pacientes_data) > 1): ?>
    <div class="result-card no-print">
        <h3><i class="fas fa-chart-bar"></i> Gráfico Comparativo</h3>
        <canvas id="comparisonChart" height="100"></canvas>
    </div>

    <script>
        const ctx = document.getElementById('comparisonChart').getContext('2d');
        const comparisonChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $nombres = [];
                    foreach ($pacientes_data as $p) {
                        $nombres[] = "'" . addslashes($p['nombre']) . "'";
                    }
                    echo implode(', ', $nombres);
                ?>],
                datasets: [
                    {
                        label: 'Total Visitas',
                        data: [<?php echo implode(', ', array_column($pacientes_data, 'total_visitas')); ?>],
                        backgroundColor: '#007bff',
                        borderColor: '#0056b3',
                        borderWidth: 1
                    },
                    {
                        label: 'Enfermedades Diferentes',
                        data: [<?php echo implode(', ', array_column($pacientes_data, 'enfermedades_diferentes')); ?>],
                        backgroundColor: '#28a745',
                        borderColor: '#1e7e34',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Comparación entre Pacientes'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    <?php endif; ?>

    <!-- Pie de Página -->
    <div style="text-align: center; margin-top: 30px; padding: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9em;">
        <p>Reporte de Comparación Generado por Sistema Médico</p>
        <p>© <?php echo date('Y'); ?> - Todos los derechos reservados</p>
    </div>
</body>
</html>