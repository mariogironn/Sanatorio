<?php
// actualizar_interaccion_medicina.php
// -------------------------------------------------------------
// Edita una interacción ya registrada, con el mismo estilo
// de tus pantallas y validación de duplicados (A-B = B-A).
// -------------------------------------------------------------

// 1) Conexión PDO ($con)
include './config/connection.php';

$message = '';

// 2) ID obligatorio por GET
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  $message = 'Interacción no válida.';
  header("Location: congratulation.php?goto_page=interacciones_medicina.php&message=" . urlencode($message));
  exit;
}

// 3) Asegurar existencia de la tabla (por si entran aquí primero)
try {
  $con->exec("
    CREATE TABLE IF NOT EXISTS interacciones_medicamentos (
      id               INT AUTO_INCREMENT PRIMARY KEY,
      id_medicamento_a INT NOT NULL,
      id_medicamento_b INT NOT NULL,
      severidad        ENUM('alta','media','baja') NOT NULL DEFAULT 'media',
      nota             VARCHAR(255) DEFAULT NULL,
      estado           TINYINT(1) NOT NULL DEFAULT 1,
      created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uk_par (LEAST(id_medicamento_a,id_medicamento_b), GREATEST(id_medicamento_a,id_medicamento_b))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (PDOException $ex) {
  // No bloqueamos la edición si falla el CREATE (p.ej. por CHECK/funcional),
  // la tabla ya debería existir si vienes desde la lista.
}

// 4) Cargar la interacción a editar
try {
  $q = $con->prepare("
    SELECT id, id_medicamento_a, id_medicamento_b, severidad, nota
    FROM interacciones_medicamentos
    WHERE id = :i AND estado = 1
    LIMIT 1
  ");
  $q->execute([':i' => $id]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    $message = 'La interacción no existe o está inactiva.';
    header("Location: interacciones_medicina.php?message=" . urlencode($message));
    exit;
  }
} catch (PDOException $ex) {
  echo $ex->getMessage(); exit;
}

// 5) Guardar cambios (POST)
if (isset($_POST['update_interaction'])) {
  $medA      = (int)($_POST['id_medicamento_a'] ?? 0);
  $medB      = (int)($_POST['id_medicamento_b'] ?? 0);
  $severidad = $_POST['severidad'] ?? 'media';
  $nota      = trim($_POST['nota'] ?? '');

  $sevAllowed = ['alta','media','baja'];
  if (!in_array($severidad, $sevAllowed, true)) $severidad = 'media';

  if ($medA > 0 && $medB > 0 && $medA !== $medB) {
    try {
      // a) Verifica que existan los medicamentos
      $ck = $con->prepare("SELECT COUNT(*) FROM medicamentos WHERE id IN (:a,:b)");
      // PDO no permite IN con params repetidos así que validamos por separado:
      $cka = $con->prepare("SELECT 1 FROM medicamentos WHERE id = :i LIMIT 1");
      $ckb = $con->prepare("SELECT 1 FROM medicamentos WHERE id = :i LIMIT 1");
      $cka->execute([':i'=>$medA]);
      $ckb->execute([':i'=>$medB]);
      if (!$cka->fetchColumn() || !$ckb->fetchColumn()) {
        $message = 'Selecciona medicamentos válidos.';
      } else {
                // b) Duplicado usando a_norm/b_norm (excluye este ID)
        $an = min($medA, $medB);
        $bn = max($medA, $medB);
        $dup = $con->prepare("
          SELECT 1
          FROM interacciones_medicamentos
          WHERE a_norm = :an AND b_norm = :bn
            AND estado = 1 AND id <> :id
          LIMIT 1
        ");
        $dup->execute([':an'=>$an, ':bn'=>$bn, ':id'=>$id]);


        if ($dup->fetchColumn()) {
          $message = 'Ya existe otra interacción con ese par de medicinas.';
        } else {
          // c) Actualizar
          $up = $con->prepare("
            UPDATE interacciones_medicamentos
            SET id_medicamento_a = :a,
                id_medicamento_b = :b,
                severidad = :s,
                nota = :n
            WHERE id = :id
          ");
          $up->execute([
            ':a'=>$medA, ':b'=>$medB, ':s'=>$severidad, ':n'=>$nota, ':id'=>$id
          ]);

          $message = 'Interacción actualizada correctamente.';
          header("Location: congratulation.php?goto_page=interacciones_medicina.php&message=" . urlencode($message));
          exit;
        }
      }
    } catch (PDOException $ex) {
      echo $ex->getMessage(); exit;
    }
  } else {
    $message = 'Selecciona dos medicamentos diferentes.';
  }
}

// 6) Cargar catálogo de medicinas para los selects
try {
  $meds = $con->prepare("SELECT id, nombre_medicamento FROM medicamentos ORDER BY nombre_medicamento ASC");
  $meds->execute();
  $medList = $meds->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
  echo $ex->getMessage(); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Editar Interacción</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1><i class="fas fa-edit"></i> Editar Interacción</h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary rounded-0 shadow">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Interacción</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Colapsar">
              <i class="fas fa-minus"></i>
            </button>
          </div>
        </div>
        <div class="card-body">
          <form method="post" id="form-edit" autocomplete="off">
            <input type="hidden" name="update_interaction" value="1">
            <div class="row">
              <div class="col-lg-4 col-md-6 col-sm-6">
                <label>Medicamento A</label>
                <select class="form-control form-control-sm rounded-0" name="id_medicamento_a" id="id_medicamento_a" required>
                  <option value="">Selecciona Medicina</option>
                  <?php
                    foreach ($medList as $m) {
                      $sel = ((int)$row['id_medicamento_a']===(int)$m['id']) ? ' selected' : '';
                      echo '<option value="'.$m['id'].'"'.$sel.'>'.htmlspecialchars($m['nombre_medicamento']).'</option>';
                    }
                  ?>
                </select>
              </div>
              <div class="col-lg-4 col-md-6 col-sm-6">
                <label>Medicamento B</label>
                <select class="form-control form-control-sm rounded-0" name="id_medicamento_b" id="id_medicamento_b" required>
                  <option value="">Selecciona Medicina</option>
                  <?php
                    foreach ($medList as $m) {
                      $sel = ((int)$row['id_medicamento_b']===(int)$m['id']) ? ' selected' : '';
                      echo '<option value="'.$m['id'].'"'.$sel.'>'.htmlspecialchars($m['nombre_medicamento']).'</option>';
                    }
                  ?>
                </select>
              </div>
              <div class="col-lg-2 col-md-4 col-sm-4">
                <label>Severidad</label>
                <select class="form-control form-control-sm rounded-0" name="severidad" id="severidad">
                  <option value="alta"  <?php echo $row['severidad']==='alta'  ? 'selected':''; ?>>Alta</option>
                  <option value="media" <?php echo $row['severidad']==='media' ? 'selected':''; ?>>Media</option>
                  <option value="baja"  <?php echo $row['severidad']==='baja'  ? 'selected':''; ?>>Baja</option>
                </select>
              </div>
              <div class="col-lg-6 col-md-8 col-sm-8 mt-2">
                <label>Nota (opcional)</label>
                <input type="text" class="form-control form-control-sm rounded-0" name="nota" id="nota" maxlength="255"
                       value="<?php echo htmlspecialchars($row['nota']); ?>">
              </div>
              <div class="col-lg-2 col-md-3 col-sm-4 mt-2">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm btn-flat btn-block">Guardar Cambios</button>
              </div>
              <div class="col-lg-2 col-md-3 col-sm-4 mt-2">
                <label>&nbsp;</label>
                <a href="interacciones_medicina.php" class="btn btn-secondary btn-sm btn-flat btn-block">Cancelar</a>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
// Mensaje en caso de venir por GET &message
(function(){
  const url = new URL(window.location.href);
  const msg = url.searchParams.get('message');
  if (msg) {
    Swal.fire({title:'Mensaje', text:msg, icon:'info', confirmButtonText:'OK', confirmButtonColor:'#0d6efd'});
  }
})();

// Validación de duplicado vía AJAX (excluye el propio ID)
function checkDuplicate(){
  const a = $('#id_medicamento_a').val();
  const b = $('#id_medicamento_b').val();
  if(!a || !b || a===b) return;
  $.get('ajax/verificar_interaccion_medicina.php', { id_medicamento_a:a, id_medicamento_b:b, id: <?php echo $id; ?> })
   .done(function(count){
     if(parseInt(count,10)>0){
       Swal.fire({title:'Atención', text:'Ya existe una interacción con ese par.', icon:'warning', confirmButtonText:'OK', confirmButtonColor:'#0d6efd'});
     }
   });
}
$('#id_medicamento_a, #id_medicamento_b').on('change', checkDuplicate);
</script>
</body>
</html>
