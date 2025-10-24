<?php
// index.php (LOGIN)
// - Valida credenciales con parámetros (PDO prepared statements)
// - Compatibilidad con contraseñas antiguas en MD5 (rehash automático a password_hash())
// - Bloquea usuarios en estado != ACTIVO
// - Carga rol del usuario en $_SESSION['rol_nombre']
// - Carga permisos efectivos en $_SESSION['permisos']
//
// Requisitos previos (una sola vez en la BD):
//   ALTER TABLE usuarios MODIFY contrasena VARCHAR(255) NOT NULL;

include './config/connection.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); } // <-- aseguramos sesión

$message = '';

if (isset($_POST['login'])) {
  $userName = trim($_POST['user_name'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($userName === '' || $password === '') {
    $message = 'Usuario y contraseña son obligatorios.';
  } else {
    // 1) Traer usuario y su hash (sin comparar aún)
    $sqlUser = "SELECT id, nombre_mostrar, usuario, imagen_perfil, estado, contrasena
                FROM usuarios
                WHERE usuario = :u
                LIMIT 1";
    try {
      $stmt = $con->prepare($sqlUser);
      $stmt->execute([':u' => $userName]);

      if ($stmt->rowCount() === 1) {
        $row    = $stmt->fetch(PDO::FETCH_ASSOC);
        $hashDB = (string)$row['contrasena'];
        $loginOK = false;

        // 2) Compatibilidad: si el hash en BD "parece MD5" (32 hex)
        if (preg_match('/^[a-f0-9]{32}$/i', $hashDB)) {
          if (md5($password) === $hashDB) {
            $loginOK = true;
            // Re-hash al formato moderno
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $con->prepare("UPDATE usuarios SET contrasena = :h WHERE id = :id")
                ->execute([':h' => $newHash, ':id' => (int)$row['id']]);
            $hashDB = $newHash;
          }
        } else {
          // 3) Ruta moderna: verificar con password_verify()
          $loginOK = password_verify($password, $hashDB);
          // Re-hash si el coste por defecto cambió (mantenimiento)
          if ($loginOK && password_needs_rehash($hashDB, PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $con->prepare("UPDATE usuarios SET contrasena = :h WHERE id = :id")
                ->execute([':h' => $newHash, ':id' => (int)$row['id']]);
          }
        }

        if (!$loginOK) {
          $message = 'Usuario o contraseña incorrectos';
        } else {
          // 4) Validar estado
          if (!isset($row['estado']) || $row['estado'] !== 'ACTIVO') {
            $message = 'Su cuenta está BLOQUEADA. Contacte al administrador.';
          } else {
            // 5) Login OK → sesión segura
            if (session_status() === PHP_SESSION_ACTIVE) {
              session_regenerate_id(true);
            }

            $_SESSION['user_id']        = (int)$row['id'];
            $_SESSION['nombre_mostrar'] = $row['nombre_mostrar'];
            $_SESSION['usuario']        = $row['usuario'];
            $_SESSION['imagen_perfil']  = $row['imagen_perfil'];

            // 6) Rol principal (si existe la vista)
            $_SESSION['rol_nombre'] = 'Sin rol';
            try {
              $qr = "SELECT rol_nombre FROM vw_usuario_rol_principal WHERE id_usuario = :id LIMIT 1";
              $sr = $con->prepare($qr);
              $sr->execute([':id' => $_SESSION['user_id']]);
              if ($r = $sr->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['rol_nombre'] = $r['rol_nombre'] ?: 'Sin rol';
              }
            } catch (PDOException $e) {
              // Si la vista no existe, no rompemos el login
              $_SESSION['rol_nombre'] = 'Sin rol';
            }

            // 7) Permisos efectivos por módulo (OR entre todos los roles del usuario)
            $_SESSION['permisos'] = []; // limpia cualquier sesión previa
            try {
              $qp = "SELECT m.slug,
                            MAX(rp.ver)        AS ver,
                            MAX(rp.crear)      AS crear,
                            MAX(rp.actualizar) AS actualizar,
                            MAX(rp.eliminar)   AS eliminar
                     FROM usuario_rol ur
                     JOIN rol_permiso rp ON rp.id_rol = ur.id_rol
                     JOIN modulos m      ON m.id_modulo = rp.id_modulo
                     WHERE ur.id_usuario = :uid
                     GROUP BY m.slug";
              $sp = $con->prepare($qp);
              $sp->execute([':uid' => $_SESSION['user_id']]);

              while ($p = $sp->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['permisos'][$p['slug']] = [
                  'ver'        => (int)$p['ver'],
                  'crear'      => (int)$p['crear'],
                  'actualizar' => (int)$p['actualizar'],
                  'eliminar'   => (int)$p['eliminar'],
                ];
              }
            } catch (PDOException $e) {
              $_SESSION['permisos'] = [];
            }

            // 8) Sucursales permitidas + sucursal activa (NUEVO)
            $_SESSION['sucursales_ids']   = [];
            $_SESSION['sucursal_activa']  = 0;
            $_SESSION['id_sucursal_activa'] = 0;
            try {
              $qs = $con->prepare("
                SELECT us.id_sucursal
                FROM usuario_sucursal us
                JOIN sucursales s ON s.id = us.id_sucursal AND s.estado = 1
                WHERE us.id_usuario = :u
                ORDER BY s.nombre
              ");
              $qs->execute([':u' => $_SESSION['user_id']]);
              $rows = $qs->fetchAll(PDO::FETCH_COLUMN, 0);
              $_SESSION['sucursales_ids'] = array_map('intval', $rows);

              if (!empty($_SESSION['sucursales_ids'])) {
                $_SESSION['sucursal_activa']     = (int)$_SESSION['sucursales_ids'][0];
                $_SESSION['id_sucursal_activa']  = (int)$_SESSION['sucursales_ids'][0];
              }
            } catch (Throwable $e) {
              // si falla, el header intentará forzar o mostrar texto
            }

            // 9) Redirige al dashboard
            header("Location: dashboard.php");
            exit;
          }
        }
      } else {
        $message = 'Usuario o contraseña incorrectos';
      }
    } catch (PDOException $ex) {
      $message = 'Error de conexión. Intente más tarde.';
      // echo $ex->getMessage(); exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SANATORIO LA ESPERANZA</title>

  <!-- Google Fonts y Estilos -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="shortcut icon" href="./dist/img/La Esperanza.png" type="image/x-icon">

  <style>
    body{
      background-image: url('dist/img/'); /* o 'login.png' si prefieres */
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }
    .login-box{ width:430px; }
    #system-logo{ width:5em; height:5em; object-fit:cover; object-position:center; }
    .input-icon-container{ position:relative; }
    .input-icon-container input{ padding-left:40px; padding-right:40px; }
    .input-icon-left{ position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#999; font-size:1.2rem; pointer-events:none; }
    .input-icon-right{ position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#999; font-size:1.2rem; cursor:pointer; }
  </style>
</head>

<body class="hold-transition login-page">
  <div class="login-box">
    <!-- LOGO -->
    <div class="login-logo mb-4">
      <img src="user_images/logo_esperanza.png" class="img-thumbnail p-0 border rounded-circle" id="system-logo" alt="Logo">
      <div class="text-center h2 mb-0">SANATORIO LA ESPERANZA</div>
    </div>

    <!-- TARJETA DE LOGIN -->
    <div class="card card-outline card-primary rounded-0 shadow">
      <div class="card-body login-card-body">
        <p class="login-box-msg">BIENVENIDO</p>

        <form method="post" autocomplete="off">
          <!-- Usuario -->
          <div class="form-group input-icon-container mb-3">
            <input type="text" class="form-control form-control-lg rounded-0 autofocus"
                   placeholder="Ingresa tu usuario" id="user_name" name="user_name" required>
            <i class="fas fa-user input-icon-left"></i>
          </div>

          <!-- Contraseña -->
          <div class="form-group input-icon-container mb-3">
            <input type="password" class="form-control form-control-lg rounded-0"
                   placeholder="Ingresa tu contraseña" id="password" name="password" required>
            <i class="fas fa-lock input-icon-left"></i>
            <i class="fas fa-eye input-icon-right" id="togglePassword" onclick="togglePasswordVisibility()"></i>
          </div>

          <!-- Botón Acceso -->
          <div class="row">
            <div class="col-12">
              <button name="login" type="submit" class="btn rounded-0 btn-block"
                      style="background-color:#2ecc71;color:white;font-weight:bold;border:none;transition:background-color .3s ease;">
                Acceso
              </button>
            </div>
          </div>

          <!-- Mensaje -->
          <div class="row mt-2">
            <div class="col-md-12">
              <p class="text-danger text-center"><?php if ($message !== '') echo htmlspecialchars($message); ?></p>
            </div>
          </div>
        </form>

        <!-- ===== ENLACES NUEVOS ===== -->
        <div class="text-center mt-2">
          <a href="auth/olvido.php">¿Olvidaste tu contraseña?</a><br>
          <small>¿Necesitas acceso? Contacta al administrador.</small>
        </div>
        <!-- ========================= -->
      </div>
    </div>
  </div>

  <!-- PIE -->
  <div style="text-align:center;margin-top:40px;font-size:13px;color:black;">
    <strong style="color:#2c3e50;">LA ESPERANZA</strong> © 2025<br>Todos los Derechos Reservados.
  </div>

  <script>
    function togglePasswordVisibility(){
      const passwordInput=document.getElementById('password');
      const toggleIcon=document.getElementById('togglePassword');
      if(passwordInput.type==='password'){
        passwordInput.type='text';
        toggleIcon.classList.remove('fa-eye'); toggleIcon.classList.add('fa-eye-slash');
      }else{
        passwordInput.type='password';
        toggleIcon.classList.remove('fa-eye-slash'); toggleIcon.classList.add('fa-eye');
      }
    }
  </script>
</body>
</html>
