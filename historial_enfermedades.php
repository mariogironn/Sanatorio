<?php
// sanatorio/historial_enfermedades.php

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

// Primero, verifica si existe la tabla enfermedades
$check_table = $conn->query("SHOW TABLES LIKE 'enfermedades'");
if ($check_table->num_rows == 0) {
    // Crear tabla enfermedades si no existe
    $conn->query("CREATE TABLE enfermedades (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT,
        estado ENUM('activa', 'inactiva') DEFAULT 'activa',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insertar enfermedades comunes
    $conn->query("INSERT INTO enfermedades (nombre, descripcion) VALUES 
        ('Diabetes', 'Enfermedad metabólica caracterizada por niveles elevados de glucosa en sangre'),
        ('Hipertensión', 'Presión arterial elevada de forma persistente'),
        ('Asma', 'Enfermedad crónica de las vías respiratorias'),
        ('Artritis', 'Inflamación de las articulaciones')");
}

// Verifica si existe la tabla historial_enfermedades
$check_table2 = $conn->query("SHOW TABLES LIKE 'historial_enfermedades'");
if ($check_table2->num_rows == 0) {
    // Crear tabla historial_enfermedades si no existe
    $conn->query("CREATE TABLE historial_enfermedades (
        id INT PRIMARY KEY AUTO_INCREMENT,
        id_paciente INT NOT NULL,
        id_enfermedad INT NOT NULL,
        fecha_diagnostico DATE NOT NULL,
        gravedad ENUM('leve', 'moderada', 'grave') DEFAULT 'leve',
        estado ENUM('activa', 'inactiva', 'curada') DEFAULT 'activa',
        notas TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente) ON DELETE CASCADE,
        FOREIGN KEY (id_enfermedad) REFERENCES enfermedades(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
    )");
    
    // Insertar datos de ejemplo para Rosita Balan (id 12)
    $conn->query("INSERT INTO historial_enfermedades (id_paciente, id_enfermedad, fecha_diagnostico, gravedad, estado, notas) 
    VALUES 
    (12, 1, '2023-05-15', 'moderada', 'activa', 'Diabetes tipo 1 diagnosticada. Control con insulina.'),
    (12, 2, '2024-01-10', 'leve', 'activa', 'Hipertensión controlada con medicación.')");
}

if ($id_paciente > 0) {
    // Consulta para obtener el historial de enfermedades
    $query = "SELECT he.*, e.nombre as enfermedad_nombre 
              FROM historial_enfermedades he 
              JOIN enfermedades e ON he.id_enfermedad = e.id 
              WHERE he.id_paciente = ? 
              ORDER BY he.fecha_diagnostico DESC";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $id_paciente);
        $stmt->execute();
        $result = $stmt->get_result();
        $enfermedades = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $enfermedades = [];
    }
} else {
    $enfermedades = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Enfermedades</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/dataTables.css">
    
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 1200px; 
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
            border-bottom: 2px solid #28a745;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-leve { background: #28a745; color: white; }
        .badge-moderada { background: #ffc107; color: black; }
        .badge-grave { background: #dc3545; color: white; }
        .badge-activa { background: #dc3545; color: white; }
        .badge-inactiva { background: #6c757d; color: white; }
        .badge-curada { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🏥 Historial de Enfermedades - Rosita Balan</h2>
            <button class="btn" onclick="window.history.back()">← Regresar al Historial</button>
        </div>
        
        <?php if (empty($enfermedades)): ?>
            <div style="text-align: center; padding: 20px; color: #6c757d;">
                No se encontraron enfermedades registradas para este paciente.
            </div>
        <?php else: ?>
            <table id="tablaEnfermedades" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Fecha Diagnóstico</th>
                        <th>Enfermedad</th>
                        <th>Gravedad</th>
                        <th>Estado</th>
                        <th>Notas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enfermedades as $enf): ?>
                    <tr>
                        <td><?= htmlspecialchars($enf['fecha_diagnostico'] ?? '') ?></td>
                        <td><?= htmlspecialchars($enf['enfermedad_nombre'] ?? '') ?></td>
                        <td>
                            <span class="badge badge-<?= $enf['gravedad'] ?? 'leve' ?>">
                                <?= htmlspecialchars($enf['gravedad'] ?? '') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $enf['estado'] ?? 'activa' ?>">
                                <?= htmlspecialchars($enf['estado'] ?? '') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($enf['notas'] ?? '') ?></td>
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
            $('#tablaEnfermedades').DataTable({
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