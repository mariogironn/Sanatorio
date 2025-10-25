<?php
include './config/connection.php';
try{
  // Carga de roles para la tabla
  $st=$con->query("SELECT id_rol,nombre,descripcion,estado,creado_en FROM roles ORDER BY id_rol DESC");
  $roles=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $ex){ die($ex->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Roles</title>
  <style>
    .badge-estado{font-size:.85rem}
    .action-stack{display:flex;gap:6px;justify-content:center}
    .action-stack .btn{width:36px;height:36px;padding:0;border-radius:6px}
  </style>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-user-shield"></i> Roles</h1>
        <button class="btn btn-success btn-sm rounded-0" id="btnNuevo">
          <i class="fas fa-plus-circle"></i> Nuevo
        </button>
      </div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary rounded-0 shadow">
        <div class="card-header">
          <h3 class="card-title">Lista de Roles</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
          </div>
        </div>
        <div class="card-body table-responsive">
          <table id="tbl_roles" class="table table-striped table-bordered table-hover">
            <thead>
              <tr>
                <th class="text-center">ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($roles as $r): $id=(int)$r['id_rol']; $act=((int)$r['estado']===1); ?>
              <tr data-id="<?= $id ?>">
                <td class="text-center"><?= $id ?></td>
                <td><?= htmlspecialchars($r['nombre']) ?></td>
                <td><?= htmlspecialchars($r['descripcion']) ?></td>
                <td class="text-center">
                  <?php if($act): ?>
                    <span class="badge badge-success badge-estado">Activo</span>
                  <?php else: ?>
                    <span class="badge badge-secondary badge-estado">Inactivo</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <div class="action-stack">
                    <!-- Permisos -->
                    <a class="btn btn-dark btn-sm" title="Permisos" href="permisos_roles.php?id_rol=<?= $id ?>">
                      <i class="fas fa-key"></i>
                    </a>

                    <!-- Editar -->
                    <button class="btn btn-primary btn-sm btnEditar" title="Editar"
                            data-id="<?= $id ?>"
                            data-nombre="<?= htmlspecialchars($r['nombre']) ?>"
                            data-descripcion="<?= htmlspecialchars($r['descripcion']) ?>"
                            data-estado="<?= (int)$r['estado'] ?>">
                      <i class="fas fa-pen"></i>
                    </button>

                    <!-- Activar / Inactivar -->
                    <button class="btn btn-sm btnToggleEstado <?= $act?'btn-warning':'btn-secondary' ?>"
                            title="<?= $act?'Inactivar':'Activar' ?>"
                            data-id="<?= $id ?>"
                            data-estado="<?= (int)$r['estado'] ?>">
                      <i class="fas <?= $act?'fa-toggle-on':'fa-toggle-off' ?>"></i>
                    </button>

                    <!-- Eliminar REAL -->
                    <button class="btn btn-danger btn-sm btnEliminar" title="Eliminar"
                            data-id="<?= $id ?>"
                            data-nombre="<?= htmlspecialchars($r['nombre']) ?>">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>

<script>
  showMenuSelected("#mnu_users", "#mi_users_roles");

  //inicializacion de datablese para gestion de roles
  $(function(){
    $("#tbl_roles").DataTable({
      responsive: true,v //hace la tabla adaptable a dispositivos moviles
      language: {
        decimal: "",
        emptyTable: "No hay datos disponibles en la tabla",
        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty: "Mostrando 0 a 0 de 0 registros",
        infoFiltered: "(filtrado de _MAX_ registros totales)",
        lengthMenu: "Mostrar _MENU_ registros",
        loadingRecords: "Cargando...",
        processing: "Procesando...",
        search: "Buscar:",
        zeroRecords: "No se encontraron registros coincidentes",
        paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" },
        buttons: { copy: "Copiar", csv: "CSV", excel: "Excel", pdf: "PDF", print: "Imprimir", colvis: "Columnas" }
      }
    });
  });

  // Mensajes helper
  function toast(msg, icon='success', title='Mensaje'){
    if (typeof Swal!=='undefined') Swal.fire({icon, title, text: String(msg||''), confirmButtonText:'Aceptar'});
    else alert((title?title+': ':'')+(msg||''));
  }

  // ===== Nuevo =====
  $("#btnNuevo").on('click', function(){
    Swal.fire({
      title: 'Nuevo Rol',
      html: `
        <div style="text-align:left">
          <label for="swal_nombre" style="font-weight:600;">Nombre</label>
          <input id="swal_nombre" class="swal2-input" placeholder="Nombre del rol" maxlength="50">

          <label for="swal_descripcion" style="font-weight:600;">Descripción</label>
          <textarea id="swal_descripcion" class="swal2-textarea" placeholder="Descripción (opcional)" maxlength="150"></textarea>

          <label for="swal_estado" style="font-weight:600;">Estado</label>
          <select id="swal_estado" class="swal2-select">
            <option value="1" selected>Activo</option>
            <option value="0">Inactivo</option>
          </select>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true,
      showLoaderOnConfirm: true,
      allowOutsideClick: () => !Swal.isLoading(),
      preConfirm: () => {
        const nombre = (document.getElementById('swal_nombre').value || '').trim();
        const descripcion = (document.getElementById('swal_descripcion').value || '').trim();
        const estado = document.getElementById('swal_estado').value;
        if (!nombre){ Swal.showValidationMessage('El nombre es obligatorio'); return false; }
        return $.post('ajax/guardar_rol.php', { nombre, descripcion, estado })
          .then(r=>{ r=(r||'').trim(); if(!r.startsWith('OK')) throw new Error(r||'No se pudo guardar.'); return true; })
          .catch(err=>{ Swal.showValidationMessage(err.message||'Error al guardar.'); return false; });
      }
    }).then(res=>{ if(res.isConfirmed){ toast('Rol creado correctamente.'); location.reload(); } });
  });

  // ===== Editar =====
  $(document).on('click', '.btnEditar', function(){
    const id    = $(this).data('id');
    const nombre= $(this).data('nombre') || '';
    const desc  = $(this).data('descripcion') || '';
    const estado= String($(this).data('estado'));

    Swal.fire({
      title: 'Actualizar Rol',
      html: `
        <div style="text-align:left">
          <label for="swal_nombre" style="font-weight:600;">Nombre</label>
          <input id="swal_nombre" class="swal2-input" maxlength="50" value="${nombre.replace(/"/g,'&quot;')}">

          <label for="swal_descripcion" style="font-weight:600;">Descripción</label>
          <textarea id="swal_descripcion" class="swal2-textarea" maxlength="150">${desc}</textarea>

          <label for="swal_estado" style="font-weight:600;">Estado</label>
          <select id="swal_estado" class="swal2-select">
            <option value="1"${estado==='1'?' selected':''}>Activo</option>
            <option value="0"${estado==='0'?' selected':''}>Inactivo</option>
          </select>
        </div>
      `,
      focusConfirm:false, showCancelButton:true, confirmButtonText:'Guardar', cancelButtonText:'Cancelar',
      reverseButtons:true, showLoaderOnConfirm:true, allowOutsideClick:()=>!Swal.isLoading(),
      preConfirm:()=>{
        const nombre = (document.getElementById('swal_nombre').value || '').trim();
        const descripcion = (document.getElementById('swal_descripcion').value || '').trim();
        const estado = document.getElementById('swal_estado').value;
        if(!nombre){ Swal.showValidationMessage('El nombre es obligatorio'); return false; }
        return $.post('ajax/actualizar_rol.php', { id_rol:id, nombre, descripcion, estado })
          .then(r=>{ r=(r||'').trim(); if(r!=='OK') throw new Error(r||'No se pudo actualizar.'); return true; })
          .catch(err=>{ Swal.showValidationMessage(err.message||'Error al actualizar.'); return false; });
      }
    }).then(res=>{ if(res.isConfirmed){ toast('Rol actualizado correctamente.'); location.reload(); } });
  });

  // ===== Activar / Inactivar =====
  $(document).on('click', '.btnToggleEstado', function(){
    const id  = $(this).data('id');
    const est = parseInt($(this).data('estado'),10);
    const nuevo = (est===1?0:1);
    const verbo = (nuevo===1?'activar':'inactivar');
    Swal.fire({
      icon:'warning', title:'¿Confirmar?', text:`¿Seguro que deseas ${verbo} este rol?`,
      showCancelButton:true, confirmButtonText:'Sí, continuar', cancelButtonText:'Cancelar',
      reverseButtons:true, showLoaderOnConfirm:true,
      preConfirm:()=> $.post('ajax/cambiar_estado_rol.php', { id_rol:id, nuevo })
                      .then(r=>{ r=(r||'').trim(); if(r!=='OK') throw new Error(r||'No se pudo actualizar el estado.'); return true; })
                      .catch(err=>{ Swal.showValidationMessage(err.message||'Error al actualizar el estado.'); return false; }),
      allowOutsideClick:()=>!Swal.isLoading()
    }).then(res=>{ if(res.isConfirmed){ toast('Estado actualizado.'); location.reload(); } });
  });

  // ===== Eliminar (REAL) =====
  $(document).on('click', '.btnEliminar', function(){
    const id   = $(this).data('id');
    const name = $(this).data('nombre') || '';
    Swal.fire({
      icon:'error',
      title:'Eliminar rol',
      html:`Esta acción es <b>permanente</b>.<br>¿Eliminar el rol <b>${name.replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}</b>?`,
      showCancelButton:true,
      confirmButtonText:'Eliminar',
      cancelButtonText:'Cancelar',
      confirmButtonColor:'#d33',
      reverseButtons:true,
      showLoaderOnConfirm:true,
      preConfirm:()=> $.post('ajax/eliminar_rol.php', { id_rol:id })
                      .then(r=>{ r=(r||'').trim(); if(r!=='OK') throw new Error(r||'No se pudo eliminar.'); return true; })
                      .catch(err=>{ Swal.showValidationMessage(err.message||'Error al eliminar.'); return false; }),
      allowOutsideClick:()=>!Swal.isLoading()
    }).then(res=>{
      if(res.isConfirmed){
        // Quita la fila sin recargar
        const $row = $('tr[data-id="'+id+'"]');
        const table = $('#tbl_roles').DataTable();
        table.row($row).remove().draw(false);
        toast('Rol eliminado.');
      }
    });
  });
</script>
</body>
</html>
