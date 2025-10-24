<?php
// ver_prescripcion.php - Ver detalle de prescripción (con Imprimir + Finalizar)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$id_prescripcion = (int)($_GET['id'] ?? 0);
if ($id_prescripcion <= 0) { http_response_code(400); die('ID inválido'); }

// ===== Traer datos =====
$prescripcion = null; $medicinas = [];
try {
  // ✅ Trae rol del que atendió usando la vista de rol principal
  $q = "SELECT 
            p.*, 
            pac.nombre AS paciente_nombre, 
            COALESCE(u.nombre_mostrar, u.usuario) AS medico_nombre,
            u.usuario AS medico_user,
            LOWER(v.rol_nombre) AS medico_rol
        FROM prescripciones p
        INNER JOIN pacientes pac 
                ON p.id_paciente = pac.id_paciente
        LEFT JOIN usuarios u 
               ON p.medico_id = u.id
        LEFT JOIN vw_usuario_rol_principal v
               ON v.id_usuario = u.id
        WHERE p.id_prescripcion = ?";
  $st = $con->prepare($q); 
  $st->execute([$id_prescripcion]);
  $prescripcion = $st->fetch(PDO::FETCH_ASSOC);

  if (!$prescripcion) { header('Location: nueva_prescripcion.php'); exit; }

  $q2 = "SELECT dp.*, m.nombre_medicamento 
         FROM detalle_prescripciones dp
         INNER JOIN medicamentos m ON dp.id_medicamento = m.id
         WHERE dp.id_prescripcion = ?
         ORDER BY dp.id_detalle ASC";
  $st2 = $con->prepare($q2); $st2->execute([$id_prescripcion]);
  $medicinas = $st2->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { echo $e->getMessage(); exit; }

// ===== Permisos para “Finalizar” =====
$uid = (int)$_SESSION['user_id'];
$roles = [];
try {
  $rs = $con->prepare("SELECT LOWER(r.nombre) rol
                       FROM usuario_rol ur JOIN roles r ON r.id_rol = ur.id_rol
                       WHERE ur.id_usuario = :u");
  $rs->execute([':u'=>$uid]);
  $roles = array_map(fn($x)=>$x['rol'],$rs->fetchAll(PDO::FETCH_ASSOC));
} catch(Throwable $e){}

$esClinico = (bool) array_intersect($roles, ['medico','doctor','enfermero','enfermera']);

$permisoActualizar = false;
try {
  $mods = $con->query("SELECT id_modulo FROM modulos WHERE LOWER(nombre) IN ('prescripciones','medicinas','medicamentos','pacientes')")->fetchAll(PDO::FETCH_COLUMN);
  if ($mods) {
    $qp = $con->prepare("
      SELECT 1
      FROM rol_permiso rp
      JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
      WHERE ur.id_usuario = :u AND rp.id_modulo IN (".implode(',', array_map('intval',$mods)).") AND rp.actualizar = 1
      LIMIT 1
    ");
    $qp->execute([':u'=>$uid]);
    $permisoActualizar = (bool)$qp->fetchColumn();
  }
} catch(Throwable $e){}

$puedeFinalizar = $esClinico && $permisoActualizar && (strtolower((string)$prescripcion['estado']) === 'activa');

// Helpers
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function badgeClase($estado){
  $s = strtolower((string)$estado);
  return $s==='activa' ? 'success' : ($s==='completada' ? 'info' : 'warning');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Detalle de Prescripción</title>
  <style>
    .prescription-card { border-left: 4px solid #007bff; }
    .medicine-card { border-left: 4px solid #28a745; }
    .badge-estado { font-size: 0.9em; }
    @media print { .no-print {display:none!important;} }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-eye"></i> Detalle de Prescripción</h1>
        <div class="no-print">
          <?php if ($puedeFinalizar): ?>
            <button id="btnFinalizar" class="btn btn-warning btn-sm">
              <i class="fas fa-flag-checkered"></i> Finalizar tratamiento
            </button>
          <?php endif; ?>
          <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print"></i> Imprimir / PDF
          </button>
          <a href="nueva_prescripcion.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver a la Lista
          </a>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <!-- Información Principal -->
        <div class="card prescription-card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-file-medical"></i> Prescripción #<?php echo (int)$prescripcion['id_prescripcion']; ?>
              <span class="badge badge-<?php echo badgeClase($prescripcion['estado']); ?> badge-estado ml-2">
                <?php echo ucfirst($prescripcion['estado']); ?>
              </span>
            </h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <p><strong>Paciente:</strong> <?php echo e($prescripcion['paciente_nombre']); ?></p>
                <p><strong>Fecha de Visita:</strong> <?php echo $prescripcion['fecha_visita'] ? date('d/m/Y', strtotime($prescripcion['fecha_visita'])) : '—'; ?></p>
                <?php if (!empty($prescripcion['proxima_visita'])): ?>
                  <p><strong>Próxima Visita:</strong> <?php echo date('d/m/Y', strtotime($prescripcion['proxima_visita'])); ?></p>
                <?php endif; ?>
              </div>
              <div class="col-md-6">
                <p><strong>Enfermedad:</strong> <span class="badge badge-warning"><?php echo e($prescripcion['enfermedad']); ?></span></p>
                <p><strong>Sucursal:</strong> <?php echo e($prescripcion['sucursal'] ?? '—'); ?></p>
                <?php
                  $rol = strtolower($prescripcion['medico_rol'] ?? '');
                  if (in_array($rol, ['doctor','medico','enfermero']) && !empty($prescripcion['medico_nombre'])):
                    $label = ($rol === 'enfermero') ? 'Enfermero' : 'Médico';
                ?>
                  <p><strong><?php echo $label; ?>:</strong> <?php echo e($prescripcion['medico_nombre']); ?>
                    <?php if (!empty($prescripcion['medico_user'])): ?>
                      <small class="text-muted">(usuario: <?php echo e($prescripcion['medico_user']); ?>)</small>
                    <?php endif; ?>
                  </p>
                <?php endif; ?>
              </div>
            </div>

            <?php if (!empty($prescripcion['peso']) || !empty($prescripcion['presion'])): ?>
            <div class="row mt-3">
              <div class="col-12">
                <h5><i class="fas fa-heartbeat"></i> Signos Vitales</h5>
                <div class="row">
                  <?php if (!empty($prescripcion['peso'])): ?>
                    <div class="col-md-3"><strong>Peso:</strong> <?php echo e($prescripcion['peso']); ?> kg</div>
                  <?php endif; ?>
                  <?php if (!empty($prescripcion['presion'])): ?>
                    <div class="col-md-3"><strong>Presión:</strong> <?php echo e($prescripcion['presion']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Medicinas Recetadas -->
        <div class="card medicine-card mt-4">
          <div class="card-header">
            <h3 class="card-title"><i class="fas a-pills"></i> Medicinas Recetadas</h3>
          </div>
          <div class="card-body">
            <?php if ($medicinas): ?>
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead class="bg-light">
                    <tr>
                      <th>Medicina</th>
                      <th>caja de medicamentos</th>
                      <th>Cantidad</th>
                      <th>Dosis</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($medicinas as $m): ?>
                      <tr>
                        <td><?php echo e($m['nombre_medicamento']); ?></td>
                        <td><?php echo e($m['empaque']); ?></td>
                        <td><?php echo e($m['cantidad']); ?></td>
                        <td><?php echo e($m['dosis']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted text-center m-0">No hay medicinas registradas para esta prescripción.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Fechas -->
        <div class="row mt-3">
          <div class="col-md-6">
            <small class="text-muted"><strong>Creado:</strong>
              <?php echo !empty($prescripcion['created_at']) ? date('d/m/Y H:i', strtotime($prescripcion['created_at'])) : '—'; ?>
            </small>
          </div>
          <div class="col-md-6 text-right">
            <small class="text-muted"><strong>Actualizado:</strong>
              <?php echo !empty($prescripcion['updated_at']) ? date('d/m/Y H:i', strtotime($prescripcion['updated_at'])) : '—'; ?>
            </small>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
document.getElementById('btnFinalizar')?.addEventListener('click', function(){
  Swal.fire({
    title: 'Finalizar tratamiento',
    text: '¿Seguro que deseas finalizar esta prescripción?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, finalizar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#f59e0b'
  }).then(function(res){
    if(!res.isConfirmed) return;
    fetch('ajax/finalizar_prescripcion.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'id=<?php echo (int)$id_prescripcion; ?>'
    })
    .then(r=>r.json()).then(j=>{
      if(j.success){ Swal.fire('OK','Prescripción finalizada','success').then(()=>location.reload()); }
      else{ Swal.fire('Aviso', j.message || 'No se pudo finalizar', 'warning'); }
    })
    .catch(()=> Swal.fire('Error','Fallo de red','error'));
  });
});
</script>
</body>
</html>
