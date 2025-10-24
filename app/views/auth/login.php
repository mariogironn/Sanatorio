<?php
// app/views/auth/login.php
session_start();
$message = '';

// [Mantén todo tu código PHP de validación igual...]
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SANATORIO LA ESPERANZA</title>

  <!-- === CORREGIDO: Incluir CSS directamente === -->
  <link rel="stylesheet" href="/sanatorio/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/sanatorio/dist/css/adminlte.min.css">
  <link rel="shortcut icon" href="/sanatorio/dist/img/La Esperanza.png" type="image/x-icon">

  <style>
    body{
      background-image: url('/sanatorio/dist/img/');
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
      <img src="/sanatorio/user_images/logo_esperanza.png" class="img-thumbnail p-0 border rounded-circle" id="system-logo" alt="Logo">
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

        <div class="text-center mt-2">
          <a href="/sanatorio/auth/olvido.php">¿Olvidaste tu contraseña?</a><br>
          <small>¿Necesitas acceso? Contacta al administrador.</small>
        </div>
      </div>
    </div>
  </div>

  <!-- PIE -->
  <div style="text-align:center;margin-top:40px;font-size:13px;color:black;">
    <strong style="color:#2c3e50;">LA ESPERANZA</strong> © 2025<br>Todos los Derechos Reservados.
  </div>

  <!-- === CORREGIDO: Incluir JS directamente === -->
  <script src="/sanatorio/plugins/jquery/jquery.min.js"></script>
  <script src="/sanatorio/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="/sanatorio/dist/js/adminlte.min.js"></script>

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