<?php 
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

/* ===== Auth ===== */
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

/* Usuario logueado (solo para cabecera) */
$uStmt = $con->prepare("SELECT id, usuario, nombre_mostrar FROM usuarios WHERE id = :id LIMIT 1");
$uStmt->execute([':id'=>$uid]);
$user  = $uStmt->fetch(PDO::FETCH_ASSOC) ?: ['id'=>0,'usuario'=>'','nombre_mostrar'=>'(usuario)'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Horarios Médicos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    :root{--hero:linear-gradient(135deg,#2c3e50,#3498db)}
    .hero{background:var(--hero);color:#fff;border-radius:.5rem}
    .kpi{color:#fff;border-radius:.5rem;padding:14px 16px;position:relative;min-height:84px}
    .kpi .icon{position:absolute;right:12px;top:10px;opacity:.25;font-size:34px}
    .kpi-blue{background:#0d6efd}.kpi-green{background:#198754}.kpi-amber{background:#f59e0b}
    .table-sm td,.table-sm th{padding:.45rem .5rem}
    .badge-soft{border-radius:12px;padding:.2rem .5rem}
    .btn-xxs{padding:.15rem .35rem;font-size:.75rem;line-height:1}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; ?>
  <?php include './config/sidebar.php'; ?>

  <div class="content-wrapper">

    <!-- Encabezado -->
    <section class="content-header">
      <div class="container-fluid hero px-3 py-3 d-flex align-items-center justify-content-between">
        <h1 class="h4 m-0"><i class="fas fa-calendar-check mr-2"></i>Horarios Médicos</h1>
        <div class="d-flex" style="gap:.5rem">
          <button id="btnAusencia" class="btn btn-light text-dark"><i class="fas fa-user-slash mr-1"></i> Registrar Ausencia</button>
          <button id="btnNuevo" class="btn btn-outline-light"><i class="fas fa-plus mr-1"></i> Crear Nuevo Horario</button>
        </div>
      </div>
    </section>

    <section class="content">

      <!-- KPIs -->
      <div class="row">
        <div class="col-lg-4 col-12 mb-3">
          <div class="kpi kpi-blue shadow-sm">
            <div class="icon"><i class="far fa-clock"></i></div>
            <div class="h3 mb-0" id="kpiHorarios">0</div>
            <div>Horarios activos</div>
          </div>
        </div>
        <div class="col-lg-4 col-12 mb-3">
          <div class="kpi kpi-green shadow-sm">
            <div class="icon"><i class="fas fa-user-md"></i></div>
            <div class="h3 mb-0" id="kpiMedicos">0</div>
            <div>Médicos con horario</div>
          </div>
        </div>
        <div class="col-lg-4 col-12 mb-3">
          <div class="kpi kpi-amber shadow-sm">
            <div class="icon"><i class="fas fa-ban"></i></div>
            <div class="h3 mb-0" id="kpiAusencias">0</div>
            <div>Ausencias este mes</div>
          </div>
        </div>
      </div>

      <!-- Acciones globales -->
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <button id="btnPrint" class="btn btn-secondary btn-sm mr-2"><i class="fas fa-print"></i> Imprimir</button>
          <button id="btnPdf" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
        </div>
        <div class="d-flex align-items-center" style="gap:.4rem">
          <select id="filtroMedico" class="form-control form-control-sm" style="min-width:280px"></select>
          <button id="btnFiltrar" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
        </div>
      </div>

      <!-- LISTADO -->
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-list mr-2"></i>Horarios Activos</h3>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead class="thead-dark">
                <tr>
                  <th style="width:50px">#</th>
                  <th>Doctor(a)</th>
                  <th style="width:140px">Día</th>
                  <th style="width:120px">Hora Inicio</th>
                  <th style="width:120px">Hora Fin</th>
                  <th style="width:160px">Estado</th>
                  <th style="width:180px">Acciones</th>
                </tr>
              </thead>
              <tbody id="tbodyHorarios">
                <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<!-- MODAL: NUEVO/EDITAR HORARIO -->
<div class="modal fade" id="modalHorario" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header" style="background:var(--hero);color:#fff">
      <h5 class="modal-title"><i class="fas fa-plus mr-2"></i><span id="mhTitle">Crear Nuevo Horario</span></h5>
      <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="frmHorario" autocomplete="off">
      <div class="modal-body">
        <input type="hidden" id="hId" name="id_horario">
        <div class="form-group">
          <label>Médico</label>
          <select id="hMedico" name="medico_id" class="form-control" required></select>
        </div>
        <div class="form-group">
          <label>Día de la Semana</label>
          <select id="hDia" name="dia_semana" class="form-control" required>
            <option value="">Seleccione el día</option>
            <option value="1">Lunes</option><option value="2">Martes</option><option value="3">Miércoles</option>
            <option value="4">Jueves</option><option value="5">Viernes</option><option value="6">Sábado</option><option value="7">Domingo</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Hora Inicio</label>
            <input id="hIni" name="hora_inicio" type="time" class="form-control" required>
          </div>
          <div class="form-group col-md-6">
            <label>Hora Fin</label>
            <input id="hFin" name="hora_fin" type="time" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select id="hEstado" name="estado" class="form-control">
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
            <option value="disponible">Disponible</option>
            <option value="no_disponible">No Disponible</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- MODAL: REGISTRAR AUSENCIA -->
<div class="modal fade" id="modalAusencia" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header" style="background:var(--hero);color:#fff">
      <h5 class="modal-title"><i class="fas fa-user-slash mr-2"></i>Registrar Ausencia</h5>
      <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="frmAusencia" autocomplete="off">
      <div class="modal-body">
        <div class="form-group">
          <label>Médico</label>
          <select id="aMedico" name="medico_id" class="form-control" required></select>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Fecha</label>
            <input id="aFecha" name="fecha" type="date" class="form-control" required>
          </div>
          <div class="form-group col-md-4">
            <label>Desde</label>
            <input id="aIni" name="hora_inicio" type="time" class="form-control" required>
          </div>
          <div class="form-group col-md-4">
            <label>Hasta</label>
            <input id="aFin" name="hora_fin" type="time" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label>Motivo</label>
          <input id="aMotivo" name="motivo" type="text" class="form-control" placeholder="Cirugía, capacitación, vacaciones…">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- MODAL: DETALLE -->
<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="far fa-circle-info mr-2"></i>Detalles del Horario</h5>
      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body" id="detalleBody"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
    </div>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
  let MEDICOS = [];
  const $tb  = $('#tbodyHorarios');

  init();

  function init(){
    cargarMedicos().then(()=>{
      $('#filtroMedico, #hMedico, #aMedico').html(opcionesMedicos(true));
      render();
    });

    // Acciones globales
    $('#btnFiltrar').on('click', render);
    $('#btnNuevo').on('click', ()=> abrirHorario());
    $('#btnAusencia').on('click', ()=> $('#modalAusencia').modal('show'));
    $('#btnPrint').on('click', ()=> window.print());
    $('#btnPdf').on('click', ()=> window.print());

    // Submits
    $('#frmHorario').on('submit', guardarHorario);
    $('#frmAusencia').on('submit', guardarAusencia);

    // Si eligen "No Disponible", abrimos Ausencia y no guardamos ese estado
    $('#hEstado').on('change', function(){
      const v = String($(this).val()||'').toLowerCase();
      if (v === 'no_disponible') {
        $('#aMedico').val($('#hMedico').val());
        $('#aFecha').val(new Date().toISOString().slice(0,10));
        $('#aIni').val($('#hIni').val());
        $('#aFin').val($('#hFin').val());
        $('#aMotivo').val('');
        $('#modalAusencia').modal('show');
        // volvemos al valor anterior razonable (activo)
        $(this).val('activo');
      }
    });
  }

  // Carga médicos (tolera {success,data} o [{id,text}])
  function cargarMedicos(){
    return $.get('ajax/medicos_listar.php',null,null,'json')
      .then(r=>{
        if (Array.isArray(r)) {
          MEDICOS = r.map(x => ({ id: x.id, nombre_mostrar: x.text, colegiado: x.colegiado || '' }));
        } else if (r && r.success && Array.isArray(r.data)) {
          MEDICOS = r.data;
        } else {
          MEDICOS = [];
        }
        if (!MEDICOS.length) {
          console.warn('medicos_listar.php devolvió 0 médicos.');
          Swal.fire({icon:'warning', title:'Sin médicos',
            text:'No se encontraron médicos activos.'});
        }
      })
      .catch(err=>{
        console.error('Error cargando médicos:', err);
        MEDICOS = [];
        Swal.fire({icon:'error', title:'Error', text:'No se pudo cargar la lista de médicos.'});
      });
  }

  function render(){
    $.when(
      $.get('ajax/horarios_listar.php',null,null,'json'),
      $.get('ajax/horarios_bloqueos_listar.php',null,null,'json')
    ).done(function(h,b){
      const horarios = (h[0]&&h[0].success)?(h[0].data||[]):[];
      const ausencias= (b[0]&&b[0].success)?(b[0].data||[]):[];

      // KPIs: Activo + Disponible cuentan como "activos"
      const activos = horarios.filter(x=>{
        const st = String(x.estado||'').toLowerCase();
        return (st==='activo' || st==='disponible');
      }).length;

      const medCon  = new Set(
        horarios.filter(x=>{
          const st = String(x.estado||'').toLowerCase();
          return (st==='activo' || st==='disponible');
        }).map(x=>x.medico_id)
      );

      const ym      = (new Date()).toISOString().slice(0,7);
      const ausMes  = ausencias.filter(a=>(a.fecha||'').startsWith(ym)).length;

      $('#kpiHorarios').text(activos);
      $('#kpiMedicos').text(medCon.size);
      $('#kpiAusencias').text(ausMes);

      // Filtro y enriquecimiento
      const filtro = $('#filtroMedico').val();
      const hoy = new Date().toISOString().slice(0,10);

      const rows = horarios
        .filter(x=>!filtro || String(x.medico_id)===String(filtro))
        .map(x=>{
          const m = MEDICOS.find(mm=>String((mm.id??mm.id_usuario??mm.usuario_id) || mm.id_medico)===String(x.medico_id));
          const proxima = (ausencias||[])
            .filter(a => String(a.medico_id)===String(x.medico_id) && (a.fecha||'') >= hoy)
            .sort((a,b)=>(a.fecha+a.hora_inicio).localeCompare(b.fecha+b.hora_inicio))[0] || null;

          const nombre = m ? (m.nombre_mostrar || m.nombre || '—') : '—';
          const col    = (m && m.colegiado) ? (' - Colegiado: ' + m.colegiado) : '';
          return {
            ...x,
            medico_nombre: nombre,
            medico_label:  nombre + col,
            ausencia_proxima: !!proxima,
            ausencia_motivo: proxima ? proxima.motivo : '',
            ausencia_fecha:  proxima ? proxima.fecha : '',
            ausencia_ini:    proxima ? proxima.hora_inicio : '',
            ausencia_fin:    proxima ? proxima.hora_fin : ''
          };
        })
        .sort((a,b)=> (a.medico_nombre||'').localeCompare(b.medico_nombre||'') || (a.dia_semana-b.dia_semana) || a.hora_inicio.localeCompare(b.hora_inicio));

      if(!rows.length){
        $tb.html('<tr><td colspan="7" class="text-center text-muted py-4">Sin horarios para mostrar</td></tr>');
        return;
      }

      const dias = ['','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
      let html='';
      rows.forEach((r,i)=>{
        // Badge (prioriza ausencia)
        let badge;
        if (r.ausencia_proxima) {
          const tip = esc(`Motivo: ${r.ausencia_motivo||''} (${r.ausencia_fecha||''} de ${r.ausencia_ini||''} a ${r.ausencia_fin||''})`);
          badge = `<span class="badge badge-warning" data-toggle="tooltip" title="${tip}">No Disponible</span>`;
        } else {
          const st = String(r.estado||'').toLowerCase();
          badge = (st==='activo')
            ? '<span class="badge badge-success">Activo</span>'
            : (st==='disponible'
                ? '<span class="badge badge-primary">Disponible</span>'
                : '<span class="badge badge-secondary">Inactivo</span>');
        }

        html += `<tr>
          <td>${i+1}</td>
          <td>${esc(r.medico_label||r.medico_nombre||'—')}</td>
          <td>${esc(r.dia_nombre || dias[r.dia_semana] || '?')}</td>
          <td>${esc(r.hora_inicio)}</td>
          <td>${esc(r.hora_fin||'')}</td>
          <td>${badge}</td>
          <td>
            <button class="btn btn-primary btn-xxs mr-1" data-ver="${r.id_horario||r.id}" title="Ver"><i class="far fa-eye"></i></button>
            <button class="btn btn-info btn-xxs mr-1" data-edit="${r.id_horario||r.id}" title="Editar"><i class="fas fa-pen"></i></button>
            <button class="btn btn-danger btn-xxs" data-del="${r.id_horario||r.id}" title="Eliminar"><i class="fas fa-trash"></i></button>
          </td>
        </tr>`;
      });

      $tb.html(html);
      $('[data-toggle="tooltip"]').tooltip();

      // acciones
      $tb.find('[data-edit]').on('click', function(){
        const id = $(this).data('edit');
        const r  = rows.find(x=>Number((x.id_horario||x.id))===Number(id));
        if(r) abrirHorario(r);
      });
      $tb.find('[data-del]').on('click', function(){
        const id = $(this).data('del');
        Swal.fire({icon:'warning',title:'¿Eliminar horario?',showCancelButton:true,confirmButtonText:'Sí, eliminar',confirmButtonColor:'#d33'})
          .then(res=>{
            if(!res.isConfirmed) return;
            $.post('ajax/horario_eliminar.php',{id_horario:id},function(rr){
              if(rr&&rr.success){ Swal.fire({icon:'success',title:'Eliminado',timer:1200,showConfirmButton:false}); render(); }
              else{ Swal.fire({icon:'error',title:'No se pudo eliminar',text:rr.message||''}); }
            },'json').fail(()=> Swal.fire({icon:'error',title:'Error de conexión'}));
          });
      });
      $tb.find('[data-ver]').on('click', function(){
        const id = $(this).data('ver');
        const r  = rows.find(x=>Number((x.id_horario||x.id))===Number(id));
        if(!r) return;
        const dias = ['','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];

        const badge = r.ausencia_proxima
          ? '<span class="badge badge-warning">No Disponible</span>'
          : (String(r.estado).toLowerCase()==='activo'
              ? '<span class="badge badge-success">Activo</span>'
              : (String(r.estado).toLowerCase()==='disponible'
                  ? '<span class="badge badge-primary">Disponible</span>'
                  : '<span class="badge badge-secondary">Inactivo</span>'));

        const body = `
          <div style="text-align:center">
            <div style="font-size:54px;color:#3b82f6;line-height:1">i</div>
            <h3>Detalles del Horario #${esc(r.id_horario||r.id||'')}</h3>
            <p><b>Médico:</b> ${esc(r.medico_label||r.medico_nombre||'—')}</p>
            <p><b>Día:</b> ${esc(dias[r.dia_semana]||'?')}</p>
            <p><b>Inicio:</b> ${esc(r.hora_inicio)} h</p>
            <p><b>Fin:</b> ${esc(r.hora_fin||'')} h</p>
            <p><b>Estado:</b> ${badge}</p>
            ${r.ausencia_proxima
              ? `<p><b>Motivo:</b> <span class="badge badge-danger">${esc(r.ausencia_motivo||'')}</span>
                   <span class="text-muted">(${esc(r.ausencia_fecha||'')} de ${esc(r.ausencia_ini||'')} a ${esc(r.ausencia_fin||'')})</span>
                 </p>` : '' }
          </div>`;
        $('#detalleBody').html(body);
        $('#modalDetalle').modal('show');
      });

    }).fail(()=> $tb.html('<tr><td colspan="7" class="text-center text-danger py-4">Error de conexión</td></tr>'));
  }

  // ==== CRUD Horario ====
  function abrirHorario(row=null){
    $('#frmHorario')[0].reset();
    $('#hId').val('');
    $('#mhTitle').text(row?'Editar Horario Existente':'Crear Nuevo Horario');

    $('#hMedico').html(opcionesMedicos())
                 .val(row?row.medico_id:'');

    $('#hDia').val(row?row.dia_semana:'');
    $('#hIni').val(row?row.hora_inicio:'');
    $('#hFin').val(row?row.hora_fin:'');

    // Estado: ahora mostramos el valor real (activo / inactivo / disponible)
    const estNorm = String(row?.estado || 'activo').toLowerCase();
    $('#hEstado').val(estNorm);

    if(row) $('#hId').val(row.id_horario||row.id);

    $('#modalHorario').modal('show');
  }

  function guardarHorario(e){
    e.preventDefault();
    // Validaciones front rápidas
    const mid = $('#hMedico').val();
    const dia = $('#hDia').val();
    const hi  = $('#hIni').val();
    const hf  = $('#hFin').val();
    if(!mid || !dia || !hi || !hf){
      Swal.fire({icon:'warning',title:'Campos incompletos',text:'Selecciona médico, día y horas.'});
      return;
    }
    if(hf <= hi){
      Swal.fire({icon:'warning',title:'Rango de horas inválido',text:'La hora fin debe ser mayor que la hora inicio.'});
      return;
    }

    const fd = new FormData(this);
    const raw = String(fd.get('estado')||'activo').toLowerCase();

    // Lo único que no se persiste es "no_disponible" (abre Ausencia).
    let estadoBD = 'Activo';
    if (raw === 'inactivo')      estadoBD = 'Inactivo';
    else if (raw === 'disponible') estadoBD = 'Disponible';
    // raw === 'no_disponible' no debería llegar porque lo manejamos onChange, pero por si acaso:
    if (raw === 'no_disponible') estadoBD = 'Activo';

    fd.set('estado', estadoBD); // tal cual lo almacenará el backend

    // alias común por si el endpoint lo usa
    fd.set('id_usuario_medico', fd.get('medico_id'));

    $.ajax({url:'ajax/horarios_guardar.php',type:'POST',data:fd,processData:false,contentType:false,dataType:'json'})
    .done(function(r){
      if(r&&r.success){ Swal.fire({icon:'success',title:'Guardado',timer:1200,showConfirmButton:false}); $('#modalHorario').modal('hide'); render(); }
      else{ Swal.fire({icon:'error',title:'Error',text:r && r.message ? r.message : 'No se pudo guardar'}); }
    }).fail(()=> Swal.fire({icon:'error',title:'Error de conexión'}));
  }

  // ==== Ausencia ====
  function guardarAusencia(e){
    e.preventDefault();
    const hi = $('#aIni').val(), hf = $('#aFin').val();
    if(hf <= hi){
      Swal.fire({icon:'warning',title:'Rango de horas inválido',text:'La hora fin debe ser mayor que la hora inicio.'});
      return;
    }
    const fd = new FormData(this);
    // FK correcto a usuarios.id
    fd.set('id_usuario_medico', fd.get('medico_id'));

    $.ajax({url:'ajax/horario_bloqueo_guardar.php',type:'POST',data:fd,processData:false,contentType:false,dataType:'json'})
    .done(function(r){
      if(r&&r.success){
        const medTxt = $('#aMedico option:selected').text();
        const html = `
          <div style="text-align:center">
            <div style="font-size:54px;color:#ff9800;line-height:1">!</div>
            <h3>Ausencia Registrada</h3>
            <p>Se ha registrado una ausencia para <b>${esc(medTxt)}</b>:</p>
            <p><b>Fecha:</b> ${esc($('#aFecha').val())} <b>(${esc($('#aIni').val())} a ${esc($('#aFin').val())})</b></p>
            <p><b>Motivo:</b> <span class="badge badge-danger">${esc($('#aMotivo').val()||'—')}</span></p>
          </div>`;
        Swal.fire({html,confirmButtonText:'Confirmar'});
        $('#modalAusencia').modal('hide'); render();
      }else{
        Swal.fire({icon:'error',title:'Error',text:r && r.message ? r.message : 'No se pudo guardar'});
      }
    }).fail(()=> Swal.fire({icon:'error',title:'Error de conexión'}));
  }

  // ==== Utils ====
  function opcionesMedicos(conTodos=false){
    let html = conTodos?'<option value="">Todos</option>':'';
    (MEDICOS||[]).forEach(m=>{
      const id = (m.id ?? m.id_usuario ?? m.usuario_id ?? m.id_medico);
      const txt = `${m.nombre_mostrar||m.nombre||'—'}${m.colegiado?(' - Colegiado: '+m.colegiado):''}`;
      html += `<option value="${id}">${esc(txt)}</option>`;
    });
    return html;
  }
  function esc(s){ return (s==null)?'':String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
});
</script>
</body>
</html>
