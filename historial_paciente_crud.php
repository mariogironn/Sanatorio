<?php
// historial_paciente_crud.php - CRUD de prescripciones por paciente
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

$pacienteId = (int)($_GET['paciente_id'] ?? 0);
if ($pacienteId <= 0) { header('Location: historial_paciente.php'); exit; }

// === Datos del paciente ===
$paciente = [
  'id_paciente' => $pacienteId,
  'nombre'      => '(Paciente)',
  'dpi'         => '',
  'telefono'    => '',
  'genero'      => '',
  'tipo_sangre' => ''
];
try{
  $ps = $con->prepare("SELECT id_paciente, nombre, dpi, telefono, genero, tipo_sangre
                         FROM pacientes
                        WHERE id_paciente = :id LIMIT 1");
  $ps->execute([':id'=>$pacienteId]);
  if ($row = $ps->fetch(PDO::FETCH_ASSOC)) { $paciente = $row; }
}catch(Throwable $e){ /* noop */ }

// === Listado de prescripciones del paciente ===
// NOTA: asumimos tablas: prescripciones (id_prescripcion,id_paciente,fecha_visita,enfermedad,estado,id_sucursal,proxima_visita?)
//       y sucursales (id_sucursal, nombre). Si algún campo no existe, simplemente saldrá vacío.
$prescripciones = [];
try {
  $sql = "
    SELECT pr.id_prescripcion,
           pr.fecha_visita,
           pr.enfermedad,
           pr.estado,
           pr.proxima_visita,
           COALESCE(s.nombre,'') AS sucursal,
           /* Si existe tabla prescripcion_medicinas, contamos; si no, devuelven 0 sin romper */
           COALESCE((
             SELECT COUNT(*) FROM prescripcion_medicinas pm
              WHERE pm.id_prescripcion = pr.id_prescripcion
           ), 0) AS cant_meds
      FROM prescripciones pr
      LEFT JOIN sucursales s ON s.id_sucursal = pr.id_sucursal
     WHERE pr.id_paciente = :p
     ORDER BY pr.fecha_visita DESC, pr.id_prescripcion DESC
  ";
  $st = $con->prepare($sql);
  $st->execute([':p'=>$pacienteId]);
  $prescripciones = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $prescripciones = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Prescripciones de Paciente</title>
  <style>
    .kpi{border-radius:.5rem;padding:1rem;background:#f8f9fa;border:1px solid #e9ecef}
    .kpi h3{margin:0;font-weight:700}
    .badge-pill{border-radius:10rem;padding:.35rem .6rem;font-weight:600}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-notes-medical"></i> Prescripciones de paciente</h1>
        <div>
          <a class="btn btn-secondary btn-sm" href="historial_paciente.php">
            <i class="fas fa-arrow-left"></i> Volver al listado
          </a>
          <a class="btn btn-info btn-sm" href="ver_historial_completo.php?id=<?= (int)$paciente['id_paciente'] ?>">
            <i class="fas fa-clipboard-list"></i> Historial completo
          </a>
          <a class="btn btn-primary btn-sm" href="crear_prescripcion.php?paciente_id=<?= (int)$paciente['id_paciente'] ?>">
            <i class="fas fa-plus"></i> Nueva Prescripción
          </a>
        </div>
      </div>
    </section>

    <section class="content">
      <!-- Tarjeta datos del paciente -->
      <div class="card card-primary">
        <div class="card-body" style="background:linear-gradient(90deg,#4e6ad7,#2447c1);color:#fff">
          <div class="row">
            <div class="col-md-8">
              <h4 style="margin:0 0 .25rem 0;">
                <i class="fas fa-user-injured"></i>
                <?= htmlspecialchars($paciente['nombre']) ?>
              </h4>
              <div>
                <small>
                  <i class="fas fa-id-card"></i> <?= htmlspecialchars($paciente['dpi'] ?: '—') ?> &nbsp;
                  <i class="fas fa-phone"></i> <?= htmlspecialchars($paciente['telefono'] ?: '—') ?> &nbsp;
                  <i class="fas fa-venus-mars"></i> <?= htmlspecialchars($paciente['genero'] ?: '—') ?> &nbsp;
                  <i class="fas fa-tint"></i> <?= htmlspecialchars($paciente['tipo_sangre'] ?: '—') ?>
                </small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabla de prescripciones -->
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-list"></i> Lista de Prescripciones</h3>
        </div>
        <div class="card-body table-responsive">
          <table id="tblPrescripciones" class="table table-striped table-bordered">
            <thead>
              <tr>
                <th class="text-center" style="width:60px">#</th>
                <th>Fecha visita</th>
                <th>Enfermedad</th>
                <th class="text-center">Medicinas</th>
                <th>Sucursal</th>
                <th class="text-center">Estado</th>
                <th class="text-center" style="width:160px">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if(!$prescripciones): ?>
                <!-- sin filas -->
              <?php else: foreach($prescripciones as $i=>$r):
                $id   = (int)$r['id_prescripcion'];
                $f    = $r['fecha_visita'] ? date('d/m/Y', strtotime($r['fecha_visita'])) : '—';
                $enf  = trim((string)$r['enfermedad']);
                $suc  = trim((string)($r['sucursal'] ?? ''));
                $edo  = strtolower((string)$r['estado']);
                $edoClass = ($edo==='completada' || $edo==='completado') ? 'success' : (($edo==='pendiente')?'warning':'secondary');
                $cant = (int)($r['cant_meds'] ?? 0);
              ?>
              <tr id="row-<?= $id ?>">
                <td class="text-center"><?= $i+1 ?></td>
                <td><?= htmlspecialchars($f) ?></td>
                <td><span class="badge badge-warning badge-pill"><?= htmlspecialchars($enf ?: '—') ?></span></td>
                <td class="text-center">
                  <span class="badge badge-info badge-pill"><?= $cant ?> med.</span>
                </td>
                <td><?= htmlspecialchars($suc ?: '—') ?></td>
                <td class="text-center">
                  <span class="badge badge-<?= $edoClass ?>"><?= htmlspecialchars($r['estado'] ?: '—') ?></span>
                </td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <a class="btn btn-outline-primary" title="Ver" href="ver_prescripcion.php?id=<?= $id ?>"><i class="fas fa-eye"></i></a>
                    <a class="btn btn-outline-warning" title="Editar" href="editar_prescripcion.php?id=<?= $id ?>"><i class="fas fa-edit"></i></a>
                    <button class="btn btn-outline-danger btn-delete" title="Eliminar" data-id="<?= $id ?>"><i class="fas fa-trash"></i></button>
                  </div>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>

          <?php if(!$prescripciones): ?>
            <div class="text-center text-muted"><em>No hay prescripciones registradas para este paciente.</em></div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Menu activo (usa el mismo del historial de pacientes para mantener consistencia)
  showMenuSelected("#mnu_patients", "#mi_patient_history");

  // DataTable
  $(function(){
    $("#tblPrescripciones").DataTable({
      responsive:true, lengthChange:true, autoWidth:false,
      language:{lengthMenu:"Mostrar _MENU_", search:"Buscar:", paginate:{first:"Primero",last:"Último",next:"Siguiente",previous:"Anterior"},
                zeroRecords:"Sin resultados", info:"Mostrando _START_ a _END_ de _TOTAL_", infoEmpty:"0 registros", infoFiltered:"(filtrado de _MAX_)"},
      columnDefs:[{targets:-1, orderable:false}]
    });
  });

  // Eliminar
  $(document).on('click','.btn-delete',function(){
    const id = $(this).data('id');
    Swal.fire({
      title:'Eliminar',
      text:'¿Eliminar esta prescripción?',
      icon:'question',
      showCancelButton:true,
      confirmButtonText:'Eliminar',
      confirmButtonColor:'#dc3545'
    }).then(res=>{
      if(!res.isConfirmed) return;
      $.post('ajax/eliminar_prescripcion.php',{id:id})
      .done(function(resp){
        try{ resp = JSON.parse(resp); }catch(_){ resp = {success:false, message:resp}; }
        if(resp.success){
          const dt = $("#tblPrescripciones").DataTable();
          dt.row($("#row-"+id)).remove().draw(false);
          Swal.fire('Hecho','Eliminado','success');
        }else{
          Swal.fire('Aviso', resp.message || 'No se pudo eliminar', 'warning');
        }
      })
      .fail(function(x){
        Swal.fire('Error', x.responseText || 'Fallo al eliminar', 'error');
      });
    });
  });
</script>
</body>
</html>
