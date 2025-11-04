<?php
/**
 * Página de Inventario de Medicinas
 *
 * @category Inventario
 * @package  Medicinas
 */

// Iniciar sesión si no está activa
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

// Verificar autenticación del usuario
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    header('Location: login.php');
    exit;
}

/**
 * Obtener información del usuario actual
 */
$uStmt = $con->prepare("SELECT id, usuario, nombre_mostrar FROM usuarios WHERE id = :id LIMIT 1");
$uStmt->execute([':id' => $uid]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => 0, 'usuario' => '', 'nombre_mostrar' => '(usuario)'];

/**
 * Obtener roles del usuario para control de permisos
 */
$rStmt = $con->prepare("
    SELECT LOWER(r.nombre) rol
    FROM usuario_rol ur
    JOIN roles r ON r.id_rol = ur.id_rol
    WHERE ur.id_usuario = :id
");
$rStmt->execute([':id' => $uid]);
$userRoles = array_map(fn($r) => $r['rol'], $rStmt->fetchAll(PDO::FETCH_ASSOC));

// Verificar si el usuario es personal médico
$isMedStaff = (bool) array_intersect($userRoles, ['medico', 'doctor', 'enfermero', 'enfermera']);

// Rol clínico a mostrar en el banner
$rolClinico = '';
foreach ($userRoles as $r) {
    if (in_array($r, ['medico','doctor','enfermero','enfermera'], true)) { $rolClinico = $r; break; }
}

/**
 * Verificar permisos para crear medicinas y recetar
 */
$modStmt = $con->prepare("SELECT id_modulo FROM modulos WHERE slug IN ('medicinas','medicamentos','pacientes') OR nombre IN ('Medicinas','Medicamentos','Pacientes') ORDER BY id_modulo");
$modStmt->execute();
$modIds = array_column($modStmt->fetchAll(PDO::FETCH_ASSOC), 'id_modulo');

$canCreate = false;
if ($modIds) {
    $permStmt = $con->prepare("
        SELECT 1
        FROM rol_permiso rp
        JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
        WHERE ur.id_usuario = :u AND rp.id_modulo IN (" . implode(',', array_map('intval', $modIds)) . ") AND rp.crear = 1
        LIMIT 1
    ");
    $permStmt->execute([':u' => $uid]);
    $canCreate = (bool)$permStmt->fetchColumn();
}
$canPrescribe = $isMedStaff && $canCreate;

/** Texto de rol a mostrar en banner: clínico si existe; si no, el/los rol(es) real(es) del usuario */
$rolTexto = $rolClinico ?: ( $userRoles ? implode(', ', $userRoles) : '—' );

/**
 * Obtener inventario de medicinas con información adicional
 */
$rows = [];
try {
    $stmt = $con->query("
        SELECT m.*,
               mm.presentacion, mm.laboratorio, mm.categoria, mm.descripcion,
               (SELECT COUNT(*) FROM paciente_medicinas pm
                  WHERE pm.medicina_id = m.id AND pm.estado='activo') AS pacientes_activos
          FROM medicamentos m
          LEFT JOIN medicamentos_meta mm ON mm.id_medicamento = m.id
         ORDER BY m.nombre_medicamento ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}

/**
 * Preparar opciones para formularios de recetas
 */
$optPacientes = getPacientes($con);
$optMedicinas = getMedicamentos($con);
$optMedicos = '<option value="">Seleccionar médico...</option>';
try {
    $mStmt = $con->query("
        SELECT DISTINCT u.id, u.nombre_mostrar
        FROM usuarios u
        JOIN usuario_rol ur ON ur.id_usuario = u.id
        JOIN roles r ON r.id_rol = ur.id_rol
        WHERE LOWER(r.nombre) IN ('medico','doctor','enfermero','enfermera')
        ORDER BY u.nombre_mostrar ASC
    ");
    while ($r = $mStmt->fetch(PDO::FETCH_ASSOC)) {
        $sel = ($r['id'] == $uid) ? ' selected' : '';
        $optMedicos .= '<option value="' . $r['id'] . '"' . $sel . '>' . htmlspecialchars($r['nombre_mostrar']) . '</option>';
    }
} catch (Throwable $e) {}

/**
 * Obtener recetas recientes para mostrar en el dashboard
 */
$recientes = [];
try {
    $rs = $con->query("
        SELECT pm.id, pm.fecha_asignacion, pm.dosis, pm.frecuencia, pm.motivo_prescripcion,
               p.nombre AS paciente, 
               m.nombre_medicamento AS med,
               u.id AS medico_id,
               u.nombre_mostrar AS medico,
               COALESCE((
                 SELECT LOWER(r2.nombre) FROM usuario_rol ur2 
                 JOIN roles r2 ON r2.id_rol = ur2.id_rol
                 WHERE ur2.id_usuario = u.id
                   AND LOWER(r2.nombre) IN ('doctor','medico','enfermero','enfermera')
                 LIMIT 1
               ), '') AS rol_clinico
          FROM paciente_medicinas pm
          JOIN pacientes p ON p.id_paciente = pm.paciente_id
          JOIN medicamentos m ON m.id = pm.medicina_id
     LEFT JOIN usuarios u ON u.id = pm.usuario_id
         WHERE pm.estado='activo'
      ORDER BY pm.fecha_asignacion DESC
         LIMIT 6
    ");
    $recientes = $rs->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

/**
 * Calcular KPIs para el dashboard
 */
$totalMedicinas = count($rows);
$stockBajo = 0;
$controladas = 0;
$totalUnidades = 0;

foreach ($rows as $r) {
    $act = (int)($r['stock_actual'] ?? 0);
    $min = (int)($r['stock_minimo'] ?? 0);
    $tip = (string)($r['tipo_medicamento'] ?? '');
    if ($act < $min) $stockBajo++;
    if ($tip === 'controlado') $controladas++;
    $totalUnidades += $act;
}

try {
    $pacientesActivosTotal = (int)$con->query("
        SELECT COUNT(DISTINCT paciente_id)
        FROM paciente_medicinas
        WHERE estado='activo'
    ")->fetchColumn();
} catch (Throwable $e) {
    $pacientesActivosTotal = 0;
}

try {
    $recetasHoy = (int)$con->query("
        SELECT COUNT(*)
        FROM paciente_medicinas
        WHERE DATE(fecha_asignacion) = CURDATE()
    ")->fetchColumn();
} catch (Throwable $e) {
    $recetasHoy = 0;
}

/**
 * Obtener pacientes con medicación activa para resumen
 */
$pacAct = [];
try {
    $q = $con->query("
        SELECT pm.id, pm.paciente_id, p.nombre AS paciente,
               m.nombre_medicamento AS med,
               pm.dosis, pm.frecuencia, pm.motivo_prescripcion,
               COALESCE(u.nombre_mostrar,'') AS medico
          FROM paciente_medicinas pm
          JOIN pacientes p ON p.id_paciente = pm.paciente_id
          JOIN medicamentos m ON m.id = pm.medicina_id
     LEFT JOIN usuarios u ON u.id = pm.usuario_id
         WHERE pm.estado='activo'
      ORDER BY p.nombre ASC, pm.fecha_asignacion DESC
    ");
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$r['paciente_id'];
        if (!isset($pacAct[$pid])) {
            $pacAct[$pid] = ['paciente' => $r['paciente'], 'count' => 0, 'items' => []];
        }
        $pacAct[$pid]['count']++;
        $pacAct[$pid]['items'][] = $r;
    }
} catch (Throwable $e) {
    $pacAct = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include './config/site_css_links.php'; ?>
    <?php include './config/data_tables_css.php'; ?>
    <title>Agregar Medicinas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .metric { min-height: 84px }
        .actions { display: inline-flex; gap: .35rem }
        .btn-icon { width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center }
        .badge-pill { border-radius: 10rem; padding: .35rem .6rem; font-weight: 600 }
        .recent-item { border-bottom: 1px solid #eee; padding: .5rem 0 }
        .recent-item:last-child { border-bottom: none }
        .recent-wrap { max-height: 310px; overflow-y: auto; padding-right: .25rem }
        .recent-meta-right { text-align: right; min-width: 180px; }
        .recent-role a { color: #17a2b8; font-weight: 600; }

        .table-title { font-size: 1.25rem; font-weight: 600; color: #2c3e50; margin: 0; display: flex; align-items: center; gap: 8px }
        .med-name { font-weight: 600; color: #2c3e50 }
        .med-details { font-size: .85rem; color: #6c757d; margin-top: 4px }
        .stock-info, .patients-info { text-align: center }
        .stock-number { font-size: 1.25rem; font-weight: 700 }
        .stock-normal { color: #28a745 }
        .stock-warning { color: #dc3545 }
        .stock-label, .patients-label { font-size: .8rem; color: #6c757d }
        .patients-number { font-size: 1.05rem; font-weight: 600; color: #007bff }

        .pac-card { border: 1px solid #dee2e6; border-radius: 0.25rem; padding: 1rem; margin-bottom: 1rem; background: #f8f9fa; }
        .pac-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
        .pac-badge { margin-left: auto; }
        .pac-med { font-weight: 600; color: #495057; margin-bottom: 0.25rem; }
        .pac-meta { color: #6c757d; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .pac-tag { background: #e9ecef; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.8rem; margin-left: 0.5rem; }
        .pac-foot { font-size: 0.85rem; color: #6c757d; font-style: italic; }

        /* Banner usuario/permiso */
        .role-banner{
            background: linear-gradient(90deg,#4e73df,#2e59d9);
            color:#fff; border-radius:.25rem; padding:.75rem 1rem; margin-bottom:.75rem
        }
        .role-banner .rb-name{font-weight:600}
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
    <?php include './config/header.php';
    include './config/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <h1 class="table-title"><i class="fas fa-pills"></i> Inventario de Medicinas</h1>
                <div>
                    <?php if ($canCreate): ?>
                        <button type="button" class="btn btn-primary mr-1" id="btnNuevaMed">
                            <i class="fas fa-plus"></i> Nueva Medicina
                        </button>
                    <?php endif; ?>
                    <?php if ($canPrescribe): ?>
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalReceta">
                            <i class="fas fa-file-medical"></i> Nueva Receta
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="content">

            <!-- Banner de usuario y permisos -->
            <div class="role-banner d-flex justify-content-between align-items-center">
                <div>
                    <div class="rb-name"><i class="fas fa-user"></i>
                        <?= htmlspecialchars($user['nombre_mostrar']) ?></div>
                    <small>Rol: <?= htmlspecialchars($rolTexto) ?><br>
                    Usuario: <?= htmlspecialchars($user['usuario']) ?></small>
                </div>
                <div>
                    <span>Permisos:
                        <?php if ($canPrescribe): ?>
                            <span class="badge badge-info">Recetar Medicinas</span>
                        <?php else: ?>
                            <span class="badge badge-light text-muted">Sin permiso para recetar</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary metric">
                        <div class="inner"><h3 id="kpiTotal"><?= $totalMedicinas ?></h3>
                            <p>Total Medicinas</p></div>
                        <div class="icon"><i class="fas fa-pills"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success metric">
                        <div class="inner"><h3 id="kpiPacAct"><?= $pacientesActivosTotal ?></h3>
                            <p>Pacientes Activos</p></div>
                        <div class="icon"><i class="fas fa-user-md"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning metric">
                        <div class="inner"><h3 id="kpiRecHoy"><?= $recetasHoy ?></h3>
                            <p>Recetas Hoy</p></div>
                        <div class="icon"><i class="fas fa-file-medical"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info metric">
                        <div class="inner"><h3 id="kpiBajo"><?= $stockBajo ?></h3>
                            <p>Stock Bajo</p></div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
            </div>

            <!-- Lista tipo maqueta -->
            <div class="card card-outline card-primary">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-list"></i> Lista de Medicamentos</h3>
                </div>
                <div class="card-body table-responsive">
                    <table id="tblMed" class="table table-striped table-bordered">
                        <thead>
                        <tr>
                            <th style="width:6%" class="text-center">ID</th>
                            <th style="width:34%">Medicina</th>
                            <th style="width:15%" class="text-center">Disponible</th>
                            <th style="width:20%" class="text-center">Pacientes Activos</th>
                            <th style="width:10%" class="text-center">Estado</th>
                            <th style="width:15%" class="text-center">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r):
                            $id = (int)$r['id'];
                            $act = (int)($r['stock_actual'] ?? 0);
                            $min = (int)($r['stock_minimo'] ?? 0);
                            $isLow = $act < $min;
                            $pa = (int)($r['pacientes_activos'] ?? 0);
                            $pva = trim($r['presentacion'] ?? '');
                            $lab = trim($r['laboratorio'] ?? '');
                            $prin = trim($r['principio_activo'] ?? ($r['nombre_generico'] ?? ''));
                            $tipo = (string)($r['tipo_medicamento'] ?? '');
                            $estado = (string)($r['estado'] ?? 'activo');
                            $inactiva = ($estado !== 'activo');
                            ?>
                            <tr id="med-row-<?= $id ?>" data-is-low="<?= $isLow ? '1' : '0' ?>">
                                <td class="text-center"><?= $id ?></td>
                                <td>
                                    <div class="med-name"><?= htmlspecialchars($r['nombre_medicamento']) ?></div>
                                    <div class="med-details">
                                        <?= htmlspecialchars($prin ?: ''); ?>
                                        <?php if ($pva) echo ' · ' . htmlspecialchars($pva); ?>
                                        <?php if ($lab) echo ' · ' . htmlspecialchars($lab); ?>
                                    </div>
                                </td>
                                <td class="stock-info">
                                    <div class="stock-number <?= $isLow ? 'stock-warning' : 'stock-normal' ?>"><?= $act ?></div>
                                    <div class="stock-label">unidades</div>
                                </td>
                                <td class="patients-info">
                                    <div class="patients-number"><?= $pa ?></div>
                                    <div class="patients-label">pacientes activos</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge estado-badge <?= $inactiva?'badge-secondary':'badge-success' ?>">
                                        <?= $inactiva?'Inactivo':'Disponible' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="actions">
                                        <button class="btn btn-info btn-sm btn-icon btn-view" data-id="<?= $id ?>" title="Ver"><i class="fa fa-eye"></i></button>
                                        <button class="btn btn-warning btn-sm btn-icon btn-edit" data-id="<?= $id ?>" title="Editar" <?= $inactiva?'disabled':'' ?>><i class="fa fa-edit"></i></button>
                                        <?php if ($inactiva): ?>
                                            <button class="btn btn-success btn-sm btn-icon btn-activar" data-id="<?= $id ?>" title="Activar"><i class="fa fa-undo"></i></button>
                                        <?php endif; ?>
                                        <button
                                            class="btn btn-danger btn-sm btn-icon btn-delete"
                                            data-id="<?= $id ?>"
                                            data-name="<?= htmlspecialchars($r['nombre_medicamento']) ?>"
                                            data-stock="<?= $act ?>"
                                            data-min="<?= $min ?>"
                                            data-tipo="<?= $tipo ?>"
                                            title="Eliminar">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recientes -->
            <div class="card card-outline card-secondary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history"></i> Recetas Recientes</h3>
                </div>
                <div class="card-body recent-wrap" id="contenedorRecientes">
                    <?php if (!$recientes): ?>
                        <em>No hay recetas todavía.</em>
                    <?php else:
                        foreach ($recientes as $r):
                            $rol = strtolower($r['rol_clinico'] ?? '');
                            $pref = ($rol === 'doctor' || $rol === 'medico') ? 'Dr.' : (($rol === 'enfermero') ? 'Enfermero' : (($rol === 'enfermera') ? 'Enfermera' : ''));
                            ?>
                            <div class="recent-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?= htmlspecialchars($r['paciente']) ?></strong><br>
                                        <span><?= htmlspecialchars($r['med']) ?> - <?= htmlspecialchars($r['dosis']) ?></span><br>
                                        <small class="text-muted">Para: <?= htmlspecialchars($r['motivo_prescripcion'] ?? '') ?></small>
                                    </div>
                                    <div class="recent-meta-right">
                                        <?php
                                        $ts = strtotime($r['fecha_asignacion']);
                                        $hora = str_replace(['am', 'pm'], ['a. m.', 'p. m.'], date('h:i a', $ts));
                                        echo '<small class="text-muted">' . date('d/m/Y ', $ts) . $hora . '</small>';
                                        ?><br>
                                        <?php if (!empty($r['medico'])): ?>
                                            <small class="recent-role">
                                                <?php if ($pref !== ''): echo $pref . ' '; endif; ?>
                                                <a href="usuarios.php?user=<?= (int)$r['medico_id'] ?>"><?= htmlspecialchars($r['medico']) ?></a>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php
                        endforeach;
                    endif; ?>
                </div>
            </div>

            <!-- Pacientes con medicación activa -->
            <div class="card card-outline card-info">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fas fa-user-injured"></i> Pacientes con Medicación Activa</h3>
                    <button class="btn btn-outline-secondary btn-sm d-print-none" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
                <div class="card-body" id="pacActWrap">
                    <?php if (!$pacAct): ?>
                        <em>No hay pacientes con medicación activa.</em>
                    <?php else:
                        foreach ($pacAct as $grp):
                            $first = $grp['items'][0];
                            $cnt = (int)$grp['count']; ?>
                            <div class="pac-card">
                                <div class="pac-head">
                                    <i class="fas fa-user"></i>
                                    <strong><?= htmlspecialchars($grp['paciente']) ?></strong>
                                    <span class="badge badge-primary pac-badge"><?= $cnt ?> medicamento<?= $cnt === 1 ? '' : 's' ?></span>
                                </div>
                                <div class="pac-med"><?= htmlspecialchars($first['med']) ?></div>
                                <div class="pac-meta"><?= htmlspecialchars($first['dosis']) ?>
                                    — <?= htmlspecialchars($first['frecuencia']) ?>
                                    <?php if (!empty($first['motivo_prescripcion'])): ?>
                                        <span class="pac-tag"><?= htmlspecialchars($first['motivo_prescripcion']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="pac-foot">Por: <?= htmlspecialchars($first['medico'] ?: '—') ?></div>
                            </div>
                        <?php
                        endforeach;
                    endif; ?>
                </div>
            </div>

        </section>
    </div>

    <?php include './config/footer.php'; ?>
</div>

<!-- Modal Nueva/Editar Medicina -->
<div class="modal fade" id="modalNuevaMed" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formNuevaMed" autocomplete="off">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tituloNuevaMed"><i class="fas fa-plus"></i> Nueva Medicina</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="medicina_id" value="">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Nombre Comercial</label>
                            <input name="nombre_medicamento" id="nombre_medicamento" class="form-control" required
                                   placeholder="Ej: Advil, Nurofen">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Principio Activo</label>
                            <input name="principio_activo" id="principio_activo" class="form-control" required
                                   placeholder="Ej: Ibuprofeno, Paracetamol">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Presentación</label>
                            <input name="presentacion" id="presentacion" class="form-control" required
                                   placeholder="Ej: Tabletas 400mg">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Disponible</label>
                            <input type="number" name="stock_actual" id="stock_actual" class="form-control" min="0"
                                   value="0" required placeholder="Cantidad">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Stock Mínimo</label>
                            <input type="number" name="stock_minimo" id="stock_minimo" class="form-control" min="0"
                                   value="10" placeholder="Seguridad">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Laboratorio</label>
                            <input name="laboratorio" id="laboratorio" class="form-control"
                                   placeholder="Laboratorio fabricante">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Categoría</label>
                            <select name="categoria" id="categoria" class="form-control">
                                <option value="">Seleccionar categoría</option>
                                <option value="analgesico">Analgésico</option>
                                <option value="antibiotico">Antibiótico</option>
                                <option value="antiinflamatorio">Antiinflamatorio</option>
                                <option value="cardiovascular">Cardiovascular</option>
                                <option value="gastrointestinal">Gastrointestinal</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" id="descripcion" rows="3" class="form-control"
                                  placeholder="Descripción adicional..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit" id="btnGuardarMed">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Receta -->
<div class="modal fade" id="modalReceta" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formReceta" autocomplete="off">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-file-medical"></i> Nueva Receta Médica</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <?php if (!$canPrescribe): ?>
                        <div class="alert alert-warning mb-0"><i class="fas fa-lock"></i> No tienes permiso para recetar.
                        </div>
                    <?php else: ?>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Seleccionar Paciente *</label>
                                <select name="paciente_id" id="rec_paciente" class="form-control"
                                        required><?= $optPacientes ?></select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Médico que Receta</label>
                                <select id="rec_medico_ui" class="form-control" <?= $isMedStaff ? 'disabled' : ''; ?>><?= $optMedicos ?></select>
                                <input type="hidden" name="medico_id" value="<?= $uid ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Seleccionar Medicina</label>
                                <select name="medicina_id" id="rec_medicina" class="form-control"
                                        required><?= $optMedicinas ?></select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Diagnóstico/Enfermedad</label>
                                <input type="text" name="enfermedad_diagnostico" class="form-control" required
                                       placeholder="Ej: Hipertensión">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4"><label>Dosis</label><input type="text" name="dosis"
                                                                                        class="form-control" required>
                            </div>
                            <div class="form-group col-md-4"><label>Frecuencia</label><input type="text"
                                                                                             name="frecuencia"
                                                                                             class="form-control"
                                                                                             required></div>
                            <div class="form-group col-md-4"><label>Duración</label><input type="text"
                                                                                           name="duracion_tratamiento"
                                                                                           class="form-control"></div>
                        </div>
                        <div class="form-group"><label>Instrucciones Adicionales</label>
                            <textarea name="motivo_prescripcion" class="form-control"></textarea></div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <?php if ($canPrescribe): ?>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Guardar Receta</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalle de Medicina -->
<div class="modal fade" id="modalMedDetalle" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header bg-info text-white">
      <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detalles de Medicina</h5>
      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
      <div class="row">
        <div class="col-md-6">
          <div><b>Principio Activo:</b> <span id="md_principio">—</span></div>
          <div><b>Presentación:</b> <span id="md_presentacion">—</span></div>
          <div><b>Laboratorio:</b> <span id="md_laboratorio">—</span></div>
        </div>
        <div class="col-md-6">
          <div><b>Disponible:</b> <span id="md_disponible">—</span> <small>unidades</small></div>
          <div><b>Stock mínimo:</b> <span id="md_min">—</span></div>
          <div><b>Pacientes Activos:</b> <span id="md_pac_act">—</span></div>
          <div><b>Tipo:</b> <span id="md_tipo">—</span></div>
        </div>
      </div>

      <div class="card card-outline card-primary mt-3">
        <div class="card-header"><i class="fas fa-users"></i> Pacientes que usan esta medicina</div>
        <div class="card-body table-responsive">
          <table id="tblPacientesMed" class="table table-striped table-bordered">
            <thead>
              <tr>
                <th>Paciente</th><th>Médico</th><th>Dosis</th><th>Frecuencia</th>
                <th>Motivo/Diagnóstico</th><th>Duración</th><th>Fecha</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Cerrar</button></div>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
    let dt = null;
    function esc(s){return String(s||'').replace(/[&<>\"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
    function setKpi(sel,delta){const n=v=>isNaN(v)?0:v;const g=s=>n(parseInt($(s).text(),10));const set=(s,v)=>$(s).text(v<0?0:v);set(sel,g(sel)+delta);}
    function resetFormMed(){ $('#medicina_id').val('');$('#nombre_medicamento').val('');$('#principio_activo').val('');$('#presentacion').val('');$('#laboratorio').val('');$('#categoria').val('');$('#descripcion').val('');$('#stock_actual').val('0');$('#stock_minimo').val('10');}

    $(function () {
        dt = $("#tblMed").DataTable({
            responsive:true,lengthChange:true,autoWidth:false,
            language:{lengthMenu:"Mostrar _MENU_",search:"Buscar:",paginate:{first:"Primero",last:"Último",next:"Siguiente",previous:"Anterior"},zeroRecords:"Sin resultados",info:"Mostrando _START_ a _END_ de _TOTAL_",infoEmpty:"0 registros",infoFiltered:"(filtrado de _MAX_)"},
            columnDefs:[{targets:-1,orderable:false}]
        });

        $('#btnNuevaMed').on('click', function () {
            resetFormMed();
            $('#tituloNuevaMed').html('<i class="fas fa-pills"></i> Nueva Medicina');
            $('#btnGuardarMed').text('Guardar Medicina');
            $('#modalNuevaMed').modal('show');
        });

        $(document).on('click', '.btn-edit', function () {
            const id = $(this).data('id');
            $.getJSON('ajax/get_medicina.php', {id})
                .done(function (r) {
                    if (!r || !r.success) { Swal.fire('Aviso', r.message || 'No se pudo cargar', 'warning'); return; }
                    $('#medicina_id').val(r.data.id);
                    $('#nombre_medicamento').val(r.data.nombre_medicamento);
                    $('#principio_activo').val(r.data.principio_activo || '');
                    $('#stock_actual').val(r.data.stock_actual || 0);
                    $('#stock_minimo').val(r.data.stock_minimo || 0);
                    $('#presentacion').val(r.data.presentacion || '');
                    $('#laboratorio').val(r.data.laboratorio || '');
                    $('#categoria').val(r.data.categoria || '');
                    $('#descripcion').val(r.data.descripcion || '');
                    $('#tituloNuevaMed').html('<i class="fas fa-edit"></i> Editar Medicina');
                    $('#btnGuardarMed').text('Guardar Cambios');
                    $('#modalNuevaMed').modal('show');
                })
                .fail(x => Swal.fire('Error', x.responseText || 'Fallo al cargar', 'error'));
        });

        // Ver detalle en página
        $(document).on('click', '.btn-view', function(){
            const id = $(this).data('id');
            window.location.href = 'ver_detalle_medicina.php?id=' + encodeURIComponent(id);
        });

        // Eliminar con fallback a inactivar
        $(document).on('click','.btn-delete',function(){
            const $btn=$(this); const id=$btn.data('id'); const name=$btn.data('name');
            Swal.fire({title:'Eliminar',html:'¿Eliminar <b>'+name+'</b>?',icon:'question',showCancelButton:true,confirmButtonText:'Continuar',confirmButtonColor:'#dc3545'})
            .then(res=>{
                if(!res.isConfirmed) return;
                $.post('ajax/eliminar_medicina.php',{id:id})
                .done(function(resp){
                    try{resp=(typeof resp==='string')?JSON.parse(resp):resp;}catch(_){}
                    if(resp&&resp.success){
                        const $row=$('#med-row-'+id);
                        if(resp.mode==='deleted'){
                            if($.fn.dataTable&&$.fn.dataTable.isDataTable('#tblMed')){dt.row($row).remove().draw(false);} else {$row.remove();}
                            Swal.fire('Eliminada', resp.message || '', 'success');
                        }else{
                            $row.find('.estado-badge').removeClass('badge-success').addClass('badge-secondary').text('Inactivo');
                            $row.find('.btn-edit').prop('disabled',true).addClass('disabled');
                            if($row.find('.btn-activar').length===0){
                                const activarBtn='<button class="btn btn-success btn-sm btn-icon btn-activar" data-id="'+id+'" title="Activar"><i class="fa fa-undo"></i></button>';
                                $row.find('.actions').prepend(activarBtn);
                            }
                            Swal.fire('Inactivada', resp.message || '', 'info');
                        }
                    }else{
                        Swal.fire('Aviso', (resp&&resp.message)||'No se pudo completar la operación', 'warning');
                    }
                })
                .fail(x=> Swal.fire('Error', x.responseText || 'Fallo en servidor', 'error'));
            });
        });

        // Activar
        $(document).on('click','.btn-activar',function(){
            const id=$(this).data('id');
            $.post('ajax/toggle_medicina_estado.php',{id:id,accion:'activar'})
            .done(function(r){
                try{r=(typeof r==='string')?JSON.parse(r):r;}catch(_){}
                if(r&&r.success&&r.estado==='activo'){
                    const $row=$('#med-row-'+id);
                    $row.find('.estado-badge').removeClass('badge-secondary').addClass('badge-success').text('Disponible');
                    $row.find('.btn-edit').prop('disabled',false).removeClass('disabled');
                    $row.find('.btn-activar').remove();
                    Swal.fire('Activada','La medicina se activó','success');
                }else{
                    Swal.fire('Aviso', (r&&r.message)||'No se pudo activar', 'warning');
                }
            })
            .fail(x=> Swal.fire('Error', x.responseText || 'Fallo en servidor', 'error'));
        });

        // Guardar medicina
        $("#formNuevaMed").on('submit', function (e) {
            e.preventDefault();
            const form=$(this);
            $.post('ajax/guardar_medicina_simple.php', form.serialize())
            .done(function (resp) {
                let r; try{r=(typeof resp==='string')?JSON.parse(resp):resp;}catch(_){Swal.fire('Error','Respuesta inválida del servidor','error');return;}
                if (!r.success){Swal.fire('Aviso', r.message || 'No se pudo guardar', 'warning'); return;}
                const d=r.data||{}; const rowId='#med-row-'+r.id;
                const isLow=(parseInt(d.stock_actual,10)||0) < (parseInt(d.stock_minimo,10)||0);
                const pacientesActivos=parseInt(d.pacientes_activos,10)||0;
                const estado=(d.estado||'activo'); const inactiva=(estado!=='activo');

                const medHtml='<div class="med-name">'+esc(d.nombre_medicamento)+'</div>' +
                              '<div class="med-details">'+esc([d.principio_activo,d.presentacion,d.laboratorio].filter(Boolean).join(' · '))+'</div>';
                const stockHtml='<div class="stock-number '+(isLow?'stock-warning':'stock-normal')+'">'+esc(d.stock_actual)+'</div><div class="stock-label">unidades</div>';
                const patientsHtml='<div class="patients-number">'+pacientesActivos+'</div><div class="patients-label">pacientes activos</div>';
                const estadoHtml='<span class="badge estado-badge '+(inactiva?'badge-secondary':'badge-success')+'">'+(inactiva?'Inactivo':'Disponible')+'</span>';
                const accHtml='<div class="actions">'+
                        '<button class="btn btn-info btn-sm btn-icon btn-view" data-id="'+r.id+'" title="Ver"><i class="fa fa-eye"></i></button>'+
                        '<button class="btn btn-warning btn-sm btn-icon btn-edit" data-id="'+r.id+'" title="Editar" '+(inactiva?'disabled':'')+'><i class="fa fa-edit"></i></button>'+
                        (inactiva?'<button class="btn btn-success btn-sm btn-icon btn-activar" data-id="'+r.id+'" title="Activar"><i class="fa fa-undo"></i></button>':'')+
                        '<button class="btn btn-danger btn-sm btn-icon btn-delete" data-id="'+r.id+'" data-name="'+esc(d.nombre_medicamento)+'" data-stock="'+esc(d.stock_actual)+'" data-min="'+esc(d.stock_minimo)+'" data-tipo="'+esc(d.tipo_medicamento)+'" title="Eliminar"><i class="fa fa-trash"></i></button></div>';

                if ($(rowId).length){
                    const $row=$(rowId); const wasLow=$row.data('isLow')===1;
                    dt.cell($row,1).data(medHtml);
                    dt.cell($row,2).data(stockHtml);
                    dt.cell($row,3).data(patientsHtml);
                    dt.cell($row,4).data(estadoHtml);
                    dt.cell($row,5).data(accHtml);
                    $row.data('isLow', isLow?1:0);
                    if (wasLow !== isLow) { setKpi('#kpiBajo', isLow ? +1 : -1); }
                    dt.draw(false);
                } else {
                    const node=dt.row.add([ r.id, medHtml, stockHtml, patientsHtml, estadoHtml, accHtml ]).draw(false).node();
                    $(node).attr('id','med-row-'+r.id).data('isLow', isLow?1:0);
                    setKpi('#kpiTotal', +1); if (isLow) setKpi('#kpiBajo', +1);
                }
                $('#modalNuevaMed').modal('hide');
                Swal.fire({icon:'success',title:'Guardado',toast:true,position:'top-end',timer:1200,showConfirmButton:false});
            })
            .fail(x => Swal.fire('Error', x.responseText || 'Fallo al guardar', 'error'));
        });

        // Guardar receta médica
        $("#formReceta").on('submit', function (e) {
            e.preventDefault();
            $.post('ajax/guardar_medicina_paciente.php', $(this).serialize())
            .done(function (r) {
                try{ r=JSON.parse(r);}catch(e){ r={success:false,message:r};}
                if (r.success) { $("#modalReceta").modal('hide'); Swal.fire('OK','Receta guardada','success').then(()=>location.reload()); }
                else { Swal.fire('Aviso', r.message || 'No se pudo guardar', 'warning'); }
            })
            .fail(x => Swal.fire('Error', x.responseText || 'Fallo al guardar', 'error'));
        });
    });
</script>
</body>
</html>
