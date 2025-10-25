<?php
// auth/olvido.php
// Solicita usuario y genera un enlace temporal para restablecer contraseña.
// Muestra el enlace en pantalla si DEBUG_SHOW_LINK=true (útil sin correo).

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../config/connection.php';


const DEBUG_SHOW_LINK   = true;   // pon false en producción
const RESET_TOKEN_TTL   = 120;    // 120 segundos (2 min)

$msg            = '';
$showLink       = false;
$linkToShow     = '';
$expiresInSecs  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ident = trim($_POST['usuario'] ?? '');

  // Buscar usuario
  $st = $con->prepare("SELECT id FROM usuarios WHERE usuario = :u LIMIT 1");
  $st->execute([':u' => $ident]);
  $user = $st->fetch(PDO::FETCH_ASSOC);

  // Mensaje neutro siempre (no revela si existe o no)
  $msg = "Si la cuenta existe, se ha enviado un enlace para restablecer la contraseña.";

  if ($user) {
    // Invalidar tokens previos no usados
    $con->prepare("DELETE FROM reset_password_tokens WHERE id_usuario = :id AND usado_en IS NULL")
        ->execute([':id' => (int)$user['id']]);

    // Crear token seguro y guardar solo el hash (SHA-256)
    $token     = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $token);
    $expiresAt = time() + RESET_TOKEN_TTL;                         // epoch
    $expiraDT  = date('Y-m-d H:i:s', $expiresAt);                  // para BD

    // Guardar en la tabla (con creado_en y expira_en exactos)
    $ins = $con->prepare("
      INSERT INTO reset_password_tokens
        (id_usuario, token_hash, creado_en, expira_en, ip, user_agent)
      VALUES
        (:id, :h, NOW(), :exp, :ip, :ua)
    ");
    $ins->execute([ // token de restablecimiento de contraseña
      ':id'  => (int)$user['id'],
      ':h'   => $hash,
      ':exp' => $expiraDT,
      ':ip'  => $_SERVER['REMOTE_ADDR']     ?? null,
      ':ua'  => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Enlace
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = rtrim($scheme.'://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/');
    $link   = $base . '/restablecer.php?token=' . $token;

    if (DEBUG_SHOW_LINK) {
      $showLink      = true;
      $linkToShow    = $link;
      $expiresInSecs = max(0, $expiresAt - time());   // por si hay pequeña latencia
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
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <style>
    .countdown small { opacity:.8 }
    .disabled {
      pointer-events: none;
      opacity: .6;
      text-decoration: none;
    }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">Restablecer contraseña</h3></div>
    <div class="card-body">
      <form method="post" autocomplete="off">
        <div class="form-group">
          <label>Usuario</label>
          <input class="form-control" name="usuario" required>
        </div>
        <button class="btn btn-primary btn-block">Enviar enlace</button>
      </form>

      <?php if(!empty($msg)): ?>
        <p class="mt-3 text-success"><?= htmlspecialchars($msg) ?></p>
      <?php endif; ?>

      <?php if ($showLink): ?>
        <div class="alert alert-info mt-3">
          <b>Enlace de prueba:</b><br>
          <a id="resetLink" href="<?= htmlspecialchars($linkToShow) ?>">
            <?= htmlspecialchars($linkToShow) ?>
          </a>
          <div class="countdown mt-2">
            <small>Caduca en <b><span id="left">--:--</span></b></small>
          </div>
        </div>
        <script>
          (function(){
            var left = <?= (int)$expiresInSecs ?>; // segundos
            var el = document.getElementById('left');
            var a  = document.getElementById('resetLink');

            function pad(n){ return (n<10?'0':'')+n; }
            function render(){
              var m = Math.floor(left/60), s = left%60;
              el.textContent = pad(m)+':'+pad(s);
            }
            render();
            var it = setInterval(function(){
              left--;
              if (left <= 0) {
                clearInterval(it);
                el.textContent = '00:00';
                if (a) {
                  a.classList.add('disabled');
                  a.removeAttribute('href');
                  a.setAttribute('aria-disabled','true');
                  a.title = 'El enlace ha expirado. Solicita uno nuevo.';
                }
              } else {
                render();
              }
            }, 1000);
          })();
        </script>
      <?php endif; ?>

      <div class="mt-3"><a href="../index.php">Volver al inicio</a></div>
    </div>
  </div>
</div>
</body>
</html>
