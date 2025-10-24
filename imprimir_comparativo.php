<?php
// imprimir_comparativo.php - IMPRIMIR/EXPORTAR COMPARACIÓN
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';

$id_comparacion = $_GET['id'] ?? 0;

// Obtener datos de la comparación guardada
$comparacion = null;
$resultados = [];
$tipo_comparacion = '';

try {
    // Obtener comparación
    $query = "SELECT * FROM comparaciones_pacientes WHERE id = ?";
    $stmt = $con->prepare($query);
    $stmt->execute([$id_comparacion]);
    $comparacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comparacion) {
        die("Comparación no encontrada");
    }
    
    // Decodificar parámetros
    $parametros = json_decode($comparacion['parametros'], true);
    $tipo_comparacion = $comparacion['tipo'];
    
    // Generar resultados según el tipo
    switch ($tipo_comparacion) {
        case 'personalizada':
            if (!empty($parametros['pacientes'])) {
                $placeholders = str_repeat('?,', count($parametros['pacientes']) - 1) . '?';
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
                            AVG(CAST(REPLACE(pr.peso, ' kg', '') AS DECIMAL(10,2))) as peso_promedio
                         FROM pacientes p
                         LEFT JOIN prescripciones pr ON p.id_paciente = pr.id_paciente 
                           AND pr.estado != 'cancelada'
                           AND pr.fecha_visita BETWEEN ? AND ?
                           " . (!empty($parametros['sucursal']) ? " AND pr.sucursal = ?" : "") . "
                         WHERE p.id_paciente IN ($placeholders)
                         GROUP BY p.id_paciente
                         ORDER BY total_visitas DESC";
                
                $params = array_merge([$parametros['fecha_desde'], $parametros['fecha_hasta']], $parametros['pacientes']);
                if (!empty($parametros['sucursal'])) array_splice($params, 2, 0, [$parametros['sucursal']]);
                
                $stmt = $con->prepare($query);
                $stmt->execute($params);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'edad':
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
                       " . (!empty($parametros['sucursal']) ? " AND pr.sucursal = ?" : "") . "
                     WHERE TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN ? AND ?
                     GROUP BY grupo_edad
                     ORDER BY grupo_edad";
            
            $params = [
                $parametros['grupo_edad'], 
                $parametros['grupo_edad'],
                $parametros['fecha_desde'], 
                $parametros['fecha_hasta'],
                $parametros['edad_min'], 
                $parametros['edad_max']
            ];
            if (!empty($parametros['sucursal'])) array_splice($params, 4, 0, [$parametros['sucursal']]);
            
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
                        COUNT(DISTINCT pr.enfermedad) as enfermedades_diferentes
                     FROM pacientes p
                     LEFT JOIN prescripciones pr ON p.id_paciente = pr.id_paciente 
                       AND pr.estado != 'cancelada'
                       AND pr.fecha_visita BETWEEN ? AND ?
                       " . (!empty($parametros['sucursal']) ? " AND pr.sucursal = ?" : "") . "
                     WHERE p.genero IS NOT NULL AND p.genero != ''
                     GROUP BY p.genero
                     ORDER BY total_visitas DESC";
            
            $params = [$parametros['fecha_desde'], $parametros['fecha_hasta']];
            if (!empty($parametros['sucursal'])) $params[] = $parametros['sucursal'];
            
            $stmt = $con->prepare($query);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'sangre':
            if (!empty($parametros['sangre'])) {
                $placeholders = str_repeat('?,', count($parametros['sangre']) - 1) . '?';
                $query = "SELECT 
                            p.tipo_sangre,
                            COUNT(DISTINCT p.id_paciente) as total_pacientes,
                            COUNT(DISTINCT pr.id_prescripcion) as total_visitas,
                            AVG(CAST(REPLACE(pr.peso, ' kg', '') AS DECIMAL(10,2))) as peso_promedio,
                            COUNT(DISTINCT pr.enfermedad) as enfermedades_diferentes
                         FROM pacientes p
                         LEFT JOIN prescripciones pr ON p.id_paciente = pr.id_paciente 
                           AND pr.estado != 'cancelada'
                           AND pr.fecha_visita BETWEEN ? AND ?
                           " . (!empty($parametros['sucursal']) ? " AND pr.sucursal = ?" : "") . "
                         WHERE p.tipo_sangre IN ($placeholders)
                         GROUP BY p.tipo_sangre
                         ORDER BY total_visitas DESC";
                
                $params = array_merge([$parametros['fecha_desde'], $parametros['fecha_hasta']], $parametros['sangre']);
                if (!empty($parametros['sucursal'])) array_splice($params, 2, 0, [$parametros['sucursal']]);
                
                $stmt = $con->prepare($query);
                $stmt->execute($params);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
    }

} catch (PDOException $ex) {
    die("Error: " . $ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Comparación - <?php echo htmlspecialchars($comparacion['nombre']); ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11pt; margin: 0; padding: 10px; }
            .page-break { page-break-before: always; }
        }
        @media screen {
            body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
            .report-container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .comparison-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .comparison-table th, .comparison-table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        .comparison-table th { background: #343a40; color: white; font-weight: bold; }
        .comparison-table tr:nth-child(even) { background: #f8f9fa; }
        .stat-high { background: #d4edda !important; font-weight: bold; }
        .stat-low { background: #f8d7da !important; }
        .badge-report { padding: 4px 8px; border-radius: 3px; font-size: 0.8em; }
        .progress-report { height: 15px; background: #e9ecef; border-radius: 2px; overflow: hidden; margin: 3px 0; }
        .progress-bar-report { height: 100%; background: linear-gradient(90deg, #007bff, #0056b3); }
        .summary-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #007bff; }
        .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px; text-align: center; color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <!-- Botones de Acción (solo en pantalla) -->
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
            🖨️ Imprimir Reporte
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
            ❌ Cerrar
        </button>
        <a href="reporte_comparativo.php" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">
            📊 Volver a Comparaciones
        </a>
    </div>

    <div class="report-container">
        <!-- Encabezado del Reporte -->
        <div class="header">
            <h1 style="margin: 0 0 10px 0; color: #343a40;">
                <i class="fas fa-balance-scale"></i> Reporte de Comparación
            </h1>
            <h2 style="margin: 0 0 15px 0; color: #495057;"><?php echo htmlspecialchars($comparacion['nombre']); ?></h2>
            <p style="margin: 5px 0; color: #666;">
                <strong>Tipo:</strong> <?php echo ucfirst($tipo_comparacion); ?> | 
                <strong>Generado:</strong> <?php echo date('d/m/Y H:i'); ?>
            </p>
            <p style="margin: 5px 0; color: #666;">
                <strong>Período:</strong> <?php echo date('d/m/Y', strtotime($parametros['fecha_desde'])); ?> - <?php echo date('d/m/Y', strtotime($parametros['fecha_hasta'])); ?>
                <?php if (!empty($parametros['sucursal'])): ?>
                    | <strong>Sucursal:</strong> <?php echo htmlspecialchars($parametros['sucursal']); ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Resumen de Parámetros -->
        <div class="summary-box">
            <h3 style="margin-top: 0; color: #007bff;">
                <i class="fas fa-cogs"></i> Parámetros de la Comparación
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">
                <div><strong>Tipo:</strong> <?php echo ucfirst($tipo_comparacion); ?></div>
                <div><strong>Período:</strong> <?php echo date('d/m/Y', strtotime($parametros['fecha_desde'])); ?> - <?php echo date('d/m/Y', strtotime($parametros['fecha_hasta'])); ?></div>
                <?php if (!empty($parametros['sucursal'])): ?>
                    <div><strong>Sucursal:</strong> <?php echo htmlspecialchars($parametros['sucursal']); ?></div>
                <?php endif; ?>
                
                <?php if ($tipo_comparacion == 'edad'): ?>
                    <div><strong>Rango Edad:</strong> <?php echo $parametros['edad_min']; ?> - <?php echo $parametros['edad_max']; ?> años</div>
                    <div><strong>Agrupación:</strong> Cada <?php echo $parametros['grupo_edad']; ?> años</div>
                <?php elseif ($tipo_comparacion == 'personalizada'): ?>
                    <div><strong>Pacientes:</strong> <?php echo count($parametros['pacientes']); ?> seleccionados</div>
                <?php elseif ($tipo_comparacion == 'sangre'): ?>
                    <div><strong>Tipos Sanguíneos:</strong> <?php echo count($parametros['sangre']); ?> tipos</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($resultados)): ?>
            <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 5px;">
                <h3 style="color: #dc3545;">No hay datos para mostrar</h3>
                <p>No se encontraron resultados para los criterios de comparación seleccionados.</p>
            </div>
        <?php else: ?>

        <!-- Resultados de la Comparación -->
        <div>
            <h3 style="color: #343a40; border-bottom: 2px solid #007bff; padding-bottom: 8px;">
                <i class="fas fa-chart-bar"></i> Resultados de la Comparación
            </h3>

            <?php if ($tipo_comparacion === 'personalizada'): ?>
            <!-- Tabla para Comparación Personalizada -->
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
                    <?php 
                    $max_visitas = max(array_column($resultados, 'total_visitas'));
                    $min_visitas = min(array_column($resultados, 'total_visitas'));
                    foreach ($resultados as $paciente): 
                        $porcentaje_visitas = $max_visitas > 0 ? ($paciente['total_visitas'] / $max_visitas) * 100 : 0;
                    ?>
                    <tr>
                        <td style="text-align: left; font-weight: bold;"><?php echo htmlspecialchars($paciente['nombre']); ?></td>
                        <td><?php echo $paciente['edad'] ?: 'N/A'; ?></td>
                        <td>
                            <span class="badge-report" style="background: #6f42c1; color: white;">
                                <?php echo htmlspecialchars($paciente['genero'] ?: 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($paciente['tipo_sangre']): ?>
                                <span class="badge-report" style="background: #dc3545; color: white;">
                                    <?php echo htmlspecialchars($paciente['tipo_sangre']); ?>
                                </span>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td class="<?php echo $paciente['total_visitas'] == $max_visitas ? 'stat-high' : 
                                           ($paciente['total_visitas'] == $min_visitas ? 'stat-low' : ''); ?>">
                            <strong><?php echo $paciente['total_visitas']; ?></strong>
                            <div class="progress-report">
                                <div class="progress-bar-report" style="width: <?php echo $porcentaje_visitas; ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge-report" style="background: #ffc107; color: black;">
                                <?php echo $paciente['enfermedades_diferentes']; ?>
                            </span>
                        </td>
                        <td><?php echo $paciente['peso_promedio'] ? number_format($paciente['peso_promedio'], 1) . ' kg' : 'N/A'; ?></td>
                        <td><?php echo $paciente['primera_visita'] ? date('d/m/Y', strtotime($paciente['primera_visita'])) : 'N/A'; ?></td>
                        <td><?php echo $paciente['ultima_visita'] ? date('d/m/Y', strtotime($paciente['ultima_visita'])) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php else: ?>
            <!-- Tabla para Comparación por Grupos -->
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>
                            <?php echo $tipo_comparacion == 'edad' ? 'Rango Edad' : 
                                   ($tipo_comparacion == 'genero' ? 'Género' : 'Tipo Sangre'); ?>
                        </th>
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
                            <?php if ($tipo_comparacion == 'edad'): ?>
                                <?php echo $resultado['grupo_edad'] . '-' . ($resultado['grupo_edad'] + $parametros['grupo_edad'] - 1); ?> años
                            <?php else: ?>
                                <?php echo htmlspecialchars($resultado[$tipo_comparacion]); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-report" style="background: #17a2b8; color: white;">
                                <?php echo $resultado['total_pacientes']; ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo $resultado['total_visitas']; ?></strong>
                            <div class="progress-report">
                                <div class="progress-bar-report" style="width: <?php echo $porcentaje_visitas; ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge-report" style="background: #28a745; color: white;">
                                <?php echo number_format($visitas_por_paciente, 1); ?>
                            </span>
                        </td>
                        <td><?php echo $resultado['peso_promedio'] ? number_format($resultado['peso_promedio'], 1) . ' kg' : 'N/A'; ?></td>
                        <td>
                            <span class="badge-report" style="background: #ffc107; color: black;">
                                <?php echo $resultado['enfermedades_diferentes']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Estadísticas Resumen -->
            <div class="summary-box">
                <h4 style="margin-top: 0; color: #28a745;">
                    <i class="fas fa-chart-line"></i> Resumen Estadístico
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="text-align: center;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #007bff;">
                            <?php echo count($resultados); ?>
                        </div>
                        <div style="font-size: 0.9em; color: #666;">
                            <?php echo $tipo_comparacion == 'personalizada' ? 'Pacientes' : 'Grupos'; ?> Analizados
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #28a745;">
                            <?php echo array_sum(array_column($resultados, 'total_visitas')); ?>
                        </div>
                        <div style="font-size: 0.9em; color: #666;">Total de Visitas</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #ffc107;">
                            <?php echo array_sum(array_column($resultados, 'enfermedades_diferentes')); ?>
                        </div>
                        <div style="font-size: 0.9em; color: #666;">Enfermedades Diferentes</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #dc3545;">
                            <?php 
                            $pesos = array_filter(array_column($resultados, 'peso_promedio'));
                            echo $pesos ? number_format(array_sum($pesos) / count($pesos), 1) : 'N/A'; 
                            ?>
                        </div>
                        <div style="font-size: 0.9em; color: #666;">Peso Promedio (kg)</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pie de Página -->
        <div class="footer">
            <p><strong>Reporte generado por Sistema Médico</strong></p>
            <p>Comparación guardada el: <?php echo date('d/m/Y H:i', strtotime($comparacion['creado_en'])); ?></p>
            <p>© <?php echo date('Y'); ?> - Todos los derechos reservados</p>
        </div>
    </div>

    <script>
        // Auto-imprimir al cargar (opcional)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>