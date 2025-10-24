<?php
// sanatorio/historial_visitas.php

// Intenta con estas rutas comunes:
$connection_paths = [
    'config/database.php',
    'config/connection.php', 
    'includes/database.php',
    'includes/connection.php',
    '../config/database.php',
    '../config/connection.php',
    'connection.php',
    '../connection.php'
];

$conn = null;
foreach ($connection_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Si todavía no hay conexión, crea una
if (!isset($conn) || $conn === null) {
    $conn = new mysqli('localhost', 'root', '', 'la_esperanza');
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
}

$id_paciente = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verifica si existe la tabla historial_visitas
$check_table = $conn->query("SHOW TABLES LIKE 'historial_visitas'");
if ($check_table->num_rows == 0) {
    // Crear tabla historial_visitas si no existe
    $conn->query("CREATE TABLE historial_visitas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        id_paciente INT NOT NULL,
        id_doctor INT,
        id_sucursal INT NOT NULL,
        fecha_visita DATE NOT NULL,
        motivo VARCHAR(255),
        diagnostico TEXT,
        tratamiento TEXT,
        peso VARCHAR(12),
        presion_arterial VARCHAR(23),
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente) ON DELETE CASCADE,
        FOREIGN KEY (id_doctor) REFERENCES usuarios(id) ON DELETE SET NULL,
        FOREIGN KEY (id_sucursal) REFERENCES sucursales(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
    )");
    
    // Insertar datos de ejemplo
    $conn->query("INSERT INTO historial_visitas (id_paciente, id_doctor, id_sucursal, fecha_visita, motivo, diagnostico, tratamiento, peso, presion_arterial) 
    VALUES 
    (12, 20, 1, '2025-10-14', 'Control rutinario de diabetes', 'Paciente estable, niveles de glucosa dentro de rangos aceptables', 'Continuar con tratamiento actual de insulina', '125', '120/80'),
    (12, 20, 1, '2025-09-20', 'Malestar general', 'Posible infección respiratoria', 'Reposo y medicamento para los síntomas', '123', '118/78'),
    (12, 20, 1, '2025-08-15', 'Revisión mensual', 'Control de presión arterial', 'Ajuste de medicación para hipertensión', '124', '122/79')");
}

if ($id_paciente > 0) {
    // Consulta para obtener el historial de visitas
    $query = "SELECT hv.*, s.nombre as sucursal_nombre, u.nombre_mostrar as doctor_nombre
              FROM historial_visitas hv 
              LEFT JOIN sucursales s ON hv.id_sucursal = s.id 
              LEFT JOIN usuarios u ON hv.id_doctor = u.id 
              WHERE hv.id_paciente = ? 
              ORDER BY hv.fecha_visita DESC";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $id_paciente);
        $stmt->execute();
        $result = $stmt->get_result();
        $visitas = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $visitas = [];
    }
} else {
    $visitas = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Visitas</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/dataTables.css">
    
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .btn { 
            padding: 10px 20px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-bottom: 10px;
        }
        .btn:hover { 
            background: #0056b3; 
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📅 Historial de Visitas Médicas - Rosita Balan</h2>
            <button class="btn" onclick="window.history.back()">← Regresar al Historial</button>
        </div>
        
        <?php if (empty($visitas)): ?>
            <div style="text-align: center; padding: 20px; color: #6c757d;">
                No se encontraron visitas registradas para este paciente.
            </div>
        <?php else: ?>
            <table id="tablaVisitas" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Fecha Visita</th>
                        <th>Médico</th>
                        <th>Sucursal</th>
                        <th>Motivo</th>
                        <th>Peso</th>
                        <th>Presión</th>
                        <th>Diagnóstico</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitas as $visita): ?>
                    <tr>
                        <td><?= htmlspecialchars($visita['fecha_visita'] ?? '') ?></td>
                        <td><?= htmlspecialchars($visita['doctor_nombre'] ?? 'No especificado') ?></td>
                        <td><?= htmlspecialchars($visita['sucursal_nombre'] ?? 'No especificado') ?></td>
                        <td><?= htmlspecialchars($visita['motivo'] ?? '') ?></td>
                        <td><?= htmlspecialchars($visita['peso'] ?? '') ?></td>
                        <td><?= htmlspecialchars($visita['presion_arterial'] ?? '') ?></td>
                        <td><?= htmlspecialchars($visita['diagnostico'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- jQuery -->
    <script type="text/javascript" src="assets/js/jquery.js"></script>
    
    <!-- DataTables -->
    <script type="text/javascript" src="assets/js/dataTables.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaVisitas').DataTable({
                "language": {
                    "search": "Buscar:",
                    "lengthMenu": "Mostrar _MENU_ registros por página",
                    "zeroRecords": "No se encontraron registros",
                    "info": "Mostrando página _PAGE_ de _PAGES_",
                    "infoEmpty": "No hay registros disponibles",
                    "infoFiltered": "(filtrado de _MAX_ registros totales)",
                    "paginate": {
                        "first": "Primero",
                        "last": "Último",
                        "next": "Siguiente", 
                        "previous": "Anterior"
                    }
                },
                "pageLength": 10,
                "order": [[0, 'desc']], // Ordenar por fecha descendente
                "responsive": true
            });
        });
    </script>
</body>
</html>