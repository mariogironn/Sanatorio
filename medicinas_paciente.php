<?php
session_start();
require_once 'config/connection.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Obtener pacientes con sus medicamentos
$query = "
    SELECT 
        p.id_paciente,
        p.nombre,
        p.dpi,
        p.fecha_nacimiento,
        p.genero,
        p.tipo_sangre,
        GROUP_CONCAT(DISTINCT pm.enfermedad_diagnostico SEPARATOR ', ') as enfermedades,
        COUNT(pm.medicina_id) as total_medicinas,
        GROUP_CONCAT(DISTINCT m.nombre_medicamento SEPARATOR ' • ') as medicamentos,
        MAX(pm.fecha_asignacion) as ultima_fecha
    FROM pacientes p
    LEFT JOIN paciente_medicinas pm ON p.id_paciente = pm.paciente_id AND pm.estado = 'activo'
    LEFT JOIN medicamentos m ON pm.medicina_id = m.id
    WHERE p.estado = 'activo'
    GROUP BY p.id_paciente
    ORDER BY p.nombre
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicamentos por Paciente - Sanatorio La Esperanza</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .interaction-high { border-left: 4px solid #dc3545; }
        .interaction-medium { border-left: 4px solid #ffc107; }
        .interaction-low { border-left: 4px solid #28a745; }
        .enfermedad-badge { background-color: #6f42c1; color: white; }
        .edad-badge { background-color: #17a2b8; }
        .sangre-badge { background-color: #e83e8c; }
        .card-medicina { border-left: 4px solid #007bff; }
        .btn-action { width: 35px; height: 35px; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h2 class="text-primary">
                    <i class="fas fa-user-injured mr-2"></i>Medicamentos por Paciente
                </h2>
                <p class="text-muted">Gestión de medicamentos asignados a pacientes</p>
            </div>
            <div class="col text-right">
                <button class="btn btn-success" data-toggle="modal" data-target="#modalAsignarMedicinas">
                    <i class="fas fa-plus mr-2"></i>Asignar Medicinas
                </button>
                <button class="btn btn-primary" onclick="generarPDF()">
                    <i class="fas fa-file-pdf mr-2"></i>Generar PDF
                </button>
            </div>
        </div>

        <!-- Tabla Principal -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list mr-2"></i>Pacientes y sus Medicamentos
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($pacientes)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle mr-2"></i>No hay pacientes registrados con medicamentos asignados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tablaPacientes">
                            <thead class="thead-light">
                                <tr>
                                    <th width="50">#</th>
                                    <th>Paciente</th>
                                    <th>Enfermedad/Diagnóstico</th>
                                    <th>Medicinas Asignadas</th>
                                    <th>Interacciones Detectadas</th>
                                    <th width="120">Fecha Asignación</th>
                                    <th width="120" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pacientes as $index => $paciente): ?>
                                    <?php
                                    // Calcular edad
                                    $fechaNac = new DateTime($paciente['fecha_nacimiento']);
                                    $hoy = new DateTime();
                                    $edad = $hoy->diff($fechaNac)->y;
                                    
                                    // Determinar interacciones
                                    $tieneInteracciones = $paciente['total_medicinas'] > 1;
                                    $textoInteracciones = $tieneInteracciones ? 
                                        "<span class='badge badge-danger'>" . $paciente['total_medicinas'] . " Interacciones ALTAS</span>" : 
                                        "<span class='badge badge-success'>Sin interacciones</span>";
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($paciente['nombre']); ?></strong><br>
                                            <small class="text-muted">
                                                ID: P-<?php echo str_pad($paciente['id_paciente'], 3, '0', STR_PAD_LEFT); ?> | 
                                                <span class="badge edad-badge"><?php echo $edad; ?> años</span>
                                                <?php if ($paciente['tipo_sangre']): ?>
                                                    | <span class="badge sangre-badge"><?php echo $paciente['tipo_sangre']; ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if (!empty($paciente['enfermedades'])): ?>
                                                <span class="badge enfermedad-badge"><?php echo explode(',', $paciente['enfermedades'])[0]; ?></span>
                                                <?php if (substr_count($paciente['enfermedades'], ',') > 0): ?>
                                                    <br><small class="text-muted">+<?php echo substr_count($paciente['enfermedades'], ','); ?> más</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Sin diagnóstico</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($paciente['medicamentos'])): ?>
                                                <div class="mb-1">
                                                    <?php 
                                                    $meds = explode(' • ', $paciente['medicamentos']);
                                                    $primerasMeds = array_slice($meds, 0, 2);
                                                    foreach ($primerasMeds as $med): ?>
                                                        <span class="badge badge-info"><?php echo htmlspecialchars($med); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fas fa-pills mr-1"></i>
                                                    <?php echo $paciente['total_medicinas']; ?> medicinas asignadas
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Sin medicinas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $textoInteracciones; ?>
                                            <?php if ($tieneInteracciones && !empty($paciente['medicamentos'])): ?>
                                                <br><small class="text-muted">
                                                    <?php 
                                                    $meds = explode(' • ', $paciente['medicamentos']);
                                                    echo $meds[0] . ' • ' . $meds[1];
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($paciente['ultima_fecha']): ?>
                                                <?php echo date('d/m/Y', strtotime($paciente['ultima_fecha'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-warning btn-action" 
                                                        onclick="editarPaciente(<?php echo $paciente['id_paciente']; ?>)"
                                                        title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-info btn-action" 
                                                        onclick="verDetalles(<?php echo $paciente['id_paciente']; ?>)"
                                                        title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-danger btn-action" 
                                                        onclick="eliminarAsignacion(<?php echo $paciente['id_paciente']; ?>)"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pie de tabla -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Mostrando 1 a <?php echo count($pacientes); ?> de <?php echo count($pacientes); ?> registros
                        </div>
                        <div>
                            <button class="btn btn-outline-primary btn-sm" disabled>Anterior</button>
                            <button class="btn btn-outline-primary btn-sm" disabled>Siguiente</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Asignar Medicinas -->
    <div class="modal fade" id="modalAsignarMedicinas" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-pills mr-2"></i>Asignar Medicinas a Paciente
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span class="text-white">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formAsignarMedicinas">
                        <!-- Selección de Paciente -->
                        <div class="form-group">
                            <label for="selectPaciente"><strong>Seleccionar Paciente *</strong></label>
                            <select class="form-control" id="selectPaciente" required>
                                <option value="">-- Seleccione un paciente --</option>
                                <option value="1">Juan Pérez - 45 años</option>
                                <option value="2">María García - 62 años</option>
                                <option value="3">Carlos López - 38 años</option>
                            </select>
                        </div>

                        <!-- Enfermedad del Paciente -->
                        <div class="form-group">
                            <label for="selectEnfermedad"><strong>Enfermedad/Diagnóstico *</strong></label>
                            <select class="form-control" id="selectEnfermedad" required>
                                <option value="">-- Seleccione la enfermedad --</option>
                                <option value="artritis">Artritis Reumatoide</option>
                                <option value="hipertension">Hipertensión Arterial</option>
                                <option value="diabetes">Diabetes Tipo 2</option>
                                <option value="infeccion">Infección Respiratoria</option>
                                <option value="dolor">Dolor Crónico</option>
                                <option value="ansiedad">Trastorno de Ansiedad</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <!-- Selección de Medicinas -->
                        <div class="form-group">
                            <label><strong>Seleccionar Medicinas *</strong></label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                <div class="form-check">
                                    <input class="form-check-input medicina-check" type="checkbox" value="1" id="med1">
                                    <label class="form-check-label" for="med1">
                                        Ibuprofeno - Analgésico, Antiinflamatorio
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input medicina-check" type="checkbox" value="2" id="med2">
                                    <label class="form-check-label" for="med2">
                                        Paracetamol - Analgésico, Antipirético
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input medicina-check" type="checkbox" value="3" id="med3">
                                    <label class="form-check-label" for="med3">
                                        Warfarina - Anticoagulante
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input medicina-check" type="checkbox" value="4" id="med4">
                                    <label class="form-check-label" for="med4">
                                        Aspirina - Antiagregante plaquetario
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input medicina-check" type="checkbox" value="5" id="med5">
                                    <label class="form-check-label" for="med5">
                                        Omeprazol - Protector gástrico
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Motivo de la Prescripción -->
                        <div class="form-group">
                            <label for="motivoPrescripcion"><strong>Motivo de la Prescripción *</strong></label>
                            <textarea class="form-control" id="motivoPrescripcion" rows="3" 
                                      placeholder="Describa por qué se receta esta medicina al paciente..."></textarea>
                        </div>

                        <!-- Información Adicional -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="dosis"><strong>Dosis *</strong></label>
                                    <input type="text" class="form-control" id="dosis" placeholder="Ej: 500mg" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="frecuencia"><strong>Frecuencia *</strong></label>
                                    <input type="text" class="form-control" id="frecuencia" placeholder="Ej: Cada 8 horas" required>
                                </div>
                            </div>
                        </div>

                        <!-- Duración del Tratamiento -->
                        <div class="form-group">
                            <label for="duracion"><strong>Duración del Tratamiento</strong></label>
                            <input type="text" class="form-control" id="duracion" placeholder="Ej: 7 días, 1 mes, tratamiento crónico">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarAsignacion()">
                        <i class="fas fa-save mr-2"></i>Guardar Asignación
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalles de Medicación -->
    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-file-medical-alt mr-2"></i>Detalles Completo de Medicación
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span class="text-white">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6><strong>Paciente:</strong> <span id="detallePaciente">Juan Pérez</span></h6>
                        </div>
                        <div class="col-md-6">
                            <h6><strong>Diagnóstico Principal:</strong> <span id="detalleEnfermedad">Artritis Reumatoide</span></h6>
                        </div>
                    </div>
                    
                    <h6 class="border-bottom pb-2">Medicinas Asignadas:</h6>
                    <div id="detalleMedicinas">
                        <!-- Las medicinas se cargarán aquí dinámicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="imprimirDetalles()">
                        <i class="fas fa-print mr-2"></i>Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        // Datos de ejemplo para los modales
        const datosPacientes = {
            1: {
                nombre: "Juan Pérez",
                enfermedad: "Artritis Reumatoide",
                medicinas: [
                    { nombre: "Ibuprofeno", dosis: "400mg", frecuencia: "Cada 8 horas", duracion: "7 días", motivo: "Para control del dolor e inflamación articular" },
                    { nombre: "Paracetamol", dosis: "500mg", frecuencia: "Cada 6 horas", duracion: "5 días", motivo: "Como analgésico complementario para dolor moderado" },
                    { nombre: "Warfarina", dosis: "5mg", frecuencia: "Una vez al día", duracion: "Tratamiento crónico", motivo: "Prevención de trombosis por condición cardíaca" }
                ]
            },
            2: {
                nombre: "María García", 
                enfermedad: "Diabetes Tipo 2",
                medicinas: [
                    { nombre: "Paracetamol", dosis: "500mg", frecuencia: "Cada 6 horas", duracion: "3 días", motivo: "Control de fiebre y dolor" },
                    { nombre: "Aspirina", dosis: "100mg", frecuencia: "Una vez al día", duracion: "Tratamiento crónico", motivo: "Prevención de eventos cardiovasculares" }
                ]
            }
        };

        function guardarAsignacion() {
            alert('Asignación guardada correctamente');
            $('#modalAsignarMedicinas').modal('hide');
        }

        function verDetalles(pacienteId) {
            const paciente = datosPacientes[pacienteId];
            if (!paciente) {
                alert('Paciente no encontrado');
                return;
            }

            $('#detallePaciente').text(paciente.nombre);
            $('#detalleEnfermedad').text(paciente.enfermedad);
            
            const contenedor = $('#detalleMedicinas');
            contenedor.empty();

            paciente.medicinas.forEach(med => {
                contenedor.append(`
                    <div class="card card-medicina mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title text-primary">${med.nombre}</h6>
                                <span class="badge badge-info">${med.duracion}</span>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="card-text mb-1">
                                        <strong>Dosis:</strong> ${med.dosis}
                                    </p>
                                    <p class="card-text mb-1">
                                        <strong>Frecuencia:</strong> ${med.frecuencia}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="card-text">
                                        <strong>Motivo:</strong><br>
                                        <small>${med.motivo}</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            });

            $('#modalDetalles').modal('show');
        }

        function editarPaciente(id) {
            alert('Editando paciente ID: ' + id);
        }

        function eliminarAsignacion(id) {
            if (confirm('¿Está seguro de eliminar todas las medicinas asignadas a este paciente?')) {
                alert('Eliminando asignaciones del paciente ID: ' + id);
            }
        }

        function generarPDF() {
            alert('Generando PDF del reporte...');
            // Aquí iría la lógica para generar PDF
        }

        function imprimirDetalles() {
            window.print();
        }

        // Verificar interacciones cuando se seleccionan medicinas
        $('.medicina-check').change(function() {
            const medicinasSeleccionadas = $('.medicina-check:checked').length;
            if (medicinasSeleccionadas > 1) {
                // En una implementación real, aquí se verificarían interacciones
                console.log('Verificando interacciones para ' + medicinasSeleccionadas + ' medicinas');
            }
        });
    </script>
</body>
</html>