<?php
// sanatorio/prescripciones_paciente.php

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

if ($id_paciente > 0) {
    // Consulta corregida basada en tu estructura de BD
    $query = "SELECT p.fecha_visita, p.enfermedad, p.sucursal,
                     dp.cantidad, dp.dosis,
                     m.nombre_medicamento as medicina,
                     dm.empaque
              FROM prescripciones p 
              JOIN detalle_prescripciones dp ON p.id_prescripcion = dp.id_prescripcion
              JOIN medicamentos m ON dp.id_medicamento = m.id
              LEFT JOIN detalles_medicina dm ON dp.id_medicamento = m.id
              WHERE p.id_paciente = ? 
              ORDER BY p.fecha_visita DESC";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $id_paciente);
        $stmt->execute();
        $result = $stmt->get_result();
        $prescripciones = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $prescripciones = [];
        echo "Error en la consulta: " . $conn->error;
    }
} else {
    $prescripciones = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prescripciones del Paciente</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    
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
            background: #6c757d; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-right: 10px;
        }
        .btn:hover { 
            background: #545b62; 
        }
        .btn-primary {
            background: #007bff;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📋 Prescripciones Médicas - Rosita Balan</h2>
            <button class="btn btn-primary" onclick="window.history.back()">← Regresar al Historial</button>
        </div>
        
        <?php if (empty($prescripciones)): ?>
            <div class="alert alert-info">
                No se encontraron prescripciones para este paciente.
            </div>
        <?php else: ?>
            <table id="tablaPrescripciones" class="table table-striped table-bordered" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th>Fecha Visita</th>
                        <th>Medicina</th>
                        <th>Caja de Medicamentos</th>
                        <th>Cantidad</th>
                        <th>Dosis</th>
                        <th>Enfermedad</th>
                        <th>Sucursal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescripciones as $presc): ?>
                    <tr>
                        <td><?= htmlspecialchars($presc['fecha_visita'] ?? '') ?></td>
                        <td><?= htmlspecialchars($presc['medicina'] ?? '') ?></td>
                        <td><?= htmlspecialchars($presc['empaque'] ?? '') ?></td>
                        <td><?= htmlspecialchars($presc['cantidad'] ?? '') ?></td>
                        <td><?= htmlspecialchars($presc['dosis'] ?? '') ?></td>
                        <td><?= htmlspecialchars($presc['enfermedad'] ?? '') ?></td>
                        <td><?= htmlspecialchars($presc['sucursal'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- jQuery -->
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.7.0.js"></script>
    
    <!-- DataTables -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Botones de exportación -->
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaPrescripciones').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                },
                "responsive": true,
                "dom": 'Bfrtip',
                "buttons": [
                    {
                        extend: 'excel',
                        text: 'Excel',
                        className: 'btn btn-success'
                    },
                    {
                        extend: 'csv',
                        text: 'CSV',
                        className: 'btn btn-info'
                    },
                    {
                        extend: 'pdf',
                        text: 'PDF',
                        className: 'btn btn-danger'
                    },
                    {
                        extend: 'print',
                        text: 'Imprimir',
                        className: 'btn btn-warning'
                    },
                    {
                        extend: 'colvis',
                        text: 'Columnas',
                        className: 'btn btn-secondary'
                    }
                ],
                "pageLength": 10,
                "order": [[0, 'desc']] // Ordenar por fecha descendente
            });
        });
    </script>
</body>
</html>