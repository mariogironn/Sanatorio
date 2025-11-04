<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__.'/config/auth.php';
require_once __DIR__.'/config/connection.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid<=0){ header('Location: login.php'); exit; }

$enfId = (int)($_GET['enfermedad_id'] ?? 0); // Filtro contextual desde Enfermedades
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <title>Diagnósticos de Pacientes</title>
  <?php require __DIR__.'/config/site_css_links.php'; ?>
  <style>
    /* === Arreglo: empuja el contenido bajo el top bar sticky === */
    #dx-page{ padding-top: 64px; }
    @media (max-width: 992px){ #dx-page{ padding-top: 74px; } }

    .page-bar{
      background:#2f3e4f;color:#fff;border-radius:10px;padding:12px 16px;margin-bottom:16px;
      display:flex;align-items:center;justify-content:space-between;box-shadow:0 6px 16px rgba(0,0,0,.10);
      position: sticky; top: 64px; z-index: 9;
    }
    .page-bar h1{font-size:1.25rem;margin:0;font-weight:700;letter-spacing:.2px}
    .page-bar .btn{box-shadow:0 2px 4px rgba(0,0,0,.12)}

    .card-dark{border:1px solid #e9ecef;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);background:#fff}
    .card-dark .card-head{
      background:#22313f;color:#fff;padding:10px 12px;font-weight:600;display:flex;align-items:center
    }
    .card-dark .card-head i{margin-right:8px}
    .card-dark .card-body{padding:12px}

    .dx-item{
      background:#fff;border:1px solid #e7e7e7;border-left:6px solid #6f42c1;border-radius:10px;
      padding:14px 16px;margin-bottom:12px;box-shadow:0 2px 6px rgba(0,0,0,.05)
    }
    .dx-title{font-weight:700;color:#1f3b5d}
    .dx-actions .btn{padding:.25rem .45rem}

    .pill{display:inline-block;border-radius:999px;padding:.15rem .5rem;font-size:.75rem;margin-left:.35rem}
    .pill.morado{background:#f2e9ff;color:#5a34a3;border:1px solid #dac6ff}
    .pill.verde{background:#e9f7ef;color:#1e7e34;border:1px solid #bfe3cd}
    .pill.azul{background:#e8f1ff;color:#1f5fb0;border:1px solid #c7dcff}

    /* Pills de gravedad */
    .pill.gravedad-leve{background:#e8f1ff;color:#1f5fb0;border:1px solid #c7dcff}
    .pill.gravedad-moderada{background:#fff6e5;color:#8a5c00;border:1px solid #ffd59b}
    .pill.gravedad-severa{background:#ffeaea;color:#a61c1c;border:1px solid #ffb3b3}

    .resume-line{display:flex;align-items:center;justify-content:space-between;padding:.4rem 0;border-bottom:1px dashed #edf0f2}
    .resume-line:last-child{border-bottom:0}

    /* Modal más ancho */
    @media (min-width: 992px){ #modalDx .modal-dialog{ max-width: 900px; } }
    @media (min-width: 1200px){ #modalDx .modal-dialog{ max-width: 1000px; } }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php require __DIR__.'/config/header.php'; ?>
  <?php require __DIR__.'/config/sidebar.php'; ?>

  <div class="content-wrapper" id="dx-page">
    <?php require __DIR__.'/config/top_bar.php'; ?>

    <section class="content">
      <div class="container-fluid">

        <!-- ===== Título + NUEVO diagnóstico ===== -->
        <div class="page-bar">
          <h1 class="m-0"><i class="fas fa-stethoscope mr-2"></i>DIAGNÓSTICOS DE PACIENTES</h1>
          <button id="btnNuevo" class="btn btn-primary">
            <i class="fas fa-plus mr-1"></i> Nuevo diagnóstico
          </button>
        </div>

        <div class="row">
          <div class="col-lg-12">
            <div class="card-dark">
              <div class="card-head">
                <div><i class="fas fa-list"></i> DIAGNÓSTICOS REGISTRADOS</div>
                <div class="ml-auto">
                  <button id="btnOpenFiltro" class="btn btn-sm btn-primary mr-2">
                    <i class="fas fa-filter mr-1"></i> Filtrar
                  </button>
                  <button id="btnOpenResumen" class="btn btn-sm btn-dark mr-2">
                    <i class="fas fa-chart-bar mr-1"></i> Resumen
                  </button>
                  <!-- NUEVO: Imprimir lista -->
                  <button id="btnPrintLista" class="btn btn-sm btn-secondary">
                    <i class="fas fa-print mr-1"></i> Imprimir
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div id="listaDx"></div>
                <div id="vacio" class="text-muted">Sin registros con los filtros actuales.</div>
              </div>
            </div>
          </div>

          <!-- Panel derecho oculto (modales lo reemplazan) -->
          <div class="col-lg-4 d-none">
            <div class="card-dark mb-3">
              <div class="card-head"><i class="fas fa-filter"></i> FILTRAR DIAGNÓSTICOS</div>
              <div class="card-body">
                <div class="form-group"><label>Paciente:</label><select id="f_paciente" class="form-control"></select></div>
                <div class="form-group"><label>Enfermedad:</label><select id="f_enfermedad" class="form-control"></select></div>
                <div class="form-group"><label>Médico:</label><select id="f_medico" class="form-control"></select></div>
                <button class="btn btn-primary btn-block" id="btnAplicar"><i class="fas fa-filter mr-1"></i>Aplicar filtros</button>
              </div>
            </div>

            <div class="card-dark">
              <div class="card-head"><i class="fas fa-chart-bar"></i> RESUMEN</div>
              <div class="card-body">
                <div class="resume-line"><span>Total de diagnósticos:</span><b id="k_total">0</b></div>
                <div class="resume-line"><span>Pacientes únicos:</span><b id="k_pac">0</b></div>
                <div class="resume-line"><span>Enfermedades diagnosticadas:</span><b id="k_enf">0</b></div>
                <div class="resume-line"><span>Médicos activos:</span><b id="k_med">0</b></div>
              </div>
            </div>
          </div>
        </div><!-- /.row -->

      </div>
    </section>
  </div>

  <?php require __DIR__.'/config/footer.php'; ?>
</div>

<?php require __DIR__.'/config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- ===== MODAL: Filtrar diagnósticos ===== -->
<div class="modal fade" id="modalFiltro" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="fas fa-filter mr-2"></i> Filtrar diagnósticos</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>Paciente:</label><select id="mf_paciente" class="form-control"></select></div>
        <div class="form-group"><label>Enfermedad:</label><select id="mf_enfermedad" class="form-control"></select></div>
        <div class="form-group"><label>Médico:</label><select id="mf_medico" class="form-control"></select></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
        <button class="btn btn-primary" id="btnAplicarModal">Aplicar filtros</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODAL: Resumen ===== -->
<div class="modal fade" id="modalResumen" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="fas fa-chart-bar mr-2"></i> Resumen</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="resume-line"><span>Total de diagnósticos:</span><b id="mk_total">0</b></div>
        <div class="resume-line"><span>Pacientes únicos:</span><b id="mk_pac">0</b></div>
        <div class="resume-line"><span>Enfermedades diagnosticadas:</span><b id="mk_enf">0</b></div>
        <div class="resume-line"><span>Médicos activos:</span><b id="mk_med">0</b></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Cerrar</button></div>
    </div>
  </div>
</div>

<!-- ===== MODAL: Crear/Editar ===== -->
<div class="modal fade" id="modalDx" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" id="formDx">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="ttlDx"><i class="fas fa-plus mr-2"></i> Nuevo diagnóstico</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="dx_id" name="id">
        <div class="form-group">
          <label>Paciente</label>
          <select id="dx_paciente" name="id_paciente" class="form-control" required></select>
        </div>
        <div class="form-group">
          <label>Enfermedad</label>
          <select id="dx_enfermedad" name="id_enfermedad" class="form-control" required></select>
        </div>
        <div class="form-group">
          <label>Médico</label>
          <select id="dx_medico" name="id_medico" class="form-control" required></select>
        </div>
        <div class="form-group">
          <label>Síntomas del Paciente</label>
          <textarea id="dx_sintomas" name="sintomas" class="form-control" rows="3" placeholder="Describa los síntomas…" required></textarea>
        </div>
        <div class="form-group">
          <label>Observaciones adicionales</label>
          <textarea id="dx_observaciones" name="observaciones" class="form-control" rows="3" placeholder="Observaciones, recomendaciones, notas…"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group col-sm-6">
            <label>Gravedad</label>
            <select id="dx_gravedad" name="gravedad" class="form-control">
              <option value="">(sin especificar)</option>
              <option value="Leve">Leve</option>
              <option value="Moderada">Moderada</option>
              <option value="Severa">Severa</option>
            </select>
          </div>
          <div class="form-group col-sm-6">
            <label>Fecha *</label>
            <input type="date" id="dx_fecha" name="fecha" class="form-control" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit" id="btnGuardarDx">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
const enfId = <?= $enfId ?: 0 ?>;

/* =================== UTILITARIOS DE SELECTS =================== */
function fillSelect($sel, items, label='nombre'){
  $sel.empty().append('<option value="0">Todos</option>');
  (items||[]).forEach(x => $sel.append(`<option value="${x.id}">${x[label]}</option>`));
}
function fillSelectRequired($sel, items, label='nombre'){
  $sel.empty();
  (items||[]).forEach(x => $sel.append(`<option value="${x.id}">${x[label]}</option>`));
}

/* =================== CATÁLOGOS GENERALES (pacientes / enfermedades) =================== */
function cargarCatalogos(prefillEnf){
  return $.getJSON('ajax/catalogos_diagnosticos.php', function(r){
    fillSelect($('#f_paciente'),   r.pacientes, 'paciente');
    fillSelect($('#f_enfermedad'), r.enfermedades, 'enfermedad');

    fillSelect($('#mf_paciente'),   r.pacientes, 'paciente');
    fillSelect($('#mf_enfermedad'), r.enfermedades, 'enfermedad');

    fillSelectRequired($('#dx_paciente'),   r.pacientes, 'paciente');
    fillSelectRequired($('#dx_enfermedad'), r.enfermedades, 'enfermedad');

    if (prefillEnf) {
      $('#f_enfermedad, #mf_enfermedad, #dx_enfermedad').val(prefillEnf);
    }
  });
}

/* =================== CATÁLOGO DE MÉDICOS (ROL CLÍNICO) =================== */
function cargarMedicosClinicos() {
  return $.getJSON('ajax/cargar_medicos_clinicos.php', function(r){
    fillSelect($('#f_medico'),  r.items, 'text');
    fillSelect($('#mf_medico'), r.items, 'text');
    fillSelectRequired($('#dx_medico'), r.items, 'text');
    if (r && r.sugerido) { $('#dx_medico').val(r.sugerido); }
  });
}

/* =================== RENDER LISTA =================== */
function renderLista(data){
  const $list = $('#listaDx').empty();
  if(!data || !data.length){ $('#vacio').show(); return; }
  $('#vacio').hide();

  data.forEach(r=>{
    const grav = (r.gravedad||'').toLowerCase();
    const pillGrav = grav ? `<span class="pill gravedad-${grav}">${r.gravedad}</span>` : '';
    $list.append(`
      <div class="dx-item">
        <div class="d-flex justify-content-between">
          <div class="dx-title"><a href="#" class="text-primary font-weight-bold">${r.paciente}</a></div>
          <div class="dx-actions">
            <button class="btn btn-sm btn-outline-primary btn-edit" data-id="${r.id}" title="Editar"><i class="fas fa-pen"></i></button>
            <button class="btn btn-sm btn-outline-danger btn-del" data-id="${r.id}" title="Eliminar"><i class="fas fa-trash"></i></button>
          </div>
        </div>
        <div class="mt-1"><b>Enfermedad:</b> ${r.enfermedad_corta}
          ${r.categoria?`<span class="pill morado">${r.categoria}</span>`:''}
          ${r.bandera_cronica?`<span class="pill verde">Crónica</span>`:''}
          ${r.bandera_contagiosa?`<span class="pill azul">Contagiosa</span>`:''}
          ${pillGrav}
        </div>
        <div><b>Síntomas:</b> ${r.sintomas || '<span class="text-muted">—</span>'}</div>
        ${r.observaciones ? `<div><b>Observaciones:</b> ${r.observaciones}</div>` : ''}
        <div><b>Médico:</b> ${r.medico} - ${r.fecha}</div>
      </div>
    `);
  });
}

/* =================== KPIs =================== */
function actualizarKPIs(res){
  const t = res?.total ?? 0, p = res?.pacientes ?? 0, e = res?.enfermedades ?? 0, m = res?.medicos ?? 0;
  $('#k_total').text(t); $('#k_pac').text(p); $('#k_enf').text(e); $('#k_med').text(m);
  $('#mk_total').text(t); $('#mk_pac').text(p); $('#mk_enf').text(e); $('#mk_med').text(m);
}

/* =================== CONSULTA LISTA =================== */
function cargarLista(){
  const q = {
    paciente_id:   $('#mf_paciente').val() || $('#f_paciente').val() || 0,
    enfermedad_id: enfId || $('#mf_enfermedad').val() || $('#f_enfermedad').val() || 0,
    medico_id:     $('#mf_medico').val() || $('#f_medico').val() || 0
  };
  $.getJSON('ajax/diagnosticos_listar.php', q, function(r){
    renderLista(r.data||[]);
    actualizarKPIs(r.resumen||{});
  });
}

/* ====== Imprimir lista (HTML imprimible) ====== */
function imprimirListaDx(){
  if ($('#vacio').is(':visible')) {
    Swal.fire('Sin datos', 'No hay diagnósticos para imprimir con los filtros actuales.', 'info');
    return;
  }
  const htmlLista = $('#listaDx').html();
  if (!htmlLista || !htmlLista.trim()) {
    Swal.fire('Sin datos', 'No hay diagnósticos para imprimir.', 'info');
    return;
  }

  const w = window.open('', '_blank');
  w.document.write(`<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Diagnósticos Registrados</title>
<style>
  @page { margin: 18mm 16mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color:#222; margin: 0; padding: 16px; }
  h2 { margin: 0 0 12px 0; font-size: 18px; }
  .dx-item{ border:1px solid #ccc; border-left:6px solid #6f42c1; border-radius:8px; padding:12px; margin-bottom:10px; page-break-inside: avoid; }
  .dx-title{ font-weight:700; color:#1f3b5d; margin-bottom:6px; }
  .dx-actions{ display:none; }
  .pill{display:inline-block;border-radius:999px;padding:2px 8px;font-size:11px;margin-left:6px;border:1px solid #eee}
  .pill.morado{background:#f2e9ff;color:#5a34a3;border-color:#dac6ff}
  .pill.verde{background:#e9f7ef;color:#1e7e34;border-color:#bfe3cd}
  .pill.azul{background:#e8f1ff;color:#1f5fb0;border-color:#c7dcff}
  .pill.gravedad-leve{background:#e8f1ff;color:#1f5fb0;border-color:#c7dcff}
  .pill.gravedad-moderada{background:#fff6e5;color:#8a5c00;border-color:#ffd59b}
  .pill.gravedad-severa{background:#ffeaea;color:#a61c1c;border-color:#ffb3b3}
</style>
</head>
<body>
  <h2>Diagnósticos Registrados</h2>
  ${htmlLista}
  <script>
    window.onload = function(){ window.print(); setTimeout(function(){ window.close(); }, 300); };
  <\/script>
</body>
</html>`);
  w.document.close();
}

$(function(){
  // 1) Catálogos base  2) Médicos clínicos  3) Lista
  cargarCatalogos(enfId)
    .then(() => cargarMedicosClinicos())
    .then(() => cargarLista());

  $('#btnOpenFiltro').on('click', ()=> $('#modalFiltro').modal('show'));
  $('#btnOpenResumen').on('click', ()=> $('#modalResumen').modal('show'));

  $('#btnAplicar').on('click', cargarLista);
  $('#btnAplicarModal').on('click', function(){
    $('#modalFiltro').modal('hide'); cargarLista();
  });

  // Imprimir
  $('#btnPrintLista').on('click', imprimirListaDx);

  // Abrir "Nuevo diagnóstico" (abre primero, carga médicos en segundo plano)
  $('#btnNuevo').on('click', function () {
    $('#ttlDx').html('<i class="fas fa-plus mr-2"></i> Nuevo diagnóstico');
    $('#formDx')[0].reset();
    $('#dx_id').val('');
    if (enfId) $('#dx_enfermedad').val(enfId);

    // 1) Abrir el modal inmediatamente (no bloquea si el AJAX falla)
    $('#modalDx').modal('show');

    // 2) Cargar médicos clínicos sin bloquear la apertura
    cargarMedicosClinicos()
      .fail(function () {
        // Si el endpoint da 404/500/Warning PHP, el modal IGUAL queda abierto.
      })
      .always(function () {
        // Si no quedó ningún valor seleccionado, tomar el primero disponible
        if (!$('#dx_medico').val()) {
          const first = $('#dx_medico option:first').val();
          if (first) $('#dx_medico').val(first);
        }
      });
  });

  // (Opcional) También recargamos médicos al mostrar el modal.
  $('#modalDx').on('show.bs.modal', function(){
    cargarMedicosClinicos();
  });

  /* Editar */
  $('#listaDx').on('click','.btn-edit', function(){
    const id = $(this).data('id');
    $.getJSON('ajax/diagnostico_detalle.php', {id}, function(r){
      if(!r || !r.id) return;
      $('#ttlDx').html('<i class="fas fa-pen mr-2"></i> Editar diagnóstico');
      $('#dx_id').val(r.id);
      $('#dx_paciente').val(r.id_paciente);
      $('#dx_enfermedad').val(r.id_enfermedad);

      cargarMedicosClinicos().then(() => {
        $('#dx_medico').val(r.id_medico);
      });

      $('#dx_fecha').val(r.fecha);
      $('#dx_sintomas').val(r.sintomas||'');
      $('#dx_observaciones').val(r.observaciones||'');
      $('#dx_gravedad').val(r.gravedad ? (r.gravedad.charAt(0).toUpperCase()+r.gravedad.slice(1)) : '');
      $('#modalDx').modal('show');
    });
  });

  /* Eliminar — con toast de éxito y alias de ID para compatibilidad */
  $('#listaDx').on('click', '.btn-del', function () {
    const id = parseInt($(this).data('id'), 10) || 0;

    Swal.fire({
      icon: 'warning',
      title: '¿Eliminar?',
      text: 'Esta acción no se puede deshacer.',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then(res => {
      if (!res.isConfirmed || !id) return;

      $.post(
        'ajax/diagnostico_eliminar.php',
        // Mandamos varios alias del mismo ID (id, id_diagnostico, diagnostico_id, iddx)
        { id: id, id_diagnostico: id, diagnostico_id: id, iddx: id },
        function (r) {
          if (r && r.success) {
            Swal.fire({
              toast: true,
              position: 'top-end',
              icon: 'success',
              title: 'Diagnóstico eliminado',
              showConfirmButton: false,
              timer: 1500,
              timerProgressBar: true
            });
            cargarLista();
          } else {
            Swal.fire('Error', (r && r.message) ? r.message : 'No se pudo eliminar', 'error');
          }
        },
        'json'
      ).fail(function (xhr) {
        Swal.fire('Error', (xhr.responseText || 'No se pudo eliminar'), 'error');
      });
    });
  });

  /* Guardar (crear/editar) — robusto y con compatibilidad de nombres */
  $('#formDx').on('submit', function(e){
    e.preventDefault();

    const $btn = $('#btnGuardarDx');
    $btn.prop('disabled', true).text('Guardando…');

    const data = $(this).serializeArray();
    const id = $('#dx_id').val();
    const sintomas = $('#dx_sintomas').val();

    if (id) data.push({name:'id_diagnostico', value:id});
    data.push({name:'observacion', value:sintomas});

    $.ajax({
      url: 'ajax/diagnostico_guardar.php',
      type: 'POST',
      data: $.param(data),
      dataType: 'json'
    })
    .done(function(r){
      if(r && r.success){
        Swal.fire({icon:'success', title:'Guardado', text:'El diagnóstico se guardó correctamente.', timer:1600, showConfirmButton:false});
        $('#modalDx').modal('hide');
        cargarLista();
      }else{
        Swal.fire('Error', (r && r.message) ? r.message : 'No se pudo guardar', 'error');
      }
    })
    .fail(function(xhr){
      Swal.fire({icon:'error', title:'No se pudo guardar', html: `<pre style="text-align:left;white-space:pre-wrap">${(xhr.responseText||'Error desconocido')}</pre>`});
    })
    .always(function(){
      $btn.prop('disabled', false).text('Guardar');
    });
  });
});
</script>
</body>
</html>
