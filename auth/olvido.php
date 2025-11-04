<?php
// auth/olvido.php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';

const DEBUG_SHOW_LINK   = true;
const RESET_TOKEN_TTL   = 120;

$msg            = '';
$showLink       = false;
$linkToShow     = '';
$expiresInSecs  = 0;
$usuario        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ident = trim($_POST['usuario'] ?? '');
  $usuario = $ident;

  $st = $con->prepare("SELECT id FROM usuarios WHERE usuario = :u LIMIT 1");
  $st->execute([':u' => $ident]);
  $user = $st->fetch(PDO::FETCH_ASSOC);

  $msg = "Si la cuenta existe, se ha enviado un enlace para restablecer la contraseña.";

  if ($user) {
    $con->prepare("DELETE FROM reset_password_tokens WHERE id_usuario = :id AND usado_en IS NULL")
        ->execute([':id' => (int)$user['id']]);

    $token     = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $token);
    $expiresAt = time() + RESET_TOKEN_TTL;
    $expiraDT  = date('Y-m-d H:i:s', $expiresAt);

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

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = rtrim($scheme.'://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/');
    $link   = $base . '/restablecer.php?token=' . $token;

    if (DEBUG_SHOW_LINK) {
      $showLink      = true;
      $linkToShow    = $link;
      $expiresInSecs = max(0, $expiresAt - time());
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Olvidé mi contraseña</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
    .container { background: white; border-radius: 15px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2); width: 100%; max-width: 450px; overflow: hidden; animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    .header { background: linear-gradient(to right, #4a00e0, #8e2de2); color: white; padding: 25px; text-align: center; }
    .header h1 { font-size: 1.8rem; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .header p { font-size: 1rem; opacity: 0.9; }
    .body { padding: 30px; }
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; margin-bottom: 10px; font-weight: 600; color: #333; font-size: 1rem; }
    .input-with-icon { position: relative; }
    .input-with-icon input { width: 100%; padding: 15px 15px 15px 45px; border: 2px solid #e1e5e9; border-radius: 10px; font-size: 1rem; transition: all 0.3s; }
    .input-with-icon input:focus { border-color: #4a00e0; box-shadow: 0 0 0 3px rgba(74, 0, 224, 0.1); outline: none; }
    .input-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #777; font-size: 1.1rem; }
    .btn { display: block; width: 100%; padding: 15px; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-align: center; }
    .btn-primary { background: linear-gradient(to right, #4a00e0, #8e2de2); color: white; margin-bottom: 15px; }
    .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 7px 15px rgba(74, 0, 224, 0.3); }
    .btn-secondary { background-color: #f8f9fa; color: #555; border: 1px solid #e1e5e9; }
    .btn-secondary:hover { background-color: #e9ecef; }
    .status-message { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 0.95rem; }
    .status-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    .link-section { margin-top: 25px; padding: 20px; background-color: #f8f9fa; border-radius: 10px; text-align: center; }
    .link-section h3 { margin-bottom: 12px; color: #333; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .reset-link { display: inline-block; background: linear-gradient(to right, #4a00e0, #8e2de2); color: white; padding: 12px 25px; border-radius: 25px; text-decoration: none; font-weight: 600; margin: 15px 0; transition: all 0.3s; }
    .reset-link:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(74, 0, 224, 0.3); }
    .timer { font-size: 0.9rem; color: #666; margin-top: 10px; }
    .disabled { pointer-events: none; opacity: 0.5; background: #6c757d !important; }
    @media (max-width: 480px) { .container { max-width: 100%; } .body { padding: 20px; } }
  </style>
</head>
<body>
  <div class="container">
    <?php if (!$showLink): ?>
      <!-- PANTALLA 1: Ingresar usuario -->
      <div class="header">
        <h1><i class="fas fa-key"></i> Restablecer Contraseña</h1>
        <p>Ingresa tu usuario para recibir un enlace de recuperación</p>
      </div>
      
      <div class="body">
        <!-- FORMULARIO CORREGIDO - TODO DENTRO DEL MISMO FORM -->
        <form method="post" autocomplete="off">
          <div class="form-group">
            <label for="username"><i class="fas fa-user"></i> Usuario</label>
            <div class="input-with-icon">
              <i class="fas fa-user input-icon"></i>
              <input type="text" id="username" name="usuario" placeholder="Ingresa tu nombre de usuario" value="<?= htmlspecialchars($usuario) ?>" required>
            </div>
          </div>
          
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Enviar enlace
          </button>
        </form>
        
        <a href="../index.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
      </div>

    <?php else: ?>
      <!-- PANTALLA 2: Enlace enviado -->
      <div class="header">
        <h1><i class="fas fa-envelope"></i>Verifica el enlace</h1>
        <p>Enlace en pantalla</p>
      </div>
      
      <div class="body">
        <div class="status-message status-info">
          <i class="fas fa-info-circle"></i> <?= htmlspecialchars($msg) ?>
        </div>
        
        <div class="link-section">
          <h3><i class="fas fa-link"></i> Enlace de restablecimiento</h3>
          <p>Haz clic en el siguiente enlace para continuar:</p>
          <a href="<?= htmlspecialchars($linkToShow) ?>" class="reset-link" id="resetLink">
            <i class="fas fa-external-link-alt"></i> Restablecer Contraseña
          </a>
          <div class="timer">
            <i class="fas fa-clock"></i> Caduca en: <span id="countdown">--:--</span>
          </div>
        </div>
        
        <a href="../index.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>
        
        <script>
          (function(){
            var left = <?= (int)$expiresInSecs ?>;
            var el = document.getElementById('countdown');
            var a  = document.getElementById('resetLink');
            function pad(n){ return (n<10?'0':'')+n; }
            function render(){ var m = Math.floor(left/60), s = left%60; el.textContent = pad(m)+':'+pad(s); }
            render();
            var it = setInterval(function(){
              left--;
              if (left <= 0) { clearInterval(it); el.textContent = '00:00'; if (a) { a.classList.add('disabled'); a.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Enlace Expirado'; a.removeAttribute('href'); } } else { render(); }
            }, 1000);
          })();
        </script>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>