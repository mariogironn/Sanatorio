<?php
/**
 * usuarios.php
 * Lista + alta de usuarios (compatible con PK id o id_usuario)
 */

if (session_status() === PHP_SESSION_NONE) session_start();
include './config/connection.php';

// === Auditoría ===
require_once __DIR__ . '/common_service/auditoria_service.php';
require_once __DIR__ . '/common_service/audit_helpers.php';

$message = '';

// ---------- Detecta cómo se llama la PK en tu tabla (id o id_usuario) ----------
$PK = 'id';
try {
  $ck = $con->query("SHOW COLUMNS FROM usuarios LIKE 'id_usuario'");
  if ($ck && $ck->rowCount() > 0) { $PK = 'id_usuario'; }
} catch (Throwable $e) { /* ignorar */ }

// ---------- Alta de usuario (por modal) ----------
if (isset($_POST['save_user'])) {
  $displayName = trim($_POST['display_name'] ?? '');
  $userName    = trim($_POST['user_name'] ?? '');
  $password    = $_POST['password'] ?? '';

  if ($displayName === '' || $userName === '' || $password === '') {
    $message = 'Completa nombre, usuario y contraseña.';
    header("location:congratulation.php?goto_page=usuarios.php&message=".urlencode($message));
    exit;
  }

  $encryptedPassword = password_hash($password, PASSWORD_DEFAULT);

  // Imagen (opcional)
  $targetFile = null;
  if (!empty($_FILES["profile_picture"]["name"])) {
    @mkdir('user_images', 0777, true);
    $baseName   = basename($_FILES["profile_picture"]["name"]);
    $safeName   = str_replace(' ', '_', $baseName);
    $targetFile = time() . '_' . $safeName;
    if (!move_uploaded_file($_FILES["profile_picture"]["tmp_name"], 'user_images/' . $targetFile)) {
      $targetFile = null;
    }
  }

  try {
    $con->beginTransaction();
    // No fuerzo "estado": que use el DEFAULT de tu tabla.
    $sql = "INSERT INTO usuarios (nombre_mostrar, usuario, contrasena, imagen_perfil)
            VALUES (:n,:u,:p,:img)";
    $st  = $con->prepare($sql);
    $st->execute([
      ':n' => $displayName,
      ':u' => $userName,
      ':p' => $encryptedPassword,
      ':img' => $targetFile
    ]);

    // ===== AUDITORÍA: snapshot DESPUÉS + audit_create =====
    try {
      // Intentar con lastInsertId()
      $newId = $con->lastInsertId();
      if (!is_numeric($newId) || (int)$newId <= 0) {
        // Fallback: obtener ID por usuario (por si la PK no es AI)
        $aux = $con->prepare("SELECT `$PK` AS pk FROM usuarios WHERE usuario = :u ORDER BY `$PK` DESC LIMIT 1");
        $aux->execute([':u' => $userName]);
        $rowAux = $aux->fetch(PDO::FETCH_ASSOC);
        if ($rowAux && isset($rowAux['pk'])) { $newId = (int)$rowAux['pk']; }
      } else {
        $newId = (int)$newId;
      }

      // Leer fila recién creada
      $despues = null;
      if ($newId > 0) {
        $despues = audit_row($con, 'usuarios', $PK, $newId);
      } else {
        // Último recurso: intenta por usuario
        $aux2 = $con->prepare("SELECT * FROM usuarios WHERE usuario = :u ORDER BY `$PK` DESC LIMIT 1");
        $aux2->execute([':u' => $userName]);
        $despues = $aux2->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($despues && isset($despues[$PK])) { $newId = (int)$despues[$PK]; }
      }

      // Enmascarar campos sensibles
      if (is_array($despues)) {
        unset($despues['contrasena'], $despues['password'], $despues['pass'], $despues['pwd']);
      }

      // Inferir estado_resultante si tu tabla lo maneja
      $estado = null;
      if (is_array($despues)) {
        if (array_key_exists('estado', $despues)) {
          $v = strtolower((string)$despues['estado']);
          if ($v === 'activo' || $v === 'inactivo') $estado = $v; else $estado = $despues['estado'];
        } elseif (array_key_exists('activo', $despues)) {
          $estado = ((int)$despues['activo'] === 1) ? 'activo' : 'inactivo';
        }
      }

      // Registrar auditoría CREATE (no romper si falla)
      try {
        audit_create($con, 'usuarios', 'usuarios', $newId, $despues, $estado ?? 'activo');
      } catch (Throwable $eAud) {
        error_log('AUDITORIA CREATE usuarios: ' . $eAud->getMessage());
      }
    } catch (Throwable $eSnap) {
      // No romper el flujo de inserción por auditoría
      error_log('AUDITORIA CREATE SNAP usuarios: ' . $eSnap->getMessage());
    }

    $con->commit();
    $message = 'Usuario registrado correctamente';
  } catch(PDOException $ex){
    if($con->inTransaction()) $con->rollBack();
    // Mensaje legible si el usuario está duplicado
    if (strpos($ex->getMessage(), 'Duplicate') !== false) {
      $message = 'El usuario ya existe. Elige otro nombre.';
    } else {
      $message = 'Error al guardar: '.$ex->getMessage();
    }
  }

  header("location:congratulation.php?goto_page=usuarios.php&message=".urlencode($message));
  exit;
}

// ---------- Listado ----------
$rows = [];
$lastError = '';
try {
  $q = "SELECT
          u.$PK AS id,
          u.nombre_mostrar,
          u.usuario,
          u.imagen_perfil,
          u.estado,
          (
            SELECT r.nombre
            FROM usuario_rol ur
            JOIN roles r ON r.id_rol = ur.id_rol
            WHERE ur.id_usuario = u.$PK
            ORDER BY r.nombre
            LIMIT 1
          ) AS rol_nombre
        FROM usuarios u
        ORDER BY u.nombre_mostrar";
  $st = $con->prepare($q);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $ex){
  $lastError = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Usuarios</title>
  <style>
    .user-img{width:36px;height:36px;object-fit:cover;object-position:center}
    .table-sm td,.table-sm th{padding:.45rem .5rem}
    .table td,.table th{vertical-align:middle!important}
    .actions{display:inline-flex;gap:.35rem}
    .actions .btn{width:34px;height:34px;padding:0;border-radius:.4rem;display:flex;align-items:center;justify-content:center}
    .badge-estado{font-size:.82rem}
  </style>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-users"></i> Usuarios</h1>
        <button class="btn btn-success btn-sm rounded-0" data-toggle="modal" data-target="#modalNuevoUsuario">
          <i class="fas fa-plus-circle"></i> Nuevo
        </button>
      </div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary rounded-0 shadow">
        <div class="card-header">
          <h3 class="card-title">Todos los Usuarios</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
          </div>
        </div>
        <div class="card-body">
          <?php if ($lastError && empty($rows)): ?>
            <div class="alert alert-warning p-2 mb-3">
              No se pudieron cargar usuarios. <small><?= htmlspecialchars($lastError) ?></small>
            </div>
          <?php endif; ?>
          <div class="table-responsive">
            <table id="all_users" class="table table-sm table-striped table-hover table-bordered">
              <thead>
                <tr>
                  <th class="text-center">No. serie</th>
                  <th class="text-center">ID</th>
                  <th class="text-center">Foto de Perfil</th>
                  <th>Nombre</th>
                  <th>Usuario</th>
                  <th>Rol</th>
                  <th class="text-center">Estado</th>
                  <th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach($rows as $r):
                $id  = (int)$r['id'];
                $img = $r['imagen_perfil'] ? 'user_images/'.htmlspecialchars($r['imagen_perfil']) : 'user_images/default-user.png';
                $estadoRaw = (string)($r['estado'] ?? '');
                $up = strtoupper($estadoRaw);
                $isActivo = ($up==='ACTIVO' || $up==='A' || $estadoRaw==='1' || $up==='SI' || $up==='TRUE');
              ?>
                <tr id="user-row-<?= $id ?>">
                  <td class="text-center"></td>
                  <td class="text-center"><?= $id ?></td>
                  <td class="text-center">
                    <img src="<?= $img ?>" class="img-thumbnail rounded-circle p-0 border user-img" alt="">
                  </td>
                  <td><?= htmlspecialchars($r['nombre_mostrar'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['usuario'] ?? '') ?></td>
                  <td><?= !empty($r['rol_nombre']) ? htmlspecialchars($r['rol_nombre']) : '—' ?></td>
                  <td class="text-center">
                    <span class="badge badge-estado <?= $isActivo?'badge-success':'badge-secondary' ?>">
                      <?= $isActivo?'Activo':'Inactivo' ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <div class="actions">
                      <button class="btn btn-dark btn-toggle-estado" title="<?= $isActivo?'Bloquear':'Activar' ?>"
                              data-id="<?= $id ?>" data-estado="<?= $isActivo?'ACTIVO':'INACTIVO' ?>">
                        <i class="fas <?= $isActivo?'fa-toggle-on':'fa-toggle-off' ?>"></i>
                      </button>
                      <button class="btn btn-warning btn-roles" title="Asignar roles"
                              data-id="<?= $id ?>" data-name="<?= htmlspecialchars($r['nombre_mostrar'] ?? '') ?>">
                        <i class="fas fa-id-badge"></i>
                      </button>
                      <a class="btn btn-primary" title="Editar" href="actualizar_usuario.php?user_id=<?= $id ?>">
                        <i class="fa fa-pen"></i>
                      </a>
                      <button class="btn btn-danger btn-delete" title="Eliminar"
                              data-id="<?= $id ?>" data-name="<?= htmlspecialchars($r['nombre_mostrar'] ?? '') ?>">
                        <i class="fa fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>

<!-- ✅ Mantener abierto el menú "Usuarios" y resaltar este subitem -->
<script>
  (function ensureUsersMenuActive(){
    function mark(){
      if (typeof showMenuSelected === 'function') {
        showMenuSelected("#mnu_users", "#mi_users_list");
      } else if (window.jQuery) {
        $('#mnu_users').addClass('menu-open').children('a').addClass('active');
        $('#mi_users_list').addClass('active');
      }
    }
    if (window.jQuery) { $(mark); } else { document.addEventListener('DOMContentLoaded', mark); }
  })();
</script>

<!-- Modal: Nuevo Usuario -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content rounded-0">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus"></i> Nuevo Usuario</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Mostrar Nombre</label>
              <input type="text" name="display_name" class="form-control form-control-sm rounded-0" required>
            </div>
            <div class="form-group col-md-4">
              <label>Usuario</label>
              <input type="text" name="user_name" id="user_name_modal" class="form-control form-control-sm rounded-0" required>
            </div>
            <div class="form-group col-md-4">
              <label>Contraseña</label>
              <input type="password" name="password" class="form-control form-control-sm rounded-0" required>
            </div>
            <div class="form-group col-md-6">
              <label>Imagen de Perfil</label>
              <input type="file" name="profile_picture" class="form-control form-control-sm rounded-0">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
          <button type="submit" name="save_user" class="btn btn-primary btn-sm">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const BASE = "<?= rtrim(dirname($_SERVER['PHP_SELF']),'/').'/'; ?>";
  const api  = (p) => BASE + p.replace(/^\/+/, '');

  window.showCustomMessage = function(texto, icono='success'){
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon: icono, title: 'Mensaje', text: (texto||'').toString(), confirmButtonText: 'Aceptar' });
    } else { alert('Mensaje: ' + (texto||'')); }
  };

  $(function(){
    $("#all_users").DataTable({
      responsive:true,
      pageLength:10,
      order:[[3,'asc']],
      dom:'<"row mb-2"<"col-sm-6"l><"col-sm-6 text-right"Bf>>t<"row mt-2"<"col-sm-5"i><"col-sm-7"p>>',
      columnDefs:[{
        targets:0, orderable:false, searchable:false,
        render:(d,t,r,m)=> m.row + m.settings._iDisplayStart + 1
      }],
      buttons:[
        {extend:'copyHtml5',  className:'btn btn-secondary btn-sm rounded-0', text:'Copiar',  exportOptions:{columns:[0,1,3,4,5,6]}},
        {extend:'excelHtml5', className:'btn btn-success btn-sm rounded-0',   text:'Excel',   exportOptions:{columns:[0,1,3,4,5,6]}},
        {extend:'csvHtml5',   className:'btn btn-info btn-sm rounded-0',      text:'CSV',     exportOptions:{columns:[0,1,3,4,5,6]}},
        {extend:'pdfHtml5',   className:'btn btn-danger btn-sm rounded-0',    text:'PDF',
          title:'Usuarios', exportOptions:{columns:[0,1,3,4,5,6]},
          customize:function(doc){
            if (doc.content && doc.content.length) doc.content[0].alignment='center';
            let i=doc.content.findIndex(x=>x.table); if(i>-1){
              const body=doc.content[i].table.body; if(body&&body.length){
                doc.content[i].table.widths=Array(body[0].length).fill('*');
              }
            }
            doc.styles.tableHeader.alignment='center';
          }
        },
        {extend:'print',      className:'btn btn-primary btn-sm rounded-0',   text:'Imprimir',
          title:'Usuarios', exportOptions:{columns:[0,1,3,4,5,6]},
          customize:win=>{
            $(win.document.body).find('h1').css('text-align','center');
            $(win.document.body).find('table').css('width','100%');
            $(win.document.body).find('table thead th').css('text-align','center');
          }
        },
        {extend:'colvis',     className:'btn btn-warning btn-sm rounded-0',   text:'Columnas'}
      ],
      language:{
        emptyTable:"No hay datos disponibles en la tabla",
        info:"Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty:"Mostrando 0 a 0 de 0 registros",
        infoFiltered:"(filtrado de _MAX_ registros totales)",
        lengthMenu:"Mostrar _MENU_ registros",
        search:"Buscar:",
        zeroRecords:"No se encontraron registros coincidentes",
        paginate:{ first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" },
        buttons:{ copy:"Copiar", csv:"CSV", excel:"Excel", pdf:"PDF", print:"Imprimir", colvis:"Columnas" }
      }
    });
  });

  // Eliminar
  $(document).on('click','.btn-delete',function(){
    const id   = $(this).data('id');
    const name = $(this).data('name');
    Swal.fire({
      icon:'warning', title:'Eliminar', text:'¿Eliminar al usuario "'+(name||'')+'"?',
      showCancelButton:true, confirmButtonText:'Eliminar', cancelButtonText:'Cancelar', reverseButtons:true
    }).then(res=>{
      if(!res.isConfirmed) return;
      $.post(api('ajax/eliminar_usuario.php'), {user_id:id})
       .done(r=>{
         r=(r||'').trim();
         if(r==='OK'){ $('#user-row-'+id).remove(); showCustomMessage('Eliminado.'); }
         else{ showCustomMessage(r||'No se pudo eliminar.','error');}
       })
       .fail(x=>showCustomMessage(x.responseText||'Error de servidor.','error'));
    });
  });

  // Cambiar estado
  $(document).on('click','.btn-toggle-estado',function(){
    const id   = $(this).data('id');
    const est  = (''+$(this).data('estado')).toUpperCase();
    const nuevo = (est==='ACTIVO'?'INACTIVO':'ACTIVO');
    Swal.fire({
      icon:'question', title:'Cambiar estado', text:'¿Cambiar el estado a '+nuevo+'?',
      showCancelButton:true, confirmButtonText:'Confirmar', cancelButtonText:'Cancelar', reverseButtons:true
    }).then(res=>{
      if(!res.isConfirmed) return;
      $.post(api('ajax/cambiar_estado_usuario.php'), {user_id:id, nuevo:nuevo})
       .done(t=>{
         t=(t||'').trim();
         if(t==='OK'){
           const $row=$('#user-row-'+id), $badge=$row.find('.badge-estado'), $btn=$row.find('.btn-toggle-estado'), $icon=$btn.find('i');
           if (nuevo==='INACTIVO'){ $badge.removeClass('badge-success').addClass('badge-secondary').text('Inactivo'); $btn.data('estado','INACTIVO'); $icon.removeClass('fa-toggle-on').addClass('fa-toggle-off'); }
           else { $badge.removeClass('badge-secondary').addClass('badge-success').text('Activo'); $btn.data('estado','ACTIVO'); $icon.removeClass('fa-toggle-off').addClass('fa-toggle-on'); }
           showCustomMessage('Estado actualizado.');
         } else {
           showCustomMessage(t||'No se pudo actualizar el estado.','error');
         }
       })
       .fail(x=>showCustomMessage(x.responseText||'Error de servidor.','error'));
    });
  });

  /* =========================
     ASIGNAR ROLES (SweetAlert2)
     ========================= */
  function escHtml(s){
    return String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  $(document).on('click','.btn-roles',function(){
    const uid  = $(this).data('id');
    const name = $(this).data('name') || '';

    $.get(api('ajax/cargar_roles_usuario.php'), { user_id: uid }, null, 'json')
      .done(function(resp){
        if(!resp || resp.ok === false){
          showCustomMessage((resp && resp.msg) ? resp.msg : 'No se pudieron cargar los roles.','error');
          return;
        }

        const roles = resp.roles || [];
        // Construimos un mapa id->nombre para recuperarlo tras guardar
        const roleNameById = {};
        const items = roles.map(r=>{
          const id  = Number((r.id_rol ?? r.id ?? r.rol_id) || 0);
          const nom = escHtml(r.nombre ?? r.name ?? '');
          const asig = (r.asignado===1 || r.asignado==="1" || r.asignado===true);
          roleNameById[id] = nom;
          return `
            <label class="swal2-checkbox role-item" style="display:flex;align-items:center;gap:.5rem">
              <input type="checkbox" class="sw-rol" value="${id}" ${asig?'checked':''}>
              <span>${nom}</span>
            </label>`;
        }).join('') || '<div class="text-muted">No hay roles disponibles.</div>';

        const titulo = `Roles de ${escHtml(resp.usuario?.nombre || name)}`;

        Swal.fire({
          width: 520,
          title: titulo,
          html: `
            <div id="swal_roles_wrap" style="display:flex;justify-content:center;">
              <div id="swal_roles" style="display:flex;flex-direction:column;align-items:flex-start;gap:.5rem;max-height:360px;overflow:auto;padding:.25rem;">
                ${items}
              </div>
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
            const ids = [];
            $('#swal_roles .sw-rol:checked').each(function(){ ids.push(parseInt(this.value,10)); });
            // Enviamos un ARRAY estándar (roles[])
            return $.post(api('ajax/guardar_roles_usuario.php'), { user_id: uid, 'roles': ids })
              .then(t=>{
                t = (t||'').trim();
                if(t!=='OK' && !t.startsWith('OK')) throw new Error(t || 'No se pudo guardar.');
                // Devolvemos nombres para actualizar la tabla
                const names = ids.map(id=>roleNameById[id]).filter(Boolean);
                return names;
              })
              .catch(err=>{
                Swal.showValidationMessage(err.message || 'Error al guardar.');
                return false;
              });
          }
        }).then(res=>{
          if(res.isConfirmed){
            const names = (res.value||[]).slice().sort((a,b)=>a.localeCompare(b,'es',{sensitivity:'base'}));
            const principal = names[0] || '—';
            const table = $('#all_users').DataTable();
            const $row  = $('#user-row-'+uid);
            const cell  = $row.find('td').eq(5); // columna Rol
            table.cell(cell).data(principal).draw(false);
            showCustomMessage('Roles actualizados.');
          }
        });
      })
      .fail(function(xhr){
        showCustomMessage(xhr.responseText || 'Error al cargar roles.','error');
      });
  });
</script>
</body>
</html>
