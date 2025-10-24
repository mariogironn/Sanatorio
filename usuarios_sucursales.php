<?php
// ==========================================================
// Acceso por Sucursales (CRUD de sucursales + asignación a usuarios)
// ==========================================================
include './config/connection.php';

// Detectar PK de usuarios (id | id_usuario) para no romper si cambia el esquema
$USER_PK = 'id';
try {
  $ck = $con->query("SHOW COLUMNS FROM usuarios LIKE 'id_usuario'");
  if ($ck && $ck->rowCount() > 0) { $USER_PK = 'id_usuario'; }
} catch (Throwable $e) { /* seguir con 'id' */ }

// Cargar usuarios (para el select)
$usuarios = [];
try {
  // Alias como 'id' para que el HTML no cambie
  $st = $con->query("SELECT `$USER_PK` AS id, usuario, nombre_mostrar FROM usuarios ORDER BY nombre_mostrar, usuario");
  $usuarios = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* silencio */ }

// Cargar sucursales (todas para la tabla; solo activas para asignación)
$sucursales = [];
try {
  $st = $con->query("SELECT id, nombre, estado FROM sucursales ORDER BY nombre");
  $sucursales = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* silencio */ }

$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Acceso por Sucursales</title>

  <!-- Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

  <style>
    .actions{ display:inline-flex; gap:.35rem; }
    .btn-icon{ width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; }

    /* === Select de USUARIO (single) — rojo con texto blanco === */
    #user_id + .select2 .select2-selection--single{
      background:#dc3545; color:#fff; border:1px solid #b02a37; min-height:38px;
    }
    #user_id + .select2 .select2-selection__rendered{ color:#fff; }
    #user_id + .select2 .select2-selection__placeholder{ color:#ffe6e9; }
    #user_id + .select2 .select2-selection__arrow b{
      border-color:#fff transparent transparent transparent !important;
    }
    #user_id + .select2 .select2-selection--single:focus{
      outline:none; box-shadow:0 0 0 .2rem rgba(220,53,69,.25);
    }
    .select2-container{ width:100% !important; }

    /* === Select de SUCURSALES (multiple) — chips con alto contraste y SIN superponer la “x” === */
    :root{
      --sel-bg:#f8fafc;     /* fondo caja */
      --sel-border:#b6c4d6; /* borde caja */
      --pill-bg:#0d6efd;    /* fondo chip */
      --pill-border:#0b5ed7;/* borde chip */
      --pill-text:#ffffff;  /* texto chip */
    }
    /* Caja multiple */
    #sucursales + .select2 .select2-selection--multiple{
      background:var(--sel-bg); color:#111; border:1px solid var(--sel-border); min-height:38px;
    }
    /* Contenedor chips */
    #sucursales + .select2 .select2-selection__rendered{
      display:flex; flex-wrap:wrap; gap:6px; padding:4px 6px;
    }
    /* Chip */
    #sucursales + .select2 .select2-selection__choice{
      position:relative;
      background:var(--pill-bg)!important; color:var(--pill-text)!important;
      border:1px solid var(--pill-border)!important; border-radius:9999px;
      padding:4px 12px 4px 28px;   /* espacio a la izquierda para la “x” */
      margin:0; font-weight:700; line-height:1.2;
      box-shadow:0 1px 0 rgba(0,0,0,.05);
    }
    /* Botón “x” del chip: absoluto a la izquierda, centrado vertical */
    #sucursales + .select2 .select2-selection__choice__remove{
      position:absolute; left:8px; top:50%; transform:translateY(-50%);
      color:#ffffff!important; margin:0; font-weight:900; font-size:14px;
      width:16px; height:16px; line-height:16px; text-align:center;
    }
    /* Input de búsqueda inline */
    .select2-search--inline .select2-search__field{
      border:none!important; box-shadow:none!important; padding:2px 4px; min-width:10px;
    }
    /* Dropdown */
    .select2-dropdown{ background:#fff; color:#111; border:1px solid #cbd5e1; }
    .select2-results__option--highlighted[aria-selected]{ background:#0d6efd; color:#fff; }
    .select2-search--dropdown .select2-search__field{ background:#fff; color:#111; border:1px solid #cbd5e1; }

    /* Badges del modal “Asignadas” (más grandes) */
    .badge-branch{
      background:#eef4ff; color:#0b5ed7; border:1px solid #cfe0ff;
      border-radius:8px; padding:.35rem .6rem; font-size:14px; font-weight:600;
      display:inline-block; margin:.2rem .25rem;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">

  <?php include './config/header.php'; ?>
  <?php include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <!-- Encabezado -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1><i class="fas fa-store"></i> Acceso por Sucursales</h1></div>
        </div>
      </div>
    </section>

    <!-- Contenido -->
    <section class="content">

      <!-- ======================= Tarjeta: CRUD de Sucursales ======================= -->
      <div class="card card-outline card-primary rounded-0 shadow">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-warehouse"></i> Sucursales</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Colapsar">
              <i class="fas fa-minus"></i>
            </button>
          </div>
        </div>

        <div class="card-body">
          <form id="frmSucursal" onsubmit="return false;">
            <div class="row align-items-end">
              <input type="hidden" id="sucursal_id" value="0">
              <div class="col-lg-4 col-md-6">
                <label>Nombre de Sucursal</label>
                <input type="text" id="sucursal_nombre" class="form-control form-control-sm rounded-0" required>
              </div>
              <div class="col-lg-2 col-md-3">
                <label>&nbsp;</label>
                <button type="button" id="btnGuardarSucursal" class="btn btn-primary btn-sm btn-flat btn-block">
                  Guardar
                </button>
              </div>
              <div class="col-lg-2 col-md-3">
                <label>&nbsp;</label>
                <button type="button" id="btnLimpiarSucursal" class="btn btn-secondary btn-sm btn-flat btn-block">
                  Limpiar
                </button>
              </div>
            </div>
          </form>

          <hr>

          <div class="row table-responsive">
            <table id="tblSucursales" class="table table-striped dataTable table-bordered dtr-inline">
              <colgroup>
                <col width="8%"><col width="62%"><col width="15%"><col width="15%">
              </colgroup>
              <thead>
                <tr>
                  <th class="text-center">#</th>
                  <th>Nombre</th>
                  <th class="text-center">Estado</th>
                  <th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i=0;
                foreach ($sucursales as $s) {
                  $i++;
                  $badge = ((int)$s['estado']===1) ? '<span class="badge badge-success">Activa</span>' :
                                                     '<span class="badge badge-secondary">Inactiva</span>';
                  echo '<tr id="row-suc-'.$s['id'].'">';
                  echo '  <td class="text-center">'.$i.'</td>';
                  echo '  <td>'.htmlspecialchars($s['nombre']).'</td>';
                  echo '  <td class="text-center">'.$badge.'</td>';
                  echo '  <td class="text-center">
                            <div class="actions">
                              <button type="button" class="btn btn-primary btn-sm btn-icon btn-edit" data-id="'.$s['id'].'" data-name="'.htmlspecialchars($s['nombre']).'"><i class="fa fa-edit"></i></button>
                              <button type="button" class="btn btn-danger btn-sm btn-icon btn-delete" data-id="'.$s['id'].'" data-name="'.htmlspecialchars($s['nombre']).'"><i class="fa fa-trash"></i></button>
                            </div>
                          </td>';
                  echo '</tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- =================== Tarjeta: Asignar Sucursales a Usuario =================== -->
      <div class="card card-outline card-primary rounded-0 shadow">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-user-tag"></i> Asignar Acceso a Usuarios</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Colapsar">
              <i class="fas fa-minus"></i>
            </button>
          </div>
        </div>

        <div class="card-body">
          <form id="frmAcceso" onsubmit="return false;">
            <div class="row">
              <div class="col-lg-4 col-md-6">
                <label>Usuario</label>
                <!-- id fijo: user_id -->
                <select id="user_id" class="form-control form-control-sm rounded-0">
                  <option value="">Selecciona Usuario</option>
                  <?php
                  foreach ($usuarios as $u) {
                    $label = trim($u['nombre_mostrar'].' ('.$u['usuario'].')');
                    echo '<option value="'.$u['id'].'">'.htmlspecialchars($label).'</option>';
                  }
                  ?>
                </select>
              </div>

              <div class="col-lg-6 col-md-6">
                <label>Sucursales</label>
                <!-- id fijo: sucursales -->
                <select id="sucursales" class="form-control form-control-sm rounded-0" multiple size="6">
                  <?php
                  foreach ($sucursales as $s) {
                    if ((int)$s['estado'] !== 1) continue; // solo activas
                    echo '<option value="'.$s['id'].'">'.htmlspecialchars($s['nombre']).'</option>';
                  }
                  ?>
                </select>

                <div class="mt-2 d-flex align-items-center">
                  <button type="button" class="btn btn-xs btn-outline-secondary mr-2" id="btn_sel_todo">Seleccionar todo</button>
                  <button type="button" class="btn btn-xs btn-outline-secondary mr-2" id="btn_sel_nada">Ninguno</button>
                  <button type="button" class="btn btn-xs btn-info" id="btn_ver_asignadas"><i class="fas fa-eye mr-1"></i> Ver asignadas</button>
                </div>

                <!-- Vista oculta (fuente del modal) -->
                <div class="mt-2 d-none" id="asignadas_view">
                  <span class="badge badge-secondary">Sin sucursales asignadas</span>
                </div>
              </div>

              <div class="col-lg-2 col-md-3">
                <label>&nbsp;</label>
                <button type="button" id="btnGuardarAcceso" class="btn btn-primary btn-sm btn-flat btn-block">Guardar</button>
              </div>
            </div>
          </form>
        </div>
      </div>

    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>

<!-- Select2 + SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
  // Helper SweetAlert
  window.showCustomMessage = function (msg, type) {
    Swal.fire({
      title: 'Mensaje',
      text: msg || '',
      icon: type || 'info',
      confirmButtonText: 'OK',
      confirmButtonColor: '#0d6efd'
    });
  };

  // Menú activo
  showMenuSelected("#mnu_users", "#mi_users_branches");

  // Mensaje GET
  (function(){
    var m = <?php echo json_encode($message); ?>;
    if(m){ showCustomMessage(m, 'success'); }
  })();

  // DataTable sucursales
  $(function(){
    const exportCols = [0,1,2];
    const dt = $("#tblSucursales").DataTable({
      responsive:true, lengthChange:false, autoWidth:false,
      buttons: [
        { extend:'copyHtml5',  className:'btn btn-secondary btn-sm', text:'Copiar',  exportOptions:{columns:exportCols} },
        { extend:'csvHtml5',   className:'btn btn-info btn-sm',     text:'CSV',     exportOptions:{columns:exportCols} },
        { extend:'excelHtml5', className:'btn btn-success btn-sm',  text:'Excel',   exportOptions:{columns:exportCols} },
        { extend:'pdfHtml5',   className:'btn btn-danger btn-sm',   text:'PDF',     exportOptions:{columns:exportCols}, title:'Sucursales' },
        { extend:'print',      className:'btn btn-primary btn-sm',  text:'Imprimir',exportOptions:{columns:exportCols} },
        { extend:'colvis',     className:'btn btn-warning btn-sm',  text:'Columnas' }
      ],
      language:{
        lengthMenu:"Mostrar _MENU_ registros por página",
        zeroRecords:"No se encontraron resultados",
        info:"Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty:"Mostrando 0 a 0 de 0 registros",
        infoFiltered:"(filtrado de _MAX_ registros totales)",
        search:"Buscar:",
        paginate:{ first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" },
        buttons:{ copy:"Copiar", csv:"CSV", excel:"Excel", pdf:"PDF", print:"Imprimir", colvis:"Columnas" }
      }
    });
    dt.buttons().container().appendTo('#tblSucursales_wrapper .col-md-6:eq(0)');
  });

  // ========================= Select2 =========================
  $(function(){
    // SUCURSALES multiple (chips con “x” a la izquierda sin superponer texto)
    $('#sucursales').select2({
      width:'100%',
      placeholder:'Elige una o más sucursales',
      closeOnSelect:false
    });
    // USUARIO single (rojo)
    $('#user_id').select2({ width:'100%', placeholder:'Selecciona usuario' });

    // Cambia usuario => carga asignadas y preselecciona
    $('#user_id').on('change', function(){
      const uid = $(this).val();
      if (!uid) {
        $('#asignadas_view').html('<span class="badge badge-secondary">Sin sucursales asignadas</span>');
        $('#sucursales').val(null).trigger('change');
        return;
      }
      cargarAsignadas(uid);
    });

    // Seleccionar todo / ninguno
    $('#btn_sel_todo').on('click', function(){
      const allVals = $('#sucursales option').map(function(){ return this.value; }).get();
      $('#sucursales').val(allVals).trigger('change');
    });
    $('#btn_sel_nada').on('click', function(){
      $('#sucursales').val(null).trigger('change');
    });

    // Ver asignadas en modal (SweetAlert)
    $('#btn_ver_asignadas').on('click', function(){
      const uid = $('#user_id').val();
      if(!uid){
        Swal.fire({icon:'info', title:'Asignadas', text:'Selecciona un usuario primero.', confirmButtonText:'OK'});
        return;
      }
      const html = $('#asignadas_view').html() || '<span class="badge badge-secondary">Sin sucursales asignadas</span>';
      Swal.fire({
        title: 'Sucursales asignadas',
        html: '<div style="text-align:left">'+ html +'</div>',
        icon: 'info',
        confirmButtonText: 'Cerrar',
        confirmButtonColor: '#0d6efd',
        width: 620
      });
    });
  });

  // ================== CRUD sucursal ==================
  $("#btnGuardarSucursal").on("click", function(){
    const id = parseInt($("#sucursal_id").val(),10)||0;
    const nombre = ($("#sucursal_nombre").val() || '').trim();
    if(!nombre){ showCustomMessage('Escribe el nombre de la sucursal', 'warning'); return; }

    $.get('ajax/verificar_nombre_sucursal.php', { nombre: nombre, id: id })
      .done(function(c){
        if(parseInt(c,10)>0){
          showCustomMessage('Ese nombre ya existe, elige otro.', 'warning');
        }else{
          $.post('ajax/guardar_sucursal.php', { id:id, nombre:nombre })
           .done(function(r){
             r=(r||'').trim();
             if(r==='OK'){ location.href='usuarios_sucursales.php?message='+encodeURIComponent('Sucursal guardada.'); }
             else{ showCustomMessage(r, 'error'); }
           })
           .fail(function(x){ showCustomMessage(x.responseText||'Error', 'error'); });
        }
      });
  });

  $("#btnLimpiarSucursal").on("click", function(){
    $("#sucursal_id").val('0');
    $("#sucursal_nombre").val('');
    $("#sucursal_nombre").focus();
  });

  $(document).on('click', '.btn-edit', function(){
    $("#sucursal_id").val($(this).data('id'));
    $("#sucursal_nombre").val($(this).data('name')).focus();
  });

  $(document).on('click', '.btn-delete', function(){
    const id = parseInt($(this).data('id'),10);
    const name = $(this).data('name');
    Swal.fire({
      title: 'Eliminar/Inactivar',
      html: '¿Deseas eliminar la sucursal <b>"'+name+'"</b>?<br><small>Si está en uso, se inactivará.</small>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc3545'
    }).then(function(r){
      if(!r.isConfirmed) return;
      $.post('ajax/eliminar_sucursal.php', { id:id })
       .done(function(resp){
         resp=(resp||'').trim();
         if(resp==='OK'){ location.href='usuarios_sucursales.php?message='+encodeURIComponent('Operación realizada.'); }
         else{ showCustomMessage(resp, 'error'); }
       })
       .fail(function(x){ showCustomMessage(x.responseText||'Error', 'error'); });
    });
  });

  // ================== Asignación de sucursales a usuario ==================
  $("#btnGuardarAcceso").on('click', function(){
    const uid = parseInt($("#user_id").val(),10)||0;
    if(!uid){ showCustomMessage('Selecciona un usuario', 'warning'); return; }
    const ids = $("#sucursales").val() || [];

    $.post('ajax/guardar_sucursales_usuario.php', { user_id: uid, sucursales: JSON.stringify(ids) })
     .done(function(r){
       r=(r||'').trim();
       if(r==='OK'){
         showCustomMessage('Accesos guardados', 'success');
         cargarAsignadas(uid); // refrescar chips ocultos
       } else { showCustomMessage(r, 'error'); }
     })
     .fail(function(x){ showCustomMessage(x.responseText||'Error', 'error'); });
  });

  // Cargar asignadas desde backend -> preselecciona y actualiza chips ocultos
  function cargarAsignadas(uid){
    $.getJSON('ajax/obtener_sucursales_usuario.php', { user_id: uid })
      .done(function(r){
        if(!r || !r.ok){
          $('#sucursales').val(null).trigger('change');
          $('#asignadas_view').html('<span class="badge badge-secondary">No se pudo cargar.</span>');
          return;
        }
        $('#sucursales').val(r.ids).trigger('change');

        if(!r.items || !r.items.length){
          $('#asignadas_view').html('<span class="badge badge-secondary">Sin sucursales asignadas</span>');
        } else {
          const badges = r.items.map(it =>
            '<span class="badge-branch">'+escapeHtml(it.nombre)+'</span>'
          ).join(' ');
          $('#asignadas_view').html('<div><small>Asignadas:</small><div class="mt-1">'+badges+'</div></div>');
        }
      })
      .fail(function(){
        $('#asignadas_view').html('<span class="badge badge-secondary">Error de conexión.</span>');
      });
  }

  // Helper para evitar HTML injection en badges
  function escapeHtml(str){
    return (str||'').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
  }
</script>
</body>
</html>
