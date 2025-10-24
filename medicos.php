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
  <title>Gestión de Médicos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    .bg-primary-custom{background:linear-gradient(135deg,#2c3e50,#3498db)!important;color:#fff}
    .stat-card .icon{position:absolute;right:12px;top:8px;opacity:.25;font-size:32px}
    .stat-card{position:relative;min-height:84px}
    .medico-card{border:1px solid #e5e7eb;border-left:4px solid #3498db;border-radius:6px}
    .medico-card .badge-estado{font-size:.75rem;padding:.35rem .55rem}
    .linea-info{margin:2px 0}
    .btn-stack .btn{width:100%}
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
        <h1><i class="fas fa-user-md"></i> Gestión de Médicos</h1>
        <button class="btn btn-primary" id="nuevoMedicoBtn"><i class="fas fa-plus"></i> Nuevo Médico</button>
      </div>
    </section>

    <section class="content">
      <!-- Métricas -->
      <div class="row">
        <div class="col-lg-3 col-6">
          <div class="small-box bg-primary stat-card">
            <div class="inner"><h3 id="totalMedicos">0</h3><p>Total Médicos</p></div>
            <div class="icon"><i class="fas fa-user-md"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-success stat-card">
            <div class="inner"><h3 id="medicosActivos">0</h3><p>Activos</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-warning stat-card">
            <div class="inner"><h3 id="medicosInactivos">0</h3><p>Inactivos</p></div>
            <div class="icon"><i class="fas fa-clock"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-info stat-card">
            <div class="inner"><h3 id="totalEspecialidades">0</h3><p>Especialidades</p></div>
            <div class="icon"><i class="fas fa-stethoscope"></i></div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Filtros -->
        <div class="col-lg-3">
          <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-filter"></i> Filtros</h3></div>
            <div class="card-body">
              <div class="form-group">
                <label class="small font-weight-bold">Estado:</label>
                <select class="form-control form-control-sm" id="filtroEstado">
                  <option value="todos">Todos los estados</option>
                  <option value="activo">Activo</option>
                  <option value="inactivo">Inactivo</option>
                </select>
              </div>
              <div class="form-group">
                <label class="small font-weight-bold">Especialidad:</label>
                <select class="form-control form-control-sm" id="filtroEspecialidad">
                  <option value="todos">Todas las especialidades</option>
                </select>
              </div>
              <div class="d-grid gap-2">
                <button class="btn btn-primary btn-sm mb-1" id="aplicarFiltros"><i class="fas fa-check"></i> Aplicar Filtros</button>
                <button class="btn btn-outline-secondary btn-sm" id="limpiarFiltros"><i class="fas fa-broom"></i> Limpiar Filtros</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Listado -->
        <div class="col-lg-9">
          <div class="card card-outline card-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title"><i class="fas fa-list"></i> Lista de Médicos</h3>
              <span class="badge badge-primary" id="contadorMedicos">0 médicos</span>
            </div>
            <div class="card-body">
              <div id="listaMedicos">
                <div class="text-center p-5 text-muted">
                  <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                  <div>Cargando médicos...</div>
                </div>
              </div>
              <div id="sinMedicos" class="text-center py-5 d-none">
                <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No hay médicos registrados</h5>
                <p class="text-muted">Comienza agregando el primer médico</p>
                <button class="btn btn-primary" id="crearPrimerMedico"><i class="fas fa-plus"></i> Agregar Primer Médico</button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<!-- Modal Nuevo/Editar Médico -->
<div class="modal fade" id="medicoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-primary-custom">
      <h5 class="modal-title" id="medicoModalLabel"><i class="fas fa-user-plus"></i> Nuevo Médico</h5>
      <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="formMedico" autocomplete="off">
      <div class="modal-body">
        <input type="hidden" id="medicoId" name="medico_id">

        <!-- Usuario + Nombre Completo -->
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Médico (usuario del sistema)</label>
            <select class="form-control" id="usuarioId" name="usuario_id" required>
              <option value="">Seleccione un usuario</option>
            </select>
            <small class="form-text text-muted">Usuarios con rol Médico/Doctor/Enfermero</small>
          </div>
          <div class="form-group col-md-6">
            <label>Nombre Completo</label>
            <input type="text" class="form-control" id="nombreCompleto" name="nombre_completo" placeholder="Ej: Dr. Carlos Rodríguez" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Especialidad</label>
            <select class="form-control" id="especialidadId" name="especialidad_id">
              <option value="">Seleccione especialidad</option>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Número de Colegiado</label>
            <input type="text" class="form-control" id="colegiado" name="colegiado" placeholder="Ej: 15482" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Teléfono</label>
            <input type="text" class="form-control" id="telefono" name="telefono" placeholder="Ej: 5555-1234">
          </div>
          <div class="form-group col-md-6">
            <label>Correo Electrónico</label>
            <input type="email" class="form-control" id="correo" name="correo" placeholder="Ej: doctor@sanatorio.com">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Fecha de Contrato</label>
            <input type="date" class="form-control" id="fechaContrato" name="fecha_contrato">
          </div>
          <div class="form-group col-md-6">
            <label>Estado</label>
            <select class="form-control" id="estadoMedico" name="estado" required>
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
              <option value="vacaciones">Vacaciones</option>
              <option value="licencia">Licencia Médica</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Observaciones</label>
          <textarea class="form-control" id="observaciones" name="observaciones" rows="3" placeholder="Notas adicionales sobre el médico..."></textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> <span id="textoBotonGuardar">Guardar Médico</span></button>
      </div>
    </form>
  </div></div>
</div>

<!-- Modal Detalle del Médico -->
<div class="modal fade" id="detalleMedicoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header bg-primary-custom">
      <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detalle del Médico</h5>
      <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body" id="detalleMedicoBody">
      <!-- contenido dinámico -->
    </div>
    <div class="modal-footer">
      <button type="button" id="btnImprimirDetalle" class="btn btn-light"><i class="fas fa-print"></i> Imprimir</button>
      <button class="btn btn-primary" data-dismiss="modal">Cerrar</button>
    </div>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
  let medicos = [];
  let editandoMedicoId = null;

  // Cargar combos/listado
  cargarDatosIniciales();
  cargarMedicos();

  $('#nuevoMedicoBtn, #crearPrimerMedico').on('click', mostrarModalNuevoMedico);
  $('#aplicarFiltros').on('click', aplicarFiltros);
  $('#limpiarFiltros').on('click', ()=>{ $('#filtroEstado').val('todos'); $('#filtroEspecialidad').val('todos'); cargarMedicos(); });
  $('#formMedico').on('submit', guardarMedico);

  function cargarDatosIniciales(){
    // Usuarios para el modal
    $.get('ajax/cargar_usuarios_medicos.php', function(html){
      $('#usuarioId').html('<option value="">Seleccione un usuario</option>'+html);
    }).fail(()=> $('#usuarioId').html('<option value="">Error al cargar usuarios</option>'));

    // Especialidades (modal + filtros)
    $.get('ajax/cargar_especialidades.php', function(html){
      $('#especialidadId').html('<option value="">Seleccione especialidad</option>'+html);
      $('#filtroEspecialidad').html('<option value="todos">Todas las especialidades</option>'+html);
    }).fail(()=>{
      $('#especialidadId').html('<option value="">Error al cargar especialidades</option>');
      $('#filtroEspecialidad').html('<option value="todos">Todas las especialidades</option>');
    });
  }

  // === USAR GET (endpoint lee $_GET) y aceptar {data:[...]} ===
  function cargarMedicos(filtros={}){
    $.get('ajax/medicos_listar.php', filtros, function(r){
      const lista = (r && Array.isArray(r.data)) ? r.data : [];
      // normalizar a la estructura que usa la vista
      medicos = lista.map(normalizarMedico);
      renderMedicos(medicos);
      kpis(medicos);
    }, 'json').fail(()=> err('Error de conexión al cargar los médicos'));
  }

  // Normaliza lo que venga del backend a:
  // { id_medico, nombre_mostrar, especialidad_id, especialidad_nombre, colegiado, telefono, correo, estado, fecha_contrato, fecha_registro }
  function normalizarMedico(x){
    const id  = x.id_medico ?? x.id ?? x.medico_id ?? null;
    const nom = x.nombre_mostrar ?? x.nombre ?? x.nombre_completo ?? '';
    const espId  = x.especialidad_id ?? null;
    const espNom = x.especialidad_nombre ?? x.especialidad ?? '';
    // estado puede venir como 1/0 o texto
    let est = (x.estado ?? '').toString().toLowerCase().trim();
    if (est === '1') est = 'activo';
    else if (est === '0') est = 'inactivo';
    return {
      id_medico: id,
      nombre_mostrar: nom,
      especialidad_id: espId,
      especialidad_nombre: espNom,
      colegiado: x.colegiado ?? '',
      telefono: x.telefono ?? '',
      correo: x.correo ?? '',
      estado: est || 'inactivo',
      fecha_contrato: x.fecha_contrato ?? '',
      fecha_registro: x.fecha_registro ?? ''
    };
  }

  function renderMedicos(items){
    const $list = $('#listaMedicos'), $empty = $('#sinMedicos'), $cnt = $('#contadorMedicos');
    if(!items.length){
      $list.addClass('d-none'); $empty.removeClass('d-none'); $cnt.text('0 médicos'); return;
    }
    $empty.addClass('d-none'); $list.removeClass('d-none'); $cnt.text(items.length+' médico'+(items.length!==1?'s':''));    
    let html='';
    items.forEach(m => html += cardMedico(m));
    $list.html(html);
    items.forEach(m=>{
      $('#editarMedico-'+m.id_medico).on('click', ()=> editarMedico(m.id_medico));
      $('#eliminarMedico-'+m.id_medico).on('click', ()=> eliminarMedico(m.id_medico));
      $('#detalleMedico-'+m.id_medico).on('click', ()=> verDetalleMedico(m.id_medico));
    });
  }

  function cardMedico(m){
    const est = mapEstado(m.estado);
    const fechaReg = fechaPreferida(m);
    return `
      <div class="card medico-card mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="mb-0 text-primary">${esc(m.nombre_mostrar || '—')}</h6>
            <span class="badge badge-${est.cls} badge-estado">${est.txt}</span>
          </div>

          <div class="linea-info"><i class="fas fa-id-card text-muted mr-2"></i><b>Colegiado:</b> ${esc(m.colegiado || '—')}</div>
          <div class="linea-info"><i class="fas fa-stethoscope text-muted mr-2"></i><b>Especialidad:</b> ${esc(m.especialidad_nombre || 'Sin especialidad')}</div>
          <div class="linea-info"><i class="fas fa-phone text-muted mr-2"></i><b>Teléfono:</b> ${esc(m.telefono || '—')}</div>
          <div class="linea-info"><i class="fas fa-envelope text-muted mr-2"></i><b>Correo:</b> ${esc(m.correo || '—')}</div>
          <div class="linea-info"><i class="far fa-calendar text-muted mr-2"></i><b>Registro:</b> ${fechaReg || '—'}</div>

          <div class="btn-stack mt-2">
            <button class="btn btn-warning btn-sm mb-2" id="editarMedico-${m.id_medico}"><i class="fas fa-edit"></i> Editar</button>
            <button class="btn btn-danger btn-sm mb-2" id="eliminarMedico-${m.id_medico}"><i class="fas fa-trash"></i> Eliminar</button>
            <button class="btn btn-info btn-sm" id="detalleMedico-${m.id_medico}"><i class="fas fa-eye"></i> Detalles</button>
          </div>
        </div>
      </div>`;
  }

  function kpis(items){
    $('#totalMedicos').text(items.length);
    $('#medicosActivos').text(items.filter(x=>x.estado==='activo').length);
    $('#medicosInactivos').text(items.filter(x=>x.estado==='inactivo').length);
    const esp = [...new Set(items.map(x=>x.especialidad_nombre).filter(Boolean))];
    $('#totalEspecialidades').text(esp.length);
  }

  function mostrarModalNuevoMedico(){
    editandoMedicoId=null;
    $('#medicoModalLabel').html('<i class="fas fa-user-plus"></i> Nuevo Médico');
    $('#textoBotonGuardar').text('Guardar Médico');
    $('#formMedico')[0].reset(); $('#medicoId').val('');

    // Fecha por defecto: hoy
    const hoy = new Date().toISOString().split('T')[0];
    $('#fechaContrato').val(hoy);

    $('#medicoModal').modal('show');
  }

  function editarMedico(id){
    $.post('ajax/get_medico.php',{id_medico:id},function(r){
      if(r && r.success){
        const m=r.data; editandoMedicoId=id;
        $('#medicoModalLabel').html('<i class="fas fa-edit"></i> Editar Médico');
        $('#textoBotonGuardar').text('Actualizar Médico');

        $('#medicoId').val(m.id_medico);
        $('#usuarioId').val(m.usuario_id || '');
        $('#nombreCompleto').val(m.nombre_mostrar || m.nombre_completo || '');
        $('#especialidadId').val(m.especialidad_id);
        $('#colegiado').val(m.colegiado);
        $('#telefono').val(m.telefono);
        $('#correo').val(m.correo || '');
        $('#fechaContrato').val(m.fecha_contrato || '');
        $('#estadoMedico').val(m.estado || 'activo');
        $('#observaciones').val(m.observaciones || '');

        $('#medicoModal').modal('show');
      }else{ err(r.message || 'Error al cargar el médico'); }
    },'json').fail(()=> err('Error de conexión'));
  }

  function guardarMedico(e){
    e.preventDefault();
    const fd=new FormData(this);
    if(editandoMedicoId) fd.append('editar','true');
    $.ajax({
      url:'ajax/guardar_medico.php', type:'POST', data:fd, processData:false, contentType:false, dataType:'json'
    }).done(function(r){
      if(r && r.success){
        Swal.fire({icon:'success', title:editandoMedicoId?'¡Médico actualizado!':'¡Médico creado!', text:r.message||'', timer:1800, showConfirmButton:false});
        $('#medicoModal').modal('hide'); cargarMedicos();
      }else{ err(r.message || 'No se pudo guardar'); }
    }).fail(()=> err('Error de conexión'));
  }

  // === FUNCIÓN MEJORADA eliminarMedico ===
  function eliminarMedico(id){
    // Buscar el médico para mostrar su nombre en la confirmación
    const medico = medicos.find(m => Number(m.id_medico) === Number(id));
    const nombreMedico = medico ? medico.nombre_mostrar : 'el médico';
    
    Swal.fire({
      title: '¿Eliminar médico?',
      html: `<div class="text-left">
              <p class="mb-2"><strong>${esc(nombreMedico)}</strong></p>
              <p class="mb-2 text-danger"><i class="fas fa-exclamation-triangle"></i> Esta acción eliminará permanentemente:</p>
              <ul class="text-left pl-3 mb-3">
                <li>El registro del médico</li>
                <li>Todas sus citas programadas</li>
                <li>Diagnósticos asociados</li>
                <li>Tratamientos y recetas</li>
                <li>Historial médico relacionado</li>
              </ul>
              <p class="text-warning"><small><i class="fas fa-lightbulb"></i> <strong>Nota:</strong> Esta acción no se puede deshacer.</small></p>
            </div>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar permanentemente',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      reverseButtons: true,
      focusCancel: true,
      customClass: {
        confirmButton: 'btn btn-danger mx-1',
        cancelButton: 'btn btn-secondary mx-1'
      },
      buttonsStyling: false,
      showLoaderOnConfirm: true,
      preConfirm: () => {
        return new Promise((resolve, reject) => {
          $.post('ajax/eliminar_medico.php', { 
            id_medico: id, 
            force_delete: 1 
          }, function(r) {
            if (r && r.success) {
              resolve(r);
            } else {
              reject(new Error(r && r.message ? r.message : 'No se pudo eliminar el médico'));
            }
          }, 'json').fail(() => {
            reject(new Error('Error de conexión al servidor'));
          });
        });
      },
      allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          icon: 'success',
          title: '¡Eliminado!',
          text: 'El médico y todos sus datos asociados han sido eliminados correctamente.',
          timer: 2000,
          showConfirmButton: false,
          willClose: () => {
            cargarMedicos(); // Recargar la lista
          }
        });
      }
    }).catch((error) => {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: error.message || 'Ocurrió un error al eliminar el médico',
        confirmButtonColor: '#3085d6'
      });
    });
  }

  function aplicarFiltros(){
    // Mapear valores del front a lo que espera el backend
    const estSel = $('#filtroEstado').val();
    let estado = '';
    if (estSel === 'activo') estado = '1';
    else if (estSel === 'inactivo') estado = '0';

    const espSel = $('#filtroEspecialidad').val();
    const filtros = {
      estado: estado,
      especialidad_id: (espSel && espSel !== 'todos') ? espSel : '',
      q: '' // podrías conectar una caja de búsqueda si la tienes
    };
    cargarMedicos(filtros);
  }

  // ===== Detalle =====
  function verDetalleMedico(id){
    const base = medicos.find(x => Number(x.id_medico) === Number(id)) || {};
    $.post('ajax/get_medico.php',{id_medico:id},function(r){
      if(!(r && r.success)){ err(r && r.message ? r.message : 'No se pudo cargar el detalle'); return; }

      const m = r.data || {};
      const est  = mapEstado(m.estado);
      const estadoHtml = `<span class="badge badge-${est.cls}">${est.txt}</span>`;
      const nombre   = m.nombre_mostrar || m.nombre_completo || base.nombre_mostrar || '—';
      const especNom = m.especialidad_nombre || base.especialidad_nombre || 'Sin especialidad';
      const fContrato = fechaPreferida({...base, ...m});

      $('#detalleMedicoBody').html(`
        <h4 class="mb-3"><i class="fas fa-user-md text-primary mr-2"></i>${esc(nombre)}</h4>
        <p><b>Especialidad:</b> ${esc(especNom)}</p>
        <p><b>Colegiado:</b> ${esc(m.colegiado || base.colegiado || '—')}</p>
        <p><b>Teléfono:</b> ${esc(m.telefono || base.telefono || '—')}</p>
        <p><b>Email:</b> ${esc(m.correo || base.correo || '—')}</p>
        <p><b>Estado:</b> ${estadoHtml}</p>
        <p><b>Fecha de Contrato:</b> ${fContrato || '—'}</p>
        ${m.observaciones ? `<p><b>Observaciones:</b> ${esc(m.observaciones)}</p>` : ``}
      `);

      $('#detalleMedicoModal').modal('show');
    },'json').fail(()=> err('Error de conexión'));
  }

  $('#btnImprimirDetalle').on('click', function(){
    const contenido = document.getElementById('detalleMedicoBody').innerHTML;
    const w = window.open('', 'PRINT', 'height=650,width=900');
    w.document.write(`
      <html>
      <head>
        <title>Ficha del Médico</title>
        <style>
          body{font-family:Arial,Helvetica,sans-serif;padding:16px}
          h4{margin:0 0 12px 0}
          p{margin:6px 0}
        </style>
      </head>
      <body>
        <h3>Sanatorio La Esperanza</h3>
        ${contenido}
      </body>
      </html>
    `);
    w.document.close(); w.focus(); w.print(); w.close();
  });

  // Autocompletar nombre con texto del usuario seleccionado
  $('#usuarioId').on('change', function(){
    var txt = $(this).find('option:selected').text().trim();
    if ($('#nombreCompleto').length && !$('#nombreCompleto').val()) {
      $('#nombreCompleto').val(txt);
    }
  });

  // Utils
  function esc(s){ return (s==null)?'':String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
  function formatearFecha(f){
    if(!f) return '';
    try{ return new Date(f+'T00:00:00').toLocaleDateString('es-ES',{year:'numeric',month:'long',day:'numeric'}); }
    catch(e){ return f; }
  }
  function err(m){ Swal.fire({icon:'error',title:'Error',text:m}); }

  function mapEstado(estado){
    const e = (estado||'').toString().toLowerCase().trim();
    const cls = (e==='activo')     ? 'success'
              : (e==='inactivo')   ? 'secondary'
              : (e==='vacaciones') ? 'warning'
              : (e.startsWith('licencia')) ? 'info'
              : 'secondary';
    const txt = (e==='activo')     ? 'ACTIVO'
              : (e==='inactivo')   ? 'INACTIVO'
              : (e==='vacaciones') ? 'VACACIONES'
              : (e.startsWith('licencia')) ? 'LICENCIA MÉDICA'
              : (e ? e.toUpperCase() : '');
    return {cls, txt};
  }

  function fechaPreferida(m){
    const raw = m.fecha_contrato || m.created_at || m.creado_el || m.fecha_registro || '';
    if (!raw && m.registro) return m.registro;
    const iso = String(raw).substr(0,10);
    return iso ? formatearFecha(iso) : '';
  }
});
</script>
</body>
</html>