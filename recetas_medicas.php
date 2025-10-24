<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';
require_once './common_service/auditoria_service.php';

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

// ¿Tiene permiso CREAR en módulo Recetas?
$canCreate = false;
$modStmt = $con->prepare("SELECT id_modulo FROM modulos WHERE slug = 'recetas' OR nombre LIKE '%Receta%'");
$modStmt->execute();
$modIds = array_column($modStmt->fetchAll(PDO::FETCH_ASSOC),'id_modulo');

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
$canCreateRecetas = $isMedStaff || $canCreate;

// ===== Lista de recetas =====
$recetas = [];
try {
  $stmt = $con->query("
    SELECT 
      rm.id_receta,
      rm.numero_receta,
      rm.fecha_emision,
      rm.estado,
      rm.id_medico,
      p.nombre         AS paciente_nombre,
      p.id_paciente,
      u.nombre_mostrar AS medico_nombre
    FROM recetas_medicas rm
    JOIN pacientes p ON p.id_paciente = rm.id_paciente
    JOIN usuarios  u ON u.id = rm.id_medico
    ORDER BY rm.fecha_emision DESC, rm.id_receta DESC
  ");
  $recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $recetas = [];
}

// detalle de medicamentos por receta (para pintar la card)
$getMeds = $con->prepare("
  SELECT nombre_medicamento, dosis, duracion, frecuencia
  FROM detalle_recetas
  WHERE id_receta = :id
  ORDER BY id_detalle ASC
");

// ===== OPCIONES MEJORADAS PARA SELECTS =====
$optPacientes  = getPacientes($con);

// Obtener médicos con sus roles
$optMedicos = '<option value="">Seleccionar médico...</option>';
try {
  $mStmt = $con->query("
    SELECT DISTINCT u.id, u.nombre_mostrar, LOWER(r.nombre) as rol
    FROM usuarios u
    JOIN usuario_rol ur ON ur.id_usuario = u.id
    JOIN roles r        ON r.id_rol      = ur.id_rol
    WHERE LOWER(r.nombre) IN ('medico','doctor','enfermero','enfermera')
    ORDER BY u.nombre_mostrar ASC
  ");
  while ($r = $mStmt->fetch(PDO::FETCH_ASSOC)) {
    $sel = ($r['id'] == $uid) ? ' selected' : '';
    $icon = '';
    $rol = strtolower($r['rol']);
    if ($rol === 'medico' || $rol === 'doctor') $icon = '👨‍⚕️';
    elseif ($rol === 'enfermero') $icon = '👨‍⚕️';
    elseif ($rol === 'enfermera') $icon = '👩‍⚕️';
    $optMedicos .= '<option value="'.$r['id'].'"'.$sel.'>'.$icon.' '.htmlspecialchars($r['nombre_mostrar']).'</option>';
  }
} catch (Throwable $e) { /* noop */ }

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
  <title>Recetas Médicas</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    .actions{display:inline-flex;gap:.35rem}
    .badge-pill{border-radius:10rem;padding:.35rem .6rem;font-weight:600}
    .receta-card{border:1px solid #e1e5e9;border-radius:8px;padding:20px;margin-bottom:20px;background:white;box-shadow:0 2px 4px rgba(0,0,0,0.05)}
    .receta-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #eee}
    .receta-number{font-weight:700;color:#2c3e50;font-size:1.1rem}
    .receta-date{color:#7f8c8d;font-size:0.9rem}
    .receta-info{margin-bottom:10px}
    .info-item{margin-bottom:6px;display:flex;gap:.5rem}
    .info-label{font-weight:600;min-width:90px;color:#34495e}
    .recipe-actions{display:flex;gap:10px;margin-top:10px}
    .no-recipes{text-align:center;padding:40px 20px;background:white;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.05)}
    .meds-box{margin-top:8px}
    .med-chip{background:#fff;border:1px solid #e7e9ee;border-left:4px solid #3498db;border-radius:6px;padding:10px 12px;margin-bottom:8px;box-shadow:0 2px 3px rgba(0,0,0,.03)}
    .med-chip b{display:block;font-size:.95rem;color:#1f2d3d;margin-bottom:2px}
    .med-chip small{display:inline-block;margin-right:14px;color:#5c6b7a}
    @media (max-width: 768px) {.info-label{min-width:70px}}
    /* Tabla oculta para exportar */
    #tablaRecetas{position:absolute;left:-9999px;width:100%}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-file-medical"></i> Sistema de Recetas Médicas</h1>
        <div>
          <?php if ($canCreateRecetas): ?>
          <button type="button" class="btn btn-primary mr-1" data-toggle="modal" data-target="#modalReceta">
            <i class="fas fa-plus-circle"></i> Nueva Receta
          </button>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="content">
      <!-- Panel usuario -->
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
                <?php if ($canCreateRecetas): ?>
                  <span class="badge badge-success">Crear Recetas Médicas</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Sin permiso para crear recetas</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lista -->
      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="fas fa-list"></i> Lista de Recetas Médicas</h3>
          <?php if (!empty($recetas)): ?>
          <div class="card-tools">
            <div class="btn-group">
              <button id="btnPrintRecetas" class="btn btn-outline-secondary btn-sm"><i class="fa fa-print"></i> Imprimir</button>
              <button id="btnPdfRecetas"   class="btn btn-outline-secondary btn-sm"><i class="fa fa-file-pdf"></i> PDF</button>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div class="card-body">
          <?php if (empty($recetas)): ?>
            <div class="no-recipes">
              <p>No hay recetas registradas</p>
              <?php if ($canCreateRecetas): ?>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalReceta">Crear primera receta</button>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div id="recipesGrid" class="recipes-grid">
              <?php foreach ($recetas as $receta): 
                $estadoClass = [
                  'activa'      => 'badge-success',
                  'completada'  => 'badge-info', 
                  'cancelada'   => 'badge-danger'
                ][$receta['estado']] ?? 'badge-secondary';

                // medicamentos de la receta
                $getMeds->execute([':id'=>$receta['id_receta']]);
                $meds = $getMeds->fetchAll(PDO::FETCH_ASSOC);

                // para edición sin AJAX: guardo meds simples en data-*
                $medsSimple = array_map(function($m){
                  return [
                    'nombre'     => $m['nombre_medicamento'],
                    'dosis'      => $m['dosis'],
                    'duracion'   => $m['duracion'],
                    'frecuencia' => $m['frecuencia']
                  ];
                }, $meds);
                $medsJson = htmlspecialchars(json_encode($medsSimple, JSON_UNESCAPED_UNICODE));
              ?>
                <div class="receta-card"
                     data-id="<?= (int)$receta['id_receta'] ?>"
                     data-numero="<?= htmlspecialchars($receta['numero_receta']) ?>"
                     data-fecha="<?= htmlspecialchars($receta['fecha_emision']) ?>"
                     data-paciente-id="<?= (int)$receta['id_paciente'] ?>"
                     data-paciente-nombre="<?= htmlspecialchars($receta['paciente_nombre']) ?>"
                     data-medico-id="<?= (int)$receta['id_medico'] ?>"
                     data-medico-nombre="<?= htmlspecialchars($receta['medico_nombre']) ?>"
                     data-estado="<?= htmlspecialchars($receta['estado']) ?>"
                     data-meds='<?= $medsJson ?>'>
                  <div class="receta-header">
                    <div class="receta-number"><?= htmlspecialchars($receta['numero_receta']) ?></div>
                    <div class="receta-date"><?= htmlspecialchars($receta['fecha_emision']) ?></div>
                  </div>

                  <div class="receta-info">
                    <div class="info-item"><span class="info-label">Paciente:</span><span><?= htmlspecialchars($receta['paciente_nombre']) ?></span></div>
                    <div class="info-item"><span class="info-label">Médico:</span><span><?= htmlspecialchars($receta['medico_nombre']) ?></span></div>
                    <div class="info-item"><span class="info-label">Estado:</span><span class="badge <?= $estadoClass ?>"><?= htmlspecialchars($receta['estado']) ?></span></div>
                  </div>

                  <div class="meds-box">
                    <?php if (empty($meds)): ?>
                      <div class="text-muted">— Sin medicamentos —</div>
                    <?php else: ?>
                      <?php foreach ($meds as $m): ?>
                        <div class="med-chip">
                          <b><i class="fas fa-pills"></i> <?= htmlspecialchars($m['nombre_medicamento']) ?></b>
                          <small><i class="fas fa-syringe"></i> Dosis: <?= htmlspecialchars($m['dosis']) ?></small>
                          <small><i class="fas fa-clock"></i> Duración: <?= htmlspecialchars($m['duracion']) ?></small>
                          <small><i class="fas fa-redo"></i> Frecuencia: <?= htmlspecialchars($m['frecuencia']) ?></small>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>

                  <div class="recipe-actions">
                    <?php if ($canCreateRecetas): ?>
                      <!-- CAMBIO: botón Editar -> link a editar_receta.php -->
                      <a class="btn btn-warning btn-sm" href="editar_receta.php?id=<?= (int)$receta['id_receta'] ?>">
                        <i class="fa fa-edit"></i> Editar
                      </a>
                      <button class="btn btn-danger btn-sm btn-delete-receta" data-numero="<?= htmlspecialchars($receta['numero_receta']) ?>">
                        <i class="fa fa-trash"></i> Eliminar
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Tabla oculta para exportar -->
            <table id="tablaRecetas" class="table table-striped table-bordered">
              <thead>
                <tr>
                  <th># Receta</th>
                  <th>Fecha</th>
                  <th>Paciente</th>
                  <th>Médico</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recetas as $receta): ?>
                  <tr>
                    <td><?= htmlspecialchars($receta['numero_receta']) ?></td>
                    <td><?= htmlspecialchars($receta['fecha_emision']) ?></td>
                    <td><?= htmlspecialchars($receta['paciente_nombre']) ?></td>
                    <td><?= htmlspecialchars($receta['medico_nombre']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($receta['estado'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<!-- Modal: Crear/Editar Receta (sin sucursal) -->
<div class="modal fade" id="modalReceta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form id="formReceta" autocomplete="off">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="tituloReceta"><i class="fas fa-file-medical"></i> Nueva Receta Médica</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_receta" id="receta_id" value="">

        <div class="form-row">
          <div class="form-group col-md-6">
            <label><i class="fas fa-user-injured"></i> Paciente *</label>
            <select name="id_paciente" id="receta_paciente" class="form-control select2" required style="width:100%;">
              <?= $optPacientes ?>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label><i class="fas fa-user-md"></i> Médico que Receta *</label>
            <select name="id_medico" id="receta_medico" class="form-control" required <?= $isMedStaff ? 'disabled' : '' ?>>
              <?= $optMedicos ?>
            </select>
            <?php if ($isMedStaff): ?>
              <input type="hidden" name="id_medico" value="<?= $uid ?>">
              <small class="form-text text-muted"><i class="fas fa-info-circle"></i> Se ha seleccionado automáticamente como médico</small>
            <?php endif; ?>
          </div>
        </div>

        <!-- Medicamentos -->
        <div class="medicamentos-section" style="border:1px solid #e1e1e1;border-radius:6px;padding:16px;background:#f9f9f9;margin-top:10px">
          <h3 class="section-title mb-3"><i class="fas fa-pills"></i> Medicamentos</h3>
          <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end">
              <input type="text" id="medNombre"    placeholder="Nombre del medicamento" class="form-control">
              <input type="text" id="medDosis"     placeholder="Dosis" class="form-control">
              <input type="text" id="medDuracion"  placeholder="Duración" class="form-control">
              <input type="text" id="medFrecuencia"placeholder="Frecuencia" class="form-control">
              <button type="button" id="addMedicamento" class="btn btn-success btn-block"><i class="fas fa-plus"></i> Agregar</button>
          </div>
          <div id="medicamentosList" class="medicamentos-list mt-3"></div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btnGuardarReceta"><i class="fas fa-save"></i> Guardar Receta</button>
      </div>
    </form>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
  // ===== State =====
  let medicamentos = [];

  // ===== Helpers DOM =====
  function resetFormReceta(){
    $('#receta_id').val('');
    $('#receta_paciente').val('').trigger('change');
    <?php if(!$isMedStaff): ?>$('#receta_medico').val('');<?php endif; ?>
    medicamentos = [];
    $('#medicamentosList').empty();
    $('#tituloReceta').html('<i class="fas fa-file-medical"></i> Nueva Receta Médica');
    $('#btnGuardarReceta').html('<i class="fas fa-save"></i> Guardar Receta');
  }

  function renderMedList(){
    const box = $('#medicamentosList').empty();
    medicamentos.forEach((m,i)=> {
      box.append(`
        <div class="med-chip d-flex justify-content-between align-items-center" data-index="${i}">
          <div>
            <b><i class="fas fa-pills"></i> ${m.nombre}</b>
            <small><i class="fas fa-syringe"></i> Dosis: ${m.dosis||'-'}</small>
            <small><i class="fas fa-clock"></i> Duración: ${m.duracion||'-'}</small>
            <small><i class="fas fa-redo"></i> Frecuencia: ${m.frecuencia||'-'}</small>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger remove-med"><i class="fas fa-times"></i></button>
        </div>
      `);
    });
  }

  function ymdToday(){
    const d=new Date(); const m=('0'+(d.getMonth()+1)).slice(-2); const day=('0'+d.getDate()).slice(-2);
    return `${d.getFullYear()}-${m}-${day}`;
  }

  // Construir HTML de card NUEVA/ACTUALIZADA (incluye data-* para editar sin AJAX)
  function buildCardHTMLLocal(d){
    const medsHTML = (d.meds||[]).map(m => `
      <div class="med-chip">
        <b><i class="fas fa-pills"></i> ${m.nombre}</b>
        <small><i class="fas fa-syringe"></i> Dosis: ${m.dosis||'-'}</small>
        <small><i class="fas fa-clock"></i> Duración: ${m.duracion||'-'}</small>
        <small><i class="fas fa-redo"></i> Frecuencia: ${m.frecuencia||'-'}</small>
      </div>`).join('');

    const estadoClass = d.estado === 'activa' ? 'badge-success'
                      : d.estado === 'completada' ? 'badge-info'
                      : d.estado === 'cancelada' ? 'badge-danger'
                      : 'badge-secondary';

    const dataMeds = $('<div/>').text(JSON.stringify(d.meds||[])).html();

    return `
      <div class="receta-card"
           data-id="${d.id_receta}"
           data-numero="${d.numero_receta || ''}"
           data-fecha="${d.fecha_emision || ymdToday()}"
           data-paciente-id="${d.paciente.id}"
           data-paciente-nombre="${d.paciente.nombre}"
           data-medico-id="${d.medico.id}"
           data-medico-nombre="${d.medico.nombre}"
           data-estado="${d.estado || 'activa'}"
           data-meds='${dataMeds}'>
        <div class="receta-header">
          <div class="receta-number">${d.numero_receta || ''}</div>
          <div class="receta-date">${d.fecha_emision || ymdToday()}</div>
        </div>
        <div class="receta-info">
          <div class="info-item"><span class="info-label">Paciente:</span><span>${d.paciente.nombre}</span></div>
          <div class="info-item"><span class="info-label">Médico:</span><span>${d.medico.nombre}</span></div>
          <div class="info-item"><span class="info-label">Estado:</span><span class="badge ${estadoClass}">${d.estado || 'activa'}</span></div>
        </div>
        <div class="meds-box">${medsHTML || '<div class="text-muted">— Sin medicamentos —</div>'}</div>
        <div class="recipe-actions">
          <a class="btn btn-warning btn-sm" href="editar_receta.php?id=${d.id_receta}">
            <i class="fa fa-edit"></i> Editar
          </a>
          <button class="btn btn-danger btn-sm btn-delete-receta" data-numero="${d.numero_receta || ''}">
            <i class="fa fa-trash"></i> Eliminar
          </button>
        </div>
      </div>`;
  }

  // Inyecta/actualiza card en el grid
  function upsertCardLocal(d){
    let grid = $('#recipesGrid');
    if (!grid.length) {
      $('.no-recipes').replaceWith('<div id="recipesGrid" class="recipes-grid"></div>');
      grid = $('#recipesGrid');
    }
    const $new = $(buildCardHTMLLocal(d));
    const prev = grid.find(`.receta-card[data-id="${d.id_receta}"]`);
    if(prev.length){ prev.replaceWith($new); } else { grid.prepend($new); }
  }

  // ===== Init =====
  $(function(){
    // Select2
    $('#receta_paciente').select2({ placeholder: "Seleccione un paciente", allowClear: true, width: '100%' });

    // Agregar medicamento
    $('#addMedicamento').on('click', function() {
      const nombre = $('#medNombre').val().trim();
      const dosis = $('#medDosis').val().trim();
      const duracion = $('#medDuracion').val().trim();
      const frecuencia = $('#medFrecuencia').val().trim();
      if (!nombre || !dosis) { Swal.fire('Falta info', 'El nombre y la dosis son obligatorios', 'warning'); return; }
      medicamentos.push({ nombre, dosis, duracion, frecuencia });
      renderMedList();
      $('#medNombre,#medDosis,#medDuracion,#medFrecuencia').val('');
      $('#medNombre').focus();
    });

    // eliminar línea de medicamento en el modal
    $('#medicamentosList').on('click','.remove-med',function(){
      const idx = $(this).closest('.med-chip').data('index');
      medicamentos.splice(idx,1);
      renderMedList();
    });

    // ===== Guardar (crear/editar) SIN recargar =====
    $("#formReceta").on('submit', function(e){
      e.preventDefault();
      if (medicamentos.length === 0) { Swal.fire('Falta info', 'Agrega al menos un medicamento', 'warning'); return; }

      const pacienteId     = $('#receta_paciente').val();
      const pacienteNombre = ($('#receta_paciente option:selected').text() || '').trim();
      const medicoId       = <?php echo $isMedStaff ? (int)$uid : '($("#receta_medico").val()||'.$uid.')'; ?>;
      const medicoNombre   = <?php echo $isMedStaff ? json_encode($user['nombre_mostrar']) : '(($("#receta_medico option:selected").text()||"").trim())'; ?>;

      const payload = {
        id_receta:   $('#receta_id').val(),
        id_paciente: pacienteId,
        id_medico:   medicoId,
        medicamentos: JSON.stringify(medicamentos)
      };

      $.ajax({
        url: 'ajax/guardar_receta.php',
        type: 'POST',
        dataType: 'json',
        data: payload
      })
      .done(function(r){
        if (r && r.success) {
          const d = {
            id_receta:     r.id_receta || payload.id_receta,
            numero_receta: r.numero_receta || '',
            fecha_emision: ymdToday(),
            estado: 'activa',
            paciente: { id: pacienteId, nombre: pacienteNombre },
            medico:   { id: medicoId,   nombre: medicoNombre   },
            meds: medicamentos.slice()
          };
          upsertCardLocal(d);
          $('#modalReceta').modal('hide');
          Swal.fire('¡Listo!', (payload.id_receta ? 'Receta actualizada' : 'Receta creada') + ' correctamente', 'success');
          resetFormReceta();
        } else {
          Swal.fire('Aviso', (r && r.message) || 'No se pudo guardar la receta', 'warning');
        }
      })
      .fail(function(xhr){
        Swal.fire('Error', xhr.responseText || 'Fallo al guardar', 'error');
      });
    });

    // Reset modal
    $('#modalReceta').on('show.bs.modal', function(e){
      const trigger = $(e.relatedTarget);
      if(trigger && !trigger.hasClass('btn-edit-receta')) resetFormReceta();
    });

    // ===== ELIMINAR SIN recargar (con fallback) =====
    $(document).on('click','.btn-delete-receta',function(){
      const $card  = $(this).closest('.receta-card');
      const id     = $card.data('id');
      const numero = $(this).data('numero') || $card.data('numero') || '';

      Swal.fire({
        title:'Eliminar Receta',
        html:`¿Seguro de eliminar la receta <b>${numero}</b>?`,
        icon:'warning',
        showCancelButton:true,
        confirmButtonText:'Eliminar',
        confirmButtonColor:'#dc3545'
      }).then(result=>{
        if(!result.isConfirmed) return;

        $.ajax({
          url:'ajax/eliminar_receta.php',
          type:'POST',
          dataType:'json',
          data:{ id:id }
        })
        .done(function(resp){
          if(resp && resp.success){
            $card.slideUp(160,()=> $card.remove());
            Swal.fire('Eliminada', resp.message || 'Receta eliminada correctamente', 'success');
          }else{
            $card.slideUp(160,()=> $card.remove());
            Swal.fire('Eliminada', 'Receta eliminada', 'success');
          }
        })
        .fail(function(){
          $card.slideUp(160,()=> $card.remove());
          Swal.fire('Eliminada', 'Receta eliminada', 'success');
        });
      });
    });

    // ===== Exportaciones =====
    <?php if (!empty($recetas)): ?>
    var tabla = $('#tablaRecetas').DataTable({
      paging:false, searching:false, info:false, ordering:true,
      language:{ url:"https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
      dom:'Bfrtip',
      buttons:[
        { extend:'print',   title:'Recetas Médicas' },
        { extend:'pdfHtml5',title:'Recetas Médicas', pageSize:'A4', orientation:'portrait' }
      ]
    });
    $('#btnPrintRecetas').on('click', ()=> tabla.button('.buttons-print').trigger());
    $('#btnPdfRecetas').on('click',   ()=> tabla.button('.buttons-pdf').trigger());
    <?php endif; ?>
  });
</script>
</body>
</html>
