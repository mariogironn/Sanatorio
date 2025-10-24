<?php
// editar_receta.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require_once './config/connection.php';                 // <- FIX correcto
require_once './common_service/common_functions.php';
require_once './common_service/auditoria_service.php';

// ===== Autenticación =====
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

// ===== Roles / permisos light =====
$rStmt = $con->prepare("
  SELECT LOWER(r.nombre) rol
  FROM usuario_rol ur
  JOIN roles r ON r.id_rol = ur.id_rol
  WHERE ur.id_usuario = :id
");
$rStmt->execute([':id'=>$uid]);
$userRoles   = array_map(fn($r)=>$r['rol'], $rStmt->fetchAll(PDO::FETCH_ASSOC));
$isMedStaff  = (bool) array_intersect($userRoles, ['medico','doctor','enfermero','enfermera']);

// ===== ID receta =====
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('ID inválido'); }

// ===== Receta + medicamentos =====
$receta = [];
$meds   = [];
try {
  $rs = $con->prepare("
    SELECT rm.id_receta, rm.numero_receta, rm.id_paciente, rm.id_medico,
           rm.fecha_emision, rm.estado
    FROM recetas_medicas rm
    WHERE rm.id_receta = :id
    LIMIT 1
  ");
  $rs->execute([':id'=>$id]);
  $receta = $rs->fetch(PDO::FETCH_ASSOC);
  if (!$receta) { die('Receta no encontrada'); }

  $ms = $con->prepare("
    SELECT nombre_medicamento, dosis, duracion, frecuencia
    FROM detalle_recetas
    WHERE id_receta = :id
    ORDER BY id_detalle ASC
  ");
  $ms->execute([':id'=>$id]);
  $meds = $ms->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  die('Error cargando la receta');
}

// ===== Datos de usuario para UI =====
$uStmt = $con->prepare("SELECT id, usuario, nombre_mostrar FROM usuarios WHERE id = :id LIMIT 1");
$uStmt->execute([':id'=>$uid]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC) ?: ['id'=>0,'usuario'=>'','nombre_mostrar'=>'(usuario)'];

// ===== Opciones selects =====
$optPacientes = getPacientes($con);

// Médicos
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
    $icon = in_array($r['rol'], ['medico','doctor','enfermero']) ? '👨‍⚕️' : '👩‍⚕️';
    $optMedicos .= '<option value="'.$r['id'].'">'.$icon.' '.htmlspecialchars($r['nombre_mostrar']).'</option>';
  }
} catch (Throwable $e) { /* noop */ }

// JS inicial con medicamentos actuales
$medsJS = array_map(function($m){
  return [
    'nombre'     => $m['nombre_medicamento'],
    'dosis'      => $m['dosis'],
    'duracion'   => $m['duracion'],
    'frecuencia' => $m['frecuencia']
  ];
}, $meds);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Editar Receta</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    .med-box{border:1px solid #e1e1e1;border-radius:6px;padding:16px;background:#f9f9f9}
    .med-chip{background:#fff;border:1px solid #e7e9ee;border-left:4px solid #3498db;border-radius:6px;
              padding:10px 12px;margin-bottom:8px;box-shadow:0 2px 3px rgba(0,0,0,.03)}
    .med-chip b{display:block}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-edit"></i> Editar Receta</h1>
        <a href="recetas_medicas.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
      </div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary">
        <div class="card-body">
          <form id="formEditar" autocomplete="off">
            <input type="hidden" id="id_receta" value="<?= (int)$receta['id_receta'] ?>">

            <div class="form-group">
              <label>Paciente:</label>
              <select id="id_paciente" class="form-control" required>
                <?= $optPacientes ?>
              </select>
            </div>

            <div class="form-group">
              <label>Médico:</label>
              <select id="id_medico" class="form-control" <?= $isMedStaff ? 'disabled' : '' ?> required>
                <?= $optMedicos ?>
              </select>
              <?php if ($isMedStaff): ?>
                <input type="hidden" id="id_medico_hidden" value="<?= (int)$uid ?>">
                <small class="text-muted"><i class="fas fa-info-circle"></i> Asignado automáticamente como médico.</small>
              <?php endif; ?>
            </div>

            <div class="med-box">
              <h5 class="mb-3"><i class="fas fa-pills"></i> Medicamentos</h5>
              <div class="form-row mb-2">
                <div class="col-md-4 mb-2"><input type="text" id="m_nombre" class="form-control" placeholder="Nombre del medicamento"></div>
                <div class="col-md-2 mb-2"><input type="text" id="m_dosis" class="form-control" placeholder="Dosis"></div>
                <div class="col-md-3 mb-2"><input type="text" id="m_duracion" class="form-control" placeholder="Duración"></div>
                <div class="col-md-3 mb-2"><input type="text" id="m_frecuencia" class="form-control" placeholder="Frecuencia"></div>
              </div>
              <button type="button" id="btnAddMed" class="btn btn-success btn-sm mb-3"><i class="fas fa-plus"></i> Agregar</button>
              <div id="listaMeds"></div>
            </div>

            <div class="mt-3 d-flex justify-content-end">
              <a href="recetas_medicas.php" class="btn btn-secondary mr-2">Cancelar</a>
              <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Receta</button>
            </div>
          </form>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
  // ===== Estado =====
  let medicamentos = <?= json_encode($medsJS, JSON_UNESCAPED_UNICODE) ?>;

  // Set valores iniciales en selects
  document.addEventListener('DOMContentLoaded', function(){
    document.getElementById('id_paciente').value = '<?= (int)$receta['id_paciente'] ?>';
    <?php if(!$isMedStaff): ?>
      document.getElementById('id_medico').value   = '<?= (int)$receta['id_medico'] ?>';
    <?php endif; ?>
    renderMeds();
  });

  function renderMeds(){
    const box = document.getElementById('listaMeds');
    box.innerHTML = '';
    medicamentos.forEach((m, i) => {
      const div = document.createElement('div');
      div.className = 'med-chip d-flex justify-content-between align-items-center';
      div.innerHTML = `
        <div>
          <b>${escapeHtml(m.nombre)}</b>
          <small><i class="fas fa-syringe"></i> Dosis: ${escapeHtml(m.dosis||'-')}</small>
          <small><i class="fas fa-clock"></i> Duración: ${escapeHtml(m.duracion||'-')}</small>
          <small><i class="fas fa-redo"></i> Frecuencia: ${escapeHtml(m.frecuencia||'-')}</small>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="delMed(${i})"><i class="fas fa-times"></i></button>
      `;
      box.appendChild(div);
    });
  }
  function delMed(i){ medicamentos.splice(i,1); renderMeds(); }
  function escapeHtml(s){ return (s||'').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

  document.getElementById('btnAddMed').addEventListener('click', () => {
    const nombre    = document.getElementById('m_nombre').value.trim();
    const dosis     = document.getElementById('m_dosis').value.trim();
    const duracion  = document.getElementById('m_duracion').value.trim();
    const frecuencia= document.getElementById('m_frecuencia').value.trim();
    if(!nombre || !dosis){
      Swal.fire('Falta info','Nombre y dosis son obligatorios','warning'); return;
    }
    medicamentos.push({nombre, dosis, duracion, frecuencia});
    ['m_nombre','m_dosis','m_duracion','m_frecuencia'].forEach(id=> document.getElementById(id).value='');
    renderMeds();
  });

  // Guardar vía AJAX a tu mismo endpoint
  document.getElementById('formEditar').addEventListener('submit', function(e){
    e.preventDefault();
    if(medicamentos.length === 0){ Swal.fire('Falta info','Agrega al menos un medicamento','warning'); return; }

    const id_receta  = document.getElementById('id_receta').value;
    const id_paciente= document.getElementById('id_paciente').value;
    const id_medico  = <?= $isMedStaff ? 'document.getElementById("id_medico_hidden").value' : 'document.getElementById("id_medico").value' ?>;

    const payload = new FormData();
    payload.append('id_receta', id_receta);
    payload.append('id_paciente', id_paciente);
    payload.append('id_medico', id_medico);
    payload.append('medicamentos', JSON.stringify(medicamentos));

    fetch('ajax/guardar_receta.php', { method:'POST', body: payload })
      .then(r => r.json().catch(()=>({success:false,message:'Respuesta no válida'})))
      .then(res => {
        if(res.success){
          Swal.fire('¡Guardado!','Receta actualizada correctamente','success').then(()=>{
            window.location.href = 'recetas_medicas.php';
          });
        }else{
          Swal.fire('Aviso', res.message || 'No se pudo guardar', 'warning');
        }
      })
      .catch(()=> Swal.fire('Error','Error de conexión','error'));
  });
</script>
</body>
</html>
