<?php
// auth/olvido.php

/**
 * SISTEMA DE RECUPERACIÓN DE CONTRASEÑA
 * 
 * Este script maneja el proceso de olvido de contraseña permitiendo a los usuarios
 * solicitar un enlace para restablecer sus credenciales mediante tokens temporales.
 * 
 * CARACTERÍSTICAS PRINCIPALES:
 * - Generación de tokens seguros
 * - Protección contra ataques
 * - Interfaz responsive
 * - Sistema de expiración temporal
 */

// INICIALIZACIÓN DE SESIÓN Y CONEXIÓN A BD
/**
 * Verifica si la sesión no está activa y la inicia silenciosamente
 * El operador @ suprime posibles warnings si la sesión ya está iniciada
 */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/**
 * Incluye el archivo de configuración de conexión a la base de datos
 * __DIR__ asegura la ruta absoluta independiente del include path
 */
require_once __DIR__ . '/../config/connection.php';

// CONSTANTES DE CONFIGURACIÓN DEL SISTEMA
/**
 * DEBUG_SHOW_LINK: Modo desarrollo - muestra el enlace directamente en pantalla
 * En producción debe cambiarse a false para enviar por email
 */
const DEBUG_SHOW_LINK   = true;

/**
 * RESET_TOKEN_TTL: Time To Live del token en segundos
 * 120 segundos = 2 minutos de validez
 */
const RESET_TOKEN_TTL   = 120;

// DECLARACIÓN DE VARIABLES GLOBALES
/**
 * $msg: Almacena mensajes de estado para el usuario
 */
$msg            = '';

/**
 * $showLink: Controlador de flujo - determina qué pantalla mostrar
 * false = formulario de solicitud, true = pantalla de confirmación
 */
$showLink       = false;

/**
 * $linkToShow: Contiene el enlace de restablecimiento generado
 */
$linkToShow     = '';

/**
 * $expiresInSecs: Tiempo restante en segundos para la expiración del token
 */
$expiresInSecs  = 0;

/**
 * $usuario: Almacena el nombre de usuario ingresado para repoblar el formulario
 */
$usuario        = '';

// PROCESAMIENTO DEL FORMULARIO (MÉTODO POST)
/**
 * Verifica si la solicitud es POST (envío del formulario)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
  /**
   * Obtiene y sanitiza el identificador de usuario
   * trim() elimina espacios en blanco al inicio y final
   * ?? '' proporciona valor por defecto si no existe
   */
  $ident = trim($_POST['usuario'] ?? '');
  
  /**
   * Almacena el usuario para repoblar el campo del formulario
   */
  $usuario = $ident;

  /**
   * CONSULTA PARA VERIFICAR EXISTENCIA DE USUARIO
   * 
   * Prepara y ejecuta consulta segura usando parámetros nombrados
   * para prevenir inyección SQL
   */
  $st = $con->prepare("SELECT id FROM usuarios WHERE usuario = :u LIMIT 1");
  $st->execute([':u' => $ident]);
  $user = $st->fetch(PDO::FETCH_ASSOC);

  /**
   * MENSAJE DE ESTADO GENÉRICO
   * 
   * Se muestra siempre el mismo mensaje independientemente de si el usuario existe
   * para prevenir ataques de enumeración (timing attacks)
   */
  $msg = "Si la cuenta existe, se ha enviado un enlace para restablecer la contraseña.";

  /**
   * Si el usuario existe en la base de datos, procede con la generación del token
   */
  if ($user) {
    
    /**
     * LIMPIEZA DE TOKENS PREVIOS
     * 
     * Elimina tokens de restablecimiento no utilizados del mismo usuario
     * para prevenir acumulación y posibles vulnerabilidades
     */
    $con->prepare("DELETE FROM reset_password_tokens WHERE id_usuario = :id AND usado_en IS NULL")
        ->execute([':id' => (int)$user['id']]);

    /**
     * GENERACIÓN DE TOKEN SEGURO
     * 
     * $token: Token aleatorio de 64 caracteres hexadecimales (32 bytes)
     * $hash: Hash SHA256 del token para almacenamiento seguro
     * $expiresAt: Timestamp Unix de expiración
     * $expiraDT: Fecha de expiración en formato MySQL
     */
    $token     = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $token);
    $expiresAt = time() + RESET_TOKEN_TTL;
    $expiraDT  = date('Y-m-d H:i:s', $expiresAt);

    /**
     * INSERCIÓN DEL TOKEN EN LA BASE DE DATOS
     * 
     * Almacena el hash del token junto con información de auditoría:
     * - id_usuario: Relación con el usuario
     * - token_hash: Hash seguro del token
     * - creado_en: Timestamp de creación
     * - expira_en: Timestamp de expiración
     * - ip: Dirección IP del solicitante
     * - user_agent: Navegador y sistema del solicitante
     */
    $ins = $con->prepare("
      INSERT INTO reset_password_tokens
        (id_usuario, token_hash, creado_en, expira_en, ip, user_agent)
      VALUES
        (:id, :h, NOW(), :exp, :ip, :ua)
    ");
    $ins->execute([
      ':id'  => (int)$user['id'],
      ':h'   => $hash,
      ':exp' => $expiraDT,
      ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
      ':ua'  => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    /**
     * CONSTRUCCIÓN DEL ENLACE DE RESTABLECIMIENTO
     * 
     * Genera la URL absoluta del enlace incluyendo el token como parámetro
     */
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = rtrim($scheme.'://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/');
    $link   = $base . '/restablecer.php?token=' . $token;

    /**
     * CONFIGURACIÓN PARA MODO DEBUG
     * 
     * Si DEBUG_SHOW_LINK es true, muestra el enlace directamente en pantalla
     * En producción esto debería reemplazarse por envío por correo electrónico
     */
    if (DEBUG_SHOW_LINK) {
      $showLink      = true;
      $linkToShow    = $link;
      $expiresInSecs = max(0, $expiresAt - time());
    }
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Olvidé mi contraseña</title>
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
      from { 
        opacity: 0; 
        transform: translateY(-20px); 
      } 
      to { 
        opacity: 1; 
        transform: translateY(0); 
      } 
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
    
    .status-info { 
      background-color: #d1ecf1; 
      color: #0c5460; 
      border: 1px solid #bee5eb; 
    }
    
    /* SECCIÓN DEL ENLACE */
    .link-section { 
      margin-top: 25px; 
      padding: 20px; 
      background-color: #f8f9fa; 
      border-radius: 10px; 
      text-align: center; 
    }
    
    .link-section h3 { 
      margin-bottom: 12px; 
      color: #333; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      gap: 8px; 
    }
    
    .reset-link { 
      display: inline-block; 
      background: linear-gradient(to right, #4a00e0, #8e2de2); 
      color: white; 
      padding: 12px 25px; 
      border-radius: 25px; 
      text-decoration: none; 
      font-weight: 600; 
      margin: 15px 0; 
      transition: all 0.3s; 
    }
    
    .reset-link:hover { 
      transform: translateY(-2px); 
      box-shadow: 0 5px 15px rgba(74, 0, 224, 0.3); 
    }
    
    /* TEMPORIZADOR */
    .timer { 
      font-size: 0.9rem; 
      color: #666; 
      margin-top: 10px; 
    }
    
    /* ESTADO DESHABILITADO */
    .disabled { 
      pointer-events: none; 
      opacity: 0.5; 
      background: #6c757d !important; 
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
    <?php if (!$showLink): ?>
      <!-- 
        PANTALLA 1: FORMULARIO DE SOLICITUD 
        Se muestra cuando $showLink es false (inicial o sin procesar)
      -->
      <div class="header">
        <h1><i class="fas fa-key"></i> Restablecer Contraseña</h1>
        <p>Ingresa tu usuario para recibir un enlace de recuperación</p>
      </div>
      
      <div class="body">
        <!-- FORMULARIO PRINCIPAL -->
        <form method="post" autocomplete="off">
          <div class="form-group">
            <label for="username"><i class="fas fa-user"></i> Usuario</label>
            <div class="input-with-icon">
              <i class="fas fa-user input-icon"></i>
              <!-- 
                Campo de usuario con valor repoblado usando htmlspecialchars 
                para prevenir XSS
              -->
              <input type="text" id="username" name="usuario" 
                     placeholder="Ingresa tu nombre de usuario" 
                     value="<?= htmlspecialchars($usuario) ?>" 
                     required>
            </div>
          </div>
          
          <!-- BOTÓN DE ENVÍO -->
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Enviar enlace
          </button>
        </form>
        
        <!-- BOTÓN PARA VOLVER AL INICIO -->
        <a href="../index.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
      </div>

    <?php else: ?>
      <!-- 
        PANTALLA 2: CONFIRMACIÓN Y ENLACE 
        Se muestra cuando $showLink es true (después del procesamiento POST)
      -->
      <div class="header">
        <h1><i class="fas fa-envelope"></i> Verifica el enlace</h1>
        <p>Enlace en pantalla</p>
      </div>
      
      <div class="body">
        <!-- MENSAJE DE ESTADO -->
        <div class="status-message status-info">
          <i class="fas fa-info-circle"></i> <?= htmlspecialchars($msg) ?>
        </div>
        
        <!-- SECCIÓN DEL ENLACE DE RESTABLECIMIENTO -->
        <div class="link-section">
          <h3><i class="fas fa-link"></i> Enlace de restablecimiento</h3>
          <p>Haz clic en el siguiente enlace para continuar:</p>
          
          <!-- ENLACE DE RESTABLECIMIENTO -->
          <a href="<?= htmlspecialchars($linkToShow) ?>" class="reset-link" id="resetLink">
            <i class="fas fa-external-link-alt"></i> Restablecer Contraseña
          </a>
          
          <!-- TEMPORIZADOR DE EXPIRACIÓN -->
          <div class="timer">
            <i class="fas fa-clock"></i> Caduca en: <span id="countdown">--:--</span>
          </div>
        </div>
        
        <!-- BOTÓN PARA VOLVER AL INICIO -->
        <a href="../index.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
        
        <!-- 
          SCRIPT DEL TEMPORIZADOR
          Controla la cuenta regresiva y deshabilita el enlace al expirar
        -->
        <script>
          (function(){
            // Tiempo restante en segundos
            var left = <?= (int)$expiresInSecs ?>;
            var el = document.getElementById('countdown');
            var a  = document.getElementById('resetLink');
            
            // Función para formatear números con ceros a la izquierda
            function pad(n){ return (n<10?'0':'')+n; }
            
            // Función para actualizar el display del temporizador
            function render(){ 
              var m = Math.floor(left/60), s = left%60; 
              el.textContent = pad(m)+':'+pad(s); 
            }
            
            // Renderizado inicial
            render();
            
            // Intervalo que actualiza cada segundo
            var it = setInterval(function(){
              left--;
              
              // Cuando el tiempo expire
              if (left <= 0) { 
                clearInterval(it); 
                el.textContent = '00:00'; 
                
                // Deshabilitar el enlace
                if (a) { 
                  a.classList.add('disabled'); 
                  a.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Enlace Expirado'; 
                  a.removeAttribute('href'); 
                } 
              } else { 
                render(); 
              }
            }, 1000);
          })();
        </script>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>