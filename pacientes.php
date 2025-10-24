<?php
// pacientes.php - PANTALLA PRINCIPAL (SOLO LISTA)
if (session_status() !== PHP_SESSION_ACTIVE) { 
    @session_start(); 
}
include './config/connection.php';
include './common_service/common_functions.php';

// Consulta pacientes con nuevos campos
$patients = obtenerPacientes($con);

// Función para obtener pacientes
function obtenerPacientes($conexion) {
    try {
        $query = "SELECT id_paciente, nombre, direccion, dpi, 
                         DATE_FORMAT(fecha_nacimiento, '%d/%m/%Y') AS fecha_nacimiento,
                         telefono, genero, tipo_sangre, antecedentes_personales, 
                         antecedentes_familiares, estado
                  FROM pacientes
                  ORDER BY nombre ASC";
        $stmt = $conexion->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        error_log('Error al cargar pacientes: ' . $ex->getMessage());
        return [];
    }
}

// Función para determinar clase del badge de estado
function getBadgeClassEstado($estado) {
    return $estado === 'activo' ? 'badge-activo' : 'badge-inactivo';
}

// Función para formatear antecedentes
function formatearAntecedentes($antecedentes) {
    if (empty($antecedentes)) {
        return '<span class="text-muted">No</span>';
    }
    return '<span class="badge badge-warning" title="' . htmlspecialchars($antecedentes) . '">
            <i class="fas fa-file-medical"></i> Sí
        </span>';
}

// Función para formatear tipo de sangre
function formatearTipoSangre($tipoSangre) {
    if (empty($tipoSangre)) {
        return '<span class="text-muted">-</span>';
    }
    return '<span class="badge badge-info">' . htmlspecialchars($tipoSangre) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <?php include './config/data_tables_css.php'; ?>
    <!-- DataTables Buttons (Bootstrap 4) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
    <title>Agregar Pacientes</title>
    <style>
        .badge-activo { 
            background: linear-gradient(45deg, #28a745, #20c997); 
            color: white; 
            font-weight: 500;
        }
        .badge-inactivo { 
            background: linear-gradient(45deg, #dc3545, #e83e8c); 
            color: white; 
            font-weight: 500;
        }
        .btn-icon { 
            width: 35px; 
            height: 35px; 
            padding: 0;
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            margin: 2px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .action-buttons { 
            display: flex; 
            gap: 5px; 
            justify-content: center; 
        }
        .search-box { 
            max-width: 300px; 
        }
        .card-header {
            background: linear-gradient(45deg, #0d6efd, #0dcaf0);
            color: white;
        }
        .table th {
            background: linear-gradient(45deg, #4e73df, #1cc88a);
            color: white;
            border: none;
        }
        /* Espaciado para la botonera de exportación */
        #dtButtons .btn { 
            margin-left: .25rem; 
            margin-bottom: .25rem;
        }
        .patient-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .patient-address {
            font-size: 0.85em;
            color: #6c757d;
        }
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
                        <h1><i class="fas fa-users text-primary"></i> Gestión de Pacientes</h1>
                        <small class="text-muted">Administre la información de los pacientes del sistema</small>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="agregar_paciente.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle"></i> Nuevo Paciente
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="card card-outline card-primary rounded-0 shadow">
                <div class="card-header text-white">
                    <h3 class="card-title"><i class="fas fa-list"></i> Lista de Pacientes</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Barra de búsqueda + Botonera DT -->
                    <div class="row mb-3 align-items-center">
                        <div class="col-md-6">
                            <div class="input-group search-box">
                                <input type="text" id="searchInput" class="form-control form-control-sm" 
                                       placeholder="Buscar por nombre, DPI, teléfono...">
                                <div class="input-group-append">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="fas fa-search"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <!-- Aquí se inyectarán los botones de DataTables -->
                            <div id="dtButtons" class="d-inline-block"></div>
                        </div>
                    </div>

                    <!-- Tabla de pacientes -->
                    <div class="row">
                        <div class="col-12 table-responsive">
                            <table id="all_patients" class="table table-striped table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%" class="text-center">#</th>
                                        <th width="20%">Paciente</th>
                                        <th width="12%">DPI</th>
                                        <th width="10%">Teléfono</th>
                                        <th width="8%">Género</th>
                                        <th width="8%" class="text-center">Tipo Sangre</th>
                                        <th width="12%" class="text-center">Antecedentes</th>
                                        <th width="8%" class="text-center">Estado</th>
                                        <th width="17%" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($patients)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                                No hay pacientes registrados
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($patients as $index => $patient): ?>
                                        <tr id="row-p-<?php echo (int)$patient['id_paciente']; ?>">
                                            <td class="text-center"><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="patient-name">
                                                    <?php echo htmlspecialchars($patient['nombre']); ?>
                                                </div>
                                                <div class="patient-address">
                                                    <i class="fas fa-map-marker-alt text-muted"></i> 
                                                    <?php echo htmlspecialchars($patient['direccion']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['dpi']); ?></td>
                                            <td>
                                                <?php if (!empty($patient['telefono'])): ?>
                                                    <i class="fas fa-phone text-success mr-1"></i>
                                                    <?php echo htmlspecialchars($patient['telefono']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $genero = strtolower($patient['genero'] ?? '');
                                                $icon = $genero === 'femenino' ? 'fa-venus' : 
                                                       ($genero === 'masculino' ? 'fa-mars' : 'fa-genderless');
                                                $color = $genero === 'femenino' ? 'text-danger' : 
                                                        ($genero === 'masculino' ? 'text-primary' : 'text-muted');
                                                ?>
                                                <i class="fas <?php echo $icon; ?> <?php echo $color; ?>"></i>
                                                <?php echo htmlspecialchars($patient['genero']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo formatearTipoSangre($patient['tipo_sangre']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo formatearAntecedentes($patient['antecedentes_personales']); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?php echo getBadgeClassEstado($patient['estado']); ?>">
                                                    <?php echo ucfirst($patient['estado']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center action-buttons">
                                                <!-- Editar -->
                                                <a href="editar_paciente.php?id=<?php echo (int)$patient['id_paciente']; ?>" 
                                                   class="btn btn-outline-primary btn-icon" 
                                                   title="Editar información del paciente"
                                                   data-toggle="tooltip">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <!-- Recetar -->
                                                <a href="nueva_prescripcion.php?patient=<?php echo (int)$patient['id_paciente']; ?>" 
                                                   class="btn btn-outline-success btn-icon" 
                                                   title="Crear nueva receta"
                                                   data-toggle="tooltip">
                                                    <i class="fas fa-prescription"></i>
                                                </a>
                                                <!-- Historial -->
                                                <a href="historial_paciente.php?paciente=<?php echo (int)$patient['id_paciente']; ?>" 
                                                   class="btn btn-outline-info btn-icon" 
                                                   title="Ver historial médico"
                                                   data-toggle="tooltip">
                                                    <i class="fas fa-history"></i>
                                                </a>
                                                <!-- Eliminar -->
                                                <button type="button" class="btn btn-outline-danger btn-icon btn-del"
                                                        data-id="<?php echo (int)$patient['id_paciente']; ?>"
                                                        data-name="<?php echo htmlspecialchars($patient['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        title="Eliminar paciente"
                                                        data-toggle="tooltip">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>

<!-- DataTables Buttons deps -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Menu activo
    showMenuSelected("#mnu_patients", "#mi_patients");

    // Inicializar tooltips
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });

    // Eliminación de pacientes
    $(document).on('click', '#all_patients button.btn-del', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id || '0', 10);
        const name = this.dataset.name || 'el paciente';
        
        if (!id) {
            Swal.fire('Error', 'ID de paciente no válido', 'error');
            return;
        }

        Swal.fire({
            title: `¿Eliminar a "${name}"?`,
            html: `Esta acción eliminará permanentemente al paciente <strong>"${name}"</strong> y todos sus datos asociados.<br><br>
                  <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash"></i> Sí, eliminar',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            backdrop: true,
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                Swal.fire({
                    title: 'Eliminando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('ajax/eliminar_paciente.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' 
                    },
                    body: new URLSearchParams({ 
                        id: String(id), 
                        cascade: '1' 
                    })
                })
                .then(async (response) => {
                    let data = {};
                    try { 
                        data = await response.json(); 
                    } catch (error) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return data;
                })
                .then((data) => {
                    if (data && data.ok) {
                        // Eliminar fila de la tabla
                        const row = document.getElementById(`row-p-${id}`);
                        if (row) {
                            row.style.backgroundColor = '#f8d7da';
                            setTimeout(() => {
                                row.remove();
                                // Reordenar números si es necesario
                                actualizarNumeracion();
                            }, 500);
                        }
                        
                        Swal.fire({
                            title: '¡Eliminado!',
                            text: 'Paciente eliminado correctamente',
                            icon: 'success',
                            confirmButtonColor: '#28a745',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        const errorMsg = data?.msg || data?.message || 'No se pudo eliminar el paciente.';
                        throw new Error(errorMsg);
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        html: `<strong>No se pudo eliminar el paciente:</strong><br>${error.message}`,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    });

    // Función para actualizar numeración después de eliminar
    function actualizarNumeracion() {
        $('#all_patients tbody tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    // DataTable + Buttons
    let dtPacientes;

    $(document).ready(function() {
        dtPacientes = $('#all_patients').DataTable({
            responsive: true,
            dom: '<"row align-items-center mb-3"<"col-md-6"l><"col-md-6 text-right"B>>rtip',
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            order: [[1, 'asc']], // Ordenar por nombre
            language: {
                sProcessing:   "Procesando...",
                sLengthMenu:   "Mostrar _MENU_ registros",
                sZeroRecords:  "No se encontraron pacientes",
                sEmptyTable:   "No hay pacientes registrados",
                sInfo:         "Mostrando _START_ a _END_ de _TOTAL_ pacientes",
                sInfoEmpty:    "Mostrando 0 a 0 de 0 pacientes",
                sInfoFiltered: "(filtrado de _MAX_ pacientes en total)",
                sSearch:       "Buscar:",
                sLoadingRecords: "Cargando...",
                oPaginate: {
                    sFirst:    "Primero",
                    sLast:     "Último",
                    sNext:     "Siguiente",
                    sPrevious: "Anterior"
                }
            },
            buttons: [
                {
                    extend: 'copy',
                    className: 'btn btn-sm btn-secondary',
                    text: '<i class="fas fa-copy mr-1"></i>Copiar',
                    exportOptions: { 
                        columns: ':visible:not(:last-child)',
                        modifier: { search: 'applied' }
                    }
                },
                {
                    extend: 'excel',
                    className: 'btn btn-sm btn-success',
                    text: '<i class="fas fa-file-excel mr-1"></i>Excel',
                    exportOptions: { 
                        columns: ':visible:not(:last-child)',
                        modifier: { search: 'applied' }
                    }
                },
                {
                    extend: 'csv',
                    className: 'btn btn-sm btn-info',
                    text: '<i class="fas fa-file-csv mr-1"></i>CSV',
                    exportOptions: { 
                        columns: ':visible:not(:last-child)',
                        modifier: { search: 'applied' }
                    }
                },
                {
                    extend: 'pdf',
                    className: 'btn btn-sm btn-danger',
                    text: '<i class="fas fa-file-pdf mr-1"></i>PDF',
                    exportOptions: { 
                        columns: ':visible:not(:last-child)',
                        modifier: { search: 'applied' }
                    },
                    orientation: 'landscape',
                    pageSize: 'LETTER'
                },
                {
                    extend: 'print',
                    className: 'btn btn-sm btn-dark',
                    text: '<i class="fas fa-print mr-1"></i>Imprimir',
                    exportOptions: { 
                        columns: ':visible:not(:last-child)',
                        modifier: { search: 'applied' }
                    },
                    customize: function (win) {
                        $(win.document.body).find('h1').css('text-align', 'center');
                        $(win.document.body).find('table').addClass('display').css('font-size', '12px');
                    }
                },
                {
                    extend: 'colvis',
                    className: 'btn btn-sm btn-warning',
                    text: '<i class="fas fa-columns mr-1"></i>Columnas'
                }
            ],
            columnDefs: [
                { 
                    targets: -1, 
                    orderable: false,
                    searchable: false
                }
            ],
            initComplete: function() {
                // Mueve la botonera al contenedor superior derecho
                this.api().buttons().container().appendTo('#dtButtons');
            }
        });

        // Hook del buscador personalizado
        const $search = $('#searchInput');
        $search.on('keyup', function(){
            dtPacientes.search(this.value).draw();
        });

        // Ajustar tabla en redimensionamiento
        $(window).on('resize', function() {
            dtPacientes.columns.adjust().responsive.recalc();
        });
    });
</script>
</body>
</html>