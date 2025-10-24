<?php
// ver_historial_completo.php - VER HISTORIAL COMPLETO DE UN PACIENTE
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include './config/connection.php';
include './common_service/common_functions.php';

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
        header('Location: historial_paciente.php');
        exit;
    }

    // Prescripciones del paciente
    $queryPrescripciones = "SELECT p.*, u.nombre_mostrar as medico_nombre,
                                   COUNT(d.id_detalle) as total_medicinas
                            FROM prescripciones p
                            LEFT JOIN usuarios u ON p.medico_id = u.id
                            LEFT JOIN detalle_prescripciones d ON p.id_prescripcion = d.id_prescripcion
                            WHERE p.id_paciente = ? AND p.estado != 'cancelada'
                            GROUP BY p.id_prescripcion
                            ORDER BY p.fecha_visita DESC";
    
    $stmtPrescripciones = $con->prepare($queryPrescripciones);
    $stmtPrescripciones->execute([$id_paciente]);
    $prescripciones = $stmtPrescripciones->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas
    $queryStats = "SELECT 
                    COUNT(DISTINCT id_prescripcion) as total_visitas,
                    COUNT(DISTINCT enfermedad) as enfermedades_diferentes,
                    MIN(fecha_visita) as primera_visita,
                    MAX(fecha_visita) as ultima_visita,
                    GROUP_CONCAT(DISTINCT enfermedad) as todas_enfermedades
                   FROM prescripciones 
                   WHERE id_paciente = ? AND estado != 'cancelada'";
    
    $stmtStats = $con->prepare($queryStats);
    $stmtStats->execute([$id_paciente]);
    $estadisticas = $stmtStats->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    echo "Error: " . $ex->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <title>Historial Completo del Paciente</title>
    <style>
        .historial-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card { border-left: 4px solid #3498db; }
        .prescripcion-card { border-left: 4px solid #2ecc71; transition: all 0.3s ease; }
        .prescripcion-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .badge-enfermedad { font-size: 0.8em; }
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: #e0e0e0; }
        .timeline-item { position: relative; margin-bottom: 20px; }
        .timeline-item::before { content: ''; position: absolute; left: -23px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: #3498db; }
        .nota-card { transition: all 0.3s ease; }
        .nota-card:hover { transform: translateX(5px); }
        
        /* Estilos para SweetAlert2 personalizado */
        .swal2-popup {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .swal2-title {
            color: #2c3e50;
            font-weight: 600;
        }
        .swal2-html-container {
            color: #5a6c7d;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
    <?php include './config/header.php'; include './config/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1><i class="fas fa-history"></i> Historial Médico Completo</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="historial_paciente.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </a>
                        <a href="reporte_historial_paciente.php?id=<?php echo $id_paciente; ?>" 
                           class="btn btn-info btn-sm" target="_blank">
                            <i class="fas fa-print"></i> Imprimir Reporte
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">

            <!-- Mensajes de éxito -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <div class="container-fluid">
                <?php if ($paciente): ?>
                
                <!-- Información del Paciente -->
                <div class="card historial-header mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h2 class="mb-1"><?php echo htmlspecialchars($paciente['nombre']); ?></h2>
                                <p class="mb-1">
                                    <i class="fas fa-id-card"></i> DPI: <?php echo htmlspecialchars($paciente['dpi'] ?: 'No registrado'); ?> | 
                                    <i class="fas fa-phone"></i> Tel: <?php echo htmlspecialchars($paciente['telefono'] ?: 'No registrado'); ?> | 
                                    <i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($paciente['genero'] ?: 'No especificado'); ?>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($paciente['direccion'] ?: 'Dirección no registrada'); ?>
                                    <?php if ($paciente['tipo_sangre']): ?>
                                        | <i class="fas fa-tint"></i> Tipo de Sangre: <?php echo htmlspecialchars($paciente['tipo_sangre']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <div class="btn-group">
                                    <a href="nueva_prescripcion.php?patient=<?php echo $id_paciente; ?>" 
                                       class="btn btn-light btn-sm">
                                        <i class="fas fa-file-medical"></i> Nueva Prescripción
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?php echo (int)$estadisticas['total_visitas']; ?></h3>
                                <p class="text-muted mb-0">Total de Visitas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h3 class="text-success"><?php echo (int)$estadisticas['enfermedades_diferentes']; ?></h3>
                                <p class="text-muted mb-0">Enfermedades Diferentes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h3 class="text-info">
                                    <?php echo $estadisticas['primera_visita'] ? date('d/m/Y', strtotime($estadisticas['primera_visita'])) : 'N/A'; ?>
                                </h3>
                                <p class="text-muted mb-0">Primera Visita</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h3 class="text-warning">
                                    <?php echo $estadisticas['ultima_visita'] ? date('d/m/Y', strtotime($estadisticas['ultima_visita'])) : 'N/A'; ?>
                                </h3>
                                <p class="text-muted mb-0">Última Visita</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Prescripciones -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-prescription"></i> Historial de Prescripciones</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($prescripciones)): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> Este paciente no tiene prescripciones registradas.
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($prescripciones as $prescripcion): ?>
                                <div class="timeline-item">
                                    <div class="card prescripcion-card mb-3">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-calendar-check"></i> 
                                                Visita del <?php echo date('d/m/Y', strtotime($prescripcion['fecha_visita'])); ?>
                                            </h5>
                                            <div>
                                                <span class="badge badge-<?php echo $prescripcion['estado'] === 'activa' ? 'success' : ($prescripcion['estado'] === 'completada' ? 'info' : 'warning'); ?>">
                                                    <?php echo ucfirst($prescripcion['estado']); ?>
                                                </span>
                                                <a href="ver_prescripcion.php?id=<?php echo $prescripcion['id_prescripcion']; ?>" 
                                                   class="btn btn-sm btn-outline-primary ml-2">
                                                    <i class="fas fa-eye"></i> Ver Detalle
                                                </a>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Diagnóstico:</strong> 
                                                        <span class="badge badge-warning badge-enfermedad">
                                                            <?php echo htmlspecialchars($prescripcion['enfermedad']); ?>
                                                        </span>
                                                    </p>
                                                    <p><strong>Sucursal:</strong> <?php echo htmlspecialchars($prescripcion['sucursal']); ?></p>
                                                    <?php if ($prescripcion['medico_nombre']): ?>
                                                        <p><strong>Médico:</strong> <?php echo htmlspecialchars($prescripcion['medico_nombre']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <?php if ($prescripcion['peso'] || $prescripcion['presion']): ?>
                                                        <p><strong>Signos Vitales:</strong></p>
                                                        <ul class="list-unstyled">
                                                            <?php if ($prescripcion['peso']): ?>
                                                                <li><i class="fas fa-weight"></i> Peso: <?php echo htmlspecialchars($prescripcion['peso']); ?> kg</li>
                                                            <?php endif; ?>
                                                            <?php if ($prescripcion['presion']): ?>
                                                                <li><i class="fas fa-heartbeat"></i> Presión: <?php echo htmlspecialchars($prescripcion['presion']); ?></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                    <p><strong>Medicinas:</strong> 
                                                        <span class="badge badge-info">
                                                            <?php echo (int)$prescripcion['total_medicinas']; ?> recetadas
                                                        </span>
                                                    </p>
                                                    <?php if ($prescripcion['proxima_visita']): ?>
                                                        <p><strong>Próxima visita:</strong> <?php echo date('d/m/Y', strtotime($prescripcion['proxima_visita'])); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Antecedentes -->
                <div class="row mt-4">
                    <?php if ($paciente['antecedentes_personales']): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-file-medical-alt"></i> Antecedentes Personales</h3>
                            </div>
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($paciente['antecedentes_personales'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($paciente['antecedentes_familiares']): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-users"></i> Antecedentes Familiares</h3>
                            </div>
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($paciente['antecedentes_familiares'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Notas del Historial -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title"><i class="fas fa-sticky-note"></i> Notas del Historial</h3>
                        <a href="agregar_nota_historial.php?id_paciente=<?php echo $id_paciente; ?>" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Agregar Nota
                        </a>
                    </div>
                    <div class="card-body">
                        <?php
                        // Obtener notas del historial
                        $notas_historial = [];
                        try {
                            $queryNotas = "SELECT hn.*, u.nombre_mostrar AS creado_por_nombre
                                           FROM historial_notas hn
                                           LEFT JOIN usuarios u ON hn.creado_por = u.id
                                           WHERE hn.id_paciente = ?
                                           ORDER BY hn.fecha_nota DESC, hn.creado_en DESC";
                            $stmtNotas = $con->prepare($queryNotas);
                            $stmtNotas->execute([$id_paciente]);
                            $notas_historial = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $ex) {
                            // La tabla puede no existir aún. Silenciar.
                        }
                        ?>
                        
                        <?php if (empty($notas_historial)): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> No hay notas registradas en el historial.
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($notas_historial as $nota): ?>
                                <div class="timeline-item">
                                    <div class="card mb-3 nota-card border-left-<?php 
                                        echo $nota['tipo'] == 'observacion' ? 'warning' : 
                                             ($nota['tipo'] == 'seguimiento' ? 'info' : 
                                             ($nota['tipo'] == 'recordatorio' ? 'danger' : 'success')); 
                                    ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-<?php 
                                                    echo $nota['tipo'] == 'observacion' ? 'eye' : 
                                                         ($nota['tipo'] == 'seguimiento' ? 'sync' : 
                                                         ($nota['tipo'] == 'recordatorio' ? 'bell' : 'sticky-note')); 
                                                ?>"></i> 
                                                <?php echo htmlspecialchars($nota['titulo']); ?>
                                            </h5>
                                            <div>
                                                <span class="badge badge-<?php 
                                                    echo $nota['tipo'] == 'observacion' ? 'warning' : 
                                                         ($nota['tipo'] == 'seguimiento' ? 'info' : 
                                                         ($nota['tipo'] == 'recordatorio' ? 'danger' : 'success')); 
                                                ?>">
                                                    <?php echo ucfirst($nota['tipo']); ?>
                                                </span>
                                                <small class="text-muted ml-2">
                                                    <?php echo date('d/m/Y', strtotime($nota['fecha_nota'])); ?>
                                                </small>
                                                <div class="btn-group ml-2">
                                                    <a href="editar_nota_historial.php?id=<?php echo $nota['id_nota']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Editar nota">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-nota"
                                                            data-id="<?php echo $nota['id_nota']; ?>"
                                                            data-titulo="<?php echo htmlspecialchars($nota['titulo']); ?>"
                                                            title="Eliminar nota">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p><?php echo nl2br(htmlspecialchars($nota['descripcion'])); ?></p>
                                            <?php if (!empty($nota['creado_por_nombre'])): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-user"></i> 
                                                    Registrado por: <?php echo htmlspecialchars($nota['creado_por_nombre']); ?>
                                                    el <?php echo date('d/m/Y H:i', strtotime($nota['creado_en'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> No se encontró el paciente solicitado.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>

<!-- SCRIPT CON SWEETALERT2 ELEGANTE -->
<script>
// Función para eliminar notas con SweetAlert2 elegante
function eliminarNota(btn) {
    const id = btn.getAttribute('data-id');
    const titulo = btn.getAttribute('data-titulo') || 'esta nota';
    
    // Verificar si SweetAlert2 está disponible
    if (typeof Swal !== 'undefined') {
        // Modal de confirmación elegante
        Swal.fire({
            title: '¿Eliminar Nota?',
            html: `
                <div style="text-align: center;">
                    <div style="font-size: 4rem; color: #e74c3c; margin-bottom: 1rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 style="color: #2c3e50; margin-bottom: 1rem;">¿Está seguro de eliminar esta nota?</h3>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #e74c3c;">
                        <strong style="color: #e74c3c;">"${titulo}"</strong>
                    </div>
                    <p style="color: #7f8c8d; margin-top: 1rem; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> Esta acción no se puede deshacer
                    </p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: `
                <i class="fas fa-trash"></i> Sí, eliminar
            `,
            cancelButtonText: `
                <i class="fas fa-times"></i> Cancelar
            `,
            reverseButtons: true,
            backdrop: `
                rgba(231, 76, 60, 0.1)
            `,
            customClass: {
                popup: 'animated bounceIn',
                confirmButton: 'btn-lg',
                cancelButton: 'btn-lg'
            },
            buttonsStyling: false,
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                ejecutarEliminacion(id, btn);
            }
        });
    } else {
        // Fallback con confirm nativo
        if (confirm('¿Está seguro de eliminar la nota: \"' + titulo + '\"?')) {
            ejecutarEliminacion(id, btn);
        }
    }
}

// Función que ejecuta la eliminación
function ejecutarEliminacion(id, btn) {
    console.log('Eliminando nota ID:', id);
    
    // Mostrar loading en el botón
    btn.disabled = true;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
    
    // Crear FormData para enviar
    const formData = new FormData();
    formData.append('id', id);
    
    // Hacer petición fetch
    fetch('ajax/eliminar_nota_historial.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        console.log('Respuesta del servidor:', data);
        
        if (data.success) {
            // Éxito - Mostrar modal de éxito elegante
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¡Eliminado!',
                    html: `
                        <div style="text-align: center;">
                            <div style="font-size: 4rem; color: #27ae60; margin-bottom: 1rem;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 style="color: #27ae60; margin-bottom: 1rem;">Nota Eliminada</h3>
                            <p style="color: #2c3e50;">${data.message}</p>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonColor: '#27ae60',
                    confirmButtonText: `
                        <i class="fas fa-check"></i> Aceptar
                    `,
                    buttonsStyling: false,
                    timer: 3000,
                    timerProgressBar: true,
                    showClass: {
                        popup: 'animate__animated animate__bounceIn'
                    }
                });
            } else {
                alert('Nota eliminada correctamente');
            }
            
            // Eliminar la tarjeta con animación
            const card = btn.closest('.timeline-item');
            if (card) {
                // Animación de salida
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateX(-100px)';
                card.style.height = card.offsetHeight + 'px';
                
                setTimeout(() => {
                    card.style.height = '0';
                    card.style.margin = '0';
                    card.style.padding = '0';
                    card.style.overflow = 'hidden';
                    
                    setTimeout(() => {
                        card.remove();
                        
                        // Si no quedan notas, mostrar mensaje
                        if (document.querySelectorAll('.timeline .timeline-item').length === 0) {
                            document.querySelector('.timeline').innerHTML = 
                                '<div class="alert alert-info text-center">' +
                                '<i class="fas fa-info-circle"></i> No hay notas registradas en el historial.' +
                                '</div>';
                        }
                    }, 300);
                }, 200);
            }
            
        } else {
            // Error del servidor
            throw new Error(data.message || 'Error al eliminar la nota');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Mostrar modal de error elegante
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Error',
                html: `
                    <div style="text-align: center;">
                        <div style="font-size: 4rem; color: #e74c3c; margin-bottom: 1rem;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3 style="color: #e74c3c; margin-bottom: 1rem;">Error al Eliminar</h3>
                        <p style="color: #2c3e50;">${error.message}</p>
                    </div>
                `,
                icon: 'error',
                confirmButtonColor: '#e74c3c',
                confirmButtonText: `
                    <i class="fas fa-times"></i> Aceptar
                `,
                buttonsStyling: false
            });
        } else {
            alert('Error: ' + error.message);
        }
        
        // Restaurar botón
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

// Asignar eventos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const botones = document.querySelectorAll('.btn-eliminar-nota');
    
    console.log('Asignando eventos a', botones.length, 'botones de eliminar');
    
    botones.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Click en eliminar - ID:', this.getAttribute('data-id'));
            eliminarNota(this);
        });
    });
});

// También asignar eventos para elementos cargados dinámicamente
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-eliminar-nota')) {
        e.preventDefault();
        const btn = e.target.closest('.btn-eliminar-nota');
        eliminarNota(btn);
    }
});

// Debug inicial
console.log('=== SISTEMA DE ELIMINACIÓN CON SWEETALERT2 CARGADO ===');
console.log('Botones eliminar encontrados:', document.querySelectorAll('.btn-eliminar-nota').length);
</script>

</body>
</html>