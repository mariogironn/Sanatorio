 <?php
/* ============================================================
 *  Seguridad de sesión y conexión
 * ============================================================ */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }            // Asegura sesión iniciada
require_once __DIR__.'/config/auth.php';                                      // Reglas de autenticación
require_once __DIR__.'/config/connection.php';                                // $con = PDO de la BD

$uid = (int)($_SESSION['user_id'] ?? 0);                                      // ID de usuario logueado
if ($uid<=0){ header('Location: login.php'); exit; }                          // Redirige si no hay sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <title>Tratamientos</title>
  <?php require __DIR__.'/config/site_css_links.php'; ?>
  <style>
    /* ======= Ajuste por top bar sticky ======= */
    #tr-page{ padding-top: 64px; }
    @media (max-width: 992px){ #tr-page{ padding-top: 74px; } }

    /* ======= Barra superior ======= */
    .page-bar{
      background:#2f3e4f;color:#fff;border-radius:10px;padding:12px 16px;margin-bottom:16px;
      display:flex;align-items:center;justify-content:space-between;box-shadow:0 6px 16px rgba(0,0,0,.10);
      position: sticky; top: 64px; z-index: 9;
    }
    .page-bar h1{font-size:1.25rem;margin:0;font-weight:700;letter-spacing:.2px}
    .page-bar .btn{box-shadow:0 2px 4px rgba(0,0,0,.12)}

    /* ======= Tarjeta tabla ======= */
    .card-dark{border:1px solid #e9ecef;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);background:#fff}
    .card-dark .card-head{
      background:#22313f;color:#fff;padding:10px 12px;font-weight:600;display:flex;align-items:center
    }
    .card-dark .card-head i{margin-right:8px}
    .card-dark .card-body{padding:12px}

    /* ======= Badges de estado ======= */
    .badge-state{display:inline-block;border-radius:999px;padding:.2rem .5rem;font-size:.75rem}
    .state-activo{background:#e9f7ef;color:#1e7e34;border:1px solid #bfe3cd}
    .state-completado{background:#e8f1ff;color:#1f5fb0;border:1px solid #c7dcff}
    .state-inactivo{background:#fff6e5;color:#8a5c00;border:1px solid #ffd59b}

    /* ======= Modal ancho cómodo ======= */
    @media (min-width: 992px){ #modalTrat .modal-dialog{ max-width: 900px; } }
    @media (min-width: 1200px){ #modalTrat .modal-dialog{ max-width: 1000px; } }

    /* ======= Multiselect de medicamentos más alto ======= */
    #tr_meds{ min-height: 220px; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php require __DIR__.'/config/header.php'; ?>
  <?php require __DIR__.'/config/sidebar.php'; ?>

  <div class="content-wrapper" id="tr-page">
    <?php require __DIR__.'/config/top_bar.php'; ?>

    <section class="content">
      <div class="container-fluid">

        <!-- ===== Barra superior con botón NUEVO ===== -->
        <div class="page-bar">
          <h1 class="m-0"><i class="fas fa-notes-medical mr-2"></i>TRATAMIENTOS</h1>
          <button id="btnNuevoTrat" class="btn btn-primary">
            <i class="fas fa-plus mr-1"></i> Nuevo Tratamiento
          </button>
        </div>

        <!-- ===== Tabla: listado de tratamientos ===== -->
        <div class="card-dark">
          <div class="card-head">
            <div><i class="fas fa-table"></i> Listado de Tratamientos</div>
          </div>
          <div class="card-body">
            <table id="tblTrat" class="table table-striped table-bordered w-100">
              <thead class="thead-dark">
                <tr>
                  <th>ID</th>
                  <th>Diagnóstico</th>
                  <th>Paciente</th>
                  <th>Médico</th>
                  <th>Fecha Inicio</th>
                  <th>Duración</th>
                  <th>Estado</th>
                  <th style="width:140px;">Acciones</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
            <div id="vacio" class="text-muted" style="display:none;">Sin registros.</div>
          </div>
        </div>

      </div>
    </section>
  </div>

  <?php require __DIR__.'/config/footer.php'; ?>
</div>

<?php require __DIR__.'/config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- ===== MODAL: Nuevo/Editar Tratamiento ===== -->
<div class="modal fade" id="modalTrat" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" id="formTrat">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="ttlTrat"><i class="fas fa-plus mr-2"></i> Nuevo Tratamiento</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="tr_id" name="id_tratamiento">

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Diagnóstico Relacionado *</label>
            <select id="tr_diagnostico" name="id_diagnostico" class="form-control" required></select>
          </div>
          <div class="form-group col-md-6">
            <label>Médico Tratante *</label>
            <select id="tr_medico" name="id_medico" class="form-control" required></select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Fecha de Inicio *</label>
            <input type="date" id="tr_fecha_inicio" name="fecha_inicio" class="form-control" required>
          </div>
          <div class="form-group col-md-4">
            <label>Duración Estimada</label>
            <input type="text" id="tr_duracion" name="duracion_estimada" class="form-control" placeholder="Ej: 3 meses, Permanente">
          </div>
          <div class="form-group col-md-4">
            <label>Estado</label>
            <select id="tr_estado" name="estado" class="form-control">
              <option value="Activo">Activo</option>
              <option value="Completado">Completado</option>
              <option value="Inactivo">Inactivo</option>
            </select>
          </div>
        </div>

        <!-- Multiselect de Medicamentos -->
        <div class="form-group">
          <label>Medicamentos</label>
          <select id="tr_meds" name="meds[]" class="form-control" multiple size="10"></select>
          <small class="text-muted d-block mt-1">
            Mantén presionada <b>Ctrl</b> (o <b>Cmd</b> en Mac) para seleccionar múltiples medicamentos.
          </small>
        </div>

        <div class="form-group">
          <label>Instrucciones del Tratamiento</label>
          <textarea id="tr_instrucciones" name="instrucciones" class="form-control" rows="3"
                    placeholder="Instrucciones específicas para el paciente…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit" id="btnGuardarTrat">Guardar Tratamiento</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== MODAL: Medicamentos del Tratamiento (informativo) ===== -->
<div class="modal fade" id="modalMeds" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="fas fa-pills mr-2"></i> Medicamentos en Tratamiento</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="medsBody">
        <div class="alert alert-info mb-0">Pronto aquí verás y podrás editar los medicamentos asociados.</div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Cerrar</button></div>
    </div>
  </div>
</div>

<script>
/* ============================================================================
 *  VARIABLES DE CONTROL
 * ============================================================================ */
let dt = null;                   // Instancia DataTable
let catalogsLoaded = false;      // Flag: catálogos ya cargados en el modal

/* ============================================================================
 *  HELPERS DE UI
 * ============================================================================ */

/** Devuelve el badge HTML según el estado del tratamiento. */
function stateBadge(estado){
  const e = (estado||'').toLowerCase();
  if(e==='activo')      return '<span class="badge-state state-activo">Activo</span>';
  if(e==='completado')  return '<span class="badge-state state-completado">Completado</span>';
  if(e==='inactivo')    return '<span class="badge-state state-inactivo">Inactivo</span>';
  return estado||'';
}

/** Botones de acción por fila de la tabla. */
function actionBtns(id){
  return `
    <div class="btn-group btn-group-sm">
      <button class="btn btn-info btn-meds"   data-id="${id}" title="Medicamentos"><i class="fas fa-pills"></i></button>
      <button class="btn btn-primary btn-edit" data-id="${id}" title="Editar"><i class="fas fa-edit"></i></button>
      <button class="btn btn-danger btn-del"   data-id="${id}" title="Eliminar"><i class="fas fa-trash"></i></button>
    </div>`;
}

/* ============================================================================
 *  *** NUEVO ***  CARGA DE CATÁLOGOS PARA EL MODAL
 *  - Implementa exactamente la firma solicitada por ti:
 *      function cargarCatalogosTratamientos(){ ... }
 *  - Rellena los <select> del modal: Diagnósticos, Médicos, Medicamentos
 *  - No bloquea la apertura del modal: puedes llamar a la función después de abrirlo
 * ============================================================================ */
function cargarCatalogosTratamientos(){
  // Llama al endpoint unificado que retorna: diagnosticos[], medicos[], medicamentos[]
  return $.getJSON('ajax/catalogos_tratamientos.php', function(r){

    // Detecta y asigna los selects con IDs “reales” o alternativos (defensa ante cambios de HTML)
    const $dx  = $('#tr_diagnostico').length ? $('#tr_diagnostico')
                 : ($('#t_dx_rel').length ? $('#t_dx_rel')
                 : ($('#t_diagnostico').length ? $('#t_diagnostico')
                 : ($('#diag_rel').length ? $('#diag_rel') : $())));

    const $med = $('#tr_medico').length ? $('#tr_medico')
                 : ($('#t_medico').length ? $('#t_medico')
                 : ($('#medico_id').length ? $('#medico_id')
                 : ($('#t_medico_tratante').length ? $('#t_medico_tratante') : $())));

    const $ms  = $('#tr_meds').length ? $('#tr_meds')
                 : ($('#t_medicamentos').length ? $('#t_medicamentos')
                 : ($('#med_ids').length ? $('#med_ids')
                 : ($('#medicamentos').length ? $('#medicamentos') : $())));

    // === Diagnósticos ===
    if ($dx && $dx.length) {
      $dx.empty();                                                      // Limpia opciones previas
      (r.diagnosticos || []).forEach(x =>                               // Recorre el arreglo
        $dx.append(`<option value="${x.id}">${x.text}</option>`)        // Agrega cada <option>
      );
    }

    // === Médicos ===
    if ($med && $med.length) {
      $med.empty();
      (r.medicos || []).forEach(x =>
        $med.append(`<option value="${x.id}">${x.text}</option>`)
      );
    }

    // === Medicamentos ===
    if ($ms && $ms.length) {
      $ms.empty();
      (r.medicamentos || []).forEach(x =>
        $ms.append(`<option value="${x.id}">${x.text}</option>`)
      );
    }

    catalogsLoaded = true;                                              // Marca catálogos como cargados
  }).fail(function(){
    // Si algo falla (404, 500, warning PHP), mostramos alerta pero el modal sigue abierto.
    Swal.fire('Error', 'No se pudieron cargar los catálogos', 'error');
  });
}

/* ============================================================================
 *  INICIALIZACIÓN DE LA TABLA
 * ============================================================================ */
function initTable(){
  const hasButtons = !!($.fn.dataTable && $.fn.dataTable.Buttons);     // Soporte para extensión Buttons
  dt = $('#tblTrat').DataTable({
    data: [],
    columns: [
      { data: 'codigo' },                                              // ID/código de tratamiento
      { data: 'diagnostico' },                                         // Nombre dx
      { data: 'paciente' },                                            // Paciente
      { data: 'medico' },                                              // Médico
      { data: 'fecha_inicio' },                                        // Fecha
      { data: 'duracion' },                                            // Duración
      { data: 'estado', render: (d)=>stateBadge(d) },                  // Badge de estado
      { data: 'id', orderable:false, searchable:false, render:(id)=>actionBtns(id) }
    ],
    order: [[0,'desc']],
    responsive: true,
    dom: hasButtons ? 'Bfrtip' : 'frtip',
    buttons: hasButtons ? ['copy', 'csv', 'excel', 'pdf', 'print'] : [],
    language: {                                                        // Traducción
      processing:     "Procesando...",
      search:         "Buscar:",
      lengthMenu:     "Mostrar _MENU_ registros",
      info:           "Mostrando _START_ a _END_ de _TOTAL_ registros",
      infoEmpty:      "Mostrando 0 a 0 de 0 registros",
      infoFiltered:   "(filtrado de _MAX_ en total)",
      loadingRecords: "Cargando...",
      zeroRecords:    "No se encontraron resultados",
      emptyTable:     "Sin datos disponibles",
      paginate: { first:"Primero", previous:"Anterior", next:"Siguiente", last:"Último" }
    }
  });
}

/** Carga (o recarga) los datos de la tabla desde el backend. */
function cargarTabla(){
  $.getJSON('ajax/tratamientos_listar.php', function(r){
    const rows = r && r.data ? r.data : [];
    if(!dt) initTable();
    dt.clear().rows.add(rows).draw();
    $('#vacio').toggle(rows.length===0);
  }).fail((xhr)=> {
    console.error('tratamientos_listar ERROR', xhr.responseText);
    if(!dt) initTable();
    dt.clear().draw();
    $('#vacio').show();
  });
}

/* ============================================================================
 *  READY: ENLACES DE EVENTOS Y LÓGICA
 * ============================================================================ */
$(function(){

  /* ---------- Handler NUEVO (tu botón actual) ---------- */
  $(document).on('click', '#btnNuevoTrat', function(){
    $('#ttlTrat').html('<i class="fas fa-plus mr-2"></i> Nuevo Tratamiento');  // Título del modal
    $('#formTrat')[0].reset();                                                 // Limpia formulario
    $('#tr_id').val('');                                                       // Asegura modo nuevo
    $('#tr_estado').val('Activo');                                             // Valor por defecto

    $('#modalTrat').modal('show');                                             // 1) Abre modal primero
    if(!catalogsLoaded){                                                       // 2) Carga catálogos sin bloquear
      cargarCatalogosTratamientos();
    }
  });

  /* ---------- Handler NUEVO (id alterno que pediste) ---------- */
  $(document).on('click', '#btnNuevoTratamiento', function(){
    // Mismo procedimiento, pero escuchando el id alternativo
    $('#ttlTrat').html('<i class="fas fa-plus mr-2"></i> Nuevo Tratamiento');
    $('#formTrat')[0].reset();
    $('#tr_id').val('');
    $('#tr_estado').val('Activo');
    $('#modalTrat').modal('show');
    if(!catalogsLoaded){
      cargarCatalogosTratamientos();
    }
  });

  /* ---------- Arranque de la tabla ---------- */
  initTable();
  cargarTabla();

  /* ---------- Ver medicamentos (informativo) ---------- */
  $('#tblTrat').off('click', '.btn-meds').on('click', '.btn-meds', function () {
    const $btn = $(this).closest('button.btn-meds');
    let id = parseInt($btn.attr('data-id') || $btn.data('id') || 0, 10);
    if (!id && typeof dt !== 'undefined' && dt) {
      const rowData = dt.row($btn.closest('tr')).data();
      if (rowData && rowData.id) id = parseInt(rowData.id, 10);
    }
    $('#medsBody').html('<div class="alert alert-info mb-0">Cargando medicamentos del tratamiento ' + id + '…</div>');
    $('#modalMeds').modal('show');
    $.getJSON('ajax/tratamiento_meds.php', { id: id, id_tratamiento: id }, function (r) {
      if (!r || r.ok === false) {
        $('#medsBody').html('<div class="alert alert-danger">No se pudo cargar la información.</div>');
        return;
      }
      const info = r.info || {};
      const meds = r.meds || [];
      let html = `
        <div class="card border-0">
          <div class="card-body p-3">
            <div class="mb-3 bg-light p-3 rounded">
              <div class="font-weight-bold mb-2"><i class="fas fa-info-circle mr-1"></i>Información del Tratamiento</div>
              <div><b>ID Tratamiento:</b> ${info.codigo || ''}</div>
              <div><b>Paciente:</b> ${info.paciente || ''}</div>
              <div><b>Diagnóstico:</b> ${info.diagnostico || ''}</div>
            </div>
            <div class="mb-2 font-weight-bold"><i class="fas fa-prescription-bottle-alt mr-1"></i>Medicamentos Prescritos</div>
            ${meds.length ? '<div class="list-group">' : '<div class="text-muted">Sin medicamentos asociados.</div>'}
      `;
      meds.forEach(m => {
        html += `
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <span>${m.nombre}</span>
            <small class="text-muted">${m.detalle || ''}</small>
          </div>`;
      });
      html += meds.length ? '</div>' : '';
      html += `</div></div>`;
      $('#medsBody').html(html);
    }).fail(function (xhr) {
      $('#medsBody').html('<div class="alert alert-danger">Error al cargar.<br><pre class="mb-0">' + (xhr.responseText || '') + '</pre></div>');
    });
  });

  /* ---------- Editar tratamiento ---------- */
  $('#tblTrat').off('click', '.btn-edit').on('click', '.btn-edit', function(){
    const id = $(this).data('id');

    const openEditor = () => {
      $.getJSON('ajax/tratamiento_detalle.php', {id}, function(r){
        if(!r || r.ok === false){ Swal.fire('Error', r && r.error ? r.error : 'No se pudo cargar', 'error'); return; }

        $('#ttlTrat').html('<i class="fas fa-edit mr-2"></i> Editar Tratamiento');
        $('#tr_id').val(r.id);
        $('#tr_diagnostico').val(r.id_diagnostico);

        // Si el médico del registro no está en la lista (pudo cambiar filtro), lo agregamos "al vuelo"
        if (r.id_medico && !$('#tr_medico option[value="'+r.id_medico+'"]').length) {
          const nom = r.medico || ('Médico #' + r.id_medico);
          $('#tr_medico').append(`<option value="${r.id_medico}">${nom}</option>`);
        }
        $('#tr_medico').val(r.id_medico);

        $('#tr_fecha_inicio').val(r.fecha_inicio);
        $('#tr_duracion').val(r.duracion_estimada || '');
        $('#tr_estado').val(r.estado || 'Activo');
        $('#tr_instrucciones').val(r.instrucciones || '');
        $('#tr_meds').val(r.meds || []); // multiselect

        $('#modalTrat').modal('show');
      });
    };

    // Asegura catálogos cargados antes de rellenar el form
    if (!catalogsLoaded) {
      cargarCatalogosTratamientos().then(openEditor);
    } else {
      openEditor();
    }
  });

  /* ---------- Eliminar tratamiento ---------- */
  $('#tblTrat').off('click', '.btn-del').on('click', '.btn-del', function(){
    const id = $(this).data('id');
    Swal.fire({
      icon:'warning',
      title:'¿Confirmar eliminación?',
      text:'Se eliminará el tratamiento y sus medicamentos asociados.',
      showCancelButton:true,
      confirmButtonText:'Eliminar',
      cancelButtonText:'Cancelar'
    }).then(res=>{
      if(!res.isConfirmed) return;
      $.post('ajax/tratamiento_eliminar.php', {id}, function(r){
        if(r && r.success){
          Swal.fire({icon:'success', title:'Eliminado', timer:1200, showConfirmButton:false});
          cargarTabla();
        }else{
          Swal.fire('Error', (r && r.message) ? r.message : 'No se pudo eliminar', 'error');
        }
      }, 'json').fail(function(xhr){
        Swal.fire('Error', xhr.responseText || 'No se pudo eliminar', 'error');
      });
    });
  });

  /* ---------- Guardar (crear/editar) ---------- */
  $('#formTrat').off('submit').on('submit', function (e) {
    e.preventDefault();

    const $btn = $('#btnGuardarTrat').prop('disabled', true).text('Guardando…');

    // FormData nos permite enviar arrays (meds[]) y duplicar nombres para compatibilidad
    const fd = new FormData(this);

    // Aliases por si el backend espera nombres alternos
    if (fd.get('id_medico'))         fd.append('medico_id',        fd.get('id_medico'));
    if (fd.get('id_diagnostico'))    fd.append('diagnostico_id',   fd.get('id_diagnostico'));
    if (fd.get('duracion_estimada')) fd.append('duracion',         fd.get('duracion_estimada'));

    // Duplica meds[] en medicamentos[] (algunos scripts lo esperan así)
    const medsSel = $('#tr_meds').val() || [];
    medsSel.forEach(id => fd.append('medicamentos[]', id));

    // Normaliza fecha dd/mm/aaaa -> yyyy-mm-dd si fuese necesario
    const f = $('#tr_fecha_inicio').val();
    if (f && /^\d{2}\/\d{2}\/\d{4}$/.test(f)) {
      const [dd,mm,yy] = f.split('/');
      fd.set('fecha_inicio', `${yy}-${mm}-${dd}`);
    }

    $.ajax({
      url: 'ajax/tratamiento_guardar.php',
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json'
    })
    .done(function (r) {
      if (r && r.success) {
        Swal.fire({ icon: 'success', title: 'Guardado', text: 'Tratamiento ' + (r.codigo || '') + ' guardado correctamente.', timer: 1600, showConfirmButton: false });
        $('#modalTrat').modal('hide');
        cargarTabla();
      } else {
        Swal.fire('Error', (r && r.message) ? r.message : 'Error al guardar', 'error');
      }
    })
    .fail(function (xhr) {
      Swal.fire({ icon: 'error', title: 'No se pudo guardar', html: `<pre style="white-space:pre-wrap;text-align:left">${xhr.responseText || 'Error'}</pre>` });
    })
    .always(function () {
      $btn.prop('disabled', false).text('Guardar Tratamiento');
    });
  });
});
</script>
</body>
</html>
