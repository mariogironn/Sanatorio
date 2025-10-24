<?php 
// reportes_distribucion.php
// Subproceso: Reportes » Distribución de Personal y Pacientes (solo filtros + tabla)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/connection.php';

// Permiso (si lo usas)
if (is_file(__DIR__ . '/config/auth.php')) {
  require_once __DIR__ . '/config/auth.php';
  if (function_exists('require_permission')) require_permission('reportes.distribucion');
}

/** Detectar si el usuario logueado es ADMIN (Admin/Administrador/Propietario/Superadmin/Owner) */
$isAdmin = false;
try{
  $uidSess = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? 0);
  if ($uidSess > 0) {
    $stAdm = $con->prepare("SELECT 1
                              FROM usuario_rol ur
                              JOIN roles r ON r.id_rol = ur.id_rol
                             WHERE ur.id_usuario = :u
                               AND UPPER(r.nombre) IN ('ADMIN','ADMINISTRADOR','PROPIETARIO','SUPERADMIN','OWNER')
                             LIMIT 1");
    $stAdm->execute([':u'=>$uidSess]);
    $isAdmin = (bool)$stAdm->fetchColumn();
  }
} catch (Throwable $e) { /* silencio */ }

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Distribución de Personal</title>
  <style>
    .btn-rounded { border-radius:.45rem; }
    .badge-estado{ font-size:.85rem; }
    #tblDistrib.table { border-collapse: separate; border-spacing: 0 6px; }
    #tblDistrib tbody tr { background:#fff; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
    #tblDistrib thead th { background:#f6f7fb; border-bottom: 2px solid #e9ecf1; }
    #tblDistrib td, #tblDistrib th { vertical-align: middle!important; }
    .usr-avatar{width:30px;height:30px;border-radius:50%;object-fit:cover;object-position:center;margin-right:.4rem;border:1px solid rgba(0,0,0,.1)}
    .cell-inline{display:flex;align-items:center;gap:.45rem}

    /* ===== Estilos del MODAL de turnos (SweetAlert) ===== */
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
    <!-- Título + Filtros -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2 align-items-center">
          <div class="col-sm-6">
            <h1 class="mb-0"><i class="fas fa-people-arrows"></i> Distribución de Personal</h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">

        <div class="card card-outline card-primary rounded-0 shadow">
          <div class="card-body">
            <form id="formFiltros" class="form-row">
              <div class="form-group col-lg-4 col-md-6">
                <label>Sucursal</label>
                <select id="f_sucursal" class="form-control form-control-sm rounded-0">
                  <option value="">Todas las Sucursales</option>
                </select>
              </div>
              <div class="form-group col-lg-3 col-md-4">
                <label>Fecha</label>
                <input type="date" id="f_fecha" class="form-control form-control-sm rounded-0">
              </div>
              <div class="form-group col-lg-3 col-md-4 d-flex align-items-end">
                <button type="button" id="btnGenerar" class="btn btn-primary btn-sm btn-rounded">
                  <i class="fas fa-sync"></i> Generar Reporte
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Tabla -->
        <div class="card card-outline card-secondary rounded-0 shadow">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0"><i class="fas fa-table"></i> Personal Programado</h3>
            <?php if ($isAdmin): ?>
              <button type="button" id="btnProgramar" class="btn btn-success btn-sm">
                <i class="fas fa-calendar-plus"></i> Programar turno
              </button>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tblDistrib" class="table table-striped table-bordered w-100">
                <thead>
                  <tr>
                    <th>Sucursal</th>
                    <th>Rol</th>
                    <th>Usuario</th>
                    <th>Día laboral</th>
                    <th>Horario (Entrada  - Salida)</th>
                    <th>Pacientes a Cargo</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
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
  // ✅ Marca Reportes > Distribución como seleccionado
  if (typeof showMenuSelected === 'function') {
    showMenuSelected("#mnu_reports", "#mi_reports_distribucion");
  }

  // Cargar SweetAlert2 si hiciera falta
  if (typeof Swal === 'undefined') {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js';
    document.head.appendChild(s);
  }

  // Flag ADMIN para el front
  window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

  const BASE = "<?= rtrim(dirname($_SERVER['PHP_SELF']),'/').'/'; ?>";
  const api  = (p) => BASE + p.replace(/^\/+/, '');

  function getFiltros(){
    return {
      sucursal_id: $('#f_sucursal').val() || '',
      fecha: $('#f_fecha').val() || ''
    };
  }

  function cargarOpciones(){
    $.getJSON(api('ajax/opciones_distribucion.php'))
      .done(function(r){
        const $s = $('#f_sucursal').empty().append('<option value="">Todas las Sucursales</option>');
        if (r && Array.isArray(r.sucursales)) {
          r.sucursales.forEach(o => $s.append('<option value="'+o.id+'">'+o.nombre+'</option>'));
        }
      })
      .fail(function(){
        $('#f_sucursal').load(api('ajax/opciones_distribucion.php'));
      });
  }

  // Helpers de presentación
  function iconoRol(nombre){
    const n = (nombre||'').toString().toLowerCase();
    if (n.includes('médico') || n.includes('medico')) return 'fas fa-user-md';
    if (n.includes('recepcion')) return 'fas fa-concierge-bell';
    if (n.includes('enfermer')) return 'fas fa-briefcase-medical';
    if (n.includes('admin')) return 'fas fa-user-shield';
    return 'fas fa-id-badge';
  }
  function avatarUrl(r){
    return r.foto || r.imagen_perfil || r.user_foto || 'user_images/default-user.png';
  }
  // Día laboral (Español) desde yyyy-mm-dd (sin problemas de TZ)
  function diaLaboral(fechaYmd){
    if(!fechaYmd) return '—';
    try{
      const p = fechaYmd.split('-').map(Number);
      const d = new Date(p[0], p[1]-1, p[2]);
      const opt = { weekday:'long' };
      let name = d.toLocaleDateString('es-ES', opt);
      return name.charAt(0).toUpperCase() + name.slice(1);
    }catch(e){ return '—'; }
  }

  let dt = null;
  function cargarTabla(){
    $.getJSON(api('ajax/distribucion_listar.php'), getFiltros(), function(resp){
      const rows = (resp && resp.data) ? resp.data : [];
      if (!dt){
        dt = $('#tblDistrib').DataTable({
          data: rows,
          columns: [
            { data:null, render:(d,t,r)=>{
                if (r.sucursal_html) return r.sucursal_html;
                const nom = r.sucursal || '—';
                const icon = r.sucursal_icon ? `<i class="${r.sucursal_icon}"></i>` : '<i class="fas fa-store"></i>';
                return `<div class="cell-inline">${icon}<span>${nom}</span></div>`;
              }
            },
            { data:null, render:(d,t,r)=>{
                const nom = r.rol || '—';
                const icn = r.rol_icon ? r.rol_icon : iconoRol(nom);
                return `<div class="cell-inline"><i class="${icn}"></i><span>${nom}</span></div>`;
              }
            },
            { data:null, render:(d,t,r)=>{
                const nom = r.usuario || '—';
                const img = avatarUrl(r);
                return `<div class="cell-inline"><img class="usr-avatar" src="${img}" onerror="this.src='user_images/default-user.png'"><span>${nom}</span></div>`;
              }
            },
            // Día laboral
            { data:null, render:(d,t,r)=>{
                const fch = r.fecha || ($('#f_fecha').val() || '');
                return diaLaboral(fch);
              }
            },
            { data:'horario', defaultContent:'—' },
            { data:null, className:'text-center', render:(d,t,r)=>{
                if (r.cupos !== undefined && r.cupos !== null && r.cupos !== '') return String(r.cupos);
                if (r.pacientes !== undefined && r.pacientes !== null && r.pacientes !== '—') return String(r.pacientes);
                return '—';
              }
            },
            {
              data:'estado_badge',
              className:'text-center',
              orderable:false, searchable:false,
              render: function(d, t, r){
                if (d) return d;
                const val = (r.estado||'').toString().toLowerCase();
                const on  = (val==='activo'||val==='1'||val==='si'||val==='true');
                return '<span class="badge '+(on?'badge-success':'badge-secondary')+'">'+(on?'Activo':'Inactivo')+'</span>';
              }
            },
            {
              data:null, orderable:false, searchable:false, className:'text-center',
              render:(d,t,r)=>{
                if (!window.IS_ADMIN) return '—';
                const id = r.id || r.id_distribucion || 0;
                const origen = (r.origen||r.source||'').toString().toUpperCase();
                const can = r.can_edit===true || origen==='DP' || !!id;
                if (!can) return '—';
                return `
                  <button class="btn btn-sm btn-warning btn-edit"  title="Editar"   data-id="${id}"><i class="fa fa-edit"></i></button>
                  <button class="btn btn-sm btn-danger  btn-del"   title="Eliminar" data-id="${id}"><i class="fa fa-trash"></i></button>
                `;
              }
            }
          ],
          dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6 text-right"Bf>>t<"row mt-2"<"col-sm-5"i><"col-sm-7"p>>',
          buttons: [
            { extend:'copyHtml5',  className:'btn btn-secondary btn-sm btn-rounded', text:'Copiar'   },
            { extend:'excelHtml5', className:'btn btn-success  btn-sm btn-rounded', text:'Excel'    },
            { extend:'csvHtml5',   className:'btn btn-info     btn-sm btn-rounded', text:'CSV'      },
            { extend:'pdfHtml5',   className:'btn btn-danger   btn-sm btn-rounded', text:'PDF'      },
            { extend:'print',      className:'btn btn-primary  btn-sm btn-rounded', text:'Imprimir' },
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
      } else {
        dt.clear().rows.add(rows).draw(false);
      }
    }).fail(function(){
      if (dt){ dt.clear().draw(false); }
    });
  }

  // === CRUD con SweetAlert2 (solo ADMIN) ===
  function parseHoras(str){
    if(!str) return {he:'',hs:''};
    const m = String(str).match(/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/);
    return m ? {he:m[1], hs:m[2]} : {he:'',hs:''};
  }

  // Utilidad: nombre del día desde yyyy-mm-dd
  function nombreDiaES(yyyy_mm_dd){ return diaLaboral(yyyy_mm_dd); }

  // Utilidades fecha (evitar TZ)
  function parseYMD(s){ const a=s.split('-').map(Number); return new Date(a[0],a[1]-1,a[2]); }
  function fmtYMD(d){ const z=n=>String(n).padStart(2,'0'); return d.getFullYear()+'-'+z(d.getMonth()+1)+'-'+z(d.getDate()); }

  function openTurnoSwal(row){ // row puede ser null => nuevo
    if (!window.IS_ADMIN) return;

    const f = getFiltros();
    const sucId = parseInt(f.sucursal_id || 0, 10) || 0;
    if (!sucId) {
      Swal.fire('Selecciona una sucursal','Para programar un turno, elige primero la sucursal en el filtro.','info');
      return;
    }

    const id = parseInt((row && (row.id || row.id_distribucion)) || 0, 10) || 0;
    const fecha = f.fecha || new Date().toISOString().slice(0,10);

    let he = row?.hora_entrada || '';
    let hs = row?.hora_salida  || '';
    if ((!he || !hs) && row?.horario){ const h = parseHoras(row.horario); he=h.he; hs=h.hs; }

    const estado = (row?.estado && (row.estado===1 || String(row.estado).toLowerCase()==='activo')) ? '1' : '0';
    const cupos = (row?.cupos ?? '');

    // Cargar usuarios por sucursal
    $.getJSON(api('ajax/usuarios_por_sucursal.php'), { id_sucursal: sucId })
      .done(function(resp){
        const users = (resp && resp.usuarios) ? resp.usuarios : [];
        const uidSel = parseInt(row?.id_usuario || 0, 10) || 0;
        const opts  = users.map(u=>`<option value="${u.id}" ${uidSel===Number(u.id)?'selected':''}>${u.nombre}</option>`).join('');
        Swal.fire({
          width: 540,
          title: (id? 'Editar turno' : 'Programar turno'),
          html: `
            <div class="text-left">
              <div class="sw-section sw-blue">
                <div class="form-group">
                  <label>Usuario</label>
                  <select id="sw_u" class="form-control form-control-sm">${opts}</select>
                </div>
                <div class="form-group">
                  <label>Fecha</label>
                  <div class="input-group input-group-sm sw-date-nav">
                    <div class="input-group-prepend">
                      <button type="button" class="btn btn-light" id="sw_prev" title="Día anterior"><i class="fas fa-chevron-left"></i></button>
                    </div>
                    <input id="sw_f" type="date" class="form-control form-control-sm" value="${fecha}">
                    <div class="input-group-append">
                      <button type="button" class="btn btn-light" id="sw_next" title="Día siguiente"><i class="fas fa-chevron-right"></i></button>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label>Día laboral</label>
                  <div class="input-group input-group-sm sw-date-nav">
                    <div class="input-group-prepend">
                      <button type="button" class="btn btn-light" id="sw_dprev" title="Anterior"><i class="fas fa-chevron-left"></i></button>
                    </div>
                    <input id="sw_dia" type="text" class="form-control form-control-sm" list="lista_dias" autocomplete="off">
                    <datalist id="lista_dias">
                      <option value="Lunes">
                      <option value="Martes">
                      <option value="Miércoles">
                      <option value="Jueves">
                      <option value="Viernes">
                      <option value="Sábado">
                      <option value="Domingo">
                    </datalist>
                    <div class="input-group-append">
                      <button type="button" class="btn btn-light" id="sw_dnext" title="Siguiente"><i class="fas fa-chevron-right"></i></button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="sw-section sw-green">
                <div class="form-row">
                  <div class="form-group col-6">
                    <label>Entrada</label>
                    <input id="sw_he" type="time" class="form-control form-control-sm" value="${he}">
                  </div>
                  <div class="form-group col-6">
                    <label>Salida</label>
                    <input id="sw_hs" type="time" class="form-control form-control-sm" value="${hs}">
                  </div>
                </div>
              </div>

              <div class="sw-section sw-amber">
                <div class="form-row">
                  <div class="form-group col-6">
                    <label>Pacientes a cargo</label>
                    <input id="sw_cupos" type="number" min="0" step="1" class="form-control form-control-sm" value="${cupos}">
                  </div>
                  <div class="form-group col-6">
                    <label>Estado</label>
                    <select id="sw_est" class="form-control form-control-sm">
                      <option value="1" ${estado==='1'?'selected':''}>Activo</option>
                      <option value="0" ${estado==='0'?'selected':''}>Inactivo</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>`,
          customClass: {
            popup: 'sw-turno',
            title: 'sw-title',
            confirmButton: 'sw-btn-ok',
            cancelButton: 'sw-btn-cancel'
          },
          didOpen: function(){
            // Utilidades de día (índice lunes=0..domingo=6)
            const diasCanon = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
            const alias = {
              'lunes':0,'martes':1,'miercoles':2,'miércoles':2,'jueves':3,'viernes':4,'sabado':5,'sábado':5,'domingo':6
            };
            const normaliza = s => (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); // quita tildes
            const idxLun = d => (d.getDay()+6)%7; // lunes=0..domingo=6
            const inicioSemana = d => { const x=new Date(d); x.setDate(x.getDate()-idxLun(x)); return x; };

            const $f = $('#sw_f'), $dia = $('#sw_dia');
            const syncDia = ()=> { $dia.val( diasCanon[idxLun(parseYMD($f.val()))] || '' ); };

            // Inicializa día laboral desde la fecha
            syncDia();

            // Al cambiar la fecha -> refrescar día
            $f.on('input change', syncDia);

            // Flechas fecha (ya existentes)
            $('#sw_prev').on('click', function(){
              const d = parseYMD($f.val() || '<?= date('Y-m-d') ?>'); d.setDate(d.getDate()-1);
              $f.val(fmtYMD(d)); syncDia();
            });
            $('#sw_next').on('click', function(){
              const d = parseYMD($f.val() || '<?= date('Y-m-d') ?>'); d.setDate(d.getDate()+1);
              $f.val(fmtYMD(d)); syncDia();
            });

            // Cambiar día laboral con flechas propias
            const desplazaDia = dir => {
              const d = parseYMD($f.val() || '<?= date('Y-m-d') ?>');
              d.setDate(d.getDate()+dir);
              $f.val(fmtYMD(d)); syncDia();
            };
            $('#sw_dprev').on('click', ()=>desplazaDia(-1));
            $('#sw_dnext').on('click', ()=>desplazaDia(1));

            // Al escribir un día, ajustar la FECHA al día elegido dentro de la MISMA semana
            const aplicarDiaEscrito = ()=>{
              const raw = normaliza($dia.val());
              const i = alias[raw];
              if (typeof i === 'number'){
                const d = parseYMD($f.val() || '<?= date('Y-m-d') ?>');
                const ini = inicioSemana(d);              // lunes de esa semana
                const nuevo = new Date(ini); nuevo.setDate(ini.getDate()+i);
                $f.val(fmtYMD(nuevo));
                syncDia();
              }
              // si no reconoce, simplemente mantiene el valor actual
            };
            $dia.on('change blur', aplicarDiaEscrito);
            $dia.on('keydown', function(e){ if (e.key==='Enter'){ e.preventDefault(); aplicarDiaEscrito(); } });
          },
          showCancelButton:true, confirmButtonText:'Guardar', cancelButtonText:'Cancelar',
          preConfirm: ()=>{
            const uid = parseInt($('#sw_u').val(),10)||0;
            const fch = $('#sw_f').val();
            const he  = $('#sw_he').val();
            const hs  = $('#sw_hs').val();
            const es  = $('#sw_est').val();
            const cp  = $('#sw_cupos').val();

            if (!uid || !fch) { Swal.showValidationMessage('Completa usuario y fecha.'); return false; }
            if (he && hs && he >= hs) { Swal.showValidationMessage('La hora de entrada debe ser menor que la hora de salida.'); return false; }

            return $.post(api('ajax/distribucion_guardar.php'),
              { id:id, id_usuario:uid, id_sucursal:sucId, fecha:fch, hora_entrada:he, hora_salida:hs, estado:es, cupos:cp }
            ).then(t=>{
              t=(t||'').trim();
              if (t!=='OK' && !t.startsWith('OK')) throw new Error(t||'No se pudo guardar.');
            }).catch(err=>{
              Swal.showValidationMessage(err.message||'Error al guardar.');
              return false;
            });
          }
        }).then(res=>{
          if (res.isConfirmed){ cargarTabla(); Swal.fire('Listo','Turno guardado.','success'); }
        });
      })
      .fail(function(){
        Swal.fire('Error','No se pudieron cargar los usuarios de la sucursal.','error');
      });
  }

  // Eventos
  $(document).on('click','#btnProgramar', function(){ openTurnoSwal(null); });
  $(document).on('click','.btn-edit', function(){
    const row = dt.row($(this).closest('tr')).data();
    openTurnoSwal(row);
  });
  $(document).on('click','.btn-del', function(){
    if (!window.IS_ADMIN){ return; }
    const id = $(this).data('id');
    Swal.fire({
      icon:'warning', title:'Eliminar turno', text:'¿Seguro que deseas eliminar este turno?',
      showCancelButton:true, confirmButtonText:'Eliminar', cancelButtonText:'Cancelar'
    }).then(r=>{
      if(!r.isConfirmed) return;
      $.post(api('ajax/distribucion_eliminar.php'), { id:id })
        .done(t=>{
          t=(t||'').trim();
          if(t==='OK'){ cargarTabla(); Swal.fire('Eliminado','Turno eliminado.','success'); }
          else{ Swal.fire('Error', t || 'No se pudo eliminar.','error'); }
        })
        .fail(x=> Swal.fire('Error', x.responseText || 'Error de servidor.','error'));
    });
  });

  // === Inicialización ===
  $(function(){
    const hoy = new Date().toISOString().slice(0,10);
    $('#f_fecha').val(hoy);

    cargarOpciones();
    cargarTabla();

    $('#btnGenerar').on('click', function(){ cargarTabla(); });
    $('#formFiltros').on('submit', function(e){
      e.preventDefault();
      cargarTabla();
    });
  });
</script>
</body>
</html>
