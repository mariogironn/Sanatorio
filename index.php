<?php
// index.php (LOGIN)
/**
 * Sistema Sanatorio La Esperanza
 * Versión: v0.1
 * Desarrollado por: Mario Giron
 * GitHub: https://github.com/mariogironn/Sanatorio.git
 * 
 * [Descripción específica del archivo]
 */
include './config/connection.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$message = '';

if (isset($_POST['login'])) {
  $userName = trim($_POST['user_name'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($userName === '' || $password === '') {
    $message = 'Usuario y contraseña son obligatorios.';
  } else {
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

        if (preg_match('/^[a-f0-9]{32}$/i', $hashDB)) {
          if (md5($password) === $hashDB) {
            $loginOK = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $con->prepare("UPDATE usuarios SET contrasena = :h WHERE id = :id")
                ->execute([':h' => $newHash, ':id' => (int)$row['id']]);
            $hashDB = $newHash;
          }
        } else {
          $loginOK = password_verify($password, $hashDB);
          if ($loginOK && password_needs_rehash($hashDB, PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $con->prepare("UPDATE usuarios SET contrasena = :h WHERE id = :id")
                ->execute([':h' => $newHash, ':id' => (int)$row['id']]);
          }
        }

        if (!$loginOK) {
          $message = 'Usuario o contraseña incorrectos';
        } else {
          if (!isset($row['estado']) || $row['estado'] !== 'ACTIVO') {
            $message = 'Su cuenta está BLOQUEADA. Contacte al administrador.';
          } else {
            if (session_status() === PHP_SESSION_ACTIVE) {
              session_regenerate_id(true);
            }

            $_SESSION['user_id']        = (int)$row['id'];
            $_SESSION['nombre_mostrar'] = $row['nombre_mostrar'];
            $_SESSION['usuario']        = $row['usuario'];
            $_SESSION['imagen_perfil']  = $row['imagen_perfil'];

            $_SESSION['rol_nombre'] = 'Sin rol';
            try {
              $qr = "SELECT rol_nombre FROM vw_usuario_rol_principal WHERE id_usuario = :id LIMIT 1";
              $sr = $con->prepare($qr);
              $sr->execute([':id' => $_SESSION['user_id']]);
              if ($r = $sr->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['rol_nombre'] = $r['rol_nombre'] ?: 'Sin rol';
              }
            } catch (PDOException $e) {
              $_SESSION['rol_nombre'] = 'Sin rol';
            }

            $_SESSION['permisos'] = [];
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

            header("Location: dashboard.php");
            exit;
          }
        }
      } else {
        $message = 'Usuario o contraseña incorrectos';
      }
    } catch (PDOException $ex) {
      $message = 'Error de conexión. Intente más tarde.';
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="shortcut icon" href="./dist/img/La Esperanza.png" type="image/x-icon">
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }

    .login-container {
      background: white;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 420px;
      overflow: hidden;
      animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-25px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .login-header {
      background: linear-gradient(to right, #4a00e0, #8e2de2);
      color: white;
      padding: 30px 25px;
      text-align: center;
      position: relative;
    }

    .logo-container {
      width: 100px;
      height: 100px;
      margin: 0 auto 20px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
      border: 4px solid white;
      overflow: hidden;
      position: relative;
    }

    .logo-container img {
      width: 85px;
      height: 85px;
      object-fit: cover;
      border-radius: 50%;
      border: 2px solid #f8f9fa;
    }

    .logo-placeholder {
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #4a00e0, #8e2de2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 2rem;
    }

    .login-header h1 {
      font-size: 1.6rem;
      margin-bottom: 5px;
      font-weight: 700;
    }

    .login-header p {
      font-size: 0.95rem;
      opacity: 0.9;
    }

    .login-body {
      padding: 30px;
    }

    .welcome-message {
      text-align: center;
      margin-bottom: 25px;
      color: #333;
      font-size: 1.1rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .heart-beat {
      color: #ff4757;
      animation: heartBeat 1.5s ease-in-out infinite;
      font-size: 1.3rem;
      text-shadow: 0 0 10px rgba(255, 71, 87, 0.5);
    }

    @keyframes heartBeat {
      0% {
        transform: scale(1);
        text-shadow: 0 0 5px rgba(255, 71, 87, 0.5);
      }
      15% {
        transform: scale(1.3);
        text-shadow: 0 0 15px rgba(255, 71, 87, 0.8);
      }
      30% {
        transform: scale(1);
        text-shadow: 0 0 5px rgba(255, 71, 87, 0.5);
      }
      45% {
        transform: scale(1.2);
        text-shadow: 0 0 12px rgba(255, 71, 87, 0.7);
      }
      60% {
        transform: scale(1);
        text-shadow: 0 0 5px rgba(255, 71, 87, 0.5);
      }
      100% {
        transform: scale(1);
        text-shadow: 0 0 5px rgba(255, 71, 87, 0.5);
      }
    }

    .form-group {
      margin-bottom: 20px;
    }

    .input-with-icon {
      position: relative;
    }

    .input-with-icon input {
      width: 100%;
      padding: 15px 15px 15px 45px;
      border: 2px solid #e1e5e9;
      border-radius: 10px;
      font-size: 1rem;
      transition: all 0.3s;
    }

    .input-with-icon input:focus {
      border-color: #4a00e0;
      box-shadow: 0 0 0 3px rgba(74, 0, 224, 0.1);
      outline: none;
    }

    .input-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #777;
      font-size: 1.1rem;
    }

    .toggle-password {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #777;
      cursor: pointer;
      font-size: 1.1rem;
    }

    .btn-login {
      display: block;
      width: 100%;
      padding: 15px;
      border: none;
      border-radius: 10px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      text-align: center;
      background: linear-gradient(to right, #00b09b, #96c93d);
      color: white;
      margin-bottom: 15px;
      position: relative;
      overflow: hidden;
    }

    .btn-login:hover {
      transform: translateY(-3px);
      box-shadow: 0 7px 15px rgba(0, 176, 155, 0.3);
    }

    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: 0.5s;
    }

    .btn-login:hover::before {
      left: 100%;
    }

    .message {
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      font-size: 0.95rem;
    }

    .message-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .login-links {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #e9ecef;
    }

    .login-links a {
      color: #4a00e0;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.3s;
    }

    .login-links a:hover {
      color: #8e2de2;
      text-decoration: underline;
    }

    .footer {
      text-align: center;
      margin-top: 30px;
      font-size: 0.85rem;
      color: #6c757d;
    }

    .footer strong {
      color: #4a00e0;
    }

    .version-info {
      text-align: center;
      margin-top: 15px;
      padding: 10px;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 8px;
      border: 1px solid #dee2e6;
    }

    .version-text {
      font-size: 0.8rem;
      color: #495057;
      font-weight: 500;
    }

    .version-text .version {
      color: #4a00e0;
      font-weight: 700;
    }

    .version-text .developer {
      color: #8e2de2;
      font-weight: 600;
    }

    @media (max-width: 480px) {
      .login-container {
        max-width: 100%;
      }
      
      .login-body {
        padding: 25px 20px;
      }
      
      .login-header {
        padding: 25px 20px;
      }
      
      .login-header h1 {
        fontSize: 1.4rem;
      }
      
      .logo-container {
        width: 90px;
        height: 90px;
      }
      
      .logo-container img {
        width: 78px;
        height: 78px;
      }
      
      .welcome-message {
        font-size: 1rem;
      }
      
      .heart-beat {
        font-size: 1.1rem;
      }
    }

    .pulse {
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { 
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(0, 176, 155, 0.4);
      }
      70% { 
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(0, 176, 155, 0);
      }
      100% { 
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(0, 176, 155, 0);
      }
    }

    /* Efecto de brillo intermitente adicional */
    @keyframes glow {
      0%, 100% {
        filter: drop-shadow(0 0 5px rgba(255, 71, 87, 0.7));
      }
      50% {
        filter: drop-shadow(0 0 15px rgba(255, 71, 87, 0.9));
      }
    }

    .heart-glow {
      animation: glow 2s ease-in-out infinite;
    }
  </style>
</head>

<body>
  <div class="login-container">
    <!-- Encabezado -->
    <div class="login-header">
      <div class="logo-container">
        <img src="user_images/logo_esperanza.png" alt="Logo Sanatorio La Esperanza" 
             onerror="this.style.display='none'; document.getElementById('logoPlaceholder').style.display='flex';">
        <div class="logo-placeholder" id="logoPlaceholder" style="display: none;">
          <i class="fas fa-hospital"></i>
        </div>
      </div>
      <h1>SANATORIO LA ESPERANZA</h1>
      <p>Sistema de Control Admin.</p>
    </div>
    
    <!-- Cuerpo -->
    <div class="login-body">
      <div class="welcome-message">
        <i class="fas fa-heartbeat heart-beat heart-glow"></i> 
        <span>BIENVENIDO</span>
      </div>

      <form method="post" autocomplete="off">
        <!-- Campo Usuario -->
        <div class="form-group">
          <div class="input-with-icon">
            <i class="fas fa-user input-icon"></i>
            <input type="text" placeholder="Ingresa tu usuario" id="user_name" name="user_name" required autofocus>
          </div>
        </div>

        <!-- Campo Contraseña -->
        <div class="form-group">
          <div class="input-with-icon">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" placeholder="Ingresa tu contraseña" id="password" name="password" required>
            <button type="button" class="toggle-password" id="togglePassword">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- Mensaje de error -->
        <?php if ($message !== ''): ?>
          <div class="message message-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($message) ?>
          </div>
        <?php endif; ?>

        <!-- Botón de Acceso -->
        <button type="submit" name="login" class="btn-login pulse">
          <i class="fas fa-sign-in-alt"></i> ACCESO AL SISTEMA
        </button>
      </form>

      <!-- Enlaces -->
      <div class="login-links">
        <a href="auth/olvido.php">
          <i class="fas fa-key"></i> ¿Olvidaste tu contraseña?
        </a>
        <br>
        <small>¿Necesitas acceso? Contacta al administrador.</small>
      </div>

      <!-- Información de Versión -->
      <div class="version-info">
        <div class="version-text">
          <span class="version">Versión Inicial v0.1</span> | 
          <span class="developer">Desarrollado por Mario Giron</span>
        </div>
      </div>

      <!-- Pie de página -->
      <div class="footer">
        <strong>LA ESPERANZA</strong> © 2025<br>
        <small>Todos los Derechos Reservados</small>
      </div>
    </div>
  </div>

  <script>
    // Mostrar/ocultar contraseña
    document.getElementById('togglePassword').addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    });

    // Efecto de enfoque en los campos
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
      });
    });

    // Validación básica del formulario
    document.querySelector('form').addEventListener('submit', function(e) {
      const username = document.getElementById('user_name').value.trim();
      const password = document.getElementById('password').value;
      
      if (!username || !password) {
        e.preventDefault();
        alert('Por favor, completa todos los campos');
      }
    });

    // Verificar si la imagen del logo carga correctamente
    window.addEventListener('load', function() {
      const logoImg = document.querySelector('.logo-container img');
      const placeholder = document.getElementById('logoPlaceholder');
      
      setTimeout(() => {
        if (logoImg && logoImg.naturalHeight === 0) {
          logoImg.style.display = 'none';
          placeholder.style.display = 'flex';
        }
      }, 2000);
    });

    // Efecto adicional: hacer latir el corazón al hacer hover
    const heart = document.querySelector('.heart-beat');
    heart.addEventListener('mouseenter', function() {
      this.style.animation = 'heartBeat 0.8s ease-in-out infinite, glow 1s ease-in-out infinite';
    });
    
    heart.addEventListener('mouseleave', function() {
      this.style.animation = 'heartBeat 1.5s ease-in-out infinite, glow 2s ease-in-out infinite';
    });
  </script>
</body>
</html>