<?php
// reporte_historial_paciente.php - REPORTE INDIVIDUAL DE PACIENTE (PARA IMPRIMIR)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';

$id_paciente = $_GET['id'] ?? 0;

// Obtener datos del paciente
$paciente = null;
$prescripciones = [];
$estadisticas = [];

try {
    // Datos del paciente
    $queryPaciente = "SELECT * FROM pacientes WHERE id_paciente = ? AND estado = 'activo'";
    $stmtPaciente = $con->prepare($queryPaciente);
    $stmtPaciente->execute([$id_paciente]);
    $paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        die("Paciente no encontrado");
    }

    // Prescripciones del paciente
    $queryPrescripciones = "SELECT p.*, u.nombre_mostrar as medico_nombre
                            FROM prescripciones p
                            LEFT JOIN usuarios u ON p.medico_id = u.id
                            WHERE p.id_paciente = ? AND p.estado != 'cancelada'
                            ORDER BY p.fecha_visita DESC";
    
    $stmtPrescripciones = $con->prepare($queryPrescripciones);
    $stmtPrescripciones->execute([$id_paciente]);
    $prescripciones = $stmtPrescripciones->fetchAll(PDO::FETCH_ASSOC);

    // Medicinas de cada prescripción
    foreach ($prescripciones as &$prescripcion) {
        $queryMedicinas = "SELECT dp.*, m.nombre_medicamento 
                          FROM detalle_prescripciones dp
                          INNER JOIN medicamentos m ON dp.id_medicamento = m.id
                          WHERE dp.id_prescripcion = ?
                          ORDER BY dp.id_detalle ASC";
        
        $stmtMedicinas = $con->prepare($queryMedicinas);
        $stmtMedicinas->execute([$prescripcion['id_prescripcion']]);
        $prescripcion['medicinas'] = $stmtMedicinas->fetchAll(PDO::FETCH_ASSOC);
    }

    // Estadísticas
    $queryStats = "SELECT 
                    COUNT(DISTINCT id_prescripcion) as total_visitas,
                    COUNT(DISTINCT enfermedad) as enfermedades_diferentes,
                    MIN(fecha_visita) as primera_visita,
                    MAX(fecha_visita) as ultima_visita
                   FROM prescripciones 
                   WHERE id_paciente = ? AND estado != 'cancelada'";
    
    $stmtStats = $con->prepare($queryStats);
    $stmtStats->execute([$id_paciente]);
    $estadisticas = $stmtStats->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    die("Error: " . $ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Historial Médico</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12pt; }
            .card { border: 1px solid #000 !important; }
        }
        body { font-family: Arial, sans-serif; font-size: 14px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .patient-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .stat-card { border: 1px solid #ddd; padding: 10px; text-align: center; margin-bottom: 10px; }
        .prescripcion-item { border: 1px solid #ddd; margin-bottom: 15px; padding: 10px; page-break-inside: avoid; }
        .table-medicinas { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-medicinas th, .table-medicinas td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .table-medicinas th { background-color: #f8f9fa; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 0.8em; }
        .badge-success { background: #28a745; color: white; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-warning { background: #ffc107; color: black; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mb-3 { margin-bottom: 1rem; }
        .mt-3 { margin-top: 1rem; }
        .footer { margin-top: 30px; border-top: 1px solid #333; padding-top: 10px; font-size: 0.8em; text-align: center; }
    </style>
</head>
<body>
    <!-- Botón de impresión (solo visible en pantalla) -->
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" class="no-print" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🖨️ Imprimir Reporte
        </button>
        <button onclick="window.close()" class="no-print" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            ❌ Cerrar
        </button>
    </div>

    <?php if ($paciente): ?>
    
    <!-- Encabezado del Reporte -->
    <div class="header">
        <h1>Reporte de Historial Médico</h1>
        <p>Sistema Médico - <?php echo date('d/m/Y H:i'); ?></p>
    </div>

    <!-- Información del Paciente -->
    <div class="patient-info">
        <h2>Datos del Paciente</h2>
        <table style="width: 100%;">
            <tr>
                <td style="width: 30%;"><strong>Nombre:</strong></td>
                <td><?php echo htmlspecialchars($paciente['nombre']); ?></td>
                <td><strong>DPI:</strong></td>
                <td><?php echo htmlspecialchars($paciente['dpi'] ?: 'No registrado'); ?></td>
            </tr>
            <tr>
                <td><strong>Teléfono:</strong></td>
                <td><?php echo htmlspecialchars($paciente['telefono'] ?: 'No registrado'); ?></td>
                <td><strong>Género:</strong></td>
                <td><?php echo htmlspecialchars($paciente['genero'] ?: 'No especificado'); ?></td>
            </tr>
            <tr>
                <td><strong>Dirección:</strong></td>
                <td colspan="3"><?php echo htmlspecialchars($paciente['direccion'] ?: 'No registrada'); ?></td>
            </tr>
            <?php if ($paciente['tipo_sangre']): ?>
            <tr>
                <td><strong>Tipo de Sangre:</strong></td>
                <td><?php echo htmlspecialchars($paciente['tipo_sangre']); ?></td>
                <td><strong>Fecha Nacimiento:</strong></td>
                <td><?php echo $paciente['fecha_nacimiento'] ? date('d/m/Y', strtotime($paciente['fecha_nacimiento'])) : 'No registrada'; ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Estadísticas -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;">
        <div class="stat-card">
            <h3 style="margin: 0; color: #007bff;"><?php echo (int)$estadisticas['total_visitas']; ?></h3>
            <p style="margin: 0; font-size: 0.9em;">Total Visitas</p>
        </div>
        <div class="stat-card">
            <h3 style="margin: 0; color: #28a745;"><?php echo (int)$estadisticas['enfermedades_diferentes']; ?></h3>
            <p style="margin: 0; font-size: 0.9em;">Enfermedades</p>
        </div>
        <div class="stat-card">
            <h3 style="margin: 0; color: #ffc107; font-size: 1.1em;">
                <?php echo $estadisticas['primera_visita'] ? date('d/m/Y', strtotime($estadisticas['primera_visita'])) : 'N/A'; ?>
            </h3>
            <p style="margin: 0; font-size: 0.9em;">Primera Visita</p>
        </div>
        <div class="stat-card">
            <h3 style="margin: 0; color: #dc3545; font-size: 1.1em;">
                <?php echo $estadisticas['ultima_visita'] ? date('d/m/Y', strtotime($estadisticas['ultima_visita'])) : 'N/A'; ?>
            </h3>
            <p style="margin: 0; font-size: 0.9em;">Última Visita</p>
        </div>
    </div>

    <!-- Antecedentes -->
    <?php if ($paciente['antecedentes_personales'] || $paciente['antecedentes_familiares']): ?>
    <div class="mb-3">
        <h2>Antecedentes Médicos</h2>
        <?php if ($paciente['antecedentes_personales']): ?>
        <p><strong>Antecedentes Personales:</strong><br>
        <?php echo nl2br(htmlspecialchars($paciente['antecedentes_personales'])); ?></p>
        <?php endif; ?>
        
        <?php if ($paciente['antecedentes_familiares']): ?>
        <p><strong>Antecedentes Familiares:</strong><br>
        <?php echo nl2br(htmlspecialchars($paciente['antecedentes_familiares'])); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Historial de Prescripciones -->
    <h2>Historial de Prescripciones</h2>
    
    <?php if (empty($prescripciones)): ?>
        <p style="text-align: center; font-style: italic; color: #666;">
            No se registran prescripciones para este paciente.
        </p>
    <?php else: ?>
        <?php foreach ($prescripciones as $prescripcion): ?>
        <div class="prescripcion-item">
            <div style="background: #f8f9fa; padding: 8px; border-bottom: 1px solid #ddd;">
                <strong>Visita del <?php echo date('d/m/Y', strtotime($prescripcion['fecha_visita'])); ?></strong>
                <span class="badge badge-<?php echo $prescripcion['estado'] === 'activa' ? 'success' : ($prescripcion['estado'] === 'completada' ? 'info' : 'warning'); ?>" style="float: right;">
                    <?php echo ucfirst($prescripcion['estado']); ?>
                </span>
            </div>
            
            <div style="padding: 10px;">
                <table style="width: 100%; margin-bottom: 10px;">
                    <tr>
                        <td style="width: 30%;"><strong>Diagnóstico:</strong></td>
                        <td><?php echo htmlspecialchars($prescripcion['enfermedad']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Sucursal:</strong></td>
                        <td><?php echo htmlspecialchars($prescripcion['sucursal']); ?></td>
                    </tr>
                    <?php if ($prescripcion['medico_nombre']): ?>
                    <tr>
                        <td><strong>Médico:</strong></td>
                        <td><?php echo htmlspecialchars($prescripcion['medico_nombre']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($prescripcion['peso'] || $prescripcion['presion']): ?>
                    <tr>
                        <td><strong>Signos Vitales:</strong></td>
                        <td>
                            <?php if ($prescripcion['peso']): ?>Peso: <?php echo htmlspecialchars($prescripcion['peso']); ?> kg<?php endif; ?>
                            <?php if ($prescripcion['presion']): ?><?php echo $prescripcion['peso'] ? ' | ' : ''; ?>Presión: <?php echo htmlspecialchars($prescripcion['presion']); ?><?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($prescripcion['proxima_visita']): ?>
                    <tr>
                        <td><strong>Próxima Visita:</strong></td>
                        <td><?php echo date('d/m/Y', strtotime($prescripcion['proxima_visita'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <!-- Medicinas -->
                <?php if (!empty($prescripcion['medicinas'])): ?>
                <strong>Medicamentos Recetados:</strong>
                <table class="table-medicinas">
                    <thead>
                        <tr>
                            <th>Medicamento</th>
                            <th>Empaque</th>
                            <th>Cantidad</th>
                            <th>Dosis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescripcion['medicinas'] as $medicina): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($medicina['nombre_medicamento']); ?></td>
                            <td><?php echo htmlspecialchars($medicina['empaque']); ?></td>
                            <td><?php echo htmlspecialchars($medicina['cantidad']); ?></td>
                            <td><?php echo htmlspecialchars($medicina['dosis']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Pie de página -->
    <div class="footer">
        <p>Reporte generado el <?php echo date('d/m/Y H:i'); ?> | Sistema Médico</p>
        <p>Página 1 de 1</p>
    </div>

    <?php else: ?>
        <div style="text-align: center; padding: 50px;">
            <h2>Paciente no encontrado</h2>
            <p>El paciente solicitado no existe o no está activo.</p>
        </div>
    <?php endif; ?>

    <script>
        // Auto-imprimir al cargar (opcional)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>