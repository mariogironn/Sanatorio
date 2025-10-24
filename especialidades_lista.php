<?php
// especialidades_lista.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__.'/config/connection.php';
require_once __DIR__.'/common_service/common_functions.php';

// Seguridad básica
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Lista de Especialidades</title>
  <?php include __DIR__.'/config/site_css_links.php'; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
  <style>
    /* Evita que la navbar fija tape el header */
    #page { padding-top: 64px; }
    @media (max-width: 992px){ #page{ padding-top: 74px; } }

    .hero{
      background:linear-gradient(135deg,#2c3e50,#3498db)!important;
      color:#fff;border-radius:6px;
      box-shadow:0 6px 16px rgba(0,0,0,.10);
      padding:12px 16px;
      position: sticky; top: 64px; z-index: 9;   /* siempre visible */
    }
    .hero h1{font-size:1.25rem;margin:0;font-weight:700;letter-spacing:.2px}
    .hero .btn{box-shadow:0 2px 4px rgba(0,0,0,.12)}
    .spacer{ margin-left:10px; }                 /* separación entre botones */

    .list-badge{font-size:.7rem}
    .asig-card{border-left:4px solid #3498db;border-radius:6px}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include __DIR__.'/config/header.php'; ?>
  <?php include __DIR__.'/config/sidebar.php'; ?>

  <div class="content-wrapper" id="page">
    <!-- Top header propio de la página -->
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center hero">
        <h1 class="mb-0"><i class="fas fa-list mr-2"></i>Lista de Especialidades</h1>
        <div class="d-flex align-items-center">
          <button id="btnVolver" class="btn btn-light">
            <i class="fas fa-arrow-left mr-1"></i> Volver a Especialidades Médicas
          </button>
          <button id="btnCrear" class="btn btn-primary spacer">
            <i class="fas fa-plus mr-1"></i> Crear Especialidad
          </button>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="fas fa-stethoscope mr-1"></i> Especialidades</h3>
          <span class="badge badge-primary" id="badgeEspecialidades">0 especialidades</span>
        </div>
        <div class="card-body" id="boxEspecialidades">
          <div class="text-center py-4 text-muted">
            <i class="fas fa-spinner fa-spin mb-2"></i>
            <div>Cargando especialidades…</div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include __DIR__.'/config/footer.php'; ?>
</div>

<!-- Modal Crear/Editar Especialidad (para el botón Crear) -->
<div class="modal fade" id="modalEsp" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header" style="background:#2c3e50;color:#fff">
      <h5 class="modal-title"><i class="fas fa-plus mr-1"></i> <span id="titEsp">Crear Especialidad</span></h5>
      <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="formEsp" autocomplete="off">
      <div class="modal-body">
        <input type="hidden" id="espId" name="id_especialidad">
        <div class="form-group">
          <label>Nombre de la Especialidad:</label>
          <input type="text" class="form-control" id="espNombre" name="nombre" required placeholder="Ej: Cardiología, Pediatría…">
        </div>
        <div class="form-group">
          <label>Descripción:</label>
          <textarea class="form-control" id="espDesc" name="descripcion" rows="3" placeholder="Describa el ámbito…"></textarea>
        </div>
        <div class="form-group">
          <label>Estado:</label>
          <select class="form-control" id="espEstado" name="estado">
            <option value="activa">Activa</option>
            <option value="inactiva">Inactiva</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal">
          <i class="fas fa-times"></i> Cancelar
        </button>
        <button class="btn btn-primary" type="submit">
          <i class="fas fa-save"></i> <span id="btnEspTxt">Crear Especialidad</span>
        </button>
      </div>
    </form>
  </div></div>
</div>

<?php include __DIR__.'/config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){

  // --- Botones de cabecera ---
  $('#btnVolver').on('click', function(){
    // vuelve a la pantalla principal de especialidades médicas
    window.location.href = 'especialidades.php';
  });

  $('#btnCrear').on('click', function(){
    // abre modal de creación
    $('#titEsp').text('Crear Especialidad');
    $('#btnEspTxt').text('Crear Especialidad');
    $('#espId').val(''); $('#espNombre').val(''); $('#espDesc').val(''); $('#espEstado').val('activa');
    $('#modalEsp').modal('show');
  });

  // --- Guardar (crear/editar) especialidad desde el modal ---
  $('#formEsp').on('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({
      url:'ajax/especialidad_guardar.php', method:'POST',
      data:fd, processData:false, contentType:false, dataType:'json'
    }).done(function(r){
      if(r && r.success){
        Swal.fire({icon:'success',title:'¡Listo!',text:r.message||'Guardado',timer:1400,showConfirmButton:false});
        $('#modalEsp').modal('hide');
        cargarEspecialidades(); // refresca la lista
      }else{
        Swal.fire('Error', (r && r.message) ? r.message : 'No se pudo guardar', 'error');
      }
    }).fail(function(){
      Swal.fire('Error','Error de conexión','error');
    });
  });

// dentro de especialidades_lista.php (o especialidades.php)
function eliminarEsp(id){
  $.post('ajax/especialidades_eliminar.php', { id_especialidad: id }, function(r){
    if (r && r.success) {
      Swal.fire({icon:'success', title:'Eliminada', timer:1300, showConfirmButton:false});
      cargarEspecialidades();             // o la función que refresca la lista
    } else {
      Swal.fire({icon:'error', title:'No se pudo eliminar', text:(r && r.message) ? r.message : 'Intenta de nuevo.'});
    }
  }, 'json').fail(function(){
    Swal.fire({icon:'error', title:'Error', text:'Error de conexión'});
  });
}

// en el render, el botón llama a eliminarEsp(id)
$('#boxEspecialidades').on('click', '[data-eliminar]', function(){
  eliminarEsp($(this).data('eliminar'));
});

  // --- Carga y render de la lista ---
  function cargarEspecialidades(){
    $.get('ajax/especialidades_listar.php', function(r){
      if(!r || r.success === false){
        $('#badgeEspecialidades').text('0 especialidades');
        $('#boxEspecialidades').html('<div class="alert alert-danger mb-0">No se pudieron cargar las especialidades.</div>');
        return;
      }
      const list = r.data || [];
      $('#badgeEspecialidades').text(list.length+' especialidade'+(list.length!==1?'s':''));

      if(!list.length){
        $('#boxEspecialidades').html('<div class="text-center text-muted py-4">Sin especialidades.</div>');
        return;
      }

      let html = '';
      list.forEach(e => {
        const chip = (String(e.estado).toLowerCase()==='activa')
          ? '<span class="badge badge-success list-badge">activa</span>'
          : '<span class="badge badge-secondary list-badge">inactiva</span>';
        const cre = (e.created_at||'').substring(0,10);
        const count = Number(e.medicos_asignados||0);

        html += `
        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <span class="font-weight-bold text-primary">
                <i class="fas fa-heartbeat mr-1 text-danger"></i>${esc(e.nombre||'—')}
              </span> ${chip}
              <div class="text-muted small">
                <i class="far fa-calendar-alt mr-1"></i>Creada: ${cre?esc(formatearFecha(cre)):'—'} &nbsp; • &nbsp;
                <i class="fas fa-user-md mr-1"></i>${count} médico${count!==1?'s':''} asignado${count!==1?'s':''}
              </div>
              <div class="mt-1">${esc(e.descripcion||'Sin descripción')}</div>
            </div>
            <div class="btn-vert" style="min-width:170px">
              <button class="btn btn-warning btn-sm mb-1" data-editar="${e.id_especialidad}">
                <i class="fas fa-edit"></i> Editar
              </button>
              <button class="btn btn-danger btn-sm" data-eliminar="${e.id_especialidad}">
                <i class="fas fa-trash"></i> Eliminar
              </button>
            </div>
          </div>
        </div>`;
      });

      $('#boxEspecialidades').html(html);

      // Editar (rellena y reutiliza el mismo modal)
      $('#boxEspecialidades [data-editar]').on('click', function(){
        const id = $(this).data('editar');
        const row = (list || []).find(x => Number(x.id_especialidad) === Number(id));
        if(!row) return;
        $('#titEsp').text('Editar Especialidad');
        $('#btnEspTxt').text('Actualizar');
        $('#espId').val(row.id_especialidad||'');
        $('#espNombre').val(row.nombre||'');
        $('#espDesc').val(row.descripcion||'');
        $('#espEstado').val(String(row.estado||'activa').toLowerCase());
        $('#modalEsp').modal('show');
      });

      // Eliminar
      $('#boxEspecialidades [data-eliminar]').on('click', function(){
        const id = $(this).data('eliminar');
        Swal.fire({
          icon:'warning', title:'¿Eliminar especialidad?',
          text:'Solo es posible si no tiene médicos asignados.',
          showCancelButton:true, confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar', confirmButtonColor:'#d33'
        }).then(res=>{
          if(!res.isConfirmed) return;
          $.post('ajax/especialidades_eliminar.php', {id_especialidad:id}, function(r){
            if(r && r.success){
              Swal.fire({icon:'success',title:'Eliminada',timer:1200,showConfirmButton:false});
              cargarEspecialidades();
            }else{
              Swal.fire('No se pudo eliminar', (r && r.message) ? r.message : '', 'error');
            }
          }, 'json').fail(()=> Swal.fire('Error','Error de conexión','error'));
        });
      });

    }, 'json').fail(function(){
      $('#badgeEspecialidades').text('0 especialidades');
      $('#boxEspecialidades').html('<div class="alert alert-danger mb-0">No se pudieron cargar las especialidades.</div>');
    });
  }

  function esc(s){ return (s==null)?'':String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
  function formatearFecha(f){
    if(!f) return '—';
    try{ return new Date(f+'T00:00:00').toLocaleDateString('es-ES',{year:'numeric',month:'long',day:'numeric'}); }
    catch(e){ return f; }
  }

  // Carga inicial
  cargarEspecialidades();
});
</script>
</body>
</html>