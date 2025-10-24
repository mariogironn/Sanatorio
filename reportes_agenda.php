
<?php
// reportes_agenda.php
// Reportes » Agenda y Disponibilidad (vista semanal por Médico o por Sucursal)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/connection.php';

// Permiso (si lo usas)
if (is_file(__DIR__ . '/config/auth.php')) {
  require_once __DIR__ . '/config/auth.php';
  if (function_exists('require_permission')) require_permission('reportes.agenda');
}

// ¿Es admin?
$isAdmin = false;
try{
  $uidSess = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
  if ($uidSess > 0 && isset($con)) {
    $st = $con->prepare("SELECT 1
                           FROM usuario_rol ur
                           JOIN roles r ON r.id_rol = ur.id_rol
                          WHERE ur.id_usuario = :u
                            AND UPPER(r.nombre) IN ('ADMIN','ADMINISTRADOR','PROPIETARIO','SUPERADMIN','OWNER')
                          LIMIT 1");
    $st->execute([':u'=>$uidSess]);
    $isAdmin = (bool)$st->fetchColumn();
  }
} catch (Throwable $e) { /* silencio */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Agenda y Disponibilidad</title>
  <style>
    .ag-card{ border:1px solid #e5e7eb; border-radius:.6rem; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.05); }
    .ag-head{ background:#0f172a; color:#fff; padding:.9rem 1rem; font-weight:700; }
    .ag-filters{ padding:1rem; display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:12px; }
    .ag-week-nav .btn{ background:#f1f5f9; border-color:#e2e8f0; }
    .ag-week-label{ font-weight:600; padding:.375rem .75rem; color:#334155; }
    .ag-legend{ padding:.75rem 1rem; border-top:1px solid #e5e7eb; display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
    .ag-dot{ width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:.4rem; }
    .ag-disponible{ background:#d1fae5; } .ag-ocupado{ background:#fde68a; }
    .ag-no_asignado{ background:#fecaca; } .ag-en_sucursal{ background:#bfdbfe; }

    /* Tabla */
    #tblAgenda{ width:100%; border-collapse:separate; border-spacing:0 6px; }
    #tblAgenda th, #tblAgenda td{ border:1px solid #e5e7eb; vertical-align:middle; }
    #tblAgenda thead th{ background:#f8fafc; font-weight:600; }
    #tblAgenda tbody td{ background:#fff; } /* colores se aplican abajo con !important */
    .ag-time{ white-space:nowrap; width:78px; }
    .ag-cell{ cursor:pointer; }

    /* Colores en celdas (mismas que leyenda) */
    #tblAgenda tbody td.ag-no_asignado{ background:#fff1f2 !important; color:#b91c1c; }
    #tblAgenda tbody td.ag-en_sucursal{ background:#e7f0ff !important; }
    #tblAgenda tbody td.ag-disponible { background:#ecfdf5 !important; }
    #tblAgenda tbody td.ag-ocupado    { background:#fff7ed !important; }

    .ag-cell small{ color:#64748b; display:block; }
    .btn-rounded{ border-radius:.45rem; }

    /* SweetAlert modal (igual a distribución) */
    .sw-turno { border-radius:14px!important; padding:18px 18px 14px!important; }
    .sw-turno .sw-title { font-weight:700!important; }
    .sw-section { padding:.75rem; border-radius:.6rem; margin-bottom:.8rem; }
    .sw-blue  { background:#e9f2ff; border:1px solid #cfe2ff; }
    .sw-green { background:#eafaf0; border:1px solid #d3f1db; }
    .sw-amber { background:#fff6e5; border:1px solid #ffe2b6; }
    .sw-section label { font-weight:600; color:#334155; }
    .sw-turno .form-control { background:#fff; }
    .sw-date-nav .btn { background:#f1f5f9; border-color:#e2e8f0; }
    .sw-btn-ok     { background:#6c63ff!important; border-radius:.55rem!important; }
    .sw-btn-cancel { background:#6b7280!important; border-radius:.55rem!important; }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; ?>
  <?php include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2 align-items-center">
          <div class="col-sm-6"><h1 class="mb-0"><i class="far fa-calendar-alt"></i> Agenda y Disponibilidad</h1></div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="ag-card">
          <div class="ag-head">Agenda y Disponibilidad</div>

          <div class="ag-filters">
            <div>
              <label>Vista</label>
              <select id="ag_vista" class="form-control form-control-sm rounded-0">
                <option value="medico">Vista por Médico</option>
                <option value="sucursal">Vista por Sucursal</option>
              </select>
            </div>

            <div id="box_medico">
              <label>Médico</label>
              <select id="ag_medico" class="form-control form-control-sm rounded-0"></select>
            </div>

            <div id="box_sucursal" style="display:none;">
              <label>Sucursal</label>
              <select id="ag_sucursal" class="form-control form-control-sm rounded-0"></select>
            </div>

            <div>
              <label>Fecha</label>
              <div class="input-group input-group-sm ag-week-nav">
                <div class="input-group-prepend">
                  <button class="btn btn-light" id="ag_prevWeek" title="Semana anterior"><i class="fas fa-chevron-left"></i></button>
                </div>
                <input type="date" id="ag_fecha" class="form-control form-control-sm">
                <div class="input-group-append">
                  <button class="btn btn-light" id="ag_nextWeek" title="Semana siguiente"><i class="fas fa-chevron-right"></i></button>
                </div>
              </div>
              <div class="ag-week-label" id="ag_rangoLabel"></div>
            </div>
          </div>

          <div class="p-3">
            <div id="ag_grid" class="table-responsive"></div>
          </div>

          <div class="ag-legend">
            <div><span class="ag-dot ag-disponible"></span><strong>Disponible</strong></div>
            <div><span class="ag-dot ag-ocupado"></span><strong>Ocupado</strong></div>
            <div><span class="ag-dot ag-no_asignado"></span><strong>No asignado</strong></div>
            <div><span class="ag-dot ag-en_sucursal"></span><strong>En sucursal</strong></div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>
<script>
  // Si SweetAlert2 no está cargado, lo cargamos (fallback)
  if (typeof Swal === 'undefined') {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js';
    document.head.appendChild(s);
  }

  // Marca menú
  if (typeof showMenuSelected === 'function') {
    showMenuSelected("#mnu_reports", "#mi_reports_agenda");
  }

  // Admin flag
  window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

  const BASE = "<?= rtrim(dirname($_SERVER['PHP_SELF']),'/').'/'; ?>";
  const api  = (p) => BASE + p.replace(/^\/+/, '');

  // ===== Utilidades de fechas (local, sin TZ) =====
  const z2 = n => String(n).padStart(2,'0');
  const fmtYMD = d => d.getFullYear() + '-' + z2(d.getMonth()+1) + '-' + z2(d.getDate());
  function toDate(ymd){ const a=String(ymd||'').split('-').map(Number); return new Date(a[0],a[1]-1,a[2]); }
  function mondayOf(d){
    const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const day = x.getDay(); // 0=Dom, 1=Lun
    const diff = (day===0 ? -6 : 1 - day);
    x.setDate(x.getDate() + diff);
    return x;
  }
  const mesesCortos = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  function labelRango(dLunes){
    const dvier = new Date(dLunes.getFullYear(), dLunes.getMonth(), dLunes.getDate()+4);
    const L = z2(dLunes.getDate())+' '+mesesCortos[dLunes.getMonth()];
    const V = z2(dvier.getDate())+' '+mesesCortos[dvier.getMonth()]+' '+dvier.getFullYear();
    return 'Del '+L+' al '+V;
  }

  // ===== Carga de opciones =====
  function cargarOpciones(){
    $.getJSON(api('ajax/agenda_opciones.php'))
     .done(function(r){
        const $m = $('#ag_medico').empty(), $s = $('#ag_sucursal').empty();
        (r.medicos||[]).forEach(o => $m.append('<option value="'+o.id+'">'+o.nombre+'</option>'));
        (r.sucursales||[]).forEach(o => $s.append('<option value="'+o.id+'">'+o.nombre+'</option>'));

        if (!$('#ag_medico').val() && (r.medicos||[]).length>0) {
          $('#ag_medico').val(String(r.medicos[0].id));
        }
        if (!$('#ag_sucursal').val() && (r.sucursales||[]).length>0) {
          $('#ag_sucursal').val(String(r.sucursales[0].id));
        }
      })
     .always(function(){ cargarGrid(); });
  }

  let dtAgenda = null;

  // ===== Render del grid =====
  function cargarGrid(){
    let fecha = $('#ag_fecha').val();
    if (!fecha) { fecha = fmtYMD(new Date()); $('#ag_fecha').val(fecha); }

    const dMon = mondayOf(toDate(fecha));
    $('#ag_rangoLabel').text(labelRango(dMon));

    const vista = $('#ag_vista').val();
    const medico_id = $('#ag_medico').val() || '';
    const sucursal_id = $('#ag_sucursal').val() || '';

    $.getJSON(api('ajax/agenda_disponibilidad.php'), {
        vista, medico_id, sucursal_id,
        fecha, week_start: fmtYMD(dMon)
      })
     .done(function(resp){
        const days = resp.days || [];
        const times = resp.times || [];
        const grid = resp.grid || [];

        // Encabezado
        let html = '<table id="tblAgenda" class="table table-bordered"><thead><tr><th class="ag-time">Hora</th>';
        for (const d of days){ html += '<th>'+ d.label +'</th>'; }
        html += '</tr></thead><tbody>';

        // Cuerpo
        for(let i=0;i<times.length;i++){
          html += '<tr><th class="ag-time">'+times[i]+'</th>';
          for(let j=0;j<days.length;j++){
            const c = (grid[i] && grid[i][j]) ? grid[i][j] : {state:'no_asignado', text:'No asignado'};
            html += '<td class="ag-cell ag-'+c.state+'" '+
                    ' data-state="'+c.state+'" '+
                    ' data-time="'+times[i]+'" data-date="'+days[j].date+'" '+
                    ' data-medico="'+(c.medico_id||'')+'" data-sucursal="'+(c.sucursal_id||'')+'">'+
                      (c.text||'') + (c.sub?'<small>'+c.sub+'</small>':'') +
                    '</td>';
          }
          html += '</tr>';
        }
        html += '</tbody></table>';

        $('#ag_grid').html(html);

        // DataTable (exportación)
        if (dtAgenda) { dtAgenda.destroy(); dtAgenda = null; }
        dtAgenda = $('#tblAgenda').DataTable({
          ordering:false, paging:false, searching:false, info:false,
          dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6 text-right"Bf>>t',
          buttons: [
            { extend:'copyHtml5',  className:'btn btn-secondary btn-sm btn-rounded', text:'Copiar',    exportOptions:{stripHtml:true} },
            { extend:'excelHtml5', className:'btn btn-success  btn-sm btn-rounded', text:'Excel',     exportOptions:{stripHtml:true} },
            { extend:'csvHtml5',   className:'btn btn-info     btn-sm btn-rounded', text:'CSV',       exportOptions:{stripHtml:true} },
            { extend:'pdfHtml5',   className:'btn btn-danger   btn-sm btn-rounded', text:'PDF',       exportOptions:{stripHtml:true} },
            { extend:'print',      className:'btn btn-primary  btn-sm btn-rounded', text:'Imprimir',  exportOptions:{stripHtml:true} },
            { extend:'colvis',     className:'btn btn-warning  btn-sm btn-rounded', text:'Columnas' }
          ],
          language:{
            decimal:"", emptyTable:"No hay datos disponibles en la tabla",
            info:"Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty:"Mostrando 0 a 0 de 0 registros",
            infoFiltered:"(filtrado de _MAX_ registros totales)",
            lengthMenu:"Mostrar _MENU_ registros", loadingRecords:"Cargando...",
            processing:"Procesando...", search:"Buscar:",
            zeroRecords:"No se encontraron registros coincidentes",
            paginate:{ first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" },
            buttons:{ copy:"Copiar", csv:"CSV", excel:"Excel", pdf:"PDF", print:"Imprimir", colvis:"Columnas" }
          }
        });
      })
     .fail(function(){ $('#ag_grid').html('<div class="p-3 text-muted">Sin datos.</div>'); });
  }

  // ===== Modal de turno (reutiliza tu backend de distribución) =====
  function openTurnoSwalPreset(preset){
    if (!window.IS_ADMIN) return;

    const fecha = preset.fecha;
    const hora  = (preset.hora||'').slice(0,5);

    const sucId = parseInt(preset.sucursal_id || $('#ag_sucursal').val() || 0, 10) || 0;
    if (!sucId){
      Swal.fire('Selecciona una sucursal','Para programar un turno, abre el modal desde la vista por sucursal o proporciona la sucursal.','info');
      return;
    }

    const uidPre = parseInt(preset.medico_id || $('#ag_medico').val() || 0, 10) || 0;

    $.getJSON(api('ajax/usuarios_por_sucursal.php'), { id_sucursal: sucId })
     .done(function(r){
        const users = (r && r.usuarios) ? r.usuarios : [];
        const opts = users.map(u=>`<option value="${u.id}" ${uidPre===Number(u.id)?'selected':''}>${u.nombre}</option>`).join('');

        Swal.fire({
          width: 540,
          title: 'Programar turno',
          html: `
            <div class="text-left">
              <div class="sw-section sw-blue">
                <div class="form-group">
                  <label>Usuario</label>
                  <select id="sw_u" class="form-control form-control-sm">${opts}</select>
                </div>
                <div class="form-group">
                  <label>Fecha</label>
                  <input id="sw_f" type="date" class="form-control form-control-sm" value="${fecha}">
                </div>
              </div>
              <div class="sw-section sw-green">
                <div class="form-row">
                  <div class="form-group col-6">
                    <label>Entrada</label>
                    <input id="sw_he" type="time" class="form-control form-control-sm" value="${hora}">
                  </div>
                  <div class="form-group col-6">
                    <label>Salida</label>
                    <input id="sw_hs" type="time" class="form-control form-control-sm" value="">
                  </div>
                </div>
              </div>
              <div class="sw-section sw-amber">
                <div class="form-row">
                  <div class="form-group col-6">
                    <label>Pacientes a cargo</label>
                    <input id="sw_cupos" type="number" min="0" step="1" class="form-control form-control-sm" value="">
                  </div>
                  <div class="form-group col-6">
                    <label>Estado</label>
                    <select id="sw_est" class="form-control form-control-sm">
                      <option value="1" selected>Activo</option>
                      <option value="0">Inactivo</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>`,
          showCancelButton:true, confirmButtonText:'Guardar', cancelButtonText:'Cancelar',
          customClass:{ popup:'sw-turno', title:'sw-title', confirmButton:'sw-btn-ok', cancelButton:'sw-btn-cancel' },
          preConfirm: ()=>{
            const uid = parseInt($('#sw_u').val(),10)||0;
            const fch = $('#sw_f').val();
            const he  = $('#sw_he').val();
            const hs  = $('#sw_hs').val();
            const es  = $('#sw_est').val();
            const cp  = $('#sw_cupos').val();

            if (!uid || !fch){ Swal.showValidationMessage('Completa usuario y fecha.'); return false; }
            if (he && hs && he >= hs){ Swal.showValidationMessage('Entrada debe ser menor que salida.'); return false; }

            return $.post(api('ajax/distribucion_guardar.php'), {
              id:0, id_usuario:uid, id_sucursal:sucId, fecha:fch, hora_entrada:he, hora_salida:hs, estado:es, cupos:cp
            }).then(t=>{
              if ((t||'').trim().substr(0,2) !== 'OK') throw new Error(t||'No se pudo guardar.');
            }).catch(err=>{
              Swal.showValidationMessage(err.message||'Error al guardar.');
              return false;
            });
          }
        }).then(res=>{ if(res.isConfirmed){ cargarGrid(); Swal.fire('Listo','Turno guardado.','success'); }});
     })
     .fail(()=> Swal.fire('Error','No se pudieron cargar los usuarios de la sucursal.','error'));
  }

  // ===== Eventos =====
  $(document).on('change', '#ag_vista', function(){
    const v = $(this).val();
    $('#box_medico').toggle(v==='medico');
    $('#box_sucursal').toggle(v==='sucursal');
    cargarGrid();
  });

  $('#ag_prevWeek').on('click', function(){
    const d = toDate($('#ag_fecha').val() || fmtYMD(new Date()));
    d.setDate(d.getDate()-7);
    $('#ag_fecha').val(fmtYMD(d));
    cargarGrid();
  });
  $('#ag_nextWeek').on('click', function(){
    const d = toDate($('#ag_fecha').val() || fmtYMD(new Date()));
    d.setDate(d.getDate()+7);
    $('#ag_fecha').val(fmtYMD(d));
    cargarGrid();
  });
  $('#ag_fecha').on('change', cargarGrid);
  $('#ag_medico, #ag_sucursal').on('change', cargarGrid);

  // Click en celda con lógica tipo video:
  $(document).on('click', '#tblAgenda td.ag-cell', function(){
    if (!window.IS_ADMIN) return;
    const $td   = $(this);
    const state = ($td.data('state')||'').toString();

    const fecha = $td.data('date');
    const hora  = $td.data('time');
    const suc   = $td.data('sucursal') || ($('#ag_sucursal').val() || '');
    const med   = $td.data('medico')   || ($('#ag_medico').val() || '');

    if (state === 'no_asignado' || state === 'disponible') {
      Swal.fire({
        icon:'question',
        title:'Turno Disponible',
        text:'¿Desea asignar un turno en este horario?',
        showCancelButton:true,
        confirmButtonText:'Sí, asignar',
        cancelButtonText:'Cancelar'
      }).then(r=>{
        if (r.isConfirmed) openTurnoSwalPreset({ fecha, hora, sucursal_id:suc, medico_id:med });
      });
    } else if (state === 'en_sucursal' || state === 'ocupado') {
      const who = ($td.text()||'').trim();
      Swal.fire({
        icon:'info',
        title:'Turno Ocupado',
        html:'Este turno está ocupado por: <b>'+who+'</b>',
        confirmButtonText:'Aceptar'
      });
    }
  });

  // ===== Init =====
  $(function(){
    const hoy = new Date();
    $('#ag_fecha').val(fmtYMD(hoy));
    cargarOpciones(); // llama a cargarGrid() al finalizar
  });
</script>
</body>
</html>
