<?php
include './config/connection.php';

$idRol = (int)($_GET['id_rol'] ?? 0);
if($idRol<=0){ die('Rol inválido.'); }

// datos del rol
$rol = $con->prepare("SELECT nombre FROM roles WHERE id_rol=:id");
$rol->execute([':id'=>$idRol]);
$rolNombre = ($r = $rol->fetch(PDO::FETCH_ASSOC)) ? $r['nombre'] : '';

// módulos + permisos actuales
// 👉 Filtramos explícitamente cualquier registro llamado “Auditoría” o con slug “auditoria”
$sql = "SELECT m.id_modulo, m.nombre, m.slug,
        COALESCE(rp.ver,0) ver, COALESCE(rp.crear,0) crear,
        COALESCE(rp.actualizar,0) actualizar, COALESCE(rp.eliminar,0) eliminar
        FROM modulos m
        LEFT JOIN rol_permiso rp ON rp.id_modulo=m.id_modulo AND rp.id_rol=:id
        WHERE (m.nombre <> 'Auditoría') AND (m.slug IS NULL OR m.slug <> 'auditoria')
        ORDER BY m.id_modulo";
$st = $con->prepare($sql); $st->execute([':id'=>$idRol]);
$mods = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Permisos</title>
  <style>
    .switch { position: relative; display: inline-block; width: 44px; height: 22px; }
    .switch input {display:none;}
    .slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#ccc; transition:.2s; border-radius:22px;}
    .slider:before { position:absolute; content:""; height:18px; width:18px; left:2px; bottom:2px; background:white; transition:.2s; border-radius:50%;}
    input:checked + .slider { background:#28a745; }
    input:checked + .slider:before { transform:translateX(22px); }
  </style>

  <!-- SweetAlert2 (solo para esta página; no interfiere con el resto del sitio) -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-key"></i> Permisos — Rol: <?= htmlspecialchars($rolNombre) ?></h1>
        <a href="usuarios_roles.php" class="btn btn-secondary btn-sm rounded-0"><i class="fas fa-arrow-left"></i> Volver a Roles</a>
      </div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary rounded-0 shadow">
        <div class="card-header"><h3 class="card-title">Matriz de permisos</h3></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead>
                <tr>
                  <th class="text-center">#</th>
                  <th>Módulo</th>
                  <th class="text-center">Ver</th>
                  <th class="text-center">Crear</th>
                  <th class="text-center">Actualizar</th>
                  <th class="text-center">Eliminar</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($mods)): ?>
                  <tr><td colspan="6" class="text-center text-muted">No hay módulos disponibles.</td></tr>
                <?php else: ?>
                  <?php $i=0; foreach($mods as $m): $i++; ?>
                  <tr data-mod="<?= (int)$m['id_modulo'] ?>">
                    <td class="text-center"><?= $i ?></td>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <?php foreach(['ver','crear','actualizar','eliminar'] as $k): ?>
                      <td class="text-center">
                        <label class="switch mb-0">
                          <input type="checkbox" class="perm" data-k="<?= $k ?>" <?= ((int)$m[$k]===1?'checked':'') ?>>
                          <span class="slider"></span>
                        </label>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="text-right">
            <button class="btn btn-success btn-sm" id="btnGuardar"><i class="fas fa-check"></i> Guardar</button>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include './config/footer.php'; ?>
</div>

<?php include './config/site_js_links.php'; ?>

<script>
  // Si existía una función showCustomMessage en tus JS globales, la sobreescribimos aquí
  // para que use SweetAlert2 en español y el título "Mensaje".
  function showCustomMessage(texto, icono = 'success', titulo = 'Mensaje') {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: icono,        // success | error | warning | info | question
        title: titulo,      // "Mensaje"
        text: (texto || '').toString(),
        confirmButtonText: 'Aceptar'
      });
    } else {
      // Fallback por si el CDN no cargó (no rompe flujo)
      alert((titulo ? (titulo + ': ') : '') + (texto || ''));
    }
  }

  showMenuSelected("#mnu_users", "#mi_users_perms");

  $('#btnGuardar').on('click', function(){
    const data = [];
    $('tbody tr').each(function(){
      const idm = parseInt($(this).data('mod'),10);
      if (!idm) return;
      const row = {id_modulo:idm, ver:0, crear:0, actualizar:0, eliminar:0};
      $(this).find('.perm').each(function(){
        row[$(this).data('k')] = this.checked ? 1 : 0;
      });
      data.push(row);
    });

    $.post('ajax/guardar_permisos_rol.php', {id_rol: <?= $idRol ?>, json: JSON.stringify(data)})
      .done(function(t){
        t=(t||'').trim();
        if(t==='OK'){
          showCustomMessage('Permisos guardados.', 'success', 'Mensaje');
        } else {
          showCustomMessage(t||'No se pudo guardar.', 'warning', 'Mensaje');
        }
      })
      .fail(function(x){
        showCustomMessage(x.responseText||'Error al guardar.', 'error', 'Mensaje');
      });
  });
</script>
</body>
</html>
