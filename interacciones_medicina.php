<?php
// interacciones_medicina.php  (versión por PACIENTE)
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once './config/connection.php';
require_once './common_service/common_functions.php';

// === Estado/mensajes ===
$message = $_GET['message'] ?? '';
$prePid  = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;

// ===== BD activa (fallback a la_esperanza) =====
try { $dbName = $con->query("SELECT DATABASE()")->fetchColumn(); } catch (Throwable $e) { $dbName = null; }
if (!$dbName) $dbName = 'la_esperanza';

// Helpers schema
function tableExists(PDO $con, $db, $table) {
  $q = $con->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=:db AND table_name=:t");
  $q->execute([':db'=>$db, ':t'=>$table]); return (int)$q->fetchColumn() > 0;
}
function colExists(PDO $con, $db, $table, $col) {
  $q = $con->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=:db AND table_name=:t AND column_name=:c");
  $q->execute([':db'=>$db, ':t'=>$table, ':c'=>$col]); return (int)$q->fetchColumn() > 0;
}

// ===== Tablas/columnas de catálogo =====
$TBL_INT = 'interacciones_medicamentos';
$TBL_MED = 'medicamentos';

if (!tableExists($con, $dbName, $TBL_INT)) {
  // si no existe, la creamos tal como la versión anterior
  try {
    $con->exec("
      CREATE TABLE `$dbName`.`$TBL_INT` (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        id_medicamento_a INT NOT NULL,
        id_medicamento_b INT NOT NULL,
        a_norm           INT AS (LEAST(id_medicamento_a, id_medicamento_b)) STORED,
        b_norm           INT AS (GREATEST(id_medicamento_a, id_medicamento_b)) STORED,
        severidad        ENUM('alta','media','baja') NOT NULL DEFAULT 'media',
        nota             VARCHAR(255) DEFAULT NULL,
        estado           TINYINT(1) NOT NULL DEFAULT 1,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_par (a_norm, b_norm)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
  } catch (Throwable $e) { /* silencioso */ }
}
$INT_ID  = colExists($con,$dbName,$TBL_INT,'id') ? 'id'
         : (colExists($con,$dbName,$TBL_INT,'id_interaccion') ? 'id_interaccion' : 'id');
$INT_A   = colExists($con,$dbName,$TBL_INT,'id_medicamento_a') ? 'id_medicamento_a'
         : (colExists($con,$dbName,$TBL_INT,'medicamento_a_id') ? 'medicamento_a_id'
         : (colExists($con,$dbName,$TBL_INT,'id_medicina_a')    ? 'id_medicina_a'    : 'id_medicamento_a'));
$INT_B   = colExists($con,$dbName,$TBL_INT,'id_medicamento_b') ? 'id_medicamento_b'
         : (colExists($con,$dbName,$TBL_INT,'medicamento_b_id') ? 'medicamento_b_id'
         : (colExists($con,$dbName,$TBL_INT,'id_medicina_b')    ? 'id_medicina_b'    : 'id_medicamento_b'));
$INT_SEV = colExists($con,$dbName,$TBL_INT,'severidad') ? 'severidad'
         : (colExists($con,$dbName,$TBL_INT,'severity')  ? 'severity'
         : (colExists($con,$dbName,$TBL_INT,'nivel')     ? 'nivel'     : 'severidad'));
$INT_NOTE= colExists($con,$dbName,$TBL_INT,'nota') ? 'nota'
         : (colExists($con,$dbName,$TBL_INT,'observacion') ? 'observacion'
         : (colExists($con,$dbName,$TBL_INT,'descripcion') ? 'descripcion' : 'nota'));
$HAS_EST = colExists($con,$dbName,$TBL_INT,'estado');

$Q_TBL_INT = "`$dbName`.`$TBL_INT`";
$Q_TBL_MED = "`$dbName`.`$TBL_MED`";

// ====== CRUD server-side mínimo (reusar lo que ya tenías) ======

// Eliminar (soft/hard)
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
  header('Content-Type: text/plain; charset=UTF-8');
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { echo 'ID inválido'; exit; }
  try {
    if ($HAS_EST)
      $st = $con->prepare("UPDATE $Q_TBL_INT SET `estado`=0 WHERE `$INT_ID`=:i");
    else
      $st = $con->prepare("DELETE FROM $Q_TBL_INT WHERE `$INT_ID`=:i");
    $st->execute([':i'=>$id]);
    echo ($st->rowCount() ? 'OK' : 'No se pudo eliminar.');
  } catch (Throwable $e) { echo 'Error: '.$e->getMessage(); }
  exit;
}

// Crear (desde modal)
if (isset($_POST['save_interaction'])) {
  $medA      = (int)($_POST['id_medicamento_a'] ?? 0);
  $medB      = (int)($_POST['id_medicamento_b'] ?? 0);
  $severidad = $_POST['severidad'] ?? 'media';
  $nota      = trim($_POST['nota'] ?? '');
  $pidRet    = (int)($_POST['return_pid'] ?? 0);

  $sevAllowed = ['alta','media','baja'];
  if (!in_array($severidad, $sevAllowed, true)) $severidad = 'media';

  if ($medA > 0 && $medB > 0 && $medA !== $medB) {
    try {
      $an = min($medA,$medB); $bn = max($medA,$medB);
      $dup = $con->prepare("SELECT 1 FROM $Q_TBL_INT WHERE a_norm=:an AND b_norm=:bn " . ($HAS_EST? "AND estado=1 ":"") . "LIMIT 1");
      $dup->execute([':an'=>$an, ':bn'=>$bn]);
      if ($dup->fetchColumn()) {
        $message = 'Ya existe una interacción para ese par.';
      } else {
        if ($HAS_EST)
          $ins = $con->prepare("INSERT INTO $Q_TBL_INT (`$INT_A`,`$INT_B`,`$INT_SEV`,`$INT_NOTE`,`estado`) VALUES (:a,:b,:s,:n,1)");
        else
          $ins = $con->prepare("INSERT INTO $Q_TBL_INT (`$INT_A`,`$INT_B`,`$INT_SEV`,`$INT_NOTE`) VALUES (:a,:b,:s,:n)");
        $ins->execute([':a'=>$medA, ':b'=>$medB, ':s'=>$severidad, ':n'=>$nota]);
        $message = 'Interacción registrada.';
      }
    } catch (Throwable $e) { $message = 'Error: '.$e->getMessage(); }
  } else {
    $message = 'Selecciona dos medicamentos diferentes.';
  }
  header("Location: interacciones_medicina.php?pid={$pidRet}&message=" . urlencode($message));
  exit;
}

// ====== Datos para combos y catálogo global ======
$optPacientes = getPacientes($con);

// Medicamentos (para modal)
$medList = [];
try {
  $meds = $con->prepare("SELECT `id`, `nombre_medicamento` FROM $Q_TBL_MED ORDER BY `nombre_medicamento`");
  $meds->execute();
  $medList = $meds->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Catálogo global
try {
  $where = $HAS_EST ? "WHERE i.`estado`=1" : "";
  $sql = "SELECT i.`$INT_ID` AS id,
                 A.`nombre_medicamento` AS med_a,
                 B.`nombre_medicamento` AS med_b,
                 i.`$INT_SEV` AS severidad,
                 COALESCE(i.`$INT_NOTE`,'') AS nota
          FROM $Q_TBL_INT i
          JOIN $Q_TBL_MED A ON A.`id` = i.`$INT_A`
          JOIN $Q_TBL_MED B ON B.`id` = i.`$INT_B`
          $where
          ORDER BY A.`nombre_medicamento`, B.`nombre_medicamento`";
  $stmt = $con->prepare($sql);
  $stmt->execute();
  $intRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $intRows = []; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <?php include './config/data_tables_css.php'; ?>
  <title>Interacciones de Medicamentos</title>
  <style>
    .actions{ display:inline-flex; gap:.35rem; }
    .btn-icon{ width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; }
    .badge-soft{background:#eef2ff;color:#334155;border-radius:12px;padding:.15rem .5rem;font-size:.75rem}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-pills"></i> Interacciones de Medicamentos</h1>
      </div>
    </section>

    <!-- Filtro Paciente -->
    <section class="content">
      <div class="card card-outline card-primary">
        <div class="card-body">
          <div class="form-row">
            <div class="form-group col-lg-6">
              <label>Paciente</label>
              <select id="paciente_id" class="form-control">
                <?= $optPacientes ?>
              </select>
            </div>
            <div class="form-group col-lg-2 d-flex align-items-end">
              <button id="btnBuscar" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
            </div>
            <div class="col-lg-4 d-flex align-items-end">
              <span id="lblInfo" class="badge-soft"></span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Interacciones del PACIENTE -->
    <section class="content">
      <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="fas fa-user-md"></i> Interacciones del paciente</h3>
          <small class="text-muted">* Se detectan a partir de las medicinas recetadas al paciente.</small>
        </div>
        <div class="card-body table-responsive">
          <table id="tbl_paciente" class="table table-striped table-bordered">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>Medicamento A</th>
                <th>Medicamento B</th>
                <th>Severidad</th>
                <th>Nota</th>
                <th class="text-center">Acción</th>
              </tr>
            </thead>
            <tbody><!-- AJAX --></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Catálogo Global (colapsable) -->
    <section class="content">
      <div class="card card-outline card-secondary">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-list-ol"></i> Catálogo global de interacciones</h3>
          <div class="card-tools">
            <button class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="tbl_catalogo" class="table table-striped table-bordered">
              <thead>
                <tr>
                  <th class="text-center">No Serie</th>
                  <th>Medicamento A</th>
                  <th>Medicamento B</th>
                  <th>Severidad</th>
                  <th>Nota</th>
                  <th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php $serial=0; foreach ($intRows as $row): $serial++; $id=(int)$row['id']; ?>
                <tr id="int-row-<?php echo $id; ?>">
                  <td class="text-center"><?php echo $serial; ?></td>
                  <td><?php echo htmlspecialchars($row['med_a']); ?></td>
                  <td><?php echo htmlspecialchars($row['med_b']); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($row['severidad'])); ?></td>
                  <td><?php echo htmlspecialchars($row['nota']); ?></td>
                  <td class="text-center">
                    <div class="actions">
                      <a href="actualizar_interaccion_medicina.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm btn-icon" title="Editar"><i class="fa fa-edit"></i></a>
                      <button type="button" class="btn btn-danger btn-sm btn-icon btn-del-cat" data-id="<?php echo $id; ?>" title="Eliminar"><i class="fa fa-trash"></i></button>
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

<!-- Modal Registrar Interacción -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" autocomplete="off">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-notes-medical"></i> Registrar Interacción</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="return_pid" id="return_pid" value="<?php echo (int)$prePid; ?>">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Medicamento A</label>
            <select class="form-control" name="id_medicamento_a" id="id_medicamento_a" required>
              <option value="">Selecciona</option>
              <?php foreach ($medList as $m) echo '<option value="'.$m['id'].'">'.htmlspecialchars($m['nombre_medicamento']).'</option>'; ?>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Medicamento B</label>
            <select class="form-control" name="id_medicamento_b" id="id_medicamento_b" required>
              <option value="">Selecciona</option>
              <?php foreach ($medList as $m) echo '<option value="'.$m['id'].'">'.htmlspecialchars($m['nombre_medicamento']).'</option>'; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Severidad</label>
            <select class="form-control" name="severidad" id="severidad">
              <option value="alta">Alta</option>
              <option value="media" selected>Media</option>
              <option value="baja">Baja</option>
            </select>
          </div>
          <div class="form-group col-md-8">
            <label>Nota (opcional)</label>
            <input type="text" class="form-control" name="nota" id="nota" maxlength="255" placeholder="Descripción breve">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit" name="save_interaction"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div></div>
</div>

<?php include './config/site_js_links.php'; ?>
<?php include './config/data_tables_js.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
  showMenuSelected("#mnu_medicines", "#mi_medicine_interactions");

  // Mensaje (si viene de redirect)
  (function(){
    var msg = <?php echo json_encode($message); ?>;
    if(msg){ Swal.fire('Mensaje', msg, 'info'); }
  })();

  // DataTable Catálogo
  var dtCat = $("#tbl_catalogo").DataTable({
    responsive:true, lengthChange:false, autoWidth:false,
    language:{lengthMenu:"Mostrar _MENU_",search:"Buscar:",paginate:{first:"Primero",last:"Último",next:"Siguiente",previous:"Anterior"},zeroRecords:"Sin resultados",info:"Mostrando _START_ a _END_ de _TOTAL_",infoEmpty:"0 de 0"},
    buttons:["copyHtml5","csvHtml5","excelHtml5","pdfHtml5","print","colvis"]
  });
  dtCat.buttons().container().appendTo('#tbl_catalogo_wrapper .col-md-6:eq(0)');

  // Tabla del paciente (simple)
  var dtPac = $("#tbl_paciente").DataTable({
    responsive:true, lengthChange:false, autoWidth:false,
    language:{lengthMenu:"Mostrar _MENU_",search:"Buscar:",paginate:{first:"Primero",last:"Último",next:"Siguiente",previous:"Anterior"},zeroRecords:"Sin resultados",info:"Mostrando _START_ a _END_ de _TOTAL_",infoEmpty:"0 de 0"}
  });

  function cargarPaciente(){
    const pid = $("#paciente_id").val();
    if(!pid){ dtPac.clear().draw(); $("#lblInfo").text(''); return; }

    $("#lblInfo").text('Paciente ID: '+pid);
    $("#return_pid").val(pid); // para volver con el mismo paciente tras guardar

    $.getJSON('ajax/interacciones_paciente_listar.php',{paciente_id:pid})
      .done(function(r){
        if(!r.success){ Swal.fire('Aviso', r.message || 'Sin datos', 'info'); dtPac.clear().draw(); return; }
        dtPac.clear();
        var i=0;
        r.rows.forEach(function(row){
          i++;
          var acciones = '';
          if(row.inter_id){ // existe en catálogo
            acciones = `
              <a class="btn btn-primary btn-sm btn-icon" href="actualizar_interaccion_medicina.php?id=${row.inter_id}" title="Editar"><i class="fa fa-edit"></i></a>
              <button class="btn btn-danger btn-sm btn-icon btn-del" data-id="${row.inter_id}" title="Eliminar"><i class="fa fa-trash"></i></button>
            `;
          }else{
            acciones = `
              <button class="btn btn-success btn-sm btn-icon btn-add" 
                data-a="${row.a_id}" data-b="${row.b_id}" 
                data-an="${row.a_nombre}" data-bn="${row.b_nombre}">
                <i class="fa fa-plus"></i>
              </button>`;
          }
          dtPac.row.add([
            '<div class="text-center">'+i+'</div>',
            row.a_nombre,
            row.b_nombre,
            row.severidad || '<span class="text-muted">—</span>',
            row.nota || '<span class="text-muted">—</span>',
            '<div class="text-center">'+acciones+'</div>'
          ]);
        });
        dtPac.draw();
      })
      .fail(function(x){ Swal.fire('Error', x.responseText || 'Fallo al consultar', 'error'); });
  }

  $("#btnBuscar, #paciente_id").on('click change', cargarPaciente);

  // Registrar (abre modal pre-cargado)
  $(document).on('click','.btn-add', function(){
    var a = $(this).data('a'), b=$(this).data('b'), an=$(this).data('an'), bn=$(this).data('bn');
    $("#id_medicamento_a").val(a);
    $("#id_medicamento_b").val(b);
    $("#severidad").val('media');
    $("#nota").val('Posible interacción detectada para el paciente');
    $("#modalAdd").modal('show');
  });

  // Eliminar desde ambas tablas
  function eliminar(id){
    Swal.fire({title:'Eliminar interacción',text:'¿Deseas eliminar esta interacción?',icon:'question',showCancelButton:true,confirmButtonText:'Eliminar',confirmButtonColor:'#dc3545'})
      .then(function(r){
        if(!r.isConfirmed) return;
        $.post('interacciones_medicina.php',{action:'delete',id:id})
          .done(function(t){
            t=(t||'').trim();
            if(t==='OK'){
              // refrescar ambas vistas
              dtCat.row($('#int-row-'+id)).remove().draw(false);
              cargarPaciente();
              Swal.fire('Hecho','Eliminado','success');
            }else{
              Swal.fire('Aviso', t || 'No se pudo eliminar', 'warning');
            }
          })
          .fail(function(x){ Swal.fire('Error', x.responseText || 'Fallo al eliminar', 'error'); });
      });
  }
  $(document).on('click','.btn-del, .btn-del-cat', function(){ eliminar($(this).data('id')); });

  // Si venimos con ?pid=...
  (function(){
    var pre = <?php echo (int)$prePid; ?>;
    if(pre){ $("#paciente_id").val(String(pre)); cargarPaciente(); }
  })();
</script>
</body>
</html>

