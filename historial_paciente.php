<?php
// historial_paciente.php - PANTALLA PRINCIPAL DE HISTORIAL (CRUD COMPLETO)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

// ==== Opciones de selects ====
// Pacientes
$optPacientes  = getPacientes($con);
// Medicinas
$optMedicinas  = getMedicamentos($con);

// Obtener sucursales directamente desde la base de datos (solo para los modales)
$optSucursales = '<option value="">Seleccionar sucursal</option>';
try {
    $sqlSucursales = "SELECT id, nombre FROM sucursales WHERE estado = 1 ORDER BY nombre";
    $stmtSucursales = $con->prepare($sqlSucursales);
    $stmtSucursales->execute();
    $sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sucursales as $sucursal) {
        $optSucursales .= '<option value="' . $sucursal['id'] . '">' . htmlspecialchars($sucursal['nombre']) . '</option>';
    }
} catch (PDOException $e) {
    // Si hay error, usar opción por defecto
    $optSucursales = '<option value="">Santa Lucía Cotzumalguapa</option>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Historial del Paciente</title>
  <style>
    .card-ctx{border-left:4px solid #0d6efd}
    .btn-icon{width:34px;height:34px;padding:0;display:inline-flex;align-items:center;justify-content:center}
    .note{font-size:.85rem;color:#64748b}
    .table thead th{white-space:nowrap}
    .action-buttons .btn { margin-right: 3px; }
    .search-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .patient-summary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-history"></i> Historial del Paciente</h1>
        <div>
          <!-- BOTÓN REVISIÓN DE HISTORIAL - SOLO SE MUESTRA CUANDO HAY PACIENTE SELECCIONADO -->
          <a id="btnRevisionHistorial" href="#" class="btn btn-info btn-sm" style="display:none;">
            <i class="fas fa-file-medical"></i> Revisión de Historial Completo
          </a>
          <button id="btnAgregarPrincipal" class="btn btn-success btn-sm">
            <i class="fas fa-plus"></i> Agregar Registro
          </button>
        </div>
      </div>
    </section>

    <section class="content">
      <!-- Sección de Búsqueda Simplificada -->
      <div class="card card-ctx">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Historial de Paciente</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label>Buscar Paciente</label>
                <select id="paciente_id" class="form-control" required>
                  <option value="">Selecciona un paciente</option>
                  <?= $optPacientes ?>
                </select>
              </div>
            </div>
          </div>
          <button id="btnBuscar" class="btn btn-primary">
            <i class="fas fa-search"></i> Buscar Historial
          </button>
          <button id="btnLimpiar" class="btn btn-secondary">
            <i class="fas fa-broom"></i> Limpiar
          </button>
        </div>
      </div>

      <!-- Resumen del Paciente (aparece cuando se selecciona un paciente) -->
      <div id="resumenPaciente" class="card patient-summary" style="display:none;">
        <div class="card-body">
          <div class="row">
            <div class="col-md-12">
              <h5 class="mb-1"><i class="fas fa-user"></i> <span id="nombrePacienteCompleto"></span></h5>
              <p class="mb-1"><strong>Sucursal Principal:</strong> <span id="sucursalPaciente"></span></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabla de Historial -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0"><i class="fas fa-list"></i> Registros del Historial</h3>
          <div>
            <span class="badge badge-primary mr-2" id="contadorRegistros">0 registros</span>
            <button id="btnExportar" class="btn btn-outline-secondary btn-sm" style="display:none;">
              <i class="fas fa-file-excel"></i> Excel
            </button>
            <button id="btnExportarPDF" class="btn btn-outline-secondary btn-sm" style="display:none;">
              <i class="fas fa-file-pdf"></i> PDF
            </button>
          </div>
        </div>
        <div class="card-body table-responsive">
          <table id="tablaHistorial" class="table table-striped table-bordered">
            <thead>
              <tr>
                <th>N. Serie</th>
                <th>Fecha Visita</th>
                <th>Enfermedad</th>
                <th>Medicina</th>
                <th>Unidad</th>
                <th class="text-right">Cantidad</th>
                <th>Dosis</th>
                <th>Sucursal</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <!-- filas por AJAX -->
            </tbody>
          </table>
          <div class="note mt-2">
            * Selecciona un paciente y presiona <b>Buscar Historial</b> para ver los registros.
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<!-- Modales (Agregar y Editar) - Sucursal se mantiene en los modales -->
<!-- Modal: Agregar Registro -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form id="formAdd" autocomplete="off">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-notes-medical"></i> Agregar Registro de Historial</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="paciente_id" id="paciente_id_add" value="">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>N. Serie</label>
            <input type="text" class="form-control" name="n_serie" placeholder="Opcional">
          </div>
          <div class="form-group col-md-4">
            <label>Fecha Visita *</label>
            <input type="date" class="form-control" name="fecha_visita" required>
          </div>
          <div class="form-group col-md-4">
            <label>Sucursal *</label>
            <select class="form-control" name="sucursal_id" required>
              <?= $optSucursales ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Enfermedad *</label>
            <input type="text" class="form-control" name="enfermedad" required>
          </div>
          <div class="form-group col-md-6">
            <label>Medicina *</label>
            <select class="form-control" name="medicina_id" required>
              <option value="">Seleccionar medicina</option>
              <?= $optMedicinas ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Unidad</label>
            <input type="text" class="form-control" name="paquete" placeholder="Ej: tabletas, ml, etc.">
          </div>
          <div class="form-group col-md-4">
            <label>Cantidad *</label>
            <input type="number" min="1" class="form-control" name="cantidad" required>
          </div>
          <div class="form-group col-md-4">
            <label>Dosis *</label>
            <input type="text" class="form-control" name="dosis" placeholder="Ej: 500mg cada 8h" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Modal: Editar Registro -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form id="formEdit" autocomplete="off">
      <div class="modal-header bg-warning text-white">
        <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Registro de Historial</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="detalle_id" id="detalle_id_edit" value="">
        <input type="hidden" name="prescripcion_id" id="prescripcion_id_edit" value="">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>N. Serie</label>
            <input type="text" class="form-control" name="n_serie" id="n_serie_edit" placeholder="Opcional">
          </div>
          <div class="form-group col-md-4">
            <label>Fecha Visita *</label>
            <input type="date" class="form-control" name="fecha_visita" id="fecha_visita_edit" required>
          </div>
          <div class="form-group col-md-4">
            <label>Sucursal *</label>
            <select class="form-control" name="sucursal_id" id="sucursal_id_edit" required>
              <?= $optSucursales ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Enfermedad *</label>
            <input type="text" class="form-control" name="enfermedad" id="enfermedad_edit" required>
          </div>
          <div class="form-group col-md-6">
            <label>Medicina *</label>
            <select class="form-control" name="medicina_id" id="medicina_id_edit" required>
              <option value="">Seleccionar medicina</option>
              <?= $optMedicinas ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Unidad</label>
            <input type="text" class="form-control" name="paquete" id="paquete_edit" placeholder="Ej: tabletas, ml, etc.">
          </div>
          <div class="form-group col-md-4">
            <label>Cantidad *</label>
            <input type="number" min="1" class="form-control" name="cantidad" id="cantidad_edit" required>
          </div>
          <div class="form-group col-md-4">
            <label>Dosis *</label>
            <input type="text" class="form-control" name="dosis" id="dosis_edit" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-warning" type="submit"><i class="fas fa-save"></i> Actualizar</button>
      </div>
    </form>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<!-- jsPDF + AutoTable para PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.3/jspdf.plugin.autotable.min.js"></script>

<script>
// === JS COMPLETO CON TODAS LAS FUNCIONALIDADES ===
let pacienteSeleccionado = null;
let pacienteNombre = '';

function renderFila(r){
  return `
  <tr id="row-${r.detalle_id}">
    <td>${r.n_serie || 'N/A'}</td>
    <td>${r.fecha_visita}</td>
    <td>${r.enfermedad}</td>
    <td>${r.medicina}</td>
    <td>${r.paquete || '-'}</td>
    <td class="text-right">${r.cantidad}</td>
    <td>${r.dosis}</td>
    <td>${r.sucursal || 'No especificada'}</td>
    <td class="text-center action-buttons">
      <button class="btn btn-warning btn-sm btn-edit" 
        data-toggle="modal" data-target="#modalEdit"
        data-det="${r.detalle_id}" data-pid="${r.prescripcion_id}"
        data-nserie="${r.n_serie}" data-fecha="${r.fecha_visita}"
        data-enf="${r.enfermedad}" data-mid="${r.id_medicina}"
        data-cant="${r.cantidad}" data-dosis="${r.dosis}"
        data-paquete="${r.paquete}" data-sucursal="${r.id_sucursal}" title="Editar">
        <i class="fas fa-edit"></i>
      </button>
      <button class="btn btn-danger btn-sm btn-del" data-det="${r.detalle_id}" title="Eliminar">
        <i class="fas fa-trash"></i>
      </button>
    </td>
  </tr>`;
}

function cargarHistorial(){
  const pid = $('#paciente_id').val();
  
  if(!pid){ 
    resetearInterfaz();
    return; 
  }
  
  // Mostrar loading
  $('#tablaHistorial tbody').html('<tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando historial...</td></tr>');
  
  // LLAMADA SIMPLIFICADA - sin filtros de fecha ni sucursal
  $.getJSON('ajax/buscar_historial_paciente.php', { paciente_id: pid })
    .done(res=>{
      if(!res.success || !res.rows || res.rows.length === 0){ 
        $('#tablaHistorial tbody').html('<tr><td colspan="9" class="text-center text-muted">No se encontraron registros</td></tr>');
        $('#contadorRegistros').text('0 registros');
        $('#btnExportar, #btnExportarPDF').hide();
        
        // Aún así mostrar resumen del paciente si hay datos
        if(res.paciente_info){
          mostrarResumenPaciente(res.paciente_info);
        }
        return; 
      }
      
      const html = res.rows.map(renderFila).join('');
      $('#tablaHistorial tbody').html(html);
      $('#contadorRegistros').text(res.rows.length + ' registro(s)');
      $('#btnExportar, #btnExportarPDF').show();
      
      // Mostrar información del paciente
      if(res.rows[0]){
        pacienteSeleccionado = pid;
        pacienteNombre = res.rows[0].nombre_paciente || 'Paciente';
        
        const pacienteInfo = {
          nombre: res.rows[0].nombre_paciente || 'Paciente',
          sucursal: res.rows[0].sucursal_principal || 'No especificada',
          primera_visita: res.rows[0].primera_visita || 'No registrada',
          ultima_visita: res.rows[0].ultima_visita || 'No registrada'
        };
        
        mostrarResumenPaciente(pacienteInfo);
      }
    })
    .fail(x=> {
      $('#tablaHistorial tbody').html('<tr><td colspan="9" class="text-center text-danger">Error al cargar los datos</td></tr>');
      Swal.fire('Error', x.responseText || 'Fallo al cargar el historial','error');
    });
}

function mostrarResumenPaciente(info) {
  $('#nombrePacienteCompleto').text(info.nombre);
  $('#sucursalPaciente').text(info.sucursal);
  $('#resumenPaciente').show();
  
  // Configurar botón de Revisión de Historial
  $('#btnRevisionHistorial')
    .attr('href', 'ver_historial_completo.php?id=' + pacienteSeleccionado + '&nombre=' + encodeURIComponent(info.nombre))
    .show();
}

function resetearInterfaz() {
  $('#tablaHistorial tbody').html(''); 
  $('#resumenPaciente').hide();
  $('#btnRevisionHistorial').hide();
  $('#btnExportar, #btnExportarPDF').hide();
  $('#contadorRegistros').text('0 registros');
}

// === EXCEL ===
function exportarAExcel() {
  const tabla = document.getElementById('tablaHistorial');
  const paciente = $('#nombrePacienteCompleto').text() || 'Paciente';
  const fecha = new Date().toISOString().split('T')[0];
  
  // Clonar y quitar última columna (Acciones)
  const tablaClone = tabla.cloneNode(true);
  const filasAccion = tablaClone.querySelectorAll('th:last-child, td:last-child');
  filasAccion.forEach(celda => celda.remove());
  
  const wb = XLSX.utils.table_to_book(tablaClone, {sheet: "Historial"});
  XLSX.writeFile(wb, `Historial_${paciente}_${fecha}.xlsx`);
}

// === PDF ===
function exportarAPdf() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF('l', 'pt', 'letter'); // landscape, puntos, tamaño carta
  
  const paciente = $('#nombrePacienteCompleto').text() || 'Paciente';
  const fecha = new Date().toISOString().substring(0,10);
  
  // Título
  doc.setFontSize(14);
  doc.text(`Historial del Paciente — ${paciente}`, 40, 40);
  doc.setFontSize(10);
  doc.text(`Fecha de exportación: ${fecha}`, 40, 58);
  
  // Construir headers y filas excluyendo la última columna (Acciones)
  const headers = Array.from(document.querySelectorAll('#tablaHistorial thead th'))
                  .map(th => th.innerText.trim()).slice(0, -1);
  const body = Array.from(document.querySelectorAll('#tablaHistorial tbody tr')).map(tr => {
    return Array.from(tr.querySelectorAll('td')).slice(0, -1).map(td => td.innerText.trim());
  });
  
  doc.autoTable({
    head: [headers],
    body,
    startY: 75,
    styles: { fontSize: 9, cellPadding: 4 },
    headStyles: { fillColor: [102,126,234], textColor: 255 },
    didDrawPage: (data) => {
      // Footer simple
      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      doc.setFontSize(9);
      doc.text('SANATORIO LA ESPERANZA', pageWidth - 180, pageHeight - 20);
    }
  });
  
  doc.save(`Historial_${paciente}_${fecha}.pdf`);
}

// === FUNCIÓN PARA ELIMINAR REGISTRO ===
function eliminarRegistro(detalleId) {
  Swal.fire({
    title: '¿Estás seguro?',
    text: "No podrás revertir esta acción.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('ajax/eliminar_historial_paciente.php', { detalle_id: detalleId })
        .done(function(response) {
          try {
            const res = JSON.parse(response);
            if (res.success) {
              Swal.fire('Eliminado', 'El registro ha sido eliminado.', 'success');
              $(`#row-${detalleId}`).remove();
              const count = $('#tablaHistorial tbody tr').length;
              $('#contadorRegistros').text(count + ' registro(s)');
              if (count === 0) { $('#btnExportar, #btnExportarPDF').hide(); }
            } else {
              Swal.fire('Error', res.message || 'No se pudo eliminar el registro', 'error');
            }
          } catch (e) {
            Swal.fire('Error', 'Error al procesar la respuesta', 'error');
          }
        })
        .fail(function() { Swal.fire('Error', 'Error de conexión', 'error'); });
    }
  });
}

// === DETALLE ===
function verDetalles(detalleId) {
  window.location.href = `ver_detalle_historial.php?id=${detalleId}`;
}

// === Funcionalidades de botones del resumen (se mantienen por compatibilidad) ===
function verPrescripciones(){ if(pacienteSeleccionado){ window.location.href = `prescripciones_paciente.php?id=${pacienteSeleccionado}`; } }
function verEnfermedades(){ if(pacienteSeleccionado){ window.location.href = `historial_enfermedades.php?id=${pacienteSeleccionado}`; } }
function verVisitas(){ if(pacienteSeleccionado){ window.location.href = `historial_visitas.php?id=${pacienteSeleccionado}`; } }

// Listeners
$('#btnBuscar').on('click', cargarHistorial);
$('#paciente_id').on('change', function(){ if($(this).val()) cargarHistorial(); });
$('#btnLimpiar').on('click', function(){ $('#paciente_id').val(''); resetearInterfaz(); });

$('#btnRevisionHistorial').on('click', function(e) {
  if(!pacienteSeleccionado) { e.preventDefault(); Swal.fire('Aviso', 'Primero selecciona un paciente', 'warning'); }
});

// Exportes
$('#btnExportar').on('click', exportarAExcel);
$('#btnExportarPDF').on('click', exportarAPdf);

// Delegación para acciones dinámicas
$(document).on('click', '.btn-historial', function() { verDetalles($(this).data('id')); });
$(document).on('click', '.btn-del', function() { eliminarRegistro($(this).data('det')); });

// Editar registro
$(document).on('click', '.btn-edit', function() {
  const $btn = $(this);
  $('#detalle_id_edit').val($btn.data('det'));
  $('#prescripcion_id_edit').val($btn.data('pid'));
  $('#n_serie_edit').val($btn.data('nserie') || '');
  $('#fecha_visita_edit').val($btn.data('fecha'));
  $('#enfermedad_edit').val($btn.data('enf'));
  $('#medicina_id_edit').val($btn.data('mid'));
  $('#cantidad_edit').val($btn.data('cant'));
  $('#dosis_edit').val($btn.data('dosis'));
  $('#paquete_edit').val($btn.data('paquete') || '');
  $('#sucursal_id_edit').val($btn.data('sucursal') || '');
});

// Guardar edición
$('#formEdit').on('submit', function(e){
  e.preventDefault();
  const datos = $(this).serialize();
  $.post('ajax/actualizar_historial_paciente.php', datos)
    .done(r=>{
      try{r=JSON.parse(r)}catch(_){}
      if(r.success){
        $('#modalEdit').modal('hide');
        Swal.fire('Éxito','Registro actualizado correctamente','success');
        cargarHistorial();
      }else{ 
        Swal.fire('Aviso', r.message || 'No se pudo actualizar el registro','warning'); 
      }
    })
    .fail(x=> Swal.fire('Error', x.responseText || 'Fallo al actualizar','error'));
});

// Agregar registro
$('#btnAgregarPrincipal').on('click', function(){
  const pid = $('#paciente_id').val();
  if(!pid){ Swal.fire('Aviso','Selecciona un paciente primero','warning'); return; }
  $('#formAdd')[0].reset();
  $('#paciente_id_add').val(pid);
  $('#modalAdd').modal('show');
});

$('#formAdd').on('submit', function(e){
  e.preventDefault();
  const datos = $(this).serialize();
  $.post('ajax/crear_historial_paciente.php', datos)
    .done(r=>{
      try{r=JSON.parse(r)}catch(_){}
      if(r.success){
        $('#tablaHistorial tbody').prepend(renderFila(r.row));
        $('#modalAdd').modal('hide');
        Swal.fire('Éxito','Registro agregado correctamente','success');
        const count = $('#tablaHistorial tbody tr').length;
        $('#contadorRegistros').text(count + ' registro(s)');
        $('#btnExportar, #btnExportarPDF').show();
      }else{ 
        Swal.fire('Aviso', r.message || 'No se pudo guardar el registro','warning'); 
      }
    })
    .fail(x=> Swal.fire('Error', x.responseText || 'Fallo al guardar','error'));
});

// Init
$(document).ready(function(){});
</script>
</body>
</html>
