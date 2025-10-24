<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

/* ===== Autenticación y datos de usuario ===== */
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

$uStmt = $con->prepare("SELECT id, usuario, nombre_mostrar FROM usuarios WHERE id = :id LIMIT 1");
$uStmt->execute([':id'=>$uid]);
$user  = $uStmt->fetch(PDO::FETCH_ASSOC) ?: ['id'=>0,'usuario'=>'','nombre_mostrar'=>'(usuario)'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Especialidades Médicas</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    .hero{background:linear-gradient(135deg,#2c3e50,#3498db)!important;color:#fff;border-radius:6px}
    .stat-card{min-height:96px;position:relative;border:0;border-radius:8px;color:#fff}
    .stat-card .inner{padding:14px 16px}
    .stat-card .icon{position:absolute;right:12px;top:10px;opacity:.25;font-size:36px}
    .bg-blue{background:#1e90ff}.bg-green{background:#28a745}.bg-amber{background:#f39c12}.bg-cyan{background:#17a2b8}
    .badge-soft{padding:.35rem .55rem;font-size:.75rem;border-radius:16px}
    .asig-card{border-left:4px solid #3498db;border-radius:6px}
    .asig-card .meta{font-size:.9rem}
    .btn-vert .btn{width:100%}
    .modal-header.hero{border-top-left-radius:6px;border-top-right-radius:6px}
    .section-gap{margin-top:24px;} @media (min-width: 992px){ .section-gap{margin-top:28px;} }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; ?>
  <?php include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center hero px-3 py-3">
        <h1 class="mb-0"><i class="fas fa-user-md mr-2"></i>Especialidades Médicas</h1>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-light" id="btnAsignar">
            <i class="fas fa-user-plus mr-1"></i> Asignar Especialidad
          </button>
          <button class="btn btn-light" id="btnCrearEsp">
            <i class="fas fa-plus mr-1"></i> Crear / Gestionar Especialidades
          </button>
        </div>
      </div>
    </section>

    <section class="content">
      <!-- KPIs -->
      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="stat-card bg-blue rounded shadow-sm">
            <div class="inner">
              <h3 id="kpiActivas" class="mb-1">0</h3><div>Especialidades Activas</div>
            </div><div class="icon"><i class="fas fa-stethoscope"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="stat-card bg-green rounded shadow-sm">
            <div class="inner">
              <h3 id="kpiMedicosEsp" class="mb-1">0</h3><div>Médicos Especializados</div>
            </div><div class="icon"><i class="fas fa-user-check"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="stat-card bg-amber rounded shadow-sm">
            <div class="inner">
              <h3 id="kpiAreas" class="mb-1">0</h3><div>Áreas Médicas</div>
            </div><div class="icon"><i class="fas fa-clinic-medical"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="stat-card bg-cyan rounded shadow-sm">
            <div class="inner">
              <h3 id="kpiConsultas" class="mb-1">0</h3><div>Consultas Este Mes</div>
            </div><div class="icon"><i class="fas fa-calendar-check"></i></div>
          </div>
        </div>
      </div>

      <!-- Asignaciones ancho completo -->
      <div class="row section-gap">
        <div class="col-lg-12">
          <div class="card card-outline card-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title"><i class="fas fa-file-medical mr-1"></i> Especialidades Asignadas</h3>
              <div class="d-flex align-items-center">
                <span class="badge badge-primary mr-2" id="badgeAsignaciones">0 asignaciones</span>
                <a class="btn btn-sm btn-primary" href="especialidades_lista.php">
                  <i class="fas fa-list mr-1"></i> Ir a lista de especialidades
                </a>
              </div>
            </div>
            <div class="card-body" id="boxAsignaciones">
              <div class="text-center py-5 text-muted">
                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                <div>Cargando asignaciones…</div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<!-- Modal Reasignar/Asignar -->
<div class="modal fade" id="modalAsig" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header hero">
      <h5 class="modal-title"><i class="fas fa-user-tag mr-1"></i> <span id="titAsig">Asignar Especialidad</span></h5>
      <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="formAsig" autocomplete="off">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Seleccionar Personal:</label>
            <select class="form-control" id="asigMedico" name="medico_id" required>
              <option value="">Seleccione médico o enfermero</option>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Tipo de Personal:</label>
            <select class="form-control" id="asigTipo">
              <option>Médico</option>
              <option>Doctor</option>
              <option>Enfermero</option>
              <option>Enfermera</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Especialidad:</label>
            <select class="form-control" id="asigEsp" name="especialidad_id" required>
              <option value="">Seleccione una especialidad</option>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Estado:</label>
            <select class="form-control" id="asigEstado" name="estado">
              <option value="activa">Activa</option>
              <option value="inactiva">Inactiva</option>
              <option value="capacitacion">En Capacitación</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Descripción/Ámbito:</label>
          <textarea class="form-control" id="asigDesc" name="descripcion" rows="2" placeholder="Ámbito específico de la especialidad…"></textarea>
        </div>

        <div class="form-group">
          <label>Fecha de Certificación:</label>
          <input type="date" class="form-control" id="asigFecha" name="fecha_certificacion">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> <span id="btnAsigTxt">Asignar Especialidad</span></button>
      </div>
    </form>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
  // Estado
  let medicos = [];  // para KPIs/listas
  let especialidades = []; // solo para KPIs/select

  // Botones header
  $('#btnCrearEsp').on('click', ()=> window.location.href = 'especialidades_lista.php');
  $('#btnAsignar').on('click', ()=> abrirAsignar());

  cargarTodo();

  function cargarTodo(){
    $.when(
      $.post('ajax/medicos_listar.php', {}, null, 'json'),
      $.get('ajax/especialidades_listar.php', null, null, 'json')
    ).done(function(rMed,rEsp){
      const rm = rMed[0]||{}; const re = rEsp[0]||{};
      medicos = rm.success ? (rm.data||[]) : [];
      especialidades = re.success ? (re.data||[]) : [];

      renderKpis();
      renderAsignaciones();

      // selects para el modal
      llenarSelectMedicos();
      llenarSelectEspecialidades();

      $.get('ajax/kpi_consultas_mes.php', null, function(x){
        if(x && x.success){ $('#kpiConsultas').text(x.consultas_mes||0); }
      }, 'json').fail(()=> $('#kpiConsultas').text('0'));
    }).fail(function(){
      Swal.fire({icon:'error',title:'Error',text:'No se pudo cargar información inicial'});
    });
  }

  function renderKpis(){
    const activas = especialidades.filter(e=>String(e.estado).toLowerCase()==='activa').length;
    const areas   = especialidades.length;
    const conEsp  = medicos.filter(m=>m.especialidad_id && m.especialidad_nombre).length;
    $('#kpiActivas').text(activas);
    $('#kpiAreas').text(areas);
    $('#kpiMedicosEsp').text(conEsp);
  }

  function renderAsignaciones(){
    const data = medicos.filter(m => m.especialidad_id);
    const $box = $('#boxAsignaciones');
    $('#badgeAsignaciones').text(data.length + ' asignacion' + (data.length!==1?'es':''));

    if(!data.length){
      $box.html(`
        <div class="text-center py-5 text-muted">
          <i class="fas fa-user-md fa-3x mb-2"></i>
          <h5 class="mb-1">No hay médicos asignados</h5>
          <p>Usa "Asignar Especialidad" para comenzar o ve a la <a href="especialidades_lista.php">lista de especialidades</a>.</p>
        </div>
      `);
      return;
    }

    let html='';
    data.forEach(m=>{
      const estado = (m.estado || 'activa').toLowerCase();
      const badgeClass = estado==='activa'?'success':(estado==='capacitacion'?'info':'secondary');
      const fecha = (m.especialidad_fecha_certificacion||'').substring(0,10);
      html += `
        <div class="card asig-card mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h5 class="mb-1 text-primary">${esc(m.especialidad_nombre||'—')} 
                  <span class="badge badge-${badgeClass} badge-soft text-uppercase ml-1">${esc(estado)}</span>
                </h5>
                <div class="meta text-muted">
                  <i class="fas fa-user-md mr-1"></i><b>Médico:</b> ${esc(m.nombre_mostrar||'—')} &nbsp; 
                  <i class="far fa-id-card ml-3 mr-1"></i><b>Colegiado:</b> ${esc(m.colegiado||'—')}
                </div>
                <div class="mt-1">${esc(m.especialidad_descripcion||'Sin descripción')}</div>
                ${fecha? `<div class="mt-1 text-muted"><i class="far fa-calendar-check mr-1"></i>Certificación: ${esc(formatearFecha(fecha))}</div>`:''}
              </div>
              <div class="btn-vert" style="min-width:180px">
                <button class="btn btn-warning btn-sm mb-1" data-reasignar="${m.id_medico}">
                  <i class="fas fa-sync-alt"></i> Editar
                </button>
                <button class="btn btn-danger btn-sm" data-quitar="${m.id_medico}">
                  <i class="fas fa-trash"></i> Eliminar
                </button>
              </div>
            </div>
          </div>
        </div>`;
    });
    $box.html(html);

    $box.find('[data-reasignar]').on('click', function(){
      const id = $(this).data('reasignar');
      const row = medicos.find(x=>Number(x.id_medico)===Number(id));
      if(!row){ return; }
      abrirAsignar(row);
    });
    $box.find('[data-quitar]').on('click', function(){
      const id = $(this).data('quitar');
      quitarAsignacion(id);
    });
  }

  // ===== Selects para modal =====
  function llenarSelectMedicos(preselectId){
    $('#asigMedico').html('<option value="">Cargando…</option>');
    $.get('ajax/cargar_personal_especialidades.php', function(html){
      const base = '<option value="">Seleccione médico o enfermero</option>';
      $('#asigMedico').html(base + html);
      if (preselectId) { $('#asigMedico').val(String(preselectId)); }
    }).fail(function(){
      $('#asigMedico').html('<option value="">Error al cargar personal</option>');
    });
  }
  function llenarSelectEspecialidades(){
    const $sel = $('#asigEsp');
    let html = '<option value="">Seleccione una especialidad</option>';
    (especialidades||[]).forEach(e=>{
      html += `<option value="${e.id_especialidad}">${esc(e.nombre||'—')}</option>`;
    });
    $sel.html(html);
  }

  // ===== Modal Asignar/Reasignar =====
  function abrirAsignar(row=null){
    $('#titAsig').text(row?'Reasignar Especialidad':'Asignar Especialidad');
    $('#btnAsigTxt').text(row?'Actualizar Asignación':'Asignar Especialidad');

    $('#formAsig')[0].reset();
    llenarSelectEspecialidades();

    if(row){
      llenarSelectMedicos(row.id_medico);
      $('#asigEsp').val(row.especialidad_id||'');
      $('#asigEstado').val((row.estado||'activa').toLowerCase());
      $('#asigDesc').val(row.especialidad_descripcion||'');
      $('#asigFecha').val((row.especialidad_fecha_certificacion||'').substring(0,10));
    }else{
      llenarSelectMedicos();
    }
    $('#modalAsig').modal('show');
  }

  $('#formAsig').on('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({
      url:'ajax/medico_especialidad_reasignar.php', type:'POST', data:fd, processData:false, contentType:false, dataType:'json'
    }).done(function(r){
      if(r && r.success){
        Swal.fire({icon:'success',title:'¡Especialidad actualizada!',timer:1500,showConfirmButton:false});
        $('#modalAsig').modal('hide');
        cargarTodo();
      }else{
        Swal.fire({icon:'error',title:'Error',text:r.message||'No se pudo asignar'});
      }
    }).fail(()=> Swal.fire({icon:'error',title:'Error',text:'Error de conexión'}));
  });

  function quitarAsignacion(medicoId){
    Swal.fire({
      icon:'question', title:'¿Quitar especialidad?', html:'Esta acción removerá la especialidad asignada al profesional.',
      showCancelButton:true, confirmButtonText:'Sí, quitar', cancelButtonText:'Cancelar', confirmButtonColor:'#d33'
    }).then(res=>{
      if(!res.isConfirmed) return;
      $.post('ajax/medico_especialidad_quitar.php',{medico_id:medicoId},function(r){
        if(r && r.success){
          Swal.fire({icon:'success',title:'Especialidad quitada',timer:1300,showConfirmButton:false});
          cargarTodo();
        }else{
          Swal.fire({icon:'error',title:'Error',text:r.message||'No se pudo quitar'});
        }
      },'json').fail(()=> Swal.fire({icon:'error',title:'Error',text:'Error de conexión'}));
    });
  }

  // Utils
  function esc(s){ return (s==null)?'':String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
  function formatearFecha(f){
    if(!f) return '—';
    try{ return new Date(f+'T00:00:00').toLocaleDateString('es-ES',{year:'numeric',month:'long',day:'numeric'}); }
    catch(e){ return f; }
  }
});
</script>
</body>
</html>