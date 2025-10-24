<?php
// Conexión PDO ($con)
if (session_status() === PHP_SESSION_NONE) session_start();
include './config/connection.php';
include './common_service/common_functions.php'; // (ok dejarlo)

// === Auditoría ===
require_once __DIR__ . '/common_service/auditoria_service.php';
require_once __DIR__ . '/common_service/audit_helpers.php';

// Helper local para derivar estado textual si tu tabla lo maneja
function _infer_estado(?array $row) {
  if (!$row) return null;
  if (array_key_exists('estado', $row)) {
    $v = strtolower((string)$row['estado']);
    if ($v === 'activo' || $v === 'inactivo') return $v;
    return $row['estado'];
  }
  if (array_key_exists('activo', $row)) {
    return ((int)$row['activo'] === 1) ? 'activo' : 'inactivo';
  }
  return null;
}
// Enmascara campos sensibles en el snapshot
function _mask_user_row(?array $row) {
  if (!$row) return $row;
  unset($row['contrasena'], $row['password'], $row['pass'], $row['pwd']);
  return $row;
}

// ===== Cargar datos del usuario (GET) =====
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

try {
  $sql = "SELECT `id`, `nombre_mostrar`, `usuario`, `imagen_perfil`
          FROM `usuarios` WHERE `id` = :id";
  $stmt = $con->prepare($sql);
  $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
  $stmt->execute();
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { header("Location: usuarios.php"); exit; }
} catch (PDOException $ex) {
  echo $ex->getMessage(); exit;
}

$message = '';

// ===== Guardar cambios (POST) =====
if (isset($_POST['save_user'])) {
  $hiddenId    = (int)($_POST['hidden_id'] ?? 0);
  $displayName = trim($_POST['display_name']);
  $userName    = trim($_POST['username']);
  $password    = $_POST['password'] ?? ''; // si viene vacío, no cambia

  // --- imagen (opcional) ---
  $targetFile = '';
  if (!empty($_FILES['profile_picture']['name'])) {
    $baseName   = basename($_FILES['profile_picture']['name']);
    $safeName   = str_replace(' ', '_', $baseName);
    $targetFile = time() . '_' . $safeName;
    $ok = move_uploaded_file($_FILES['profile_picture']['tmp_name'], 'user_images/' . $targetFile);
    if (!$ok) { $targetFile = ''; } // si falla, no actualiza imagen
  }

  // Validación simple
  if ($hiddenId > 0 && $displayName !== '' && $userName !== '') {

    // === AUDITORÍA: capturar ANTES ===
    try {
      $antes = audit_row($con, 'usuarios', 'id', $hiddenId);
      $antes = _mask_user_row($antes);
    } catch (Throwable $e) { $antes = null; /* no romper flujo */ }

    // Build dinámico del SET según lo que llegó
    $sets   = ['`nombre_mostrar` = :nombre', '`usuario` = :usuario'];
    $params = [':nombre' => $displayName, ':usuario' => $userName, ':id' => $hiddenId];

    if ($password !== '') {                    // cambia pass solo si enviaron una nueva
      $sets[] = '`contrasena` = :pass';
      // CAMBIO: usar password_hash() (antes: md5($password))
      $params[':pass'] = password_hash($password, PASSWORD_DEFAULT);
      // (opcional) validar longitud mínima
    }
    if ($targetFile !== '') {                  // cambia imagen solo si subieron una nueva
      $sets[] = '`imagen_perfil` = :img';
      $params[':img'] = $targetFile;
    }

    $updateSql = "UPDATE `usuarios` SET " . implode(', ', $sets) . " WHERE `id` = :id";

    try {
      $con->beginTransaction();
      $up = $con->prepare($updateSql);
      foreach ($params as $k => $v) {
        $type = ($k === ':id') ? PDO::PARAM_INT : PDO::PARAM_STR;
        $up->bindValue($k, $v, $type);
      }
      $up->execute();

      // === AUDITORÍA: capturar DESPUÉS (dentro de la misma conexión) ===
      $despues = null; $estado = null;
      try {
        $despues = audit_row($con, 'usuarios', 'id', $hiddenId);
        $estado  = _infer_estado($despues);
        $despues = _mask_user_row($despues);
      } catch (Throwable $e) { /* noop */ }

      $con->commit();
      $message = "Usuario actualizado correctamente";

      // === AUDITORÍA: registrar UPDATE ===
      try {
        audit_update(
          $con,
          'usuarios',          // módulo
          'usuarios',          // tabla
          $hiddenId,           // id_registro
          $antes,              // antes_json (enmascarado)
          $despues,            // despues_json (enmascarado)
          $estado              // estado_resultante (si aplica)
        );
      } catch (Throwable $e) {
        // No interrumpir la UX por un fallo de auditoría
        error_log('AUDITORIA UPDATE usuarios: '.$e->getMessage());
      }

    } catch (PDOException $ex) {
      if ($con->inTransaction()) { $con->rollBack(); }
      echo $ex->getMessage(); exit;
    }

    header("Location: congratulation.php?goto_page=usuarios.php&message=$message");
    exit;
  } else {
    $message = "Completa nombre y usuario.";
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include './config/site_css_links.php'; ?>
  <title>Usuario Actualizado</title>
</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
  <div class="wrapper">
    <?php include './config/header.php'; include './config/sidebar.php'; ?>

    <div class="content-wrapper">
      <!-- Título -->
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
              <h1>Actualizar Información de Usuario</h1>
            </div>
            <div class="col-sm-6 text-right">
              <a href="usuarios.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Volver a Usuarios
              </a>
            </div>
          </div>
        </div>
      </section>

      <!-- Formulario -->
      <section class="content">
        <div class="card card-outline card-primary rounded-0 shadow">
          <div class="card-header">
            <h3 class="card-title">Actualizar Usuario</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Colapsar">
                <i class="fas fa-minus"></i>
              </button>
            </div>
          </div>

          <div class="card-body">
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="hidden_id" value="<?php echo (int)$row['id']; ?>">
              <div class="row">
                <div class="col-lg-4">
                  <label>Nombre del Usuario</label>
                  <input type="text" id="display_name" name="display_name" required
                         class="form-control form-control-sm rounded-0"
                         value="<?php echo htmlspecialchars($row['nombre_mostrar']); ?>" />
                </div>

                <div class="col-lg-4">
                  <label>Usuario</label>
                  <input type="text" id="username" name="username" required
                         class="form-control form-control-sm rounded-0"
                         value="<?php echo htmlspecialchars($row['usuario']); ?>" />
                </div>

                <div class="col-lg-4">
                  <label>Contraseña</label>
                  <input type="password" id="password" name="password"
                         class="form-control form-control-sm rounded-0" />
                </div>

                <div class="col-lg-4">
                  <label>Imagen de Perfil</label>
                  <input type="file" id="profile_picture" name="profile_picture"
                         class="form-control form-control-sm rounded-0" />
                </div>
              </div>

              <div class="clearfix">&nbsp;</div>
              <div class="row">
                <div class="col-lg-11 col-md-10 col-sm-10">&nbsp;</div>
                <div class="col-lg-1 col-md-2 col-sm-2 col-xs-2">
                  <button type="submit" id="save_user" name="save_user"
                          class="btn btn-primary btn-sm btn-flat btn-block">
                    Actualizar
                  </button>
                </div>
              </div>
            </form>
          </div>

        </div>
      </section>
    </div>

    <?php
      include './config/footer.php';
      $message = isset($_GET['message']) ? $_GET['message'] : '';
    ?>
  </div>

  <?php include './config/site_js_links.php'; ?>
  <script>
    var message = '<?php echo $message; ?>';
    if (message !== '') { showCustomMessage(message); }
  </script>
</body>
</html>
