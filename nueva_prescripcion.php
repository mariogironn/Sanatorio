<?php
// nueva_prescripcion.php - PANTALLA PRINCIPAL DE PRESCRIPCIONES (LISTA CRUD)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

// ======== BLOQUEO DE SESIÓN ========
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

// ======== ROLES Y PERMISOS (crear/actualizar/eliminar) ========
$roles = [];
try {
  $rs = $con->prepare("SELECT LOWER(r.nombre) rol
                       FROM usuario_rol ur JOIN roles r ON r.id_rol = ur.id_rol
                       WHERE ur.id_usuario = :u");
  $rs->execute([':u'=>$uid]);
  $roles = array_map(fn($x)=>$x['rol'], $rs->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) { /* noop */ }

// módulos relevantes
$modIds = [];
try {
  $mods = $con->query("SELECT id_modulo FROM modulos WHERE LOWER(nombre) IN ('prescripciones','pacientes','medicinas','medicamentos')");
  if ($mods) { $modIds = $mods->fetchAll(PDO::FETCH_COLUMN); }
} catch (Throwable $e) { $modIds = []; }

$canCreatePresc = $canUpdatePresc = $canDeletePresc = false;
if ($modIds) {
  // crear
  $q = $con->prepare("SELECT 1 FROM rol_permiso rp
                      JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
                      WHERE ur.id_usuario = :u AND rp.id_modulo IN (".implode(',', array_map('intval',$modIds)).")
                        AND rp.crear = 1 LIMIT 1");
  $q->execute([':u'=>$uid]); $canCreatePresc = (bool)$q->fetchColumn();

  // actualizar
  $q = $con->prepare("SELECT 1 FROM rol_permiso rp
                      JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
                      WHERE ur.id_usuario = :u AND rp.id_modulo IN (".implode(',', array_map('intval',$modIds)).")
                        AND rp.actualizar = 1 LIMIT 1");
  $q->execute([':u'=>$uid]); $canUpdatePresc = (bool)$q->fetchColumn();

  // eliminar
  $q = $con->prepare("SELECT 1 FROM rol_permiso rp
                      JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
                      WHERE ur.id_usuario = :u AND rp.id_modulo IN (".implode(',', array_map('intval',$modIds)).")
                        AND rp.eliminar = 1 LIMIT 1");
  $q->execute([':u'=>$uid]); $canDeletePresc = (bool)$q->fetchColumn();
}

// === VISIBILIDAD UNIVERSAL DE ACCIONES ===
// Clínicos que deberían ver siempre las acciones (por si decides modo conservador)
$esClinico = (bool) array_intersect(['doctor','medico','enfermero','enfermera'], $roles);

// Modo “mostrar a TODOS los roles” (requisito tuyo):
$mostrarAccionesParaTodos = true;

if ($mostrarAccionesParaTodos) {
    $canCreatePresc  = true;
    $canUpdatePresc  = true;
    $canDeletePresc  = true;
} else {
    // Opción alternativa: respeta permisos, pero garantiza a clínicos
    $canCreatePresc  = $canCreatePresc || $esClinico;
    $canUpdatePresc  = $canUpdatePresc || $esClinico;
    $canDeletePresc  = $canDeletePresc || $esClinico;
}

// ======== Consulta prescripciones con datos relacionados ========
try {
    $query = "
    SELECT 
        p.id_prescripcion, p.fecha_visita, p.proxima_visita, p.peso, p.presion, 
        p.enfermedad, p.sucursal, p.estado, p.created_at,
        pac.nombre AS paciente_nombre, pac.id_paciente,

        -- nombre a mostrar (si no hay, cae al usuario)
        COALESCE(u.nombre_mostrar, u.usuario) AS medico_nombre,
        u.usuario AS medico_usuario,
        LOWER(v.rol_nombre) AS rol_nombre,

        COUNT(d.id_detalle) AS total_medicinas
    FROM prescripciones p
    INNER JOIN pacientes pac 
            ON p.id_paciente = pac.id_paciente
    LEFT JOIN usuarios u 
           ON p.medico_id = u.id
    LEFT JOIN vw_usuario_rol_principal v 
           ON v.id_usuario = u.id
    LEFT JOIN detalle_prescripciones d 
           ON p.id_prescripcion = d.id_prescripcion
    WHERE p.estado <> 'cancelada'
    GROUP BY p.id_prescripcion
    ORDER BY p.fecha_visita DESC, p.created_at DESC
    ";
    $stmtPrescripciones = $con->prepare($query);
    $stmtPrescripciones->execute();
} catch (PDOException $ex) {
    echo $ex->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <?php include './config/data_tables_css.php'; ?>
    <!-- DataTables Buttons (Bootstrap 4) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
    <title>Nueva Prescripciones</title>
    <style>
        .badge-activa { background-color: #28a745; color: white; }
        .badge-completada { background-color: #17a2b8; color: white; }
        .badge-pendiente { background-color: #ffc107; color: black; }
        .badge-cancelada { background-color: #dc3545; color: white; }
        .btn-icon { width: 35px; height: 35px; padding: 0; display: inline-flex; align-items: center; justify-content: center; margin: 2px; }
        .action-buttons { display: flex; gap: 5px; justify-content: center; }
        .search-box { max-width: 300px; }
        .medicina-count { font-size: 0.8em; }
        .patient-name { font-weight: 600; }
        .disease-badge { font-size: 0.85em; }
        #dtButtons .btn { margin-left: .25rem; }
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
                        <h1><i class="fas fa-prescription"></i>Gestion de Prescripción</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <?php if ($canCreatePresc): ?>
                        <a href="crear_prescripcion.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle"></i> Crear Prescripción
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="card card-outline card-primary rounded-0 shadow">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Lista de Prescripciones</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Barra de búsqueda + Botonera DT -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group search-box">
                                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Buscar prescripciones...">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <div id="dtButtons" class="d-inline-block"></div>
                        </div>
                    </div>

                    <!-- Tabla de prescripciones -->
                    <div class="row">
                        <div class="col-12 table-responsive">
                            <table id="all_prescriptions" class="table table-striped table-bordered table-hover">
                                <thead class="bg-gradient-primary text-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Paciente</th>
                                        <th>Fecha Visita</th>
                                        <th>Enfermedad</th>
                                        <th class="text-center">Medicinas</th>
                                        <th>Sucursal</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $count = 0;
                                    while ($row = $stmtPrescripciones->fetch(PDO::FETCH_ASSOC)) {
                                        $count++;
                                        $estado = $row['estado'] ?? 'activa';
                                        $badgeClass = 'badge-' . $estado; // usa tus clases .badge-activa, .badge-completada...
                                        $idp = (int)$row['id_prescripcion'];
                                    ?>
                                    <tr id="row-pr-<?php echo $idp; ?>">
                                        <td class="text-center"><?php echo $count; ?></td>
                                        <td>
                                            <span class="patient-name"><?php echo htmlspecialchars($row['paciente_nombre']); ?></span>
                                            <?php
                                              $rol = strtolower($row['rol_nombre'] ?? '');
                                              if (in_array($rol, ['doctor','medico','enfermero']) && !empty($row['medico_nombre'])) {
                                                  $label = ($rol === 'enfermero') ? 'Enfermero' : 'Médico';
                                                  echo '<br><small class="text-muted">' . $label . ': ' . htmlspecialchars($row['medico_nombre']) . '</small>';
                                              }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($row['fecha_visita'])); ?>
                                            <?php if (!empty($row['proxima_visita'])): ?>
                                                <br><small class="text-muted medicina-count">
                                                    <i class="fas fa-calendar-check"></i> Próx: <?php echo date('d/m/Y', strtotime($row['proxima_visita'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning disease-badge"><?php echo htmlspecialchars($row['enfermedad']); ?></span>
                                            <?php if (!empty($row['peso']) || !empty($row['presion'])): ?>
                                                <br><small class="text-muted">
                                                    <?php if (!empty($row['peso'])): ?>P: <?php echo htmlspecialchars($row['peso']); ?>kg<?php endif; ?>
                                                    <?php if (!empty($row['presion'])): ?> | PA: <?php echo htmlspecialchars($row['presion']); ?><?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-info medicina-count"><i class="fas fa-pills"></i> <?php echo (int)$row['total_medicinas']; ?> med.</span>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($row['sucursal']); ?></small></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($estado); ?></span>
                                        </td>
                                        <td class="text-center action-buttons">
                                            <?php if ($canUpdatePresc): ?>
                                            <a href="editar_prescripcion.php?id=<?php echo $idp; ?>" class="btn btn-outline-primary btn-icon" title="Editar Prescripción">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>

                                            <a href="ver_prescripcion.php?id=<?php echo $idp; ?>" class="btn btn-outline-info btn-icon" title="Ver Detalle">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <a href="historial_paciente.php?paciente=<?php echo (int)$row['id_paciente']; ?>" class="btn btn-outline-success btn-icon" title="Historial del Paciente">
                                                <i class="fas fa-history"></i>
                                            </a>

                                            <?php if ($canDeletePresc): ?>
                                            <button type="button" class="btn btn-outline-danger btn-icon btn-del"
                                                    data-id="<?php echo $idp; ?>"
                                                    data-name="prescripción de <?php echo htmlspecialchars($row['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
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
    showMenuSelected("#mnu_patients", "#mi_prescriptions");

    // Eliminación de prescripciones (el botón solo aparece si hay permiso)
    $(document).on('click', '#all_prescriptions button.btn-del', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id || '0', 10);
        const name = this.dataset.name || 'la prescripción';
        if (!id) return;

        Swal.fire({
            title: `¿Eliminar ${name}?`,
            text: 'Esta acción eliminará la prescripción y todos sus detalles. No se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('ajax/eliminar_prescripcion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({ id: String(id) })
                })
                .then(async (r) => {
                    let json = {};
                    try { json = await r.json(); } catch (_) {}
                    return json;
                })
                .then((json) => {
                    if (json && json.success) {
                        const tr = document.getElementById(`row-pr-${id}`);
                        if (tr) tr.remove();
                        Swal.fire('Eliminado', 'Prescripción eliminada correctamente', 'success');
                    } else {
                        const msg = json?.msg || json?.message || 'No se pudo eliminar.';
                        Swal.fire('Error', msg, 'error');
                    }
                })
                .catch(() => {
                    Swal.fire('Error', 'Error de conexión', 'error');
                });
            }
        });
    });

    // DataTable + Buttons
    var dtPresc;
    $(document).ready(function() {
        dtPresc = $('#all_prescriptions').DataTable({
            responsive: true,
            dom: '<"row align-items-center mb-2"<"col-md-6"l><"col-md-6 text-right"B>>rtip',
            pageLength: 10,
            order: [[2, 'desc']], // Fecha de visita DESC
            language: {
                sProcessing:   "Procesando...",
                sLengthMenu:   "Mostrar _MENU_ registros",
                sZeroRecords:  "No se encontraron resultados",
                sEmptyTable:   "Ningún dato disponible en esta tabla",
                sInfo:         "Mostrando _START_ a _END_ de _TOTAL_ registros",
                sInfoEmpty:    "Mostrando 0 a 0 de 0 registros",
                sInfoFiltered: "(filtrado de _MAX_ registros en total)",
                sSearch:       "Buscar:",
                sLoadingRecords: "Cargando...",
                oPaginate: {
                    sFirst:    "Primero",
                    sLast:     "Último",
                    sNext:     "Siguiente",
                    sPrevious: "Anterior"
                },
                buttons: {
                    copy: "Copiar",
                    excel: "Excel",
                    csv: "CSV",
                    pdf: "PDF",
                    print: "Imprimir",
                    colvis: "Columnas"
                }
            },
            buttons: [
                { extend: 'copyHtml5',  text: 'Copiar',   className: 'btn btn-sm btn-secondary',
                  exportOptions: { columns: ':visible:not(:last-child)' } },
                { extend: 'excelHtml5', text: 'Excel',    className: 'btn btn-sm btn-success',
                  exportOptions: { columns: ':visible:not(:last-child)' } },
                { extend: 'csvHtml5',   text: 'CSV',      className: 'btn btn-sm btn-info',
                  exportOptions: { columns: ':visible:not(:last-child)' } },
                { extend: 'pdfHtml5',   text: 'PDF',      className: 'btn btn-sm btn-danger',
                  exportOptions: { columns: ':visible:not(:last-child)' }, orientation: 'landscape', pageSize: 'LETTER' },
                { extend: 'print',      text: 'Imprimir', className: 'btn btn-sm btn-dark',
                  exportOptions: { columns: ':visible:not(:last-child)' } },
                { extend: 'colvis',     text: 'Columnas', className: 'btn btn-sm btn-warning' }
            ],
            columnDefs: [{ targets: -1, orderable: false }]
        });

        dtPresc.buttons().container().appendTo('#dtButtons');
        $('#searchInput').on('keyup', function(){ dtPresc.search(this.value).draw(); });
    });
</script>
</body>
</html>
