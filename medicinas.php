<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

// ===== Utilidades de sesión / usuario actual =====
$uid   = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

$uStmt = $con->prepare("SELECT id, usuario, nombre_mostrar FROM usuarios WHERE id = :id LIMIT 1");
$uStmt->execute([':id'=>$uid]);
$user  = $uStmt->fetch(PDO::FETCH_ASSOC) ?: ['id'=>0,'usuario'=>'','nombre_mostrar'=>'(usuario)'];

// Roles del usuario
$rStmt = $con->prepare("
  SELECT LOWER(r.nombre) rol
  FROM usuario_rol ur
  JOIN roles r ON r.id_rol = ur.id_rol
  WHERE ur.id_usuario = :id
");
$rStmt->execute([':id'=>$uid]);
$userRoles = array_map(fn($r)=>$r['rol'], $rStmt->fetchAll(PDO::FETCH_ASSOC));

// ¿Es personal médico?
$isMedStaff = (bool) array_intersect($userRoles, ['medico','doctor','enfermero','enfermera']);

// ¿Tiene permiso CREAR en módulo Medicinas/Pacientes?
$modStmt = $con->prepare("SELECT id_modulo FROM modulos WHERE slug IN ('medicinas','medicamentos','pacientes') OR nombre IN ('Medicinas','Medicamentos','Pacientes') ORDER BY id_modulo");
$modStmt->execute();
$modIds = array_column($modStmt->fetchAll(PDO::FETCH_ASSOC),'id_modulo');

$canCreate = false;
if ($modIds) {
  $permStmt = $con->prepare("
    SELECT 1
    FROM rol_permiso rp
    JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
    WHERE ur.id_usuario = :u AND rp.id_modulo IN (".implode(',', array_map('intval',$modIds)).") AND rp.crear = 1
    LIMIT 1
  ");
  $permStmt->execute([':u'=>$uid]);
  $canCreate = (bool)$permStmt->fetchColumn();
}

// Permiso final para RECETAR
$canPrescribe = $isMedStaff && $canCreate;

// ===== Inventario de medicinas (con pacientes activos) =====
$rows = [];
try {
  $stmt = $con->query("
    SELECT m.*,
           (SELECT COUNT(*)
              FROM paciente_medicinas pm
             WHERE pm.medicina_id = m.id AND pm.estado = 'activo') AS pacientes_activos
    FROM medicamentos m
    ORDER BY m.nombre_medicamento ASC
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $rows = [];
}

// ===== Opciones para selects del modal de receta =====
$optPacientes  = getPacientes($con);
$optMedicinas  = getMedicamentos($con);
$optMedicos    = '<option value="">Seleccionar médico...</option>';
try {
  $mStmt = $con->query("
    SELECT DISTINCT u.id, u.nombre_mostrar
    FROM usuarios u
    JOIN usuario_rol ur ON ur.id_usuario = u.id
    JOIN roles r        ON r.id_rol      = ur.id_rol
    WHERE LOWER(r.nombre) IN ('medico','doctor','enfermero','enfermera')
    ORDER BY u.nombre_mostrar ASC
  ");
  while ($r = $mStmt->fetch(PDO::FETCH_ASSOC)) {
    $sel = ($r['id'] == $uid) ? ' selected' : '';
    $optMedicos .= '<option value="'.$r['id'].'"'.$sel.'>'.htmlspecialchars($r['nombre_mostrar']).'</option>';
  }
} catch (Throwable $e) { /* noop */ }

// ===== Recetas recientes =====
$recientes = [];
try {
  $rs = $con->query("
    SELECT pm.id, pm.fecha_asignacion, pm.dosis, pm.frecuencia, pm.motivo_prescripcion,
           p.nombre AS paciente, 
           m.nombre_medicamento AS med,
           u.id AS medico_id,
           u.nombre_mostrar AS medico,
           /* rol clínico principal del usuario que recetó */
           COALESCE((
             SELECT LOWER(r2.nombre)
               FROM usuario_rol ur2 
               JOIN roles r2 ON r2.id_rol = ur2.id_rol
              WHERE ur2.id_usuario = u.id
                AND LOWER(r2.nombre) IN ('doctor','medico','enfermero','enfermera')
              LIMIT 1
           ), '') AS rol_clinico
    FROM paciente_medicinas pm
    JOIN pacientes p    ON p.id_paciente = pm.paciente_id
    JOIN medicamentos m ON m.id = pm.medicina_id
    LEFT JOIN usuarios u ON u.id = pm.usuario_id
    WHERE pm.estado='activo'
    ORDER BY pm.fecha_asignacion DESC
    LIMIT 6
  ");
  $recientes = $rs->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $recientes = []; }

// ===== Métricas rápidas =====
$totalMedicinas   = count($rows);
$stockBajo        = 0;
$controladas      = 0;
$totalUnidades    = 0;
foreach ($rows as $r) {
  $act = (int)($r['stock_actual']  ?? 0);
  $min = (int)($r['stock_minimo']  ?? 0);
  $tip = (string)($r['tipo_medicamento'] ?? '');
  if ($act < $min) $stockBajo++;
  if ($tip === 'controlado') $controladas++;
  $totalUnidades += $act;
}

/* NUEVAS MÉTRICAS para el bloque de resumen */
try {
  $pacientesActivosTotal = (int)$con->query("
    SELECT COUNT(DISTINCT paciente_id)
    FROM paciente_medicinas
    WHERE estado='activo'
  ")->fetchColumn();
} catch (Throwable $e) { $pacientesActivosTotal = 0; }

try {
  $recetasHoy = (int)$con->query("
    SELECT COUNT(*)
    FROM paciente_medicinas
    WHERE DATE(fecha_asignacion) = CURDATE()
  ")->fetchColumn();
} catch (Throwable $e) { $recetasHoy = 0; }

/* ========= NUEVO: Pacientes con medicación activa ========= */
$pacAct = [];
try{
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
  while($r = $q->fetch(PDO::FETCH_ASSOC)){
    $pid = (int)$r['paciente_id'];
    if(!isset($pacAct[$pid])){
      $pacAct[$pid] = ['paciente'=>$r['paciente'],'count'=>0,'items'=>[]];
    }
    $pacAct[$pid]['count']++;
    $pacAct[$pid]['items'][] = $r;
  }
}catch(Throwable $e){ $pacAct = []; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Agregar Medicinas</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    .metric{min-height:84px}
    .actions{display:inline-flex;gap:.35rem}
    .btn-icon{width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center}
    .stock-low{background:#fff4f4}
    .badge-pill{border-radius:10rem;padding:.35rem .6rem;font-weight:600}
    .recent-item{border-bottom:1px solid #eee;padding:.5rem 0}
    .recent-item:last-child{border-bottom:none}
    .recent-wrap{max-height:310px;overflow-y:auto;padding-right:.25rem}
    .recent-meta-right{ text-align:right; min-width:180px; }
    .recent-role a{ color:#17a2b8; font-weight:600; }

    /* ==== Estilos del bloque Pacientes con medicación activa ==== */
    .pac-card{border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;margin-bottom:1rem;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .pac-head{display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem}
    .pac-head i{color:#6c757d}
    .pac-badge{margin-left:.5rem}
    .pac-med{font-weight:700}
    .pac-meta{color:#6c757d}
    .pac-tag{display:inline-block;background:#eef2ff;color:#374151;border-radius:.35rem;padding:.15rem .45rem;font-size:.75rem;margin-left:.5rem}
    .pac-foot{font-style:italic;color:#495057;margin-top:.35rem}

    @media print {
      .d-print-none { display:none !important; }
      body * { visibility:hidden; }
      #pacActWrap, #pacActWrap * { visibility:visible; }
      #pacActWrap { position:absolute; left:0; top:0; width:100%; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
          <h1><i class="fas fa-pills"></i> Gestión de Medicinas</h1>
          <div>
            <?php if ($canCreate): ?>
            <button type="button" class="btn btn-primary mr-1" data-toggle="modal" data-target="#modalNuevaMed">
              <i class="fas fa-plus-circle"></i> Nueva Medicina
            </button>
            <?php endif; ?>
            <?php if ($canPrescribe): ?>
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalReceta">
              <i class="fas fa-file-medical"></i> Nueva Receta
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="card card-primary">
        <div class="card-body" style="background:linear-gradient(90deg,#4e6ad7,#2447c1);color:#fff">
          <div class="row">
            <div class="col-md-6">
              <h5 style="margin:0 0 .25rem 0;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['nombre_mostrar']); ?></h5>
              <div><b>Rol:</b> <?php echo htmlspecialchars(implode(', ', $userRoles) ?: '—'); ?></div>
              <div><b>Usuario:</b> <?php echo htmlspecialchars($user['usuario']); ?></div>
            </div>
            <div class="col-md-6 text-md-right">
              <div><b>Permisos:</b> 
                <?php if ($canPrescribe): ?>
                  <span class="badge badge-info">Recetar Medicinas</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Sin permiso para recetar</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ====== BLOQUE DE RESUMEN ====== -->
      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="small-box bg-primary metric">
            <div class="inner">
              <h3 id="kpiTotal"><?= $totalMedicinas ?></h3>
              <p>Total Medicinas</p>
            </div>
            <div class="icon"><i class="fas fa-pills"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-success metric">
            <div class="inner">
              <h3 id="kpiPacAct"><?= $pacientesActivosTotal ?></h3>
              <p>Pacientes Activos</p>
            </div>
            <div class="icon"><i class="fas fa-user-md"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-warning metric">
            <div class="inner">
              <h3 id="kpiRecHoy"><?= $recetasHoy ?></h3>
              <p>Recetas Hoy</p>
            </div>
            <div class="icon"><i class="fas fa-file-medical"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-info metric">
            <div class="inner">
              <h3 id="kpiBajo"><?= $stockBajo ?></h3>
              <p>Stock Bajo</p>
            </div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
          </div>
        </div>
      </div>
      <!-- ====== FIN BLOQUE DE RESUMEN ====== -->

      <!-- Inventario -->
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-list"></i> Inventario de Medicinas</h3>
        </div>
        <div class="card-body table-responsive">
          <table id="tblMed" class="table table-striped table-bordered">
            <thead>
              <tr>
                <th class="text-center">ID</th>
                <th>Medicina</th>
                <th class="text-center">Stock</th>
                <!-- Se quitó la columna Tipo -->
                <th class="text-center">Pacientes Activos</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
              $id   = (int)$r['id'];
              $act  = (int)($r['stock_actual'] ?? 0);
              $min  = (int)($r['stock_minimo'] ?? 0);
              $tipo = (string)($r['tipo_medicamento'] ?? 'no_controlado'); // para data-* del botón
              $isLow = $act < $min;
              $pa = (int)($r['pacientes_activos'] ?? 0);
              ?>
              <tr id="med-row-<?= $id ?>" class="<?= $isLow?'stock-low':'' ?>">
                <td class="text-center"><?= $id ?></td>
                <td>
                  <strong><?= htmlspecialchars($r['nombre_medicamento']) ?></strong>
                  <?php if (!empty($r['nombre_generico'])): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($r['nombre_generico']) ?></small>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <span class="badge badge-<?= $isLow?'danger':'success' ?>">
                    <?= $act ?> unidad<?= $act===1 ? '' : 'es' ?>
                  </span>
                </td>
                <td class="text-center">
                  <span class="badge badge-info badge-pill">
                    <?= $pa ?> paciente<?= $pa===1 ? '' : 's' ?>
                  </span>
                </td>
                <td class="text-center">
                  <div class="actions">
                    <button class="btn btn-info btn-sm btn-icon btn-view" data-id="<?= $id ?>" title="Ver"><i class="fa fa-eye"></i></button>
                    <button class="btn btn-warning btn-sm btn-icon btn-edit" data-id="<?= $id ?>" title="Editar"><i class="fa fa-edit"></i></button>
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

      <!-- Recetas recientes -->
      <div class="card card-outline card-secondary">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-history"></i> Recetas Recientes</h3></div>
        <div class="card-body recent-wrap" id="contenedorRecientes">
          <?php if (!$recientes): ?>
            <em>No hay recetas todavía.</em>
          <?php else: foreach ($recientes as $r): 
            $rol  = strtolower($r['rol_clinico'] ?? '');
            $pref = ($rol==='doctor' || $rol==='medico') ? 'Dr.' : (($rol==='enfermero') ? 'Enfermero' : (($rol==='enfermera') ? 'Enfermera' : ''));
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
                    $hora = str_replace(['am','pm'], ['a. m.','p. m.'], date('h:i a', $ts));
                    echo '<small class="text-muted">'.date('d/m/Y ', $ts).$hora.'</small>';
                  ?><br>
                  <?php if (!empty($r['medico'])): ?>
                    <small class="recent-role">
                      <?php if ($pref!==''): echo $pref.' '; endif; ?>
                      <a href="usuarios.php?user=<?= (int)$r['medico_id'] ?>"><?= htmlspecialchars($r['medico']) ?></a>
                    </small>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Pacientes con Medicación Activa -->
      <div class="card card-outline card-info">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="fas fa-user-injured"></i> Pacientes con Medicación Activa</h3>
          <button class="btn btn-outline-secondary btn-sm d-print-none" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir
          </button>
        </div>
        <div class="card-body" id="pacActWrap">
          <?php if(!$pacAct): ?>
            <em>No hay pacientes con medicación activa.</em>
          <?php else: ?>
            <?php foreach($pacAct as $grp): 
              $first = $grp['items'][0];
              $cnt   = (int)$grp['count'];
            ?>
              <div class="pac-card">
                <div class="pac-head">
                  <i class="fas fa-user"></i>
                  <strong><?= htmlspecialchars($grp['paciente']) ?></strong>
                  <span class="badge badge-primary pac-badge"><?= $cnt ?> medicamento<?= $cnt===1?'':'s' ?></span>
                </div>
                <div class="pac-med"><?= htmlspecialchars($first['med']) ?></div>
                <div class="pac-meta"><?= htmlspecialchars($first['dosis']) ?> — <?= htmlspecialchars($first['frecuencia']) ?>
                  <?php if(!empty($first['motivo_prescripcion'])): ?>
                    <span class="pac-tag"><?= htmlspecialchars($first['motivo_prescripcion']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="pac-foot">Por: <?= htmlspecialchars($first['medico'] ?: '—') ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<!-- Modal: Nueva Medicina -->
<div class="modal fade" id="modalNuevaMed" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form id="formNuevaMed" autocomplete="off">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="tituloNuevaMed"><i class="fas fa-pills"></i> Agregar Nueva Medicina</h5>
      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="medicina_id" value="">
        <div class="form-row">
          <div class="form-group col-md-12">
            <label>Nombre Comercial *</label>
            <input name="nombre_medicamento" id="nombre_medicamento" class="form-control" required>
          </div>
          <div class="form-group col-md-12">
            <label>Principio Activo *</label>
            <input name="principio_activo" id="principio_activo" class="form-control" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Stock Inicial</label>
            <input type="number" name="stock_actual" id="stock_actual" class="form-control" value="0" min="0">
          </div>
          <div class="form-group col-md-6">
            <label>Stock Mínimo Alertas</label>
            <input type="number" name="stock_minimo" id="stock_minimo" class="form-control" value="10" min="0">
          </div>
        </div>
        <small class="text-muted">
          * Para “forma farmacéutica / vía / concentración” usa la edición avanzada (si aplica).
        </small>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit" id="btnGuardarMed">Guardar Medicina</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Modal: Nueva Receta -->
<div class="modal fade" id="modalReceta" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form id="formReceta" autocomplete="off">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-file-medical"></i> Nueva Receta Médica</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <?php if(!$canPrescribe): ?>
          <div class="alert alert-warning mb-0"><i class="fas fa-lock"></i> No tienes permiso para recetar.</div>
        <?php else: ?>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Seleccionar Paciente *</label>
            <select name="paciente_id" id="rec_paciente" class="form-control" required><?= $optPacientes ?></select>
          </div>
          <div class="form-group col-md-6">
            <label>Médico que Receta *</label>
            <select id="rec_medico_ui" class="form-control" <?= $isMedStaff?'disabled':''; ?>>
              <?= $optMedicos ?>
            </select>
            <input type="hidden" name="medico_id" value="<?= $uid ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Seleccionar Medicina *</label>
            <select name="medicina_id" id="rec_medicina" class="form-control" required><?= $optMedicinas ?></select>
          </div>
          <div class="form-group col-md-6">
            <label>Diagnóstico/Enfermedad *</label>
            <input type="text" name="enfermedad_diagnostico" class="form-control" placeholder="Ej: Hipertensión, Diabetes..." required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Dosis *</label>
            <input type="text" name="dosis" class="form-control" placeholder="Ej: 1 tableta" required>
          </div>
          <div class="form-group col-md-4">
            <label>Frecuencia *</label>
            <input type="text" name="frecuencia" class="form-control" placeholder="Ej: Cada 8 horas" required>
          </div>
          <div class="form-group col-md-4">
            <label>Duración</label>
            <input type="text" name="duracion_tratamiento" class="form-control" placeholder="Ej: 7 días">
          </div>
        </div>

        <div class="form-group">
          <label>Instrucciones Adicionales</label>
          <textarea name="motivo_prescripcion" class="form-control" placeholder="Indicaciones para el paciente..."></textarea>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <?php if ($canPrescribe): ?>
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Guardar Receta</button>
        <?php endif; ?>
      </div>
    </form>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
  let dt = null;

  function resetFormMed(){
    $('#medicina_id').val('');
    $('#nombre_medicamento').val('');
    $('#principio_activo').val('');
    $('#stock_actual').val('0');
    $('#stock_minimo').val('10');
    $('#tituloNuevaMed').html('<i class="fas fa-pills"></i> Agregar Nueva Medicina');
    $('#btnGuardarMed').text('Guardar Medicina');
  }

  $(function(){
    dt = $("#tblMed").DataTable({
      responsive:true, lengthChange:true, autoWidth:false,
      language:{lengthMenu:"Mostrar _MENU_", search:"Buscar:", paginate:{first:"Primero",last:"Último",next:"Siguiente",previous:"Anterior"},
                zeroRecords:"Sin resultados", info:"Mostrando _START_ a _END_ de _TOTAL_", infoEmpty:"0 registros", infoFiltered:"(filtrado de _MAX_)"},
      columnDefs:[{targets:-1, orderable:false}]
    });

    // Ver detalles
    $(document).on('click','.btn-view',function(){
      const id = $(this).data('id');
      window.location.href = 'ver_detalle_medicina.php?id=' + encodeURIComponent(id);
    });

    // Editar
    $(document).on('click','.btn-edit',function(){
      const id = $(this).data('id');
      $.getJSON('ajax/get_medicina.php', {id:id})
        .done(function(r){
          if(r && r.success){
            $('#medicina_id').val(r.data.id);
            $('#nombre_medicamento').val(r.data.nombre_medicamento);
            $('#principio_activo').val(r.data.principio_activo || '');
            $('#stock_actual').val(r.data.stock_actual ?? 0);
            $('#stock_minimo').val(r.data.stock_minimo ?? 0);
            $('#tituloNuevaMed').html('<i class="fas fa-edit"></i> Editar Medicina');
            $('#btnGuardarMed').text('Guardar Cambios');
            $('#modalNuevaMed').modal('show');
          }else{
            Swal.fire('Aviso', r.message || 'No se pudo cargar la medicina', 'warning');
          }
        })
        .fail(x=> Swal.fire('Error', x.responseText || 'Fallo al cargar', 'error'));
    });

    // Eliminar (sin recargar, con fallback si la respuesta no es JSON)
    $(document).on('click','.btn-delete',function(){
      const $btn  = $(this);
      const id    = $btn.data('id');
      const name  = $btn.data('name');
      const stock = parseInt($btn.data('stock') || 0, 10);
      const min   = parseInt($btn.data('min')   || 0, 10);

      Swal.fire({
        title: 'Eliminar',
        html: '¿Eliminar <b>'+name+'</b>?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        confirmButtonColor: '#dc3545'
      }).then(res=>{
        if(!res.isConfirmed) return;

        $.post('ajax/eliminar_medicina.php', {id:id})
          .done(function(resp){
            let ok = false, msg = '';
            // Intentar parsear JSON
            try {
              const j = (typeof resp === 'string') ? JSON.parse(resp) : resp;
              ok  = !!(j && j.success);
              msg = (j && j.message) ? j.message : '';
            } catch(_e) {
              // Fallback: tratar 200 no-JSON como éxito si viene "ok", "1", "true", o texto corto sin HTML
              const t = String(resp || '').trim().toLowerCase();
              if (t === 'ok' || t === '1' || t === 'true' || (t && t.length < 40 && t.indexOf('<') === -1 && t.indexOf('error') === -1)) {
                ok = true;
                msg = 'Medicina eliminada correctamente.';
              }
            }

            if (ok) {
              const $row = $('#med-row-'+id);
              if ($.fn.dataTable && $.fn.dataTable.isDataTable('#tblMed')) {
                dt.row($row).remove().draw(false);
              } else {
                $row.fadeOut(150, function(){ $(this).remove(); });
              }
              // Actualizar KPIs
              const n = v => isNaN(v) ? 0 : v;
              const get = sel => n(parseInt($(sel).text(),10));
              const set = (sel, val) => $(sel).text(val < 0 ? 0 : val);
              set('#kpiTotal', get('#kpiTotal') - 1);
              set('#kpiBajo',  get('#kpiBajo')  - (stock < min ? 1 : 0));

              Swal.fire('Eliminada', msg || 'Registro eliminado', 'success');
            } else {
              Swal.fire('Aviso', msg || 'No se pudo eliminar', 'warning');
            }
          })
          .fail(function(x){
            // Mostrar mensaje del backend si viene JSON; si no, construir mensaje útil
            let msg = '';
            try {
              const j = x.responseJSON || JSON.parse(x.responseText || '{}');
              msg = j && (j.message || j.error) ? (j.message || j.error) : '';
            } catch(_) {}
            if(!msg){
              if (x.status === 0) msg = 'No hay conexión con el servidor.';
              else if (x.status === 404) msg = 'Recurso no encontrado (404).';
              else if (x.status === 405) msg = 'Método no permitido.';
              else if (x.status === 500) msg = 'Error interno del servidor.';
              else msg = x.statusText || 'Fallo al eliminar';
            }
            const icon = (x.status && x.status < 500) ? 'warning' : 'error';
            Swal.fire('Aviso', msg, icon);
          });
      });
    });

    // Guardar (crear/editar)
    $("#formNuevaMed").on('submit', function(e){
      e.preventDefault();
      const data = $(this).serialize();
      $.post('ajax/guardar_medicina_simple.php', data)
        .done(function(r){
          try{ r = JSON.parse(r); }catch(_){ r={success:false,message:r}; }
          if(r.success){
            $('#modalNuevaMed').modal('hide');
            Swal.fire('¡Listo!', 'Cambios guardados', 'success').then(()=> location.reload());
          }else{
            Swal.fire('Aviso', r.message || 'No se pudo guardar', 'warning');
          }
        })
        .fail(function(x){ Swal.fire('Error', x.responseText || 'Fallo al guardar', 'error'); });
    });

    // Reset modal
    $('#modalNuevaMed').on('show.bs.modal', function(e){
      const trigger = $(e.relatedTarget);
      if(trigger && !trigger.hasClass('btn-edit')) resetFormMed();
    });

    // Guardar receta
    $("#formReceta").on('submit', function(e){
      e.preventDefault();
      const data = $(this).serialize();
      $.post('ajax/guardar_medicina_paciente.php', data)
        .done(function(r){
          try{ r = JSON.parse(r); }catch(e){ r={success:false,message:r}; }
          if(r.success){
            $("#modalReceta").modal('hide');
            Swal.fire('OK','Receta guardada','success').then(()=>location.reload());
          }else{
            Swal.fire('Aviso', r.message || 'No se pudo guardar', 'warning');
          }
        })
        .fail(x=> Swal.fire('Error', x.responseText || 'Fallo al guardar', 'error'));
    });
  });
</script>
</body>
</html>
