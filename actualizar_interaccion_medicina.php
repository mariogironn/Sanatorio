<?php
// actualizar_interaccion_medicina.php
// Editar una interacción existente (sin cambiar nada fuera de este archivo)
include './config/connection.php';

$message = '';

// ===== BD activa (fallback a la_esperanza) =====
try { $dbName = $con->query("SELECT DATABASE()")->fetchColumn(); } catch (Throwable $e) { $dbName = null; }
if (!$dbName) $dbName = 'la_esperanza';

// ===== Helpers =====
function colExists(PDO $con, $db, $table, $col) {
  $q = $con->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=:db AND table_name=:t AND column_name=:c");
  $q->execute([':db'=>$db, ':t'=>$table, ':c'=>$col]);
  return (int)$q->fetchColumn() > 0;
}

// Tablas
$TBL_INT = 'interacciones_medicamentos';
$TBL_MED = 'medicamentos';
$Q_TBL_INT = "`$dbName`.`$TBL_INT`";
$Q_TBL_MED = "`$dbName`.`$TBL_MED`";

// Columnas (autodetección por si difieren)
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

$HAS_EST = colExists($con,$dbName,$TBL_INT,'estado'); // no se usa al editar, pero por si acaso

// ===== ID a editar =====
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location: congratulation.php?goto_page=interacciones_medicina.php&message=" . urlencode('ID inválido.'));
  exit;
}

// ===== Obtener registro actual =====
try {
  $sql = "
    SELECT i.`$INT_ID` AS id,
           i.`$INT_A`  AS med_a_id,
           i.`$INT_B`  AS med_b_id,
           i.`$INT_SEV` AS severidad,
           COALESCE(i.`$INT_NOTE`,'') AS nota
    FROM $Q_TBL_INT i
    WHERE i.`$INT_ID` = :id
    LIMIT 1
  ";
  $st = $con->prepare($sql);
  $st->execute([':id'=>$id]);
  $current = $st->fetch(PDO::FETCH_ASSOC);
  if (!$current) {
    header("Location: congratulation.php?goto_page=interacciones_medicina.php&message=" . urlencode('Interacción no encontrada.'));
    exit;
  }

  // Lista de medicamentos para selects
  $meds = $con->query("SELECT `id`,`nombre_medicamento` FROM $Q_TBL_MED ORDER BY `nombre_medicamento` ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
  echo $ex->getMessage(); exit;
}

// ===== Actualizar (POST) =====
if (isset($_POST['update_interaction'])) {
  $medA      = (int)($_POST['id_medicamento_a'] ?? 0);
  $medB      = (int)($_POST['id_medicamento_b'] ?? 0);
  $severidad = $_POST['severidad'] ?? 'media';
  $nota      = trim($_POST['nota'] ?? '');

  $sevAllowed = ['alta','media','baja'];
  if (!in_array($severidad, $sevAllowed, true)) $severidad = 'media';

  if ($medA > 0 && $medB > 0 && $medA !== $medB) {
    try {
      // Verificar que existan
      $ck = $con->prepare("SELECT 1 FROM $Q_TBL_MED WHERE `id` = :i LIMIT 1");
      $ck->execute([':i'=>$medA]); $okA = (bool)$ck->fetchColumn();
      $ck->execute([':i'=>$medB]); $okB = (bool)$ck->fetchColumn();
      if (!$okA || !$okB) {
        $msg = 'Selecciona medicamentos válidos.';
      } else {
        // Evitar duplicado por par normalizado (A–B == B–A) distinto del propio id
        $an = min($medA,$medB);
        $bn = max($medA,$medB);

        $dup = $con->prepare("
          SELECT 1
          FROM $Q_TBL_INT
          WHERE a_norm = :an AND b_norm = :bn AND `$INT_ID` <> :id
          LIMIT 1
        ");
        $dup->execute([':an'=>$an, ':bn'=>$bn, ':id'=>$id]);

        if ($dup->fetchColumn()) {
          $msg = 'Ya existe una interacción con ese par de medicinas.';
        } else {
          $con->beginTransaction();
          $up = $con->prepare("
            UPDATE $Q_TBL_INT
               SET `$INT_A` = :a,
                   `$INT_B` = :b,
                   `$INT_SEV` = :s,
                   `$INT_NOTE` = :n
             WHERE `$INT_ID` = :id
            LIMIT 1
          ");
          $up->execute([':a'=>$medA, ':b'=>$medB, ':s'=>$severidad, ':n'=>$nota, ':id'=>$id]);
          $con->commit();
          $msg = 'Interacción actualizada correctamente.';
        }
      }
    } catch (PDOException $ex) {
      if ($con->inTransaction()) $con->rollBack();
      $msg = 'Error: ' . $ex->getMessage();
    }
  } else {
    $msg = 'Selecciona dos medicamentos diferentes.';
  }

  header("Location: congratulation.php?goto_page=interacciones_medicina.php&message=" . urlencode($msg));
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Editar Interacción</title>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
  <?php include './config/header.php'; include './config/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6">
        <h1><i class="fas fa-edit"></i> Editar Interacción</h1>
      </div></div></div>
    </section>

    <section class="content">
      <div class="card card-outline card-primary rounded-0 shadow">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Actualizar</h3>
          <div class="card-tools">
            <a href="interacciones_medicina.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
          </div>
        </div>
        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="row">
              <div class="col-lg-4 col-md-6 col-sm-6">
                <label>Medicamento A</label>
                <select class="form-control form-control-sm rounded-0" name="id_medicamento_a" required>
                  <option value="">Selecciona Medicina</option>
                  <?php foreach($meds as $m): ?>
                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)$current['med_a_id']==(int)$m['id'])?'selected':''; ?>>
                      <?php echo htmlspecialchars($m['nombre_medicamento']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-lg-4 col-md-6 col-sm-6">
                <label>Medicamento B</label>
                <select class="form-control form-control-sm rounded-0" name="id_medicamento_b" required>
                  <option value="">Selecciona Medicina</option>
                  <?php foreach($meds as $m): ?>
                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)$current['med_b_id']==(int)$m['id'])?'selected':''; ?>>
                      <?php echo htmlspecialchars($m['nombre_medicamento']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-lg-2 col-md-4 col-sm-4">
                <label>Severidad</label>
                <select class="form-control form-control-sm rounded-0" name="severidad">
                  <?php
                    $sev = $current['severidad'];
                    $opts = ['alta'=>'Alta','media'=>'Media','baja'=>'Baja'];
                    foreach($opts as $val=>$lab){
                      $sel = ($sev===$val)?'selected':'';
                      echo "<option value=\"$val\" $sel>$lab</option>";
                    }
                  ?>
                </select>
              </div>

              <div class="col-lg-6 col-md-8 col-sm-8 mt-2">
                <label>Nota (opcional)</label>
                <input type="text" class="form-control form-control-sm rounded-0" name="nota" maxlength="255"
                       value="<?php echo htmlspecialchars($current['nota']); ?>">
              </div>

              <div class="col-lg-2 col-md-3 col-sm-4 mt-2">
                <label>&nbsp;</label>
                <button type="submit" name="update_interaction" class="btn btn-primary btn-sm btn-block">Actualizar</button>
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
</body>
</html>
