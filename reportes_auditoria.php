<?php
// reportes_auditoria.php
// Subproceso: Reportes » Auditoría (KPIs + Filtros + Tabla + Modal personalizado + Purga)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/connection.php';

if (is_file(__DIR__ . '/config/auth.php')) {
  require_once __DIR__ . '/config/auth.php';
  if (function_exists('require_permission')) require_permission('reportes.auditoria');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Auditoría</title>
  <style>
    /* ===== KPIs ===== */
    .kpi-card { min-height: 100px; }
    .kpi-value { font-size: 28px; font-weight: 700; line-height: 1; }
    .kpi-label { font-size: 13px; text-transform: uppercase; letter-spacing: .5px; }
    .kpi-icon { font-size: 28px; opacity: .75; }
    .badge-state { font-weight: 700; font-size: 11px; text-transform: uppercase; }
    .td-nowrap { white-space: nowrap; }

    /* ===== Colores suaves (chips) ===== */
    .chip { display:inline-block; padding:.25rem .5rem; border-radius:999px; font-size:12px; font-weight:600; line-height:1; }
    .chip-green  { background:#e9f9ee; color:#15803d; }  /* crear / activo */
    .chip-red    { background:#fde7ea; color:#b4232c; }  /* eliminar */
    .chip-blue   { background:#e6f0ff; color:#1d4ed8; }  /* actualizar */
    .chip-indigo { background:#ede9fe; color:#5b21b6; }  /* generar */
    .chip-orange { background:#fff1e6; color:#b45309; }  /* inactivo / desactivar */
    .chip-purple { background:#f3e8ff; color:#7c3aed; }  /* rol */

    /* ===== Tabla (look de la segunda imagen) ===== */
    .card-table { border:1px solid #e5e7eb; border-radius:.5rem; }
    .card-table .dataTables_wrapper .dataTables_paginate .paginate_button { padding:.25rem .6rem!important; border-radius:.375rem!important; }
    .card-table .dataTables_wrapper .dataTables_length { font-size:14px; }
    .card-table .dataTables_wrapper .dataTables_length select { display:inline-block; width:auto; margin:0 .25rem; }
    #tblAuditoria { border-collapse:separate; border-spacing:0; }
    #tblAuditoria thead th { background:#f8fafc; color:#334155; border-bottom:1px solid #e5e7eb; }
    #tblAuditoria tbody td { border-top:1px solid #f1f5f9; }
    #tblAuditoria tbody tr:first-child td { border-top:0; }
    #tblAuditoria .btn-view { background:#2563eb; color:#fff; border:none; border-radius:.375rem; padding:.25rem .6rem; font-size:12px; }
    #tblAuditoria .btn-view i { margin-right:.25rem; }

    /* ===== Modal (de versiones previas, se mantiene) ===== */
    :root{
      --panel-bg:#2e3a46;
      --panel-text:#f7f9fc;
      --muted:#6c757d;
      --table-border:#e5e7eb;
      --shadow:0 10px 25px rgba(0,0,0,.15);
    }
    .aud-modal-backdrop{ position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:center; justify-content:center; padding:16px; z-index:9999; }
    .aud-modal-card{ width:min(920px,100%); background:#fff; border-radius:12px; overflow:hidden; box-shadow:var(--shadow); display:flex; flex-direction:column; }
    .aud-modal-header{ background:var(--panel-bg); color:var(--panel-text); display:flex; align-items:center; justify-content:space-between; padding:14px 16px; }
    .aud-modal-title{ font-size:16px; font-weight:700 }
    .aud-header-actions{ display:flex; gap:8px }
    .aud-btn{ display:inline-flex; align-items:center; gap:8px; background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; border-radius:6px; padding:6px 10px; font-size:13px; cursor:pointer; }
    .aud-badge{ display:inline-block; padding:.25rem .5rem; border-radius:6px; font-size:12px; font-weight:600; background:#dc3545; color:#fff; }
    .aud-modal-body{ padding:16px }
    .aud-kv{ display:grid; grid-template-columns:160px 1fr; gap:8px 16px; margin:8px 0; }
    .aud-kv .label{ color:#6b7280; font-size:14px }
    .aud-kv .value{ font-weight:600 }
    .aud-divider{ height:1px; background:#e5e7eb; margin:12px 0 }
    .aud-section-title{ color:#374151; font-weight:700; margin:8px 0 6px }
    .aud-table{ width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--table-border); border-radius:8px; overflow:hidden }
    .aud-table th,.aud-table td{ padding:10px 12px; border-bottom:1px solid var(--table-border); font-size:14px }
    .aud-table th{ background:#f8fafc; text-align:left; color:#334155; width:220px }
    .aud-modal-footer{ padding:12px 16px; display:flex; justify-content:flex-end; gap:8px }
    .aud-muted{ color:#6c757d; font-style:italic }
    @media (max-width:640px){ .aud-kv{ grid-template-columns:1fr } .aud-table th{ width:42% } .aud-modal-header{ padding:12px } .aud-modal-body{ padding:12px } }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; ?>
  <?php include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-clipboard-list"></i> Auditoría</h1>
        <button type="button" id="btnPurgar" class="btn btn-danger btn-sm">
          <i class="fas fa-broom"></i> Purgar…
        </button>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">

        <!-- KPIs -->
        <div class="row">
          <div class="col-sm-6 col-md-3 mb-3">
            <div class="card card-outline card-primary kpi-card rounded-0 shadow">
              <div class="card-body d-flex align-items-center justify-content-between">
                <div><div class="kpi-value" id="kpiTotal">0</div><div class="kpi-label text-muted">Registros de Auditoría</div></div>
                <i class="fas fa-clipboard-list kpi-icon text-primary"></i>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3 mb-3">
            <div class="card card-outline card-success kpi-card rounded-0 shadow">
              <div class="card-body d-flex align-items-center justify-content-between">
                <div><div class="kpi-value" id="kpiCreates">0</div><div class="kpi-label text-muted">Creaciones</div></div>
                <i class="fas fa-plus-circle kpi-icon text-success"></i>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3 mb-3">
            <div class="card card-outline card-info kpi-card rounded-0 shadow">
              <div class="card-body d-flex align-items-center justify-content-between">
                <div><div class="kpi-value" id="kpiUpdates">0</div><div class="kpi-label text-muted">Actualizaciones</div></div>
                <i class="fas fa-edit kpi-icon text-info"></i>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3 mb-3">
            <div class="card card-outline card-danger kpi-card rounded-0 shadow">
              <div class="card-body d-flex align-items-center justify-content-between">
                <div><div class="kpi-value" id="kpiDeletes">0</div><div class="kpi-label text-muted">Eliminaciones</div></div>
                <i class="fas fa-trash-alt kpi-icon text-danger"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Filtros -->
        <div class="card card-outline card-primary rounded-0 shadow">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter"></i> Filtros</h3>
            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
          </div>
          <div class="card-body">
            <form id="formFiltros" class="form-row">
              <div class="form-group col-lg-3 col-md-4">
                <label>Módulo</label>
                <select name="modulo" id="f_modulo" class="form-control form-control-sm">
                  <option value="">Todos los módulos</option>
                </select>
              </div>
              <div class="form-group col-lg-3 col-md-4">
                <label>Acción</label>
                <select name="accion" id="f_accion" class="form-control form-control-sm">
                  <option value="">Todas las acciones</option>
                </select>
              </div>
              <div class="form-group col-lg-3 col-md-4">
                <label>Usuario</label>
                <select name="usuario_id" id="f_usuario" class="form-control form-control-sm">
                  <option value="">Todos los usuarios</option>
                </select>
              </div>
              <div class="form-group col-lg-3 col-md-4">
                <label>Fecha</label>
                <input type="date" name="fecha" id="f_fecha" class="form-control form-control-sm">
              </div>
              <div class="form-group col-12 text-right">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Aplicar filtros</button>
                <button type="button" id="btnLimpiar" class="btn btn-secondary btn-sm">Limpiar</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Tabla -->
        <div class="card card-outline card-info rounded-0 shadow card-table">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0"><i class="fas fa-list"></i> Registros de Auditoría</h3>
          </div>
          <div class="card-body">
            <div class="row table-responsive">
              <table id="tblAuditoria" class="table w-100">
                <thead>
                  <tr>
                    <th>No. serie</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Módulo</th>
                    <th>Acción</th>
                    <th>Registro ID</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Detalles</th>
                  </tr>
                </thead>
              </table>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
  <?php include './config/site_js_links.php'; ?>
  <?php include './config/data_tables_js.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

  <!-- ===== MODAL (se mantiene) ===== -->
  <div class="aud-modal-backdrop" id="modalAuditoria" role="dialog" aria-modal="true" aria-labelledby="audTitulo">
    <div class="aud-modal-card" id="audCard">
      <div class="aud-modal-header">
        <div class="aud-modal-title" id="audTitulo">Detalles de Registro de Auditoría</div>
        <div class="aud-header-actions no-print">
          <button class="aud-btn" id="audBtnPdf" title="Exportar PDF"><i class="fas fa-file-pdf"></i> PDF</button>
        </div>
      </div>
      <div class="aud-modal-body" id="audContenido">
        <div class="aud-kv"><div class="label">Fecha y hora:</div><div class="value" id="m-fecha">—</div></div>
        <div class="aud-kv"><div class="label">Módulo:</div><div class="value" id="m-modulo">—</div></div>
        <div class="aud-kv"><div class="label">Usuario:</div><div class="value" id="m-usuario">—</div></div>
        <div class="aud-kv"><div class="label">Acción:</div>
          <div class="value"> <span id="m-accion-text">—</span> <span id="m-accion-badge" class="aud-badge" style="display:none">Registro eliminado</span> </div>
        </div>
        <div class="aud-divider"></div>
        <div class="aud-section-title" id="titulo-valores">Valores</div>
        <table class="aud-table" aria-label="Valores"><tbody id="kv-body"></tbody></table>
      </div>
      <div class="aud-modal-footer no-print">
        <button class="aud-btn" id="audBtnCerrar"><i class="fas fa-xmark"></i> Cerrar</button>
      </div>
    </div>
  </div>

  <script>
    if (typeof showMenuSelected === 'function') {
      showMenuSelected("#mnu_reports", "#mi_reports_auditoria");
    }

    // ===== Helpers y colores =====
    const ACC_ICON  = { CREATE:'fa-plus-circle', UPDATE:'fa-edit', DELETE:'fa-trash-alt', ACTIVAR:'fa-toggle-on', DESACTIVAR:'fa-toggle-off', GENERAR:'fa-file-alt' };
    const ACC_LABEL = { CREATE:'Crear', UPDATE:'Actualizar', DELETE:'Eliminar', ACTIVAR:'Activar', DESACTIVAR:'Desactivar', GENERAR:'Generar' };

    function deriveEstadoFront(estado, accion){
      if (estado) return String(estado).toLowerCase();
      const a = String(accion||'').toUpperCase();
      if (a==='DELETE' || a==='DESACTIVAR') return 'inactivo';
      if (a==='CREATE' || a==='UPDATE' || a==='ACTIVAR') return 'activo';
      return '';
    }

    function getFilters() {
      const o = {};
      $('#formFiltros').serializeArray().forEach(it => o[it.name] = it.value);
      if (o.fecha) { o.desde = o.fecha; o.hasta = o.fecha; }
      return o;
    }

    function cargarKPIs(){
      $.getJSON('ajax/resumen_auditoria.php', getFilters(), function(r){
        $('#kpiTotal').text(r.total ?? 0);
        $('#kpiCreates').text(r.creates ?? 0);
        $('#kpiUpdates').text(r.updates ?? 0);
        $('#kpiDeletes').text(r.deletes ?? 0);
      });
    }

    function cargarOpciones(){
      $.getJSON('ajax/opciones_auditoria.php', function(r){
        const $m = $('#f_modulo').empty().append('<option value="">Todos los módulos</option>');
        (r.modulos || []).forEach(v => $m.append('<option>'+v+'</option>'));
        const $a = $('#f_accion').empty().append('<option value="">Todas las acciones</option>');
        (r.acciones || []).forEach(v => $a.append('<option>'+v+'</option>'));
        const $u = $('#f_usuario').empty().append('<option value="">Todos los usuarios</option>');
        (r.usuarios || []).forEach(o => $u.append('<option value="'+o.id+'">'+o.nombre+'</option>'));
      });
    }

    // ===== DataTable =====
    let dt;
    $(document).ready(function () {
      cargarOpciones();

      dt = $('#tblAuditoria').DataTable({
        serverSide:true, processing:true, responsive:true, searching:false, /* sin barra de búsqueda */
        ajax:{ url:'ajax/listar_auditoria.php', type:'GET', data:d=>Object.assign(d, getFilters()) },
        order:[[1,'desc']], pageLength:7,
        /* solo tabla + footer con length y paginación */
        dom: 't<"d-flex justify-content-between align-items-center mt-3"<"dt-length"l><"dt-paging"p>>',
        language:{
          decimal:"", emptyTable:"No hay datos disponibles en la tabla",
          info:"Mostrando _START_ a _END_ de _TOTAL_ registros",
          infoEmpty:"Mostrando 0 a 0 de 0 registros",
          infoFiltered:"(filtrado de _MAX_ registros totales)",
          lengthMenu:"Registros por página: _MENU_",
          loadingRecords:"Cargando...", processing:"Procesando...",
          zeroRecords:"No se encontraron registros coincidentes",
          paginate:{ first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" }
        },
        columns:[
          { data:null, className:'td-nowrap text-center', orderable:false, searchable:false,
            render:(d,t,r,meta)=> meta.row + meta.settings._iDisplayStart + 1 },

          { data:'creado_en', className:'td-nowrap' },
          { data:'usuario',   className:'td-nowrap' },
          { data:'modulo',    className:'td-nowrap' },

          { data:'accion', className:'td-nowrap',
            render:function(d){
              const k = (d||'').toUpperCase();
              const label = ACC_LABEL[k] || k;
              const cls = (k==='CREATE')?'chip chip-green'
                        :(k==='DELETE')?'chip chip-red'
                        :(k==='UPDATE')?'chip chip-blue'
                        :(k==='DESACTIVAR')?'chip chip-orange'
                        :(k==='ACTIVAR')?'chip chip-green'
                        :(k==='GENERAR')?'chip chip-indigo':'chip';
              return `<span class="${cls}">${label}</span>`;
            } },

          { data:'id_registro', className:'td-nowrap' },

          { data:'rol', className:'td-nowrap',
            render:(d)=> d ? `<span class="chip chip-purple">${d}</span>` : '—' },

          { data:null, className:'td-nowrap',
            render:function(row){
              const est = deriveEstadoFront(row.estado_resultante, row.accion);
              if (!est) return '—';
              const txt = (est==='activo')?'Activo':'Inactivo';
              const cls = (est==='activo')?'chip chip-green':'chip chip-orange';
              return `<span class="${cls}">${txt}</span>`;
            } },

          { data:null, orderable:false, searchable:false,
            render:r => `<button type="button" class="btn-view ver-detalle" title="Ver" data-id="${r.id}"><i class="fas fa-eye"></i>Ver</button>` }
        ],
        drawCallback:function(){ cargarKPIs(); }
      });

      $('#formFiltros').on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });
      $('#btnLimpiar').on('click', function(){
        const f=document.getElementById('formFiltros'); if (f) f.reset();
        $('#f_modulo,#f_accion,#f_usuario').val(''); $('#f_fecha').val('');
        dt.ajax.reload();
      });

      // Abrir modal DETALLE
      $('#tblAuditoria').on('click', '.ver-detalle', function(){
        const id = $(this).data('id');
        $.getJSON('ajax/ver_auditoria_detalle.php', { id }, function(r){
          openAuditoriaModalFromResponse(r);
        }).fail(function(){ Swal.fire('Error','No se pudo cargar el detalle','error'); });
      });

      // PURGAR
      $('#btnPurgar').on('click', function(){
        Swal.fire({
          title: 'Purgar auditoría',
          html: `<div class="text-left">
            <p>Elige una fecha límite. Se eliminarán los registros <b>hasta</b> esa fecha (incluida).</p>
            <label for="purga_fecha" style="font-weight:600;">Fecha hasta*</label>
            <input id="purga_fecha" type="date" class="swal2-input" style="width:auto">
          </div>`,
          icon: 'warning', showCancelButton:true, confirmButtonText:'Purgar', cancelButtonText:'Cancelar',
          reverseButtons:true, showLoaderOnConfirm:true, allowOutsideClick: () => !Swal.isLoading(),
          preConfirm: () => {
            const fecha = (document.getElementById('purga_fecha').value || '').trim();
            if (!fecha) { Swal.showValidationMessage('Debes seleccionar una fecha.'); return false; }
            return $.post('ajax/purgar_auditoria.php', { fecha_hasta: fecha })
              .then(r => { try { r = JSON.parse(r); } catch(_) { throw new Error('Respuesta inválida.'); }
                           if (!r.ok) throw new Error(r.msg || 'No se pudo purgar.'); return r; })
              .catch(err => { Swal.showValidationMessage(err.message || 'Error al purgar.'); return false; });
          }
        }).then(res => { if (res.isConfirmed) Swal.fire('Listo', res.value.msg || 'Purgado correctamente.', 'success').then(()=> dt.ajax.reload()); });
      });
    });

    /* ===========================
       Modal + PDF (se mantiene)
    ============================ */
    const $audModal = document.getElementById('modalAuditoria');
    const $audCard  = document.getElementById('audCard');
    const $audBtnCerrar = document.getElementById('audBtnCerrar');
    const $audBtnPdf = document.getElementById('audBtnPdf');

    $audModal.addEventListener('click', (e)=>{ if(!$audCard.contains(e.target)) closeAuditoriaModal(); });
    if ($audBtnCerrar) $audBtnCerrar.addEventListener('click', closeAuditoriaModal);
    document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && $audModal.style.display==='flex') closeAuditoriaModal(); });
    function openAuditoriaModal(){ $audModal.style.display='flex'; document.body.style.overflow='hidden'; }
    function closeAuditoriaModal(){ $audModal.style.display='none'; document.body.style.overflow=''; }

    const SKIP_KEYS = new Set(['created_by','updated_by','created_at','updated_at','creado_en','actualizado_en','createdon','updatedon','created_on','updated_on','fecha_creacion','fecha_actualizacion','deleted_at']);
    const LABEL_MAP = {'id':'ID','id_paciente':'ID paciente','id_medicina':'ID medicina','fecha_nacimiento':'Fecha de nacimiento','genero':'Género','dpi':'DPI','telefono':'Teléfono','nombre':'Nombre','direccion':'Dirección','estado':'Estado'};
    function prettyLabel(k){ const kl=String(k||'').toLowerCase(); if(LABEL_MAP[kl]) return LABEL_MAP[kl];
      return (String(k||'').replace(/_/g,' ').replace(/\s+/g,' ').trim()).toLowerCase().replace(/(^|[\s])\S/g,s=>s.toUpperCase()); }
    function rowHTML(label, value, isRol=false){
      const v=(value===undefined||value===null||value==='') ? (isRol?'<span class="aud-muted">No especificado</span>':'—') : String(value);
      return `<tr><th>${label}</th><td>${v}</td></tr>`;
    }
    function fillDistribucionRows(tbody, src, fallbackEstado){
      const rows=[];
      rows.push(rowHTML('Id Distribución', src.id_distribucion ?? src.idDistribucion ?? src.distribucion_id ?? src.id));
      rows.push(rowHTML('Id Usuario',      src.id_usuario ?? src.idUsuario));
      rows.push(rowHTML('Id Sucursal',     src.id_sucursal ?? src.idSucursal));
      rows.push(rowHTML('Fecha',           src.fecha));
      rows.push(rowHTML('Hora Entrada',    src.hora_entrada ?? src.horaEntrada));
      rows.push(rowHTML('Hora Salida',     src.hora_salida ?? src.horaSalida));
      rows.push(rowHTML('Cupos',           src.cupos));
      rows.push(rowHTML('Id Rol',          src.id_rol ?? src.idRol, true));
      rows.push(rowHTML('Estado',          src.estado ?? fallbackEstado));
      tbody.innerHTML = rows.join('');
    }
    function fillGenericRows(tbody, src){
      const rows=[];
      Object.keys(src||{}).forEach(k=>{
        if (SKIP_KEYS.has(String(k).toLowerCase())) return;
        const val = (typeof src[k] === 'object') ? JSON.stringify(src[k]) : src[k];
        rows.push(rowHTML(prettyLabel(k), val));
      });
      tbody.innerHTML = rows.length ? rows.join('') : `<tr><td colspan="2"><span class="aud-muted">Sin datos.</span></td></tr>`;
    }

    let lastDetalle=null;

    function openAuditoriaModalFromResponse(r){
      lastDetalle=r;
      setText('m-fecha',   r.creado_en || r.fecha_hora || r.datetime || '—');
      setText('m-modulo',  r.modulo || '—');
      setText('m-usuario', r.usuario || r.usuario_nombre || '—');

      const accionKey=String(r.accion||'').toUpperCase();
      const map={CREATE:'Creación', UPDATE:'Actualización', DELETE:'Eliminación', ACTIVAR:'Activación', DESACTIVAR:'Desactivación'};
      setText('m-accion-text', r.accion_fmt || map[accionKey] || (r.accion || '—'));
      document.getElementById('m-accion-badge').style.display=(accionKey==='DELETE')?'inline-block':'none';

      let src={};
      if (accionKey==='DELETE'){ src=r.previos||r.valores_previos||r.antes_json||{}; document.getElementById('titulo-valores').textContent='Valores previos'; }
      else if (accionKey==='CREATE'){ src=r.despues_json||r.despues||r.after||{}; document.getElementById('titulo-valores').textContent='Valores actuales'; }
      else { src=r.previos||r.valores_previos||r.antes_json||{}; if(!Object.keys(src).length) src=r.despues_json||{}; document.getElementById('titulo-valores').textContent='Valores'; }

      const tbody=document.getElementById('kv-body');
      const modulo=String(r.modulo||'').toLowerCase();
      const fallbackEstado=r.estado_resultante ?? r.estado ?? '';
      if (modulo==='distribucion_personal') fillDistribucionRows(tbody, src, fallbackEstado);
      else fillGenericRows(tbody, src);

      openAuditoriaModal();
    }
    function setText(id,val){ const el=document.getElementById(id); if(el) el.textContent=(val ?? '—'); }

    // ===== PDF del modal (pdfMake, ya implementado previamente) =====
    function ensurePdfMake(cb){
      if (window.pdfMake && window.pdfMake.vfs) { cb(); return; }
      const load = src => new Promise((res,rej)=>{ const s=document.createElement('script'); s.src=src; s.onload=res; s.onerror=()=>rej(src); document.head.appendChild(s); });
      Promise.resolve()
        .then(()=> load('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js'))
        .then(()=> load('https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js'))
        .then(cb)
        .catch(()=> (window.Swal ? Swal.fire('Error','No se pudo cargar pdfMake.','error') : alert('No se pudo cargar pdfMake.')));
    }
    const SKIP_KEYS2 = SKIP_KEYS;
    function prettyLabel2(k){ return prettyLabel(k); }
    function makeKVTableBodyForPdf(src, modulo, fallbackEstado) {
      const body=[], push=(label,val,italic=false)=> body.push([{text:label,bold:true,fillColor:'#f8fafc',color:'#334155',margin:[4,4,4,4]},{text:(val===undefined||val===null||val==='')?(italic?'No especificado':'—'):String(val), italics: italic && (val===undefined||val===null||val===''), margin:[4,4,4,4]}]);
      if (modulo==='distribucion_personal'){
        push('Id Distribución', src.id_distribucion ?? src.idDistribucion ?? src.distribucion_id ?? src.id);
        push('Id Usuario',      src.id_usuario ?? src.idUsuario);
        push('Id Sucursal',     src.id_sucursal ?? src.idSucursal);
        push('Fecha',           src.fecha);
        push('Hora Entrada',    src.hora_entrada ?? src.horaEntrada);
        push('Hora Salida',     src.hora_salida ?? src.horaSalida);
        push('Cupos',           src.cupos);
        push('Id Rol',          src.id_rol ?? src.idRol, true);
        push('Estado',          src.estado ?? fallbackEstado);
        return body;
      }
      const keys=Object.keys(src||{});
      if(!keys.length){ push('Información','Sin datos.'); return body; }
      keys.forEach(k=>{ if (SKIP_KEYS2.has(String(k).toLowerCase())) return; const val=(typeof src[k]==='object')?JSON.stringify(src[k]):src[k]; push(prettyLabel2(k),val); });
      return body;
    }
    const $audBtnPdfEl=document.getElementById('audBtnPdf');
    if ($audBtnPdfEl) {
      $audBtnPdfEl.addEventListener('click', ()=>{
        if (!lastDetalle) { (window.Swal? Swal.fire('Aviso','No hay datos para exportar.','warning') : alert('No hay datos para exportar.')); return; }
        ensurePdfMake(()=>{
          const r=lastDetalle; const accionKey=String(r.accion||'').toUpperCase();
          const accionTxt=r.accion_fmt || (ACC_LABEL[accionKey] || accionKey);
          const fecha=r.creado_en_fmt || r.creado_en || ''; const modulo=String(r.modulo||'').toLowerCase();
          let src={}; if(accionKey==='DELETE') src=r.previos||r.valores_previos||r.antes_json||{};
          else if(accionKey==='CREATE') src=r.despues_json||r.despues||r.after||{};
          else { src=r.previos||r.valores_previos||r.antes_json||{}; if(!Object.keys(src).length) src=r.despues_json||{}; }
          const body=makeKVTableBodyForPdf(src, modulo, r.estado_resultante ?? r.estado ?? '');
          const headerInfo=[
            [{text:'Fecha y hora:', color:'#6b7280'}, {text:fecha, bold:true}],
            [{text:'Módulo:', color:'#6b7280'}, {text:r.modulo||'—', bold:true}],
            [{text:'Usuario:', color:'#6b7280'}, {text:r.usuario||'—', bold:true}],
            [{text:'Acción:', color:'#6b7280'}, { columns:[{text:accionTxt,bold:true,margin:[0,0,6,0]}, (accionKey==='DELETE'?{text:'Registro eliminado',color:'#fff',fillColor:'#dc3545',fontSize:10,bold:true,alignment:'center',width:120}:{text:''})], columnGap:6 }]
          ];
          const doc={ pageSize:'A4', pageMargins:[30,40,30,40],
            content:[
              { text:'Detalles de Registro de Auditoría', fontSize:14, bold:true, margin:[0,0,0,8] },
              { table:{ widths:[110,'*'], body:headerInfo }, layout:'noBorders', margin:[0,0,0,8] },
              { canvas:[{type:'line',x1:0,y1:0,x2:515,y2:0,lineWidth:1,lineColor:'#e5e7eb'}], margin:[0,6,0,10] },
              { text:(accionKey==='DELETE'?'Valores previos':(accionKey==='CREATE'?'Valores actuales':'Valores')), bold:true, margin:[0,0,0,6] },
              { table:{ widths:[180,'*'], body:body }, layout:{ fillColor:(row,col)=> (col===0?'#f8fafc':null), hLineColor:'#e5e7eb', vLineColor:'#e5e7eb' } }
            ] };
          const safe=(fecha||'detalle').toString().replace(/[:\s]/g,'_').slice(0,80);
          pdfMake.createPdf(doc).download(`detalles_auditoria_${safe}.pdf`);
        });
      });
    }
  </script>
</div>
</body>
</html>
