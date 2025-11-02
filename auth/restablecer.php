<?php
// auth/restablecer.php
/**
 * SISTEMA DE RESTABLECIMIENTO DE CONTRASEÑA
 * 
 * Este script valida tokens de restablecimiento, solicita una nueva contraseña
 * y la almacena de forma segura usando password_hash().
 * 
 * CARACTERÍSTICAS PRINCIPALES:
 * - Validación robusta de tokens
 * - Almacenamiento seguro de contraseñas
 * - Interfaz con validación en tiempo real
 * - Códigos HTTP semánticos
 */

// INICIALIZACIÓN DE SESIÓN Y CONEXIÓN A BD
/**
 * Verifica e inicia la sesión PHP si no está activa
 * El operador @ suprime posibles warnings
 */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/**
 * Incluye la configuración de conexión a la base de datos
 */
require_once __DIR__ . '/../config/connection.php';

// CONSTRUCCIÓN DE URLs
/**
 * Determina el esquema (http/https) basado en la configuración del servidor
 */
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

/**
 * Construye la URL base de la aplicación
 */
$baseUrl   = rtrim($scheme.'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']), '/');

/**
 * URL para redireccionar a la página de olvido de contraseña
 */
$olvidoUrl = $baseUrl . '/olvido.php';

// PROCESAMIENTO DEL TOKEN
/**
 * Obtiene el token desde los parámetros GET
 */
$token = $_GET['token'] ?? '';

/**
 * Calcula el hash SHA256 del token para comparación segura en la BD
 */
$hash  = $token ? hash('sha256', $token) : '';

/**
 * $tok: Almacenará la información del token desde la base de datos
 */
$tok   = null;

/**
 * $state: Controla el estado del flujo de la aplicación
 * Posibles valores: 'form' | 'expired' | 'invalid' | 'done'
 */
$state = 'form';

/**
 * $error: Almacena mensajes de error para el usuario
 */
$error = '';

// 1) BÚSQUEDA DEL TOKEN EN LA BASE DE DATOS
/**
 * Si existe un token, busca su información en la base de datos
 * Nota: La validación de fecha y uso se hace posteriormente en PHP
 */
if ($hash) {
  /**
   * Consulta preparada para buscar el token por su hash
   * Selecciona información crítica para validación
   */
  $st = $con->prepare("
    SELECT id, id_usuario, expira_en, usado_en
    FROM reset_password_tokens
    WHERE token_hash = :h
    LIMIT 1
  ");
  $st->execute([':h' => $hash]);
  $tok = $st->fetch(PDO::FETCH_ASSOC);
}

// 2) VALIDACIÓN DEL ESTADO DEL TOKEN
/**
 * Si no se encuentra el token, marca como inválido
 */
if (!$tok) {
  $state = 'invalid';
} else {
  /**
   * VALIDACIÓN TEMPORAL ROBUSTA CON DateTimeImmutable
   * 
   * Usa DateTimeImmutable para prevenir efectos secundarios
   * y manejar zonas horarias correctamente
   */
  $now = new DateTimeImmutable('now');
  
  /**
   * Convierte la fecha de expiración de string a objeto DateTime
   * Maneja posibles excepciones en el formato
   */
  try {
    $exp = new DateTimeImmutable($tok['expira_en']);
  } catch (Throwable $e) {
    /**
     * Si hay error en el formato, fuerza expiración restando 1 segundo
     */
    $exp = $now->sub(new DateInterval('PT1S'));
  }

  /**
   * Verifica si el token ya fue usado o está expirado
   */
  if (!is_null($tok['usado_en']) || $exp < $now) {
    $state = 'expired';
  }
}

// 3) PROCESAMIENTO DEL CAMBIO DE CONTRASEÑA (MÉTODO POST)
/**
 * Si el token es válido y se envió el formulario, procesa el cambio
 */
if ($state === 'form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  /**
   * Obtiene las contraseñas del formulario
   */
  $p1 = $_POST['pwd']  ?? '';
  $p2 = $_POST['pwd2'] ?? '';

  /**
   * VALIDACIÓN DE CONTRASEÑAS
   * - Verifica que coincidan
   * - Longitud mínima de 8 caracteres
   */
  if ($p1 !== $p2 || strlen($p1) < 8) {
    $error = 'Las contraseñas no coinciden o son demasiado cortas (mínimo 8).';
  } else {
    /**
     * HASH SEGURO DE CONTRASEÑA
     * 
     * Usa password_hash() con PASSWORD_DEFAULT que automáticamente
     * selecciona el algoritmo más seguro disponible
     */
    $hashPwd = password_hash($p1, PASSWORD_DEFAULT);
    
    /**
     * ACTUALIZACIÓN DE CONTRASEÑA EN LA BASE DE DATOS
     * 
     * Actualiza la contraseña del usuario identificado por el token
     */
    $con->prepare("UPDATE usuarios SET contrasena = :p WHERE id = :id")
        ->execute([':p' => $hashPwd, ':id' => (int)$tok['id_usuario']]);

    /**
     * MARCADO DEL TOKEN COMO USADO
     * 
     * Actualiza el token para evitar reutilización
     * COALESCE preserva datos de auditoría existentes si los hay
     */
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

    /**
     * Cambia el estado a completado
     */
    $state = 'done';
  }
}

// 4) CÓDIGOS HTTP SEMÁNTICOS
/**
 * Asigna códigos de estado HTTP apropiados para cada situación
 */
if ($state === 'expired') {
  http_response_code(410); // Gone - recurso ya no disponible
} elseif ($state === 'invalid') {
  http_response_code(400); // Bad Request - solicitud mal formada
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Restablecer contraseña</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- INCLUSIÓN DE FONT AWESOME PARA ÍCONOS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* RESET Y CONFIGURACIONES BASE */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* FONDO CON GRADIENTE ANIMADO */
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }

    /* CONTENEDOR PRINCIPAL */
    .container {
      background: white;
      border-radius: 15px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 450px;
      overflow: hidden;
      animation: fadeIn 0.5s ease-out;
    }

    /* ANIMACIÓN DE ENTRADA */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* CABECERA CON GRADIENTE */
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

    /* CUERPO DEL FORMULARIO */
    .body {
      padding: 30px;
    }

    /* GRUPOS DE FORMULARIO */
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

    /* INPUT CON ÍCONO */
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

    /* BOTONES */
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

    /* BOTÓN DE ÉXITO (VERDE) */
    .btn-success {
      background: linear-gradient(to right, #00b09b, #96c93d);
      color: white;
      margin-bottom: 15px;
    }

    .btn-success:hover {
      transform: translateY(-3px);
      box-shadow: 0 7px 15px rgba(0, 176, 155, 0.3);
    }

    /* BOTÓN SECUNDARIO */
    .btn-secondary {
      background-color: #f8f9fa;
      color: #555;
      border: 1px solid #e1e5e9;
    }

    .btn-secondary:hover {
      background-color: #e9ecef;
    }

    /* MENSAJES DE ESTADO */
    .status-message {
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      font-size: 0.95rem;
    }

    /* VARIANTES DE MENSAJES DE ESTADO */
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

    /* INDICADOR DE FORTALEZA DE CONTRASEÑA */
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

    /* NIVELES DE FORTALEZA */
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

    /* HINTS Y AYUDAS */
    .password-hints {
      font-size: 0.8rem;
      color: #777;
      margin-top: 5px;
    }

    /* BOTÓN PARA MOSTRAR/OCULTAR CONTRASEÑA */
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

    /* RESPONSIVE DESIGN */
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
        <!-- 
          ESTADO: CAMBIO COMPLETADO
          Se muestra cuando la contraseña se actualizó exitosamente
        -->
        <div class="status-message status-success">
          <i class="fas fa-check-circle"></i> Contraseña actualizada. Ya puede iniciar sesión.
        </div>
        <a class="btn btn-success" href="../index.php">
          <i class="fas fa-home"></i> Ir al inicio
        </a>

      <?php elseif ($state === 'expired'): ?>
        <!-- 
          ESTADO: TOKEN EXPIRADO O USADO
          Código HTTP 410 - Gone
        -->
        <div class="status-message status-warning">
          <i class="fas fa-exclamation-triangle"></i> El enlace para restablecer la contraseña <b>ha expirado</b> o ya fue utilizado.
        </div>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($olvidoUrl) ?>">
          <i class="fas fa-redo"></i> Solicitar un nuevo enlace
        </a>

      <?php elseif ($state === 'invalid'): ?>
        <!-- 
          ESTADO: TOKEN INVÁLIDO
          Código HTTP 400 - Bad Request
        -->
        <div class="status-message status-danger">
          <i class="fas fa-times-circle"></i> Enlace inválido.
        </div>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($olvidoUrl) ?>">
          <i class="fas fa-redo"></i> Solicitar un nuevo enlace
        </a>

      <?php else: ?>
        <!-- 
          ESTADO: FORMULARIO ACTIVO
          Se muestra el formulario para ingresar nueva contraseña
        -->
        
        <?php if ($error): ?>
          <!-- MENSAJE DE ERROR DEL SERVIDOR -->
          <div class="status-message status-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        
        <!-- FORMULARIO DE NUEVA CONTRASEÑA -->
        <form method="post" autocomplete="off" id="passwordForm">
          <!-- CAMPO CONTRASEÑA PRINCIPAL -->
          <div class="form-group">
            <label for="pwd"><i class="fas fa-lock"></i> Nueva contraseña</label>
            <div class="input-with-icon">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" id="pwd" name="pwd" minlength="8" required>
              <!-- BOTÓN TOGGLE VISIBILIDAD -->
              <button type="button" class="toggle-password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <!-- INDICADOR VISUAL DE FORTALEZA -->
            <div class="password-strength">
              <div class="password-strength-bar" id="passwordStrength"></div>
            </div>
            <!-- HINTS DE REQUISITOS -->
            <div class="password-hints">
              La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, un número y un símbolo.
            </div>
          </div>
          
          <!-- CAMPO CONFIRMACIÓN DE CONTRASEÑA -->
          <div class="form-group">
            <label for="pwd2"><i class="fas fa-lock"></i> Repite la contraseña</label>
            <div class="input-with-icon">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" id="pwd2" name="pwd2" minlength="8" required>
              <!-- BOTÓN TOGGLE VISIBILIDAD -->
              <button type="button" class="toggle-password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <!-- INDICADOR DE COINCIDENCIA -->
            <div class="password-hints" id="passwordMatch"></div>
          </div>
          
          <!-- BOTÓN DE ENVÍO -->
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save"></i> Guardar nueva contraseña
          </button>
        </form>
        
        <!-- ENLACE PARA VOLVER -->
        <a href="<?= htmlspecialchars($olvidoUrl) ?>" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Volver
        </a>

        <!-- 
          SCRIPT DE VALIDACIÓN EN TIEMPO REAL
          Proporciona feedback inmediato al usuario sobre la calidad de la contraseña
        -->
        <script>
          /**
           * INICIALIZACIÓN CUANDO EL DOM ESTÁ LISTO
           */
          document.addEventListener('DOMContentLoaded', function() {
            // REFERENCIAS A ELEMENTOS DEL DOM
            const pwdInput = document.getElementById('pwd');
            const pwd2Input = document.getElementById('pwd2');
            const strengthBar = document.getElementById('passwordStrength');
            const matchText = document.getElementById('passwordMatch');
            const toggleButtons = document.querySelectorAll('.toggle-password');
            
            // FUNCIONALIDAD TOGGLE VISIBILIDAD DE CONTRASEÑA
            toggleButtons.forEach(btn => {
              btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                
                // ALTERNAR ENTRE TIPO PASSWORD Y TEXT
                if (input.type === 'password') {
                  input.type = 'text';
                  icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                  input.type = 'password';
                  icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
              });
            });
            
            // EVALUACIÓN DE FORTALEZA DE CONTRASEÑA EN TIEMPO REAL
            pwdInput.addEventListener('input', function() {
              const pwd = this.value;
              let strength = 0;
              
              // CRITERIOS DE FORTALEZA (CADA UNO VALE 25 PUNTOS)
              if (pwd.length >= 8) strength += 25;                    // Longitud mínima
              if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) strength += 25; // Mayúsculas y minúsculas
              if (/\d/.test(pwd)) strength += 25;                     // Números
              if (/[^A-Za-z0-9]/.test(pwd)) strength += 25;          // Símbolos
              
              // ACTUALIZAR INDICADOR VISUAL
              strengthBar.className = 'password-strength-bar';
              if (strength <= 25) {
                strengthBar.classList.add('strength-weak');
              } else if (strength <= 50) {
                strengthBar.classList.add('strength-medium');
              } else {
                strengthBar.classList.add('strength-strong');
              }
              
              // VERIFICAR COINCIDENCIA
              checkPasswordMatch();
            });
            
            // VERIFICACIÓN DE COINCIDENCIA EN SEGUNDO CAMPO
            pwd2Input.addEventListener('input', checkPasswordMatch);
            
            /**
             * FUNCIÓN: VERIFICAR COINCIDENCIA DE CONTRASEÑAS
             * Actualiza el texto indicador según si las contraseñas coinciden
             */
            function checkPasswordMatch() {
              const pwd1 = pwdInput.value;
              const pwd2 = pwd2Input.value;
              
              // Si el segundo campo está vacío, limpiar mensaje
              if (!pwd2) {
                matchText.textContent = '';
                return;
              }
              
              // Mostrar mensaje de coincidencia o error
              if (pwd1 === pwd2) {
                matchText.textContent = '✓ Las contraseñas coinciden';
                matchText.style.color = '#2ed573'; // Verde
              } else {
                matchText.textContent = '✗ Las contraseñas no coinciden';
                matchText.style.color = '#ff4757'; // Rojo
              }
            }
          });
        </script>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>