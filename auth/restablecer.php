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
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">Nueva contraseña</h3></div>
    <div class="card-body">
      <?php if ($state === 'done'): ?>
        <div class="alert alert-success">Contraseña actualizada. Ya puede iniciar sesión.</div>
        <a class="btn btn-primary btn-block" href="../index.php">Ir al inicio</a>

      <?php elseif ($state === 'expired'): ?>
        <div class="alert alert-warning">
          El enlace para restablecer la contraseña <b>ha expirado</b> o ya fue utilizado.
        </div>
        <a class="btn btn-secondary btn-block" href="<?= htmlspecialchars($olvidoUrl) ?>">
          Solicitar un nuevo enlace
        </a>

      <?php elseif ($state === 'invalid'): ?>
        <div class="alert alert-danger">Enlace inválido.</div>
        <a class="btn btn-secondary btn-block" href="<?= htmlspecialchars($olvidoUrl) ?>">
          Solicitar un nuevo enlace
        </a>

      <?php else: ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" autocomplete="off">
          <div class="form-group">
            <label>Nueva contraseña</label>
            <input type="password" name="pwd" class="form-control" minlength="8" required>
          </div>
          <div class="form-group">
            <label>Repite la contraseña</label>
            <input type="password" name="pwd2" class="form-control" minlength="8" required>
          </div>
          <button class="btn btn-success btn-block">Guardar</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
