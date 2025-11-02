<?php
// auth/restablecer.php
// Valida token, pide nueva contraseña y la guarda con password_hash().

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';

$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl   = rtrim($scheme.'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']), '/');
$olvidoUrl = $baseUrl . '/olvido.php';

$token = $_GET['token'] ?? '';
$hash  = $token ? hash('sha256', $token) : '';

$tok   = null;
$state = 'form';   // 'form' | 'expired' | 'invalid' | 'done'
$error = '';

// 1) Buscar el token (sin filtrar por fecha/uso todavía; lo haremos en PHP)
if ($hash) {
  $st = $con->prepare("
    SELECT id, id_usuario, expira_en, usado_en
    FROM reset_password_tokens
    WHERE token_hash = :h
    LIMIT 1
  ");
  $st->execute([':h' => $hash]);
  $tok = $st->fetch(PDO::FETCH_ASSOC);
}

if (!$tok) {
  $state = 'invalid';
} else {
  // 2) Validación fuerte en servidor con DateTimeImmutable
  $now = new DateTimeImmutable('now');
  // expira_en viene como string 'Y-m-d H:i:s'
  try {
    $exp = new DateTimeImmutable($tok['expira_en']);
  } catch (Throwable $e) {
    $exp = $now->sub(new DateInterval('PT1S')); // fuerza expirado si formato raro
  }

  if (!is_null($tok['usado_en']) || $exp < $now) {
    $state = 'expired';
  }
}

// 3) Si el token es válido y llega POST, procesar cambio de contraseña
if ($state === 'form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $p1 = $_POST['pwd']  ?? '';
  $p2 = $_POST['pwd2'] ?? '';

  if ($p1 !== $p2 || strlen($p1) < 8) {
    $error = 'Las contraseñas no coinciden o son demasiado cortas (mínimo 8).';
  } else {
    // Guardar nueva contraseña (hash moderno)
    $hashPwd = password_hash($p1, PASSWORD_DEFAULT);
    $con->prepare("UPDATE usuarios SET contrasena = :p WHERE id = :id")
        ->execute([':p' => $hashPwd, ':id' => (int)$tok['id_usuario']]);

    // Marcar token como usado (y guardar huella opcional)
    $up = $con->prepare("
      UPDATE reset_password_tokens
      SET usado_en = NOW(), ip = COALESCE(ip, :ip), user_agent = COALESCE(user_agent, :ua)
      WHERE id = :id
    ");
    $up->execute([
      ':ip' => $_SERVER['REMOTE_ADDR']     ?? null,
      ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
      ':id' => (int)$tok['id']
    ]);

    $state = 'done';
  }
}

// 4) Códigos HTTP adecuados
if ($state === 'expired') {
  http_response_code(410); // Gone
} elseif ($state === 'invalid') {
  http_response_code(400); // Bad Request
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Restablecer contraseña</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    .container {
      background: white;
      border-radius: 15px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 450px;
      overflow: hidden;
      animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .header {
      background: linear-gradient(to right, #4a00e0, #8e2de2);
      color: white;
      padding: 25px;
      text-align: center;
    }

    .header h1 {
      font-size: 1.8rem;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .header p {
      font-size: 1rem;
      opacity: 0.9;
    }

    .body {
      padding: 30px;
    }

    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      margin-bottom: 10px;
      font-weight: 600;
      color: #333;
      font-size: 1rem;
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

    .btn {
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
    }

    .btn-primary {
      background: linear-gradient(to right, #4a00e0, #8e2de2);
      color: white;
      margin-bottom: 15px;
    }

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 7px 15px rgba(74, 0, 224, 0.3);
    }

    .btn-success {
      background: linear-gradient(to right, #00b09b, #96c93d);
      color: white;
      margin-bottom: 15px;
    }

    .btn-success:hover {
      transform: translateY(-3px);
      box-shadow: 0 7px 15px rgba(0, 176, 155, 0.3);
    }

    .btn-secondary {
      background-color: #f8f9fa;
      color: #555;
      border: 1px solid #e1e5e9;
    }

    .btn-secondary:hover {
      background-color: #e9ecef;
    }

    .status-message {
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      font-size: 0.95rem;
    }

    .status-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .status-warning {
      background-color: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }

    .status-danger {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .password-strength {
      margin-top: 8px;
      height: 4px;
      border-radius: 2px;
      background-color: #eee;
      overflow: hidden;
    }

    .password-strength-bar {
      height: 100%;
      width: 0;
      transition: width 0.3s, background-color 0.3s;
    }

    .strength-weak {
      background-color: #ff4757;
      width: 25%;
    }

    .strength-medium {
      background-color: #ffa502;
      width: 50%;
    }

    .strength-strong {
      background-color: #2ed573;
      width: 100%;
    }

    .password-hints {
      font-size: 0.8rem;
      color: #777;
      margin-top: 5px;
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
    }

    @media (max-width: 480px) {
      .container {
        max-width: 100%;
      }
      
      .body {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-lock"></i> Nueva Contraseña</h1>
      <p>Crea una nueva contraseña segura</p>
    </div>
    
    <div class="body">
      <?php if ($state === 'done'): ?>
        <div class="status-message status-success">
          <i class="fas fa-check-circle"></i> Contraseña actualizada. Ya puede iniciar sesión.
        </div>
        <a class="btn btn-success" href="../index.php">
          <i class="fas fa-home"></i> Ir al inicio
        </a>

      <?php elseif ($state === 'expired'): ?>
        <div class="status-message status-warning">
          <i class="fas fa-exclamation-triangle"></i> El enlace para restablecer la contraseña <b>ha expirado</b> o ya fue utilizado.
        </div>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($olvidoUrl) ?>">
          <i class="fas fa-redo"></i> Solicitar un nuevo enlace
        </a>

      <?php elseif ($state === 'invalid'): ?>
        <div class="status-message status-danger">
          <i class="fas fa-times-circle"></i> Enlace inválido.
        </div>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($olvidoUrl) ?>">
          <i class="fas fa-redo"></i> Solicitar un nuevo enlace
        </a>

      <?php else: ?>
        <?php if ($error): ?>
          <div class="status-message status-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        
        <form method="post" autocomplete="off" id="passwordForm">
          <div class="form-group">
            <label for="pwd"><i class="fas fa-lock"></i> Nueva contraseña</label>
            <div class="input-with-icon">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" id="pwd" name="pwd" minlength="8" required>
              <button type="button" class="toggle-password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div class="password-strength">
              <div class="password-strength-bar" id="passwordStrength"></div>
            </div>
            <div class="password-hints">
              La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, un número y un símbolo.
            </div>
          </div>
          
          <div class="form-group">
            <label for="pwd2"><i class="fas fa-lock"></i> Repite la contraseña</label>
            <div class="input-with-icon">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" id="pwd2" name="pwd2" minlength="8" required>
              <button type="button" class="toggle-password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div class="password-hints" id="passwordMatch"></div>
          </div>
          
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save"></i> Guardar nueva contraseña
          </button>
        </form>
        
        <a href="<?= htmlspecialchars($olvidoUrl) ?>" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Volver
        </a>

        <script>
          document.addEventListener('DOMContentLoaded', function() {
            const pwdInput = document.getElementById('pwd');
            const pwd2Input = document.getElementById('pwd2');
            const strengthBar = document.getElementById('passwordStrength');
            const matchText = document.getElementById('passwordMatch');
            const toggleButtons = document.querySelectorAll('.toggle-password');
            
            // Mostrar/ocultar contraseña
            toggleButtons.forEach(btn => {
              btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                  input.type = 'text';
                  icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                  input.type = 'password';
                  icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
              });
            });
            
            // Evaluar fortaleza de contraseña
            pwdInput.addEventListener('input', function() {
              const pwd = this.value;
              let strength = 0;
              
              if (pwd.length >= 8) strength += 25;
              if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) strength += 25;
              if (/\d/.test(pwd)) strength += 25;
              if (/[^A-Za-z0-9]/.test(pwd)) strength += 25;
              
              strengthBar.className = 'password-strength-bar';
              if (strength <= 25) {
                strengthBar.classList.add('strength-weak');
              } else if (strength <= 50) {
                strengthBar.classList.add('strength-medium');
              } else {
                strengthBar.classList.add('strength-strong');
              }
              
              checkPasswordMatch();
            });
            
            // Verificar coincidencia
            pwd2Input.addEventListener('input', checkPasswordMatch);
            
            function checkPasswordMatch() {
              const pwd1 = pwdInput.value;
              const pwd2 = pwd2Input.value;
              
              if (!pwd2) {
                matchText.textContent = '';
                return;
              }
              
              if (pwd1 === pwd2) {
                matchText.textContent = '✓ Las contraseñas coinciden';
                matchText.style.color = '#2ed573';
              } else {
                matchText.textContent = '✗ Las contraseñas no coinciden';
                matchText.style.color = '#ff4757';
              }
            }
          });
        </script>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>