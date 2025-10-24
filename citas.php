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
  <?php include './config/data_tables_css.php'; ?>
  <title>Citas Médicas</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    .bg-primary-custom{background:linear-gradient(135deg,#2c3e50,#3498db)!important;color:#fff}
    .stat-card .icon{position:absolute;right:12px;top:8px;opacity:.25;font-size:32px}
    .stat-card{position:relative;min-height:84px}
    .cita-card{border:1px solid #e5e7eb;border-left:4px solid #3498db;border-radius:6px}
    .cita-card.pendiente{border-left-color:#f39c12}
    .cita-card.atendida{border-left-color:#28a745}
    .cita-card.cancelada{border-left-color:#dc3545}
    .badge-estado{font-size:.75rem;padding:.35rem .55rem}
    .btn-vertical .btn{width:100%}
    @media (max-width:768px){.btn-vertical .btn{margin-bottom6px}}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; ?>
  <?php include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <!-- Encabezado -->
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-calendar-check"></i>Citas Médicas</h1>
        <button class="btn btn-primary" id="nuevaCitaBtn"><i class="fas fa-plus"></i> Nueva Cita</button>
      </div>
    </section>

    <section class="content">
      <!-- Métricas -->
      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="small-box bg-primary stat-card">
            <div class="inner">
              <h3 id="totalCitas">0</h3><p>Total Citas</p>
            </div>
            <div class="icon"><i class="fas fa-calendar"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-warning stat-card">
            <div class="inner">
              <h3 id="citasPendientes">0</h3><p>Pendientes</p>
            </div>
            <div class="icon"><i class="fas fa-clock"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-success stat-card">
            <div class="inner">
              <h3 id="citasAtendidas">0</h3><p>Atendidas</p>
            </div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-danger stat-card">
            <div class="inner">
              <h3 id="citasCanceladas">0</h3><p>Canceladas</p>
            </div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Filtros -->
        <div class="col-lg-3">
          <div class="card card-outline card-primary">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-filter"></i> Filtros</h3>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="small font-weight-bold">Estado:</label>
                <select class="form-control form-control-sm" id="filtroEstado">
                  <option value="todos">Todos los estados</option>
                  <option value="pendiente">Pendiente</option>
                  <option value="atendida">Atendida</option>
                  <option value="cancelada">Cancelada</option>
                </select>
              </div>
              <div class="form-group">
                <label class="small font-weight-bold">Fecha:</label>
                <input type="date" class="form-control form-control-sm" id="filtroFecha">
              </div>
              <div class="form-group">
                <label class="small font-weight-bold">Médico:</label>
                <select class="form-control form-control-sm" id="filtroMedico">
                  <option value="todos">Todos los médicos</option>
                </select>
              </div>
              <div class="d-grid gap-2">
                <button class="btn btn-primary btn-sm mb-1" id="aplicarFiltros"><i class="fas fa-check"></i> Aplicar Filtros</button>
                <button class="btn btn-outline-secondary btn-sm mb-1" id="limpiarFiltros"><i class="fas fa-broom"></i> Limpiar Filtros</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Listado -->
        <div class="col-lg-9">
          <div class="card card-outline card-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title"><i class="fas fa-list"></i> Lista de Citas</h3>
              <span class="badge badge-primary" id="contadorCitas">0 citas</span>
            </div>
            <div class="card-body">
              <div id="listaCitas">
                <div class="text-center p-5 text-muted">
                  <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                  <div>Cargando citas...</div>
                </div>
              </div>
              <div id="sinCitas" class="text-center py-5 d-none">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No hay citas registradas</h5>
                <p class="text-muted">Comienza creando tu primera cita médica</p>
                <button class="btn btn-primary" id="crearPrimeraCita"><i class="fas fa-plus"></i> Crear Primera Cita</button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<!-- Modal Nueva/Editar Cita -->
<div class="modal fade" id="citaModal" tabindex="-1" role="dialog" aria-labelledby="citaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-primary-custom">
      <h5 class="modal-title" id="citaModalLabel"><i class="fas fa-calendar-plus"></i> Nueva Cita Médica</h5>
      <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="formCita" autocomplete="off">
      <div class="modal-body">
        <input type="hidden" id="citaId" name="cita_id">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Paciente</label>
            <select class="form-control" id="pacienteId" name="paciente_id" required>
              <option value="">Seleccione un paciente</option>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Médico</label>
            <select class="form-control" id="medicoId" name="medico_id" required>
              <option value="">Seleccione un médico</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Fecha</label>
            <input type="date" class="form-control" id="fechaCita" name="fecha" required>
          </div>
          <div class="form-group col-md-6">
            <label>Hora</label>
            <input type="time" class="form-control" id="horaCita" name="hora" required>
          </div>
        </div>
        <div class="form-group">
          <label>Motivo de la consulta</label>
          <textarea class="form-control" id="motivoCita" name="motivo" rows="3" required></textarea>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select class="form-control" id="estadoCita" name="estado" required>
            <option value="pendiente">Pendiente</option>
            <option value="atendida">Atendida</option>
            <option value="cancelada">Cancelada</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> <span id="textoBotonGuardar">Guardar Cita</span></button>
      </div>
    </form>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
  // === Estado ===
  let citas = [];
  let editandoCitaId = null;

  // === Cargar combos + citas ===
  cargarDatosIniciales();
  cargarCitas();

  $('#nuevaCitaBtn, #crearPrimeraCita').on('click', mostrarModalNuevaCita);
  $('#aplicarFiltros').on('click', aplicarFiltros);
  $('#limpiarFiltros').on('click', limpiarFiltros);
  $('#imprimirTodo').on('click', ()=> window.open('imprimir_citas.php','_blank'));
  $('#formCita').on('submit', guardarCita);

  function cargarDatosIniciales(){
    $.get('ajax/cargar_pacientes.php', function(html){
      $('#pacienteId').html('<option value="">Seleccione un paciente</option>'+html);
    }).fail(()=> $('#pacienteId').html('<option value="">Error al cargar pacientes</option>'));

    $.get('ajax/cargar_medicos.php', function(html){
      $('#medicoId').html('<option value="">Seleccione un médico</option>'+html);
      $('#filtroMedico').html('<option value="todos">Todos los médicos</option>'+html);
    }).fail(()=>{
      $('#medicoId').html('<option value="">Error al cargar médicos</option>');
      $('#filtroMedico').html('<option value="todos">Todos los médicos</option>');
    });

    const hoy = new Date().toISOString().split('T')[0];
    $('#fechaCita').attr('min', hoy);
    $('#filtroFecha').attr('min', hoy);
  }

  function cargarCitas(filtros={}){
    $.post('ajax/citas_listar.php', filtros, function(r){
      // tolerante: si llega texto, intento parsear
      if (typeof r === 'string') {
        try { r = JSON.parse(r); } catch(_){ r = {success:false, message:r}; }
      }
      if(r && r.success){
        citas = r.data || [];
        renderCitas(citas);
        kpis(citas);
      }else{
        errorMsg(r && r.message ? r.message : 'Error al cargar las citas');
      }
    }, 'text') // <- no forzamos json, parseamos arriba
    .fail(function(xhr){
      errorMsg(extractAjaxError(xhr));
    });
  }

  function renderCitas(items){
    const $list = $('#listaCitas'), $empty = $('#sinCitas'), $cnt = $('#contadorCitas');
    if(!items.length){
      $list.addClass('d-none'); $empty.removeClass('d-none'); $cnt.text('0 citas'); return;
    }
    $empty.addClass('d-none'); $list.removeClass('d-none'); $cnt.text(items.length+' cita'+(items.length!==1?'s':''));
    let html='';
    items.forEach(c => html += cardCita(c));
    $list.html(html);

    items.forEach(c=>{
      $('#editarCita-'+c.id_cita).on('click', ()=> editarCita(c.id_cita));
      $('#eliminarCita-'+c.id_cita).on('click', ()=> eliminarCita(c.id_cita));
      $('#imprimirCita-'+c.id_cita).on('click', ()=> window.open('imprimir_cita.php?id='+c.id_cita,'_blank'));
    });
  }

  function cardCita(c){
    const badge = (c.estado==='pendiente')?'warning':(c.estado==='atendida')?'success':'danger';
    const fecha = formatFecha(c.fecha);
    const hora  = (c.hora||'').substring(0,5);
    return `
      <div class="card cita-card mb-3 ${c.estado}">
        <div class="card-body">
          <div class="row">
            <div class="col-md-8">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="mb-0 text-primary">Cita #${c.id_cita}</h6>
                <span class="badge badge-${badge} badge-estado text-uppercase">${c.estado}</span>
              </div>
              <p class="mb-1"><i class="fas fa-user text-muted mr-2"></i><b>Paciente:</b> ${c.nombre_paciente||'N/A'}</p>
              <p class="mb-1"><i class="fas fa-user-md text-muted mr-2"></i><b>Médico:</b> ${c.nombre_medico||'N/A'}</p>
              <p class="mb-1"><i class="fas fa-calendar text-muted mr-2"></i><b>Fecha:</b> ${fecha} ${hora}</p>
              <p class="mb-0"><i class="fas fa-stethoscope text-muted mr-2"></i><b>Motivo:</b> ${c.motivo||'—'}</p>
            </div>
            <div class="col-md-4 text-right btn-vertical">
              <button class="btn btn-warning btn-sm mb-1" id="editarCita-${c.id_cita}"><i class="fas fa-edit"></i> Editar</button>
              <button class="btn btn-danger  btn-sm mb-1" id="eliminarCita-${c.id_cita}"><i class="fas fa-trash"></i> Eliminar</button>
              <button class="btn btn-info    btn-sm"     id="imprimirCita-${c.id_cita}"><i class="fas fa-print"></i> Imprimir</button>
            </div>
          </div>
        </div>
      </div>`;
  }

  function kpis(items){
    $('#totalCitas').text(items.length);
    $('#citasPendientes').text(items.filter(x=>x.estado==='pendiente').length);
    $('#citasAtendidas').text(items.filter(x=>x.estado==='atendida').length);
    $('#citasCanceladas').text(items.filter(x=>x.estado==='cancelada').length);
  }

  function mostrarModalNuevaCita(){
    editandoCitaId=null;
    $('#citaModalLabel').html('<i class="fas fa-calendar-plus"></i> Nueva Cita Médica');
    $('#textoBotonGuardar').text('Guardar Cita');
    $('#formCita')[0].reset(); $('#citaId').val('');
    const hoy = new Date().toISOString().split('T')[0];
    $('#fechaCita').val(hoy);
    $('#citaModal').modal('show');
  }

  function editarCita(id){
    $.post('ajax/get_cita.php', {id_cita:id}, function(r){
      if (typeof r === 'string') { try { r = JSON.parse(r); } catch(_) { r = {success:false,message:r}; } }
      if(r && r.success){
        const c = r.data; editandoCitaId = id;
        $('#citaModalLabel').html('<i class="fas fa-edit"></i> Editar Cita Médica');
        $('#textoBotonGuardar').text('Actualizar Cita');
        $('#citaId').val(c.id_cita);
        $('#pacienteId').val(c.paciente_id);
        $('#medicoId').val(c.medico_id);
        $('#fechaCita').val(c.fecha);
        $('#horaCita').val(c.hora);
        $('#motivoCita').val(c.motivo);
        $('#estadoCita').val(c.estado);
        $('#citaModal').modal('show');
      }else{ errorMsg(r.message || 'Error al cargar la cita'); }
    }, 'text').fail(xhr => errorMsg(extractAjaxError(xhr)));
  }

  // ====== GUARDAR (crear/editar) TOLERANTE A RESPUESTAS ======
  function guardarCita(e){
    e.preventDefault();
    const fd = new FormData(this);
    if (editandoCitaId) fd.append('editar','true');

    $.ajax({
      url:'ajax/guardar_cita.php',
      type:'POST',
      data:fd,
      processData:false,
      contentType:false,
      dataType:'text' // <- no forzamos json; parseamos manual
    })
    .done(function(resp){
      let r = resp;
      if (typeof r === 'string') {
        try { r = JSON.parse(r); }
        catch(_) { r = {success:false, message: (r || 'Respuesta no válida del servidor')}; }
      }
      if (r && r.success){
        Swal.fire({
          icon:'success',
          title: editandoCitaId ? '¡Cita actualizada!' : '¡Cita creada!',
          text: r.message || '',
          timer: 1600,
          showConfirmButton:false
        });
        $('#citaModal').modal('hide');
        cargarCitas();   // refresca lista + KPIs sin recargar la página
      } else {
        errorMsg(r && r.message ? r.message : 'No se pudo guardar la cita');
      }
    })
    .fail(function(xhr){
      errorMsg(extractAjaxError(xhr));
    });
  }

  function eliminarCita(id){
    Swal.fire({
      title:'¿Estás seguro?',
      text:'Esta acción no se puede deshacer',
      icon:'warning',
      showCancelButton:true,
      confirmButtonText:'Sí, eliminar',
      confirmButtonColor:'#d33',
      cancelButtonText:'Cancelar'
    }).then(res=>{
      if(!res.isConfirmed) return;
      $.post('ajax/eliminar_cita.php',{id_cita:id},function(r){
        if (typeof r === 'string') { try { r = JSON.parse(r); } catch(_) { r = {success:false,message:r}; } }
        if(r && r.success){
          Swal.fire({icon:'success',title:'¡Eliminada!',text:r.message||'',timer:1500,showConfirmButton:false});
          cargarCitas();
        }else{ errorMsg(r.message || 'No se pudo eliminar'); }
      }, 'text').fail(xhr => errorMsg(extractAjaxError(xhr)));
    });
  }

  function aplicarFiltros(){
    cargarCitas({
      estado: $('#filtroEstado').val(),
      fecha:  $('#filtroFecha').val(),
      medico: $('#filtroMedico').val()
    });
  }
  function limpiarFiltros(){
    $('#filtroEstado').val('todos'); $('#filtroFecha').val(''); $('#filtroMedico').val('todos'); cargarCitas();
  }

  // Utils
  function formatFecha(f){
    try{ return new Date(f+'T00:00:00').toLocaleDateString('es-ES',{year:'numeric',month:'long',day:'numeric'}); }
    catch(e){ return f; }
  }
  function errorMsg(m){ Swal.fire({icon:'error', title:'Error', text:String(m||'Error')}); }
  function extractAjaxError(xhr){
    try{
      const j = xhr.responseJSON || JSON.parse(xhr.responseText||'{}');
      return j.message || j.error || xhr.statusText || 'Error de conexión';
    }catch(_){
      return (xhr.responseText && xhr.responseText.trim()) || xhr.statusText || 'Error de conexión';
    }
  }
});
</script>
</body>
</html>
