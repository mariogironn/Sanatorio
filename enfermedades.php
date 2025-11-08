<?php
// Arranque de sesión y guards mínimos
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__.'/config/auth.php';
require_once __DIR__.'/config/connection.php';

// Autenticación básica
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid<=0){ header('Location: login.php'); exit; }

/* ===== KPIs: totales, activas, inactivas, categorías (si existe la tabla) ===== */
try {
  $total = (int)$con->query("SELECT COUNT(*) FROM enfermedades")->fetchColumn();
  $act   = (int)$con->query("SELECT COUNT(*) FROM enfermedades WHERE estado='activa'")->fetchColumn();
  $ina   = (int)$con->query("SELECT COUNT(*) FROM enfermedades WHERE estado='inactiva'")->fetchColumn();
  try { $cats  = (int)$con->query("SELECT COUNT(*) FROM categorias_enfermedad")->fetchColumn(); } catch(Throwable $e){ $cats=0; }
} catch(Throwable $e) { $total=$act=$ina=$cats=0; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <title>Enfermedades</title>

  <!-- CSS base del sitio + estilos de DataTables -->
  <?php require __DIR__.'/config/site_css_links.php'; ?>
  <?php require __DIR__.'/config/data_tables_css.php'; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

  <style>
    /* Estilos visuales de tabla y KPIs */
    #tablaEnfermedades thead th{ background:linear-gradient(90deg,#6f42c1,#8367d8); color:#fff; border-color:#6f42c1;}
    .dt-buttons{display:none}
    .badge-pill{padding:.35em .6em}
    .no-export{}
    .content-wrapper .content{ padding-top: .75rem !important; }
    .content .container-fluid, .content-wrapper .container-fluid{ overflow: visible; }

    .main-page-title{font-size:2.2rem;font-weight:700;color:#2c3e50;margin:0 0 2rem 0;display:flex;align-items:center}
    .main-page-title i{margin-right:15px;font-size:2rem;color:#6f42c1;background:linear-gradient(135deg,#6f42c1,#8367d8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

    .kpi-container{margin-bottom:2rem}
    .kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem;margin-bottom:2rem}
    .kpi-card{border-radius:12px;padding:1.5rem;box-shadow:0 4px 12px rgba(0,0,0,.15);position:relative;overflow:hidden;transition:.3s;color:#fff;min-height:140px;display:flex;flex-direction:column;justify-content:center}
    .kpi-card:hover{transform:translateY(-5px);box-shadow:0 8px 20px rgba(0,0,0,.25)}
    .kpi-number{font-size:3rem;font-weight:800;margin:0 0 .5rem 0;text-shadow:2px 2px 4px rgba(0,0,0,.3)}
    .kpi-label{font-size:1rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
    .kpi-icon{position:absolute;top:1rem;right:1rem;font-size:3rem;opacity:.2;color:#fff}
    .kpi-total{background:linear-gradient(135deg,#3498db,#2980b9);border-left:6px solid #1f618d}
    .kpi-activas{background:linear-gradient(135deg,#27ae60,#229954);border-left:6px solid #196f3d}
    .kpi-inactivas{background:linear-gradient(135deg,#e74c3c,#cb4335);border-left:6px solid #a93226}
    .kpi-categorias{background:linear-gradient(135deg,#9b59b6,#8e44ad);border-left:6px solid #6c3483}
    .kpi-card::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);transition:left .5s}
    .kpi-card:hover::before{left:100%}
    @media (max-width:1024px){.kpi-grid{grid-template-columns:repeat(2,1fr)} .main-page-title{font-size:2rem}}
    @media (max-width:768px){.kpi-grid{grid-template-columns:repeat(2,1fr)} .kpi-card{min-height:120px} .kpi-number{font-size:2.5rem} .kpi-icon{font-size:2.5rem;top:.75rem;right:.75rem} .main-page-title{font-size:1.8rem;margin-bottom:1.5rem}}
    @media (max-width:480px){.kpi-grid{grid-template-columns:1fr} .main-page-title{font-size:1.6rem}}

    .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;padding:1rem 0;border-bottom:2px solid #e9ecef}
    .page-title{font-size:1.8rem;font-weight:600;color:#2c3e50;margin:0;display:flex;align-items:center}
    .page-title i{margin-right:10px;color:#6f42c1}
    .content-wrapper .content{padding:20px !important}
    .container-fluid{padding:0 15px}
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php require __DIR__.'/config/header.php'; ?>
  <?php require __DIR__.'/config/sidebar.php'; ?>

  <div class="content-wrapper">
    <?php require __DIR__.'/config/top_bar.php'; ?>

    <section class="content">
      <div class="container-fluid">

        <!-- Título -->
        <h1 class="main-page-title">
          <i class="fas fa-heartbeat"></i> Enfermedades
        </h1>

        <!-- KPIs -->
        <div class="kpi-container">
          <div class="kpi-grid">
            <div class="kpi-card kpi-total">
              <div class="kpi-number" id="kpiTotal"><?= (int)$total ?></div>
              <div class="kpi-label">Total de enfermedades</div>
              <i class="fas fa-list-ul kpi-icon"></i>
            </div>
            <div class="kpi-card kpi-activas">
              <div class="kpi-number" id="kpiAct"><?= (int)$act ?></div>
              <div class="kpi-label">Activas</div>
              <i class="fas fa-check-circle kpi-icon"></i>
            </div>
            <div class="kpi-card kpi-inactivas">
              <div class="kpi-number" id="kpiIna"><?= (int)$ina ?></div>
              <div class="kpi-label">Inactivas</div>
              <i class="fas fa-ban kpi-icon"></i>
            </div>
            <div class="kpi-card kpi-categorias">
              <div class="kpi-number" id="kpiCat"><?= (int)$cats ?></div>
              <div class="kpi-label">Categorías</div>
              <i class="fas fa-stream kpi-icon"></i>
            </div>
          </div>
        </div>

        <!-- Card principal con filtros + tabla -->
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="mb-0"><i class="fas fa-list-ul mr-2"></i>Lista de Enfermedades</h3>
            <div>
              <button class="btn btn-light border" id="btnPrint"><i class="fas fa-print mr-1"></i> Imprimir</button>
              <button class="btn btn-danger" id="btnPDF"><i class="fas fa-file-pdf mr-1"></i> Exportar PDF</button>
            </div>
          </div>

          <div class="card-body">
            <!-- Filtros -->
            <div class="form-row align-items-center mb-3">
              <div class="col-md-3 mb-2">
                <select id="filtroEstado" class="form-control">
                  <option value="">Todos los estados</option>
                  <option value="Activo">Activos</option>
                  <option value="Inactivo">Inactivos</option>
                </select>
              </div>
              <div class="col-md-3 mb-2">
                <select id="filtroCategoria" class="form-control">
                  <option value="">Todas las categorías</option>
                </select>
              </div>
              <div class="col-md-3 mb-2">
                <input id="filtroNombre" type="text" class="form-control" placeholder="Buscar por nombre o CIE-10">
              </div>
              <div class="col-md-3 mb-2 d-flex">
                <button id="btnAplicar" class="btn btn-primary mr-2"><i class="fas fa-filter mr-1"></i>Aplicar</button>
                <button id="btnLimpiar" class="btn btn-secondary mr-2"><i class="fas fa-eraser mr-1"></i>Limpiar</button>
                <button class="btn btn-success ml-auto" id="btnNueva"><i class="fas fa-plus mr-1"></i>Nueva Enfermedad</button>
              </div>
            </div>

            <!-- Tabla -->
            <div class="table-responsive">
              <table id="tablaEnfermedades" class="table table-bordered table-hover w-100">
                <thead>
                  <tr>
                    <th style="width:5%">#</th>
                    <th style="width:22%">Nombre</th>
                    <th style="width:10%">CIE-10</th>
                    <th style="width:13%">Categoría</th>
                    <th style="width:15%">Banderas</th>
                    <th>Descripción</th>
                    <th style="width:8%">Estado</th>
                    <th class="no-export" style="width:13%">Acciones</th>
                  </tr>
                </thead>
              </table>
            </div>

          </div>
        </div>

      </div>
    </section>
  </div>

  <!-- Modales (alta/edición, categoría, detalle, pacientes) -->
  <div class="modal fade" id="modalEnfermedad" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <form class="modal-content" id="formEnfermedad">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="ttlEnf"><i class="fas fa-plus mr-2"></i> Nueva Enfermedad</h5>
          <button type="button" class="close" data-dismiss="modal"><span class="text-white">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="id">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Nombre *</label>
              <input type="text" class="form-control" name="nombre" id="nombre" required>
            </div>
            <div class="form-group col-md-3">
              <label>Código CIE-10</label>
              <input type="text" class="form-control" name="cie10" id="cie10" placeholder="Ej: J10.1">
            </div>
            <div class="form-group col-md-3">
              <label>Categoría *</label>
              <div class="input-group">
                <select class="form-control" name="categoria_id" id="categoria_id" required></select>
                <div class="input-group-append">
                  <button class="btn btn-secondary" type="button" id="btnAddCat" title="Nueva categoría">
                    <i class="fas fa-plus"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Estado</label>
              <select class="form-control" name="estado" id="estado">
                <option value="activa">Activo</option>
                <option value="inactiva">Inactivo</option>
              </select>
            </div>
            <div class="form-group col-md-8">
              <label>Características</label><br>
              <div id="wrapBanderas" class="pt-1"></div>
            </div>
          </div>

          <div class="form-group">
            <label>Descripción *</label>
            <textarea class="form-control" name="descripcion" id="descripcion" rows="3" required placeholder="Descripción breve…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="formCategoria">
        <div class="modal-header">
          <h5 class="modal-title">Nueva categoría</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="text" class="form-control" id="nombre_categoria" placeholder="Ej: Neurológico" required>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" type="submit">Agregar</button>
          <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-eye mr-2"></i>Detalle de Enfermedad</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-2" style="font-size:26px;font-weight:600" id="det_nombre">—</div>
          <div class="text-center text-primary small mb-2" id="det_cie">CIE-10: —</div>
          <p><b>Categoría:</b> <span id="det_categoria">—</span></p>
          <p><b>Estado:</b> <span id="det_estado" class="badge badge-secondary">—</span></p>
          <p><b>Banderas:</b> <span id="det_banderas">—</span></p>
          <hr>
          <p><b>Descripción:</b></p>
          <div id="det_desc">—</div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Cerrar</button></div>
      </div>
    </div>
  </div>

  <!-- El modalPacientes permanece por si lo usas en otra vista, pero ya no hay botón que lo abra aquí -->
  <div class="modal fade" id="modalPacientes" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title"><i class="fas fa-users mr-2"></i>Pacientes con: <span id="lblEnfermedad"></span></h5>
          <button type="button" class="close" data-dismiss="modal"><span class="text-white">&times;</span></button>
        </div>
        <div class="modal-body">
          <ul class="list-group mb-3" id="listaPacientes"></ul>
          <div class="alert alert-warning small mb-0">Se listan los últimos 5 diagnósticos.</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
          <a id="btnIrDiagnosticos" class="btn btn-primary">
            <i class="fas fa-external-link-alt mr-1"></i> Ir a Módulo Diagnósticos
          </a>
        </div>
      </div>
    </div>
  </div>

  <?php require __DIR__.'/config/footer.php'; ?>
</div>

<!-- JS base + DataTables + SweetAlert -->
<?php require __DIR__.'/config/site_js_links.php'; ?>
<?php require __DIR__.'/config/data_tables_js.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
/* Utilidades de render en tabla */
let dt;
const pill = t => `<span class="badge badge-info badge-pill mr-1">${t}</span>`;
const badgeEstado = v => `<span class="badge badge-${v==='activa'?'success':'secondary'}">${v==='activa'?'Activo':'Inactivo'}</span>`;

/* Carga de catálogos para filtros y formularios */
function cargarCatalogos(){
  return $.getJSON('ajax/enfermedades_listar.php', { meta:'catalogos' }, function(r){
    const selF = $('#filtroCategoria').empty().append('<option value="">Todas las categorías</option>');
    const selM = $('#categoria_id').empty();
    (r.categorias||[]).forEach(c=>{
      selF.append(`<option value="${c.nombre}">${c.nombre}</option>`);
      selM.append(`<option value="${c.id}">${c.nombre}</option>`);
    });
    const wrap = $('#wrapBanderas').empty();
    (r.banderas||[]).forEach(b=>{
      wrap.append(`<div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" name="banderas[]" id="ban_${b.id}" value="${b.id}">
        <label class="form-check-label" for="ban_${b.id}">${b.nombre}</label>
      </div>`);
    });
  });
}

$(function(){
  cargarCatalogos();

  /* Inicializa DataTable
     NOTA: Se retiró el botón azul "ver pacientes" de Acciones */
  dt = $('#tablaEnfermedades').DataTable({
    ajax: 'ajax/enfermedades_listar.php',
    columns: [
      { data:'rownum' },
      { data:'nombre' },
      { data:'cie10' },
      { data:'categoria' },
      { data:'banderas', render:d=> (d||[]).map(pill).join('') },
      { data:'descripcion' },
      { data:'estado', render:d=>badgeEstado(d) },
      { data:null, orderable:false, className:'no-export',
        render:(r)=>`
          <button class="btn btn-sm btn-secondary ver-detalle"  title="Ver Detalle"><i class="fas fa-eye"></i></button>
          <button class="btn btn-sm btn-outline-success editar"  title="Editar"><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-outline-danger eliminar"  title="Eliminar"><i class="fas fa-trash"></i></button>`
      }
    ],
    order:[[0,'asc']],
    dom:'Bfrtip',
    buttons:[
      {extend:'pdfHtml5',  className:'buttons-pdf',  title:'Lista de Enfermedades', exportOptions:{columns:':not(.no-export)'}},
      {extend:'print',     className:'buttons-print',title:'Lista de Enfermedades', exportOptions:{columns:':not(.no-export)'}}
    ],
    language:{ url:'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' }
  });

  // Botones globales de exportación
  $('#btnPrint').on('click', ()=> dt.button('.buttons-print').trigger() );
  $('#btnPDF').on('click',   ()=> dt.button('.buttons-pdf').trigger() );

  // Filtros client-side sobre columnas
  $('#btnAplicar').on('click', ()=>{
    const q = $('#filtroNombre').val();
    const cat = $('#filtroCategoria').val();
    const est = $('#filtroEstado').val();
    dt.column(1).search(q);
    dt.column(2).search(q);
    dt.column(3).search(cat);
    dt.column(6).search(est);
    dt.draw();
  });
  $('#btnLimpiar').on('click', ()=>{
    $('#filtroNombre').val(''); $('#filtroCategoria').val(''); $('#filtroEstado').val('');
    dt.columns().search(''); dt.draw();
  });

  // Alta rápida
  $('#btnNueva').on('click', ()=>{
    $('#ttlEnf').html('<i class="fas fa-plus mr-2"></i> Nueva Enfermedad');
    $('#formEnfermedad')[0].reset(); $('#id').val('');
    $('input[name="banderas[]"]').prop('checked', false);
    $('#modalEnfermedad').modal('show');
  });

  // Crear categoría desde modal
  $('#btnAddCat').on('click', ()=> $('#modalCategoria').modal('show'));
  $('#formCategoria').on('submit', function(e){
    e.preventDefault();
    const nombre = $('#nombre_categoria').val().trim();
    if(!nombre) return;
    $.post('ajax/categoria_guardar.php', { nombre }, function(r){
      if(r.success){
        $('#modalCategoria').modal('hide'); $('#nombre_categoria').val('');
        cargarCatalogos().then(()=> $('#categoria_id').val(r.id));
        $('#kpiCat').text(parseInt($('#kpiCat').text()||'0',10)+1);
      }else{ alert(r.message||'Error'); }
    }, 'json');
  });

  // Ver detalle
  $('#tablaEnfermedades').on('click','.ver-detalle', function(){
    const d = dt.row($(this).closest('tr')).data();
    $('#det_nombre').text(d.nombre||'—');
    $('#det_cie').text('CIE-10: ' + (d.cie10||'—'));
    $('#det_categoria').text(d.categoria||'—');
    $('#det_estado').removeClass('badge-success badge-secondary')
                    .addClass(d.estado==='activa'?'badge-success':'badge-secondary')
                    .text(d.estado==='activa'?'Activo':'Inactivo');
    $('#det_banderas').html((d.banderas||[]).map(pill).join('')||'—');
    $('#det_desc').text(d.descripcion||'—');
    $('#modalDetalle').modal('show');
  });

  // Editar
  $('#tablaEnfermedades').on('click','.editar', function(){
    const d = dt.row($(this).closest('tr')).data();
    $('#ttlEnf').html('<i class="fas fa-plus mr-2"></i> Editar Enfermedad');
    $('#id').val(d.id); $('#nombre').val(d.nombre); $('#cie10').val(d.cie10);
    $('#descripcion').val(d.descripcion); $('#estado').val(d.estado);
    cargarCatalogos().then(()=>{
      $('#categoria_id').val(d.categoria_id||'');
      $('input[name="banderas[]"]').prop('checked', false);
      (d.banderas_ids||[]).forEach(id=> $('#ban_'+id).prop('checked', true));
    });
    $('#modalEnfermedad').modal('show');
  });

  // Guardar (crear/editar)
  $('#formEnfermedad').on('submit', function(e){
    e.preventDefault();
    $.post('ajax/enfermedad_guardar.php', $(this).serialize(), function(r){
      if(r.success){
        $('#modalEnfermedad').modal('hide');
        dt.ajax.reload(null,false);
        // Refrescar KPIs rápidos
        $.getJSON('ajax/kpis_enfermedades.php', function(k){
          if(k){ $('#kpiTotal').text(k.total); $('#kpiAct').text(k.act); $('#kpiIna').text(k.ina); }
        });
      } else { alert(r.message||'Error'); }
    }, 'json');
  });

  // Eliminar
  $('#tablaEnfermedades').on('click','.eliminar', function(){
    const d = dt.row($(this).closest('tr')).data();
    Swal.fire({
      icon:'warning', title:'¿Eliminar?', text:`Se eliminará "${d.nombre}".`,
      showCancelButton:true, confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancel'
    }).then(res=>{
      if(res.isConfirmed){
        $.post('ajax/enfermedad_eliminar.php', {id:d.id}, function(r){
          if(r.success){
            dt.ajax.reload(null,false);
            $.getJSON('ajax/kpis_enfermedades.php', function(k){
              if(k){ $('#kpiTotal').text(k.total); $('#kpiAct').text(k.act); $('#kpiIna').text(k.ina); }
            });
          } else { Swal.fire('Error', r.message||'No se pudo eliminar', 'error'); }
        }, 'json');
      }
    });
  });

  /* IMPORTANTE:
     Se eliminó el handler del botón .ver-pacientes porque ya no existe en la columna Acciones. */
});
</script>
</body>
</html>
